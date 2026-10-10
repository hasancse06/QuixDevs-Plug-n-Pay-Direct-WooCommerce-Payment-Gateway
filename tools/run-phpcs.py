from pathlib import Path
import os,subprocess,sys
root=Path(__file__).resolve().parents[1]
phpcs=os.environ.get('QPD_PHPCS_BINARY',str(root/'vendor/bin/phpcs'))
result=subprocess.run(['php',phpcs,'--standard='+str(root/'phpcs.xml.dist'),'--report=json','--extensions=php',str(root/'quixdevs-plugnpay-direct.php'),str(root/'includes'),str(root/'languages')],capture_output=True,text=True)
sys.stdout.write(result.stdout+result.stderr);sys.exit(result.returncode)
