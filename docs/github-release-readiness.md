# Final quality and release report — v1.0.0

**2026-10-15.** Quality remediation, packaging and local core integration verification are complete. The remaining recorded gate is merchant/application-developer acceptance of the authenticated REST cutover. No production source, Git index, commit, remote, tag, release, deployment or real processor transaction was changed.

## 1. Plugin Check before and after

Plugin Check2.1.0, including experimental checks: **40 errors/96 warnings →0 errors/2 warnings** on the exact installed ZIP. The two warnings identify legal-notice Markdown filenames. Actual messages were parsed; CLI exit0 alone was not treated as a pass.

## 2. WPCS before and after

Full WPCS 3.4.1 / PHPCS 3.13.6: **368 errors/41 warnings →0 errors/0 warnings**. Main PHP, every include/compatibility shim and `languages/` remain in scope. No file exclusion or weakened rule was introduced. All 23 narrow statement exceptions, including existing financial SQL safeguards, have local reasons.

The [Markdown inventory](quality-inventory.md) and [JSON inventory](quality-inventory.json) record all **545 baseline tool occurrences**, every rule/file/line/column/message, severity, count, A–E classification, correction and behavior impact. Tool overlap is counted separately; there are no third-party/generated runtime findings.

## 3. Exact issues resolved

| Area | Correction and practical effect |
|---|---|
| Class/file standards | Canonical implementations and guarded historical include shims; namespaces/public aliases/named arguments retained |
| Input/escaping | Typed checks, one unslash, existing card validation before use, per-value encoding, valid connection IP, escaped translated display/exception text |
| Saved-token selection | Malformed arrays/IDs rejected before lookup/journal/HTTP; ownership and per-token references retained |
| Optional legacy return | Real typed nonce plus owner, exact guest key or both merchant/order capabilities; safe redirect; corrected nonexistent helper reference |
| Helper lookup | Authoritative WooCommerce queries support posts and HPOS, including literal references; old prepared SQL was not asserted to be injection |
| Metadata | Canonical fee/net zero takes precedence; updates remain caller-saved; deletion persists both legacy/canonical keys through CRUD |
| Optional adapters | Existing saved_cards setting read without recursive construction; modern metadata; malformed/missing APIs fail predictably |
| i18n/docs | Correct text domain/literal translations, translator comments and complete standard documentation |
| Dead/time/control code | Unused private duplicate validators/unreachable code removed; UTC logger elapsed timestamp; strict comparisons in typed contexts |
| Automation | Full WPCS required in CI and read-only exact-source manual release verification; report retention remains |

No Plug'n Pay field names/order, mode, endpoint, checkout hash ordering, gateway/settings identifiers or successful financial lifecycle were changed for style. [Six protected implementations](quality-protected-runtime.json) have identical executable-token hashes to the baseline: journal, reconciliation command, input validation, request encoding, response parser and REST authorization. Helper/return/adapter behavior changes are explicitly listed and tested.

## 4. Remaining issues and justification

| Rule | File / line | Reason retained |
|---|---|---|
| `unexpected_markdown_file` | `UPSTREAM-NOTICES.md` /0 | Complete historical attribution in installer |
| `unexpected_markdown_file` | `THIRD-PARTY-NOTICES.md` /0 | Complete conditional provenance/terms explanation |

These unsuppressed filename warnings do not indicate a payment/security defect. Required metadata queries can retain metadata-query performance characteristics. Narrow SQL exceptions preserve uniqueness, uncached ownership, binary CAS and connection-scoped locks; cache/options substitutions would weaken correctness. Other exceptions document delegated validation, read-only predicates, published WooCommerce hooks and public API names. No significant verified payment/refund/authorization defect remains unresolved in the tested scope.

## 5. Payment/refund regression results

| Check | Final executed result |
|---|---|
| PHPUnit 9.6.38 / PHP 8.2.4 and8.4.13 | **102 tests/755 assertions each**, including original 80/593 plus22/162 new checks |
| Real WP6.9/Woo10.9.0/MariaDB 10.4.28/PHP 8.4.13 | **149 posts +149 HPOS =298 assertions** |
| Additional PHP 8.2.4 HPOS | **39 assertions** |
| Python packaging/draft API | **38 tests**, mocked API |
| Syntax | **48 project PHP files on each PHP version** |
| Direct access/missing Woo/missing key | Pass on both PHP versions, no provider call |
| ZIP install/activation and integrity | All **32 files** match before/after tests |
| Workflow lint | Both pass actionlint 1.7.12 |
| Source/secret/docs/repository/snapshot/archive policies | Pass |

