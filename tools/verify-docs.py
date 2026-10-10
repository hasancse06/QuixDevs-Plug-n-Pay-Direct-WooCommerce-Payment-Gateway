#!/usr/bin/env python3
"""Offline link, asset, metadata and guarded API-documentation validation."""
from pathlib import Path
import json,re,struct,sys
from urllib.parse import unquote,urlsplit
root=Path(__file__).resolve().parents[1]
errors=[];links=0
files=[root/'README.md',root/'SECURITY.md',root/'CONTRIBUTING.md',root/'CHANGELOG.md',root/'UPSTREAM-NOTICES.md',*sorted((root/'docs').rglob('*.md')),*sorted((root/'wordpress-org-assets').glob('*.md')),*sorted((root/'.github').rglob('*.md'))]
def no_code(text):return re.sub(r'```.*?```','',text,flags=re.S)
def anchors(path):
 if path.suffix.lower()!='.md':return set()
 headings=re.findall(r'^#{1,6}\s+(.+?)\s*#*$',no_code(path.read_text()),re.M)
 return {re.sub(r'[^\w\- ]','',h.lower().replace('`','')).replace(' ','-') for h in headings}
for path in files:
 if not path.is_file():errors.append('Missing document: '+str(path.relative_to(root)));continue
 text=no_code(path.read_text())
 for target in re.findall(r'!?\[[^\]\n]*\]\(([^\n)]+)\)',text):
  target=target.strip().strip('<>');url=urlsplit(target)
  if url.scheme or url.netloc:continue
  links+=1
  destination=(path.parent/unquote(url.path)).resolve() if url.path else path
  if not destination.is_relative_to(root):errors.append('Link escapes repository: '+str(path.relative_to(root)));continue
  if not destination.exists():errors.append(f'{path.relative_to(root)}: missing {url.path}');continue
  if url.fragment and destination.suffix=='.md' and unquote(url.fragment) not in anchors(destination):errors.append(f'{path.relative_to(root)}: missing anchor {target}')
images={'docs/branding/quixdevs-logo.png':(164,45),'docs/branding/github-social-preview.png':(1280,640),'wordpress-org-assets/icon-128x128.png':(128,128),'wordpress-org-assets/icon-256x256.png':(256,256),'wordpress-org-assets/banner-772x250.png':(772,250),'wordpress-org-assets/banner-1544x500.png':(1544,500)}
for name,size in images.items():
 p=root/name
 if not p.is_file() or p.read_bytes()[:8]!=b'\x89PNG\r\n\x1a\n' or struct.unpack('>II',p.read_bytes()[16:24])!=size:errors.append('Incorrect/missing PNG: '+name)
main=(root/'quixdevs-plugnpay-direct.php').read_text()
for marker in ["Plugin Name: QuixDevs Plug'n Pay Direct",'Version: 1.0.0','Text Domain: quixdevs-plugnpay-direct','namespace QuixDevs\\PlugNPayDirect;',"'plugnpaydirect'"]:
 if marker not in main:errors.append('Missing runtime identity: '+marker)
collection=json.loads((root/'docs/postman/quixdevs-plugnpay-direct.postman_collection.json').read_text())
assert collection['info']['schema'].endswith('/v2.1.0/collection.json')
if not any(event['listen']=='prerequest' and any('throw new Error' in line for line in event['script']['exec']) for event in collection['event']):errors.append('Postman network guard missing')
expected={'payment_method','order_id','plugnpaydirect-client-key','plugnpaydirect-card-number','plugnpaydirect-card-expiry-month','plugnpaydirect-card-expiry-year','plugnpaydirect-card-cvc','plugnpaydirect-card-name'}
for i,item in enumerate(collection['item']):
 request=item['request'];keys=set(json.loads(request['body']['raw']))
 if request['method']!='POST' or request['url']!='{{base_url}}/wp-json/wc/v3/plugnpay-payment':errors.append('Invented Postman operation')
 if keys!=(expected-{'payment_method'} if i==1 else expected):errors.append('Unexpected Postman body fields')
composer=json.loads((root/'composer.json').read_text())
workflow=(root/'.github/workflows/ci.yml').read_text()
for script in ['lint','test','phpcs','secrets','docs','package-check']:
 if script not in composer['scripts'] or 'composer '+script not in workflow:errors.append('CI/script mismatch: '+script)
for script in ['lint.py','run-phpcs.py','secret-scan.py','verify-docs.py','build-release.py','check-publication.py']:
 if not (root/'tools'/script).is_file():errors.append('Missing tool: '+script)
print(f'Offline documentation checks: {len(files)} Markdown documents; {links} local links; {len(images)} PNG dimensions; runtime identity, Postman guard/fields and CI command wiring checked.')
print('External URLs and hosted GitHub rendering/CI are not verified by this offline check.')
for error in errors:print('ERROR: '+error)
sys.exit(bool(errors))
