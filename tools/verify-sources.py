#!/usr/bin/env python3
"""Read-only optional check of both sibling sources, never part of a public package."""
from pathlib import Path
import hashlib,json,sys
root=Path(__file__).resolve().parents[1]
manifest_path=root/'docs/source-hashes.json'
if not manifest_path.is_file():
 print('Private source manifest unavailable; preservation check not run in this checkout.')
 sys.exit(0)
manifest=json.loads(manifest_path.read_text());errors=[]
for directory,files in manifest.items():
 source=root.parent/directory
 if not source.is_dir():print(directory+': unavailable; preservation check not run.');continue
 actual={str(p.relative_to(source)):hashlib.sha256(p.read_bytes()).hexdigest() for p in source.rglob('*') if p.is_file()}
 if actual!=files:errors.append(directory+': inventory/hash changed.')
 else:print(directory+': '+str(len(files))+' files unchanged.')
for error in errors:print(error)
sys.exit(bool(errors))