Coverage includes success/decline, uncertain/timeouts/malformed replies, durable replay blocks, independent-worker INSERT/CAS races/crashes, order drift, full/partial/duplicate refunds, native WooCommerce refund-child accounting, reconciliation overlap/recovery, older saved-card ownership, REST identity/permissions and both stores. Helper literal-reference/zero/delete and genuine nonce/slash/IP cases pass. All financial HTTP is mocked; zero live requests, stderr or new PHP diagnostics. [Full coverage and reproduction](testing.md).

## 6. REST migration requirements

Same `POST /wp-json/wc/v3/plugnpay-payment`, gateway and eight request names. The embedded-key-only app requires a coordinated authenticated path: same-site WordPress session/REST nonce or trusted backend with server-held Application Password. The existing key remains a required body field. Direct browser requests expose it to that browser; strict server-only storage requires an application-owned authenticated bridge, which this plugin does not provide.

Owners may pay their registered orders. Guest/other orders require explicit `quixdevs_plugnpay_pay_orders` **and** specific order edit permission; no role is automatically elevated. The backend independently authorizes its app customer. Clients must handle HTTP400/401/403/409 WordPress envelopes, retain integer business codes under HTTP200, consume only documented safe fields and remove blind retries. The old PHP response declared123; historical wire status was not captured and may already have been200.

Legacy unmapped methods require fresh-card re-saving; optional renewals require explicit order references. No anonymous charging, guessed card reference or new hosted/token/status/refund API was introduced. [Exact comparison](rest-client-comparison.md), [practical migration](api-authentication.md), [request/response/errors](rest-api.md) use synthetic non-sending specimens. Merchant/app-developer acceptance is pending; nothing was deployed.

## 7. License and distribution

Md Abu Hasan explicitly authorizes **GPL-2.0-or-later for developer-owned code**. Full GNU GPLv2 text, metadata/URI, copyright and QuixDevs/Hasan credits are consistent. Historical attribution and any independently owned terms are preserved. Historical helper correspondence is a qualified provenance observation, not blanket ownership/GPLv3 determination; no generic missing-authorization blocker remains. No Stripe runtime integration/dependency exists; references only explain the historical comparison. [Licensing review](licensing-review.md).

The supplied logo remains byte-identical; ProductClock/StockNotifier artwork is retained. Unverified original network PNGs stay excluded. README/readme/changelog/install/configuration/REST/security docs are updated. Classic Checkout and core posts/HPOS are verified; Blocks/full optional-extension certification are not claimed.

Keep **1.0.0** based on inspected public QuixDevs history without tags/releases. Original1.3.2 uses a distinct basename and needs reviewed manual replacement with one gateway active; no automatic downgrade is assumed. [History](version-history.md).

## 8. Final ZIP/checksum

| Item | Verified value |
|---|---|
| Local artifact | `dist/candidate/quixdevs-plugnpay-direct.zip` |
| Size | **77,662 bytes** |
| SHA-256 | `5079dae17b83a5669d2a344f74105927c0c26121468d1f0584aafa58536b0d4a` |
| Sidecar / manifest | `.zip.sha256` and `package-manifest.json` beside ZIP |
| Layout | One `quixdevs-plugnpay-direct/` root; **32 runtime/readme/license/notice files** |

Deterministic rebuild, static includes, CRC, metadata, checksum and installation are verified. Tests/fixtures/CI/vendor/logs/secrets/reports/screenshots/artwork/Git files are excluded. Final manifest has only the client gate pending. `--release` refuses until acceptance is recorded; default candidate creation remains available.

## 9. GitHub Actions readiness

Read-only CI specifies PHP 8.2/8.3/8.4; only8.2/8.4 ran locally. Both workflows enforce WPCS. Manual dispatch separates immutable reviewed tooling and source SHAs; candidate is default. Draft mode requires every gate and an existing exact tag. Only the draft job has contents-write. Explicit repository-scoped installation-token permissions fail without fallback.

