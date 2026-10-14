"""Shared, dependency-free runtime allowlist and immutable artifact verification."""
import hashlib
import io
import json
from pathlib import Path
import re
import subprocess
import sys
import zipfile

ROOT = Path(__file__).resolve().parents[1]
SLUG = 'quixdevs-plugnpay-direct'
VERSION = '1.0.0'
REQUIREMENTS = frozenset({
    'upstream_redistribution_license_confirmed', 'payment_concurrency_and_replay_review_passed',
    'refund_contract_review_passed', 'rest_input_output_transport_review_passed',
    'wordpress_woocommerce_integration_verified', 'plugin_check_review_complete', 'installation_verified',
})
TOP_LEVEL = (SLUG + '.php', 'readme.txt', 'UPSTREAM-NOTICES.md')
SUFFIXES = {'includes': {'.php'}, 'assets': {'.png', '.svg', '.css', '.js', '.txt'},
            'languages': {'.php', '.pot', '.po', '.mo'}}
ZIP_TIME = (2026, 1, 1, 0, 0, 0)


class PackageError(ValueError):
    """A package or release gate cannot be verified."""


def digest(data):
    return hashlib.sha256(data).hexdigest()


def blocked_requirements(source):
    try:
        requirements = json.loads((source / 'docs/release-state.json').read_text())['release_requirements']
    except (OSError, ValueError, KeyError, TypeError) as error:
        raise PackageError('Release-state evidence is missing or malformed.') from error
    if not isinstance(requirements, dict) or set(requirements) != REQUIREMENTS:
        raise PackageError('Release-state requirement inventory differs; review the policy explicitly.')
    # Strict boolean True; strings, numbers and empty/missing inventories cannot approve a release.
    return sorted(key for key, value in requirements.items() if value is not True)


def header(text, name):
    match = re.search(r'^\s*(?:\*\s*)?' + re.escape(name) + r':\s*(.*?)\s*$', text, re.M)
    if not match:
        raise PackageError('Required metadata missing: ' + name)
    return match[1]


def select_runtime(source):
    source = Path(source).resolve()
    selected = []
    for name in TOP_LEVEL:
        path = source / name
        if not path.is_file():
            raise PackageError('Required runtime file missing: ' + name)
        selected.append(path)
    for notice in ('LICENSE', 'COPYING.GPL3', 'THIRD-PARTY-NOTICES.md'):
        if (source / notice).exists():
            selected.append(source / notice)
    for directory, suffixes in SUFFIXES.items():
        base = source / directory
        if not base.is_dir() or base.is_symlink():
            raise PackageError('Missing or unsafe runtime directory: ' + directory)
        for path in base.rglob('*'):
            if path.is_symlink():
                raise PackageError('Runtime symlinks are forbidden.')
            if path.is_file() and path.suffix.lower() in suffixes:
                selected.append(path)
    files = {}
    for path in sorted(selected):
        if path.is_symlink() or not path.resolve().is_relative_to(source):
            raise PackageError('Unsafe runtime source path.')
        relative = path.relative_to(source).as_posix()
        if any(part.startswith('.') or part in {'vendor', 'tests', 'docs', 'node_modules'} for part in Path(relative).parts):
            raise PackageError('Development or hidden file found under runtime directory.')
        files[relative] = path.read_bytes()
    main = files[SLUG + '.php'].decode()
    expected = {'Plugin Name': "QuixDevs Plug'n Pay Direct", 'Version': VERSION,
                'Author': 'QuixDevs', 'Author URI': 'https://quixdevs.com/',
                'Text Domain': SLUG, 'Requires PHP': '8.2'}
    for name, value in expected.items():
        if header(main[:8192], name) != value:
            raise PackageError('Unexpected plugin metadata: ' + name)
    if header(files['readme.txt'].decode(), 'Stable tag') != VERSION:
        raise PackageError('readme.txt stable tag differs from the plugin version.')
    if not re.search(r"define\(\s*['\"]QUIXDEVS_PLUGNPAY_VERSION['\"]\s*,\s*['\"]" + re.escape(VERSION) + r"['\"]", main):
        raise PackageError('Runtime version constant differs from the plugin header.')
    # PHP's tokenizer reads source as data. No plugin code is evaluated. This avoids
    # mistaking comments, merchant messages or URL strings for require expressions.
    tokenizer = r'''
    $files = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
    $out = [];
    foreach ($files as $name => $source) {
        $tokens = token_get_all($source);
        $out[$name] = [];
        foreach ($tokens as $position => $token) {
            if (!is_array($token) || !in_array($token[0], [T_REQUIRE, T_REQUIRE_ONCE, T_INCLUDE, T_INCLUDE_ONCE], true)) { continue; }
            $expression = '';
            for ($i = $position + 1; $i < count($tokens); $i++) {
                $next = $tokens[$i];
                if ($next === ';') { break; }
                if (is_array($next) && in_array($next[0], [T_COMMENT, T_DOC_COMMENT], true)) { continue; }
                $expression .= is_array($next) ? $next[1] : $next;
            }
            $out[$name][] = trim($expression);
        }
    }
    echo json_encode($out, JSON_THROW_ON_ERROR);
    '''
    php_files = {name: data.decode() for name, data in files.items() if name.endswith('.php')}
    try:
        result = subprocess.run(['php', '-r', tokenizer], input=json.dumps(php_files), capture_output=True, text=True, timeout=30)
        if result.returncode:
            raise PackageError('PHP dependency tokenization failed.')
        dependencies = json.loads(result.stdout)
    except (OSError, ValueError, subprocess.TimeoutExpired) as error:
        raise PackageError('A working PHP CLI is required for dependency inspection.') from error
    # Only static local PHP includes are permitted. Dynamic loading needs a reviewed resolver.
    for name, expressions in dependencies.items():
        for expression in expressions:
            relative = re.fullmatch(r"\(?\s*(?:__DIR__|dirname\(\s*__FILE__\s*\))\s*\.\s*['\"](/[^'\"]+)['\"]\s*\)?", expression.strip())
            if not relative:
                raise PackageError('Unresolved dynamic PHP dependency in ' + name)
            dependency = (Path(name).parent / relative[1].lstrip('/')).as_posix()
            if dependency not in files:
                raise PackageError('Required PHP dependency absent: ' + dependency)
    return files


