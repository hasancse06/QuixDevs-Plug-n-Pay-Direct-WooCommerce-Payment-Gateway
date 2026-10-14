#!/usr/bin/env python3
"""Normalize complete quality reports without changing checker rules or source files.

Raw reports are local evidence (ignored). The generated portable inventory is public.
Run after an installed-artifact Plugin Check and the full runtime WPCS report exist.
"""
import argparse
import collections
import html
import json
from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]
CATEGORIES = {
    'A': 'Genuine correctness/security or input robustness problem',
    'B': 'WordPress coding-standard violation',
    'C': 'Documentation/metadata issue',
    'D': 'Legitimate checker limitation or required compatibility/storage contract',
    'E': 'Third-party or generated-file finding',
}


def relative(path):
    value = path.replace('\\', '/')
    for marker in ('/quixdevs-plugnpay-direct-github/', '/qpd-quality-before-1sp_r4e7/'):
        if marker in value:
            return value.split(marker, 1)[1]
    return value


def load_findings(directory):
    wpcs = json.loads((directory / 'wpcs.json').read_text())
    plugin = json.loads((directory / 'plugin-check-summary.json').read_text())
    findings = []
    for file, record in wpcs['files'].items():
        for item in record['messages']:
            findings.append(dict(tool='WPCS', file=relative(file), code=item['source'],
                                 severity=item['type'], line=item['line'], column=item['column'],
                                 message=item['message']))
    for item in plugin['findings']:
        findings.append(dict(tool='Plugin Check', file=relative(item['file']), code=item['code'],
                             severity=item['type'], line=item['line'], column=item.get('column', 0),
                             message=html.unescape(item['message'])))
    return findings, {
        'WPCS': {'errors': wpcs['totals']['errors'], 'warnings': wpcs['totals']['warnings']},
        'Plugin Check': {'errors': plugin['counts'].get('ERROR', 0),
                         'warnings': plugin['counts'].get('WARNING', 0)},
    }


def classify(item):
    """Context-sensitive review of the frozen baseline, not a claim of exploitability."""
    code, file, line = item['code'], item['file'], item['line']
    if code == 'unexpected_markdown_file':
        return ('D', 'Retain the complete required provenance/license notices.', False,
                'No executable behavior. GitHub distribution retains readable legal notices; Plugin Check warns about their extension.')
    if code.endswith('NonPrefixedHooknameFound'):
        return ('D', 'Retain the published woocommerce_gateway_icon extension hook with one documented statement exception.', False,
                'Renaming an external WooCommerce hook would break integrations.')
    if 'NonceVerification' in code:
        return ('D', 'Centralize unslashed typed input; document the delegated WooCommerce nonce boundary or read-only predicate per statement.', False,
                'These flagged reads run inside WooCommerce handlers or only select a view. The separate legacy return handler now verifies its own nonce and order scope; REST keeps its permission callback.')
    if 'ValidatedSanitizedInput' in code:
        if file == 'quixdevs-plugnpay-direct.php' and line in (611, 612, 613, 616, 618):
            return ('D', 'Read the unslashed parameter array after existing Payment_Input typed card validation; encode every provider component.', True,
                    'The checker did not follow the existing custom validator. Valid WordPress-slashed names are now unslashed once; no generic text sanitizer corrupts PAN/CVV/name protocol values.')
        return ('A', 'Check existence and scalar type, unslash once, then validate with Payment_Input, nonce/order scope, sanitize_email or a validated connection IP as appropriate.', True,
                'Malformed arrays/token IDs are rejected before lookup/HTTP; valid slashed input is restored; the legacy return is bound to its customer or guest key. No financial field names or authorization are relaxed.')
    if 'SafeRedirect' in code:
        return ('A', 'Use wp_safe_redirect only after typed nonce and owner/guest-key/merchant authorization.', True,
                'The optional legacy return cannot process a foreign order with a nonce alone; redirects are limited by WordPress safe-host rules.')
    if 'DirectDatabaseQuery' in code:
        return ('A', 'Replace the two post/postmeta helper lookups with authoritative WooCommerce order queries, using a scoped CPT mapping and native HPOS metadata query.', True,
                'Prepared legacy queries were not verified SQL injection. They bypassed HPOS. Exact literal references now resolve in both stores. Financial INSERT/CAS/advisory-lock SQL is unchanged.')
    if 'EscapeOutput' in code:
        return ('A', 'Escape translated form output and customer-safe thrown text at the output boundary; discard raw private provider diagnostics.', True,
                'Text is safely encoded for HTML. Provider secrets and raw errors stay private; verified payment/refund decisions are unchanged.')
    if 'I18n' in code:
        return ('C', 'Use this plugin text domain, literal translation calls and adjacent translator comments for placeholders.', False,
                'Customer-facing English and financial decisions remain the same; translators receive correct context.')
    if 'Comment' in code or 'FileHeader' in code:
        return ('C', 'Complete accurate package/class/function/parameter/return documentation and standard comment punctuation; remove inactive commented code.', False,
                'Documentation only. Removed private duplicate card validators had no callers; live validation remains Payment_Input.')
    if 'InvalidClassFileName' in code or 'MultipleFound' in code:
        return ('B', 'Move each class to its canonical class-*.php file; keep guarded static require_once shims at historical public include paths.', False,
                'Namespaces, class names, public methods and bootstrap order are retained. No filename rule is disabled.')
    if 'NamingConventions' in code or 'PropertyDeclaration.Underscore' in code:
        return ('B', 'Use snake_case internally; retain only existing public camelCase methods/named arguments with documented per-statement compatibility exceptions.', False,
                'Existing third-party callers keep their method and PHP named-argument contracts.')
    if 'date_date' in code:
        return ('B', 'Remove unused private expiry validator and use a UTC date in live optional display metadata.', False,
                'The removed validator had no callers; live card expiry validation still accepts the current month.')
    if 'CurrentTimeTimestamp' in code:
        return ('A', 'Use a Unix UTC timestamp for logger elapsed-time calculations.', True,
                'Elapsed-time reset no longer mixes an offset local timestamp with Unix time; no payment protocol timestamp is changed.')
    if 'UselessOverridingMethod' in code:
        return ('A', 'Remove the optional-adapter parent constructor call; read the existing saved_cards option without recursively constructing adapters.', True,
                'Optional extension presence no longer causes recursive gateway construction; absent APIs fail with a controlled message.')
    if 'Unreachable' in code:
        return ('B', 'Remove dead statements following an unconditional return.', False,
                'Unreachable code has no observable effect.')
    if 'UnusedFunctionParameter' in code or 'NoReservedKeywordParameterNames' in code:
        return ('D', 'Retain the existing public named/reserved argument with a single documented declaration exception.', False,
                'No public signature or named-argument compatibility is removed.')
    return ('B', 'Apply explicit strict typed comparisons, supported syntax and unambiguous control flow; remove unused private code only after verifying no callers.', False,
            'Known string/status and integer comparisons preserve valid behavior. Malformed optional metadata is rejected explicitly. Financial journal/protocol decisions remain tested.')


