#!/usr/bin/env python3
"""Dependency-free policy scan. Findings never print matched secret values."""
from pathlib import Path
import argparse,re,sys
root=Path(__file__).resolve().parents[1]
skip={'vendor','.composer-cache','.git','.phpunit.cache','dist','node_modules','__pycache__'}
parser=argparse.ArgumentParser();parser.add_argument('--root',type=Path,default=root);parser.add_argument('--compare-source',type=Path);args=parser.parse_args()
root=args.root.resolve()
known=[]
if args.compare_source:
 source=args.compare_source/'quixdevs-plugnpay-direct.php'
 known=re.findall(r'\$verification_key\s*=\s*[\'"]([^\'"]+)[\'"]',source.read_text())
patterns=[('literal verification credential',r'\$verification_key\s*=\s*[\'"]([^\'"]+)[\'"]'),('literal server verification constant',r'define\s*\(\s*[\'"]QUIXDEVS_PLUGNPAY_VERIFICATION_KEY[\'"]\s*,\s*[\'"]([^\'"]+)[\'"]'),('private key material',r'(-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----)'),('credential assignment',r'[\'"](?:publisher-password|client_password|client_key|plugnpaydirect-client-key)[\'"]\s*(?:=>|:)\s*[\'"]([^\'"<>]+)[\'"]')]
findings=[];count=0
for p in sorted(root.rglob('*')):
 if not p.is_file() or any(part in skip for part in p.relative_to(root).parts):continue
 if p.suffix.lower() in {'.png','.jpg','.gif','.woff','.woff2'}:continue
 if p.is_symlink():findings.append((p,'symlink is not a distributable source file'));continue
 try:text=p.read_text()
 except UnicodeDecodeError:findings.append((p,'unexpected binary outside artwork'));continue
 count+=1
 for secret in known:
  if secret in text:findings.append((p,'inherited source credential detected'))
 for label,pattern in patterns:
  for match in re.finditer(pattern,text,re.I):
   value=match.group(1)
   if value.startswith('synthetic-') and p.relative_to(root).parts[0]=='tests':continue
   if value.startswith('{{') or value in ('','<CLIENT_KEY>'):continue
   findings.append((p,label))
 for match in re.finditer(r'(?<![A-Za-z0-9])[0-9]{13,19}(?![A-Za-z0-9])',text):
  number=match.group(0);total=0
  for i,d in enumerate(reversed(number)):
   digit=int(d)*(2 if i%2 else 1);total+=digit-9 if digit>9 else digit
  if total>0 and total%10==0:findings.append((p,'possible full card number'))
for path,label in sorted(set(findings)):print(str(path.relative_to(root))+': '+label)
print(f'Policy scan: {count} text files; {len(set(findings))} findings. Values are never printed.')
print('Known source value comparison performed.' if args.compare_source else 'Pattern scan only; no private source required.')
sys.exit(bool(findings))
