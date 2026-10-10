#!/usr/bin/env python3
"""Allowlisted canonical package; layout inspection never implies release approval."""
from pathlib import Path
import argparse,io,json,subprocess,sys,zipfile
root=Path(__file__).resolve().parents[1];slug='quixdevs-plugnpay-direct'
parser=argparse.ArgumentParser();parser.add_argument('--check',action='store_true',help='Validate an in-memory archive only; writes no ZIP.');args=parser.parse_args()
paths=[root/'quixdevs-plugnpay-direct.php',root/'readme.txt',root/'UPSTREAM-NOTICES.md',root/'CHANGELOG.md']
for directory in ['includes','assets','languages']:
 paths.extend(p for p in (root/directory).rglob('*') if p.is_file() and p.suffix in {'.php','.png','.svg','.txt','.pot','.mo','.po'})
if (root/'LICENSE').is_file():paths.append(root/'LICENSE')
for path in paths:
 if path.is_symlink() or not path.resolve().is_relative_to(root):raise SystemExit('Unsafe build source path.')
scan=subprocess.run([sys.executable,str(root/'tools/secret-scan.py')],capture_output=True,text=True)
if scan.returncode:print(scan.stdout);raise SystemExit('Secret policy failed; no package produced.')
buffer=io.BytesIO()
with zipfile.ZipFile(buffer,'w',zipfile.ZIP_DEFLATED) as archive:
 for path in sorted(set(paths)):
  entry=zipfile.ZipInfo(slug+'/'+path.relative_to(root).as_posix(),(2026,10,10,0,0,0))
  entry.compress_type=zipfile.ZIP_DEFLATED;entry.external_attr=0o100644 << 16
  archive.writestr(entry,path.read_bytes())
with zipfile.ZipFile(buffer) as archive:
 names=archive.namelist()
 assert all(n.startswith(slug+'/') and '..' not in Path(n).parts and '-github/' not in n for n in names)
 assert not any(part in {'docs','tests','.github','vendor','.git','wordpress-org-assets'} for n in names for part in Path(n).parts)
 assert slug+'/quixdevs-plugnpay-direct.php' in names
 if archive.testzip():raise SystemExit('ZIP CRC check failed.')
print('Canonical in-memory package layout: '+str(len(names))+' allowlisted files; CRC and exclusion checks passed.')
if args.check:
 print('LAYOUT ONLY: no ZIP written; release approval remains blocked.');sys.exit(0)
state=json.loads((root/'docs/release-state.json').read_text())
if not all(state['release_requirements'].values()):
 for key,value in state['release_requirements'].items():
  if not value:print('BLOCKED: '+key)
 raise SystemExit(1)
destination=root/'dist'/(slug+'.zip');destination.parent.mkdir(exist_ok=True);destination.write_bytes(buffer.getvalue());print(str(destination))
