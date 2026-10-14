"""Read-only release approval inspection; share the builder's strict gate policy."""
import subprocess
import sys
from release_package import ROOT, PackageError, blocked_requirements, build_archive

scan = subprocess.run([sys.executable, str(ROOT / 'tools/secret-scan.py')])
blocked = bool(scan.returncode)
try:
    pending = blocked_requirements(ROOT)
    for key in pending:
        print('BLOCKED: ' + key)
    blocked = blocked or bool(pending)
    if not blocked:
        build_archive(ROOT, release=True, scan=False)
except (PackageError, OSError, ValueError) as error:
    print('BLOCKED: ' + str(error))
    blocked = True
print('Recorded release conditions remain pending; no public release action was performed.' if blocked else 'Recorded conditions and in-memory installer verified; explicit publication approval is still required.')
sys.exit(blocked)