Numeric-ID paginated release discovery rejects ambiguous/published/mismatched releases, verifies identical assets and uploads only missing assets without trusting arbitrary upload URLs/redirects. No tag creation/rewriting or publication path exists. The documented external tag-deletion race requires a tag freeze before a future approved run. All action pins, lint and mocked invariants pass. No hosted CI/API write/draft creation was executed. [Handoff](release-distribution.md).

## 10. Exact files changed

Current-task changes are measured against the frozen 116-file baseline; the full pending source diff is separately measured against public/local HEAD `faf606190e99637777eba280fc0e777d68e1f0b1`. Both are in the [machine-readable file inventory](quality-files-changed.json). Original folders and index are unchanged. Ignored evidence/dependencies/ZIPs stay local.

<!-- QUALITY-FILES-START -->
58 files changed in this quality pass:

```text
modified .github/workflows/ci.yml
modified .github/workflows/release.yml
modified CHANGELOG.md
modified README.md
modified docs/api-authentication.md
modified docs/api-examples/php.md
modified docs/configuration.md
modified docs/functional-parity.md
modified docs/github-release-notes.md
modified docs/github-release-readiness.md
modified docs/github-upload.md
modified docs/postman/README.md
modified docs/publication-readiness.md
added docs/quality-files-changed.json
added docs/quality-inventory.json
added docs/quality-inventory.md
added docs/quality-protected-runtime.json
added docs/quality-suppressions-compat.json
added docs/quality-suppressions-includes.json
modified docs/release-distribution.md
modified docs/release-state.json
modified docs/rest-api.md
added docs/rest-client-comparison.md
modified docs/rights-replacement-plan.md
modified docs/screenshots/05-api-documentation.png
modified docs/security.md
modified docs/testing.md
modified docs/troubleshooting.md
modified includes/abstracts/abstract-wc-plugnpay-payment-gateway.php
added includes/abstracts/class-payment-gateway.php
added includes/class-customer-cards.php
added includes/class-customer.php
added includes/class-gateway-exception.php
added includes/class-gateway.php
added includes/class-helper.php
added includes/class-logger.php
added includes/class-rest-gateway.php
modified includes/class-wc-plugnpay-customer-cards.php
modified includes/class-wc-plugnpay-customer.php
modified includes/class-wc-plugnpay-exception.php
modified includes/class-wc-plugnpay-helper.php
modified includes/class-wc-plugnpay-logger.php
added includes/compat/class-pre-orders-compat.php
added includes/compat/class-subs-compat.php
modified includes/compat/class-wc-plugnpay-pre-orders-compat.php
modified includes/compat/class-wc-plugnpay-subs-compat.php
modified languages/index.php
modified phpunit.xml.dist
modified quixdevs-plugnpay-direct.php
modified readme.txt
added tests/CompatQualityTest.php
added tests/GatewayQualityTest.php
added tests/HelperQualityTest.php
modified tests/bootstrap.php
added tests/integration/worker-gateway-boundaries.php
added tests/integration/worker-helper-metadata.php
added tools/quality-inventory.py
modified tools/verify-docs.py
```

The complete pending diff contains 112 source files, enumerated in the JSON companion.
<!-- QUALITY-FILES-END -->

## 11. Recommended commit

```text
fix: harden Plug'n Pay quality checks and finalize v1.0.0 release candidate
```

Recommendation only; review the full pending diff before separately authorizing a commit/push/tag.

## 12. Final approval/blockers

**One recorded gate remains: REST client cutover acceptance.** The embedded-key-only app must implement/accept authenticated order scope, documented errors/output and no blind retries before coordinated deployment. This is client compatibility/approval work; tested implementation security and quality passed. [Release state](release-state.json).

Merchant HTTPS/credentials/hash setup, direct-card PCI obligations, single-primary database assumptions, real processor acceptance and optional-extension scope remain operational acceptance work. Settlement/PCI/hosted-CI certification is not invented, and missing external evidence is not called a verified defect. Publication/deployment needs a separately authorized human action. Both preserved source folders remain unchanged; no commit/push/tag/publication/deployment or real transaction was performed.
