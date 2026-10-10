#!/usr/bin/env python3
"""Check Git upload candidates without committing, rewriting history or publishing."""
from pathlib import Path
import argparse
import json
import shutil
import subprocess
import sys
import tempfile

root = Path(__file__).resolve().parents[1]
parser = argparse.ArgumentParser()
parser.add_argument('--snapshot-check', action='store_true', help='Validate a temporary clean source snapshot without ignored files.')
args = parser.parse_args()

def git(*arguments, data=None):
    return subprocess.run(['git', '-C', str(root), *arguments], input=data, capture_output=True, check=True).stdout

try:
    git('rev-parse', '--show-toplevel')
except (FileNotFoundError, subprocess.CalledProcessError):
    raise SystemExit('Repository check requires Git and an existing checkout; it never initializes one.')

tracked = set(git('ls-files', '-z').decode().strip('\0').split('\0')) - {''}
ignored_tracked = set(git('ls-files', '-ci', '--exclude-standard', '-z').decode().strip('\0').split('\0')) - {''}
errors = ['Tracked local-only file: ' + path for path in sorted(ignored_tracked)]
other = set(git('ls-files', '--others', '--exclude-standard', '-z').decode().strip('\0').split('\0')) - {''}
candidates = sorted(path for path in tracked | other if (root / path).is_file())
required = {
    '.gitignore', '.github/workflows/ci.yml', 'README.md', 'readme.txt', 'CHANGELOG.md',
    'CONTRIBUTING.md', 'SECURITY.md', 'UPSTREAM-NOTICES.md', 'composer.json', 'composer.lock',
    'phpunit.xml.dist', 'phpcs.xml.dist', 'quixdevs-plugnpay-direct.php',
    'includes/class-rest-authorization.php', 'tests/ContractTest.php', 'tools/build-release.py',
    'docs/branding/quixdevs-logo.png', 'docs/branding/github-social-preview.svg',
    'docs/branding/github-social-preview.png', 'docs/screenshots/05-api-documentation.png',
    'wordpress-org-assets/icon-128x128.png', 'wordpress-org-assets/icon-256x256.png',
    'wordpress-org-assets/banner-772x250.png', 'wordpress-org-assets/banner-1544x500.png',
    'docs/rest-api.md', 'docs/github-upload.md', 'docs/release-state.json',
}
errors += ['Missing upload candidate: ' + path for path in sorted(required - set(candidates))]
local_directories = {'vendor', 'node_modules', '.composer-cache', 'dist', 'build', 'private', 'backups', 'snapshots', 'customer-exports'}
for path in candidates:
    parts = Path(path).parts
    if any(part in local_directories for part in parts) or path.startswith('docs/verification/') or path == 'docs/source-hashes.json':
        errors.append('Local-only upload candidate: ' + path)
    if (root / path).is_symlink():
        errors.append('Symlink upload candidate: ' + path)

samples = [
    'vendor/example.php', 'node_modules/example.js', '.composer-cache/cache.json',
    '.phpunit.result.cache', 'coverage/index.html', 'auth.json', '.env',
    'wp-config.php', 'wp-tests-config.php', 'private/config.php', 'merchant-config.json',
    'backups/original.php', 'customer-exports/orders.csv', 'example.pem', 'example.key',
    'payment.log', 'example.sql.gz', 'example.sqlite3', 'quixdevs-plugnpay-direct.zip',
    'dist/preview.html', 'build/archive.zip', 'docs/verification/report.json',
    'docs/source-hashes.json', '.DS_Store', '._README.md', '.idea/workspace.xml',
]
probe = subprocess.run(['git', '-C', str(root), '-c', 'core.excludesFile=/dev/null', 'check-ignore', '--no-index', '--stdin'], input='\n'.join(samples) + '\n', text=True, capture_output=True)
ignored = set(probe.stdout.splitlines())
errors += ['Missing ignore rule: ' + path for path in sorted(set(samples) - ignored)]
probe = subprocess.run(['git', '-C', str(root), '-c', 'core.excludesFile=/dev/null', 'check-ignore', '--no-index', '--stdin'], input='\n'.join(required) + '\n', text=True, capture_output=True)
errors += ['Required file incorrectly ignored: ' + path for path in probe.stdout.splitlines()]

for error in errors:
    print('ERROR: ' + error)
print(f'Repository inventory: {len(candidates)} source/upload candidates; {len(ignored_tracked)} tracked files conflict with ignore rules.')
if errors:
    raise SystemExit(1)
print('Source, lockfiles, tests, CI, docs and artwork retained; local artifacts/configuration excluded.')

if args.snapshot_check:
    with tempfile.TemporaryDirectory(prefix='qpd-source-review-') as temporary:
        snapshot = Path(temporary)
        for path in candidates:
            destination = snapshot / path
            destination.parent.mkdir(parents=True, exist_ok=True)
            shutil.copyfile(root / path, destination)
        for script, extra in [('verify-docs.py', []), ('secret-scan.py', []), ('build-release.py', ['--check'])]:
            result = subprocess.run([sys.executable, str(snapshot / 'tools' / script), *extra], cwd=snapshot, capture_output=True, text=True)
            print(result.stdout + result.stderr, end='')
            if result.returncode:
                raise SystemExit(result.returncode)
        print('Clean source snapshot passed without vendor/cache/local reports/private source inventories.')
print('Upload hygiene passed. Legal/security release approval is a separate check: composer release-check.')