def run_secret_scan(source):
    scanner = ROOT / 'tools/secret-scan.py'
    # Reviewed tooling scans the exact source checkout, not the tooling checkout.
    result = subprocess.run([sys.executable, str(scanner), '--root', str(source)],
                            capture_output=True, text=True, timeout=60)
    if result.returncode:
        raise PackageError('Source secret policy scan failed; inspect the separate scan report.')


def build_archive(source=ROOT, release=False, scan=True):
    source = Path(source).resolve()
    blocked = blocked_requirements(source)
    if release and blocked:
        raise PackageError('Public release gates remain BLOCKED: ' + ', '.join(blocked))
    if release and not (source / 'LICENSE').is_file():
        raise PackageError('Verified redistribution license file is required for a release.')
    files = select_runtime(source)
    if scan:
        run_secret_scan(source)
    buffer = io.BytesIO()
    with zipfile.ZipFile(buffer, 'w', compression=zipfile.ZIP_DEFLATED, compresslevel=9) as archive:
        for relative, data in sorted(files.items()):
            entry = zipfile.ZipInfo(SLUG + '/' + relative, ZIP_TIME)
            entry.create_system = 3
            entry.external_attr = 0o100644 << 16
            entry.compress_type = zipfile.ZIP_DEFLATED
            archive.writestr(entry, data, compresslevel=9)
    data = buffer.getvalue()
    with zipfile.ZipFile(io.BytesIO(data)) as archive:
        if archive.testzip() or len(archive.namelist()) != len(files):
            raise PackageError('ZIP entry or CRC verification failed.')
    manifest = {'schema': 1, 'slug': SLUG, 'version': VERSION,
                'classification': 'UNPUBLISHED RELEASE CANDIDATE' if release else 'RELEASE CANDIDATE — PENDING RECORDED APPROVALS',
                'release_candidate': release, 'blocked_requirements': blocked,
                'bytes': len(data), 'sha256': digest(data),
                'entries': {name: digest(content) for name, content in sorted(files.items())}}
    return data, manifest


def write_artifacts(directory, data, manifest):
    directory = Path(directory)
    if directory.is_symlink():
        raise PackageError('Artifact output directory may not be a symlink.')
    directory.mkdir(parents=True, exist_ok=True)
    outputs = {SLUG + '.zip': data,
               SLUG + '.zip.sha256': (manifest['sha256'] + '  ' + SLUG + '.zip\n').encode(),
               'package-manifest.json': (json.dumps(manifest, indent=2, ensure_ascii=False) + '\n').encode()}
    for name, content in outputs.items():
        path = directory / name
        if path.is_symlink():
            raise PackageError('Artifact outputs may not be symlinks.')
        path.write_bytes(content)


def verify_artifacts(source, directory, release=False):
    directory = Path(directory)
    expected, manifest = build_archive(source, release=release)
    for name in (SLUG + '.zip', SLUG + '.zip.sha256', 'package-manifest.json'):
        if (directory / name).is_symlink() or not (directory / name).is_file():
            raise PackageError('Missing or unsafe artifact: ' + name)
    if (directory / (SLUG + '.zip')).read_bytes() != expected:
        raise PackageError('Archive bytes differ from the exact reviewed source checkout.')
    checksum = manifest['sha256'] + '  ' + SLUG + '.zip\n'
    if (directory / (SLUG + '.zip.sha256')).read_text() != checksum:
        raise PackageError('Checksum asset differs from the verified archive.')
    if json.loads((directory / 'package-manifest.json').read_text()) != manifest:
        raise PackageError('Artifact manifest differs from the verified source.')
    return manifest
