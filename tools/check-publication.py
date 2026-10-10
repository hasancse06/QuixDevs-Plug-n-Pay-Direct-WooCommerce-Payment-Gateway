from pathlib import Path
import json,subprocess,sys
root=Path(__file__).resolve().parents[1];status=json.loads((root/'docs/release-state.json').read_text());blocked=False
scan=subprocess.run([sys.executable,str(root/'tools/secret-scan.py')]);blocked|=bool(scan.returncode)
for key,value in status['release_requirements'].items():
 if not value:print('BLOCKED: '+key);blocked=True
print('No public release is authorized.' if blocked else 'Recorded conditions satisfied; human review/publication authorization still required.')
sys.exit(blocked)