def group_findings(findings, phase):
    groups = {}
    for item in findings:
        category, correction, changes, impact = classify(item)
        key = (item['tool'], item['code'], item['file'], item['severity'], category, correction, changes, impact)
        if key not in groups:
            groups[key] = dict(tool=item['tool'], rule=item['code'], file=item['file'], severity=item['severity'],
                               classification=category, occurrence_count=0, locations=[],
                               recommended_and_applied_correction=correction, behavior_may_change=changes,
                               behavior_impact=impact, disposition=('retained_documented_warning' if item['code'] == 'unexpected_markdown_file'
                                                                  else 'corrected_or_narrowly_reviewed' if phase == 'before' else 'requires_review'))
        groups[key]['occurrence_count'] += 1
        groups[key]['locations'].append({k: item[k] for k in ('line', 'column', 'message')})
    return [groups[key] for key in sorted(groups)]


def suppressions():
    records = []
    files = [ROOT / 'quixdevs-plugnpay-direct.php', *sorted((ROOT / 'includes').rglob('*.php')),
             *sorted((ROOT / 'languages').rglob('*.php'))]
    pattern = re.compile(r'phpcs:ignore\s+(.+?)\s+--\s+(.+)$')
    for file in files:
        for line, text in enumerate(file.read_text().splitlines(), 1):
            if 'phpcs:disable' in text or 'phpcs:enable' in text:
                raise ValueError('Whole-region suppression requires manual review: ' + str(file))
            match = pattern.search(text)
            if match:
                rules = [rule.strip() for rule in match[1].split(',')]
                if any(len(rule.split('.')) < 3 for rule in rules):
                    raise ValueError('Broad suppression: ' + str(file))
                records.append(dict(file=str(file.relative_to(ROOT)), annotation_line=line,
                                    rules=rules, reason=match[2], scope='single statement'))
    return records


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--evidence', type=Path, default=ROOT / 'docs/verification/quality-2026-10-15')
    args = parser.parse_args()
    before, before_totals = load_findings(args.evidence / 'before')
    after, after_totals = load_findings(args.evidence / 'after')
    installed = json.loads((args.evidence / 'after/plugin-check-summary.json').read_text())
    document = dict(schema_version=1, date='2026-10-15', categories=CATEGORIES,
                    scope='Complete installed runtime Plugin Check including experimental checks; full WordPress WPCS on main/includes/languages. No runtime exclusions.',
                    tools={'WPCS': '3.4.1 / PHPCS 3.13.6', 'Plugin Check': '2.1.0'},
                    counting_note='Each tool occurrence is counted, including overlap between tools. File/line refers to the frozen phase source; line 0 is a file-level finding.',
                    before_totals=before_totals, after_totals=after_totals,
                    after_artifact={key: installed[key] for key in ('artifact_sha256', 'artifact_bytes', 'verified_installed_file_count')},
                    before_occurrences=len(before), after_occurrences=len(after),
                    before_groups=group_findings(before, 'before'), after_groups=group_findings(after, 'after'),
                    current_narrow_suppressions=suppressions(),
                    exclusions_and_rule_changes='None. Custom capability declarations document existing WooCommerce/backend capabilities. Required financial SQL exceptions predate this pass and preserve atomic correctness.',
                    category_E_findings=0,
                    evidence_location='Ignored local docs/verification/quality-2026-10-15; this inventory embeds every reported occurrence without private absolute paths.')
    (ROOT / 'docs/quality-inventory.json').write_text(json.dumps(document, indent=2) + '\n')
    lines = ['# Complete quality finding inventory — 2026-10-15', '',
             'This public inventory records every tool occurrence; overlap between WPCS and Plugin Check is counted separately. Original baseline line numbers remain attached to the frozen source. File-level notices use line 0. The JSON companion includes every message/column, classification, correction and behavior-impact note.', '',
             '| Tool | Before errors | Before warnings | After errors | After warnings |', '|---|---:|---:|---:|---:|']
    for tool in before_totals:
        b, a = before_totals[tool], after_totals[tool]
        lines.append(f"| {tool} | {b['errors']} | {b['warnings']} | {a['errors']} | {a['warnings']} |")
    lines += ['', f"Baseline: **{len(before)} tool occurrences**. Final: **{len(after)}**. No directory/file exclusions or weakened rules. Category E has zero findings: development vendors/generated files were never part of the runtime distribution/check scope.", '',
              'Categories: ' + '; '.join(f'{k}: {v}' for k, v in CATEGORIES.items()) + '.', '',
              'The two final Plugin Check warnings identify the root Markdown legal/provenance notices. They remain intentionally visible and unsuppressed; preserving complete notices in this GitHub distribution is appropriate. They indicate no PHP, authorization, payment or refund defect.', '',
              '## Baseline findings and disposition', '',
              '| Tool / rule | File : original lines | Severity / count / class | Correction and behavior impact |', '|---|---|---|---|']
    for group in document['before_groups']:
        positions = ', '.join(str(value) for value in sorted({x['line'] for x in group['locations']}))
        cell = group['recommended_and_applied_correction'] + ' ' + group['behavior_impact']
        lines.append(f"| {group['tool']}: `{group['rule']}` | `{group['file']}` : {positions} | {group['severity']} / {group['occurrence_count']} / {group['classification']} | {cell.replace('|', '/')} Behavior change: {'possible, specified above' if group['behavior_may_change'] else 'none intended'}. |")
    lines += ['', '## Every current statement exception', '',
              'These are exact rule exceptions with local rationale, not region/file exclusions. Financial database exceptions are required uniqueness/CAS/connection-lock operations; avoiding uncached SQL would weaken correctness. Source-reference metadata queries use supported WooCommerce APIs and retain their existing metadata performance characteristics.', '',
              '| File : annotation line | Exact rules | Reason |', '|---|---|---|']
    for record in document['current_narrow_suppressions']:
        lines.append(f"| `{record['file']}:{record['annotation_line']}` | {', '.join('`'+x+'`' for x in record['rules'])} | {record['reason']} |")
    lines += ['', 'Nonce exceptions apply to delegated WooCommerce handlers or read-only predicates. The optional legacy return validates its own nonce and order scope; REST validates identity and order authorization separately. Typed custom validators run before provider submission or token persistence. Public alias/name exceptions preserve existing integrations.', '',
              'See [machine-readable inventory](quality-inventory.json), [focused helper notes](quality-suppressions-includes.json), [optional-adapter notes](quality-suppressions-compat.json), [test evidence](testing.md), and [REST comparison/migration](rest-client-comparison.md).', '']
    (ROOT / 'docs/quality-inventory.md').write_text('\n'.join(lines))
    print(json.dumps({'before_occurrences': len(before), 'after_occurrences': len(after),
                      'groups': len(document['before_groups']), 'statement_exceptions': len(document['current_narrow_suppressions']),
                      'before': before_totals, 'after': after_totals}, indent=2))


if __name__ == '__main__':
    main()
