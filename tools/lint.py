from pathlib import Path
import subprocess,sys
root=Path(__file__).resolve().parents[1]
paths=[root/'quixdevs-plugnpay-direct.php']+sorted((root/'includes').rglob('*.php'))+sorted((root/'languages').rglob('*.php'))+sorted((root/'tests').rglob('*.php'))+sorted((root/'tools').glob('*.php'))
failed=False
for path in paths:
 result=subprocess.run(['php','-l',str(path)],capture_output=True,text=True);print(result.stdout+result.stderr,end='');failed|=result.returncode!=0
sys.exit(failed)
