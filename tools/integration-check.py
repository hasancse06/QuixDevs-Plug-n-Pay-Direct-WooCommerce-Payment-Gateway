#!/usr/bin/env python3
"""Run real platform checks only in an explicitly marked disposable /tmp site.

This tool does not provision WordPress, download plugins, or access any real site.
All payment responses in platform.php are mocked before WordPress HTTP transport.
"""
import argparse
import json
import os
from pathlib import Path
import re
import shutil
import subprocess
import sys

parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument('--refresh', action='store_true', help='Refresh only copied runtime files inside the marked disposable site.')
parser.add_argument('--storage', choices=('posts', 'hpos'), required=True)
parser.add_argument('--output-dir', type=Path, required=True)
args = parser.parse_args()
root = Path(os.environ.get('QPD_INTEGRATION_ROOT', ''))
if os.environ.get('QPD_ALLOW_DISPOSABLE_CHECK') != '1' or not re.fullmatch(r'/private/tmp/qpd-integration-[A-Za-z0-9-]+/wordpress', str(root)):
    sys.exit('Set QPD_ALLOW_DISPOSABLE_CHECK=1 and QPD_INTEGRATION_ROOT to a marked disposable /private/tmp/qpd-integration-.../wordpress site.')
marker = root.parent / 'QPD_DISPOSABLE_INTEGRATION'
if not marker.is_file() or marker.read_text().strip() != 'Isolated synthetic QuixDevs PlugNPay integration only':
    sys.exit('Disposable environment marker is missing.')
config = root / 'wp-config.php'
if not config.is_file() or not (root / 'wp-load.php').is_file():
    sys.exit('Disposable WordPress configuration and core must already exist.')
config_text = config.read_text()
if 'qpd_integration_' not in config_text or str(root.parent / 'mysql.sock') not in config_text or "define( 'WP_HTTP_BLOCK_EXTERNAL', true )" not in config_text:
    sys.exit('The disposable database/socket/HTTP block configuration is not recognizable.')
repository = Path(__file__).resolve().parent.parent
runtime = root / 'wp-content/plugins/quixdevs-plugnpay-direct'
if args.refresh:
    if runtime.is_symlink() or any((runtime / item).is_symlink() for item in ('includes', 'assets', 'languages')):
        sys.exit('Refusing runtime symlinks.')
    runtime.mkdir(parents=True, exist_ok=True)
    for name in ('quixdevs-plugnpay-direct.php', 'readme.txt', 'UPSTREAM-NOTICES.md', 'LICENSE', 'COPYING.GPL3', 'THIRD-PARTY-NOTICES.md'):
        shutil.copy2(repository / name, runtime / name)
    for name in ('includes', 'assets', 'languages'):
        if (runtime / name).exists():
            shutil.rmtree(runtime / name)
        shutil.copytree(repository / name, runtime / name)
args.output_dir.mkdir(parents=True, exist_ok=True)
environment = os.environ.copy()
environment['QPD_INTEGRATION_STORAGE'] = args.storage
selection = subprocess.run(['php', str(repository / 'tests/integration/select-storage.php')], env=environment, text=True, capture_output=True)
(args.output_dir / ('integration-' + args.storage + '-setup.txt')).write_text(selection.stdout + selection.stderr)
if selection.returncode:
    sys.exit(selection.returncode)
result = subprocess.run(['php', str(repository / 'tests/integration/platform.php')], env=environment, text=True, capture_output=True)
(args.output_dir / ('integration-' + args.storage + '.json')).write_text(result.stdout)
(args.output_dir / ('integration-' + args.storage + '-stderr.txt')).write_text(result.stderr)
if result.returncode:
    print('Platform assertions failed; inspect the disposable PHP error log and output files.', file=sys.stderr)
    sys.exit(result.returncode)
summary = json.loads(result.stdout)
print(f"WordPress {summary['wordpress']}; WooCommerce {summary['woocommerce']}; PHP {summary['php']}; MariaDB {summary['database']}; storage={summary['order_storage']}; assertions={summary['assertions']}; live HTTP=0")
