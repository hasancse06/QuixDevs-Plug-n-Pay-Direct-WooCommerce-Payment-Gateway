#!/usr/bin/env python3
"""Build a reproducible runtime archive; candidate artifacts confer no release approval."""
import argparse
import json
from pathlib import Path
import sys

from release_package import PackageError, ROOT, SLUG, build_archive, write_artifacts


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--source-root', type=Path, default=ROOT)
    parser.add_argument('--output-dir', type=Path)
    parser.add_argument('--check', action='store_true', help='Verify layout in memory; write no artifacts.')
    parser.add_argument('--release', action='store_true', help='Require all recorded release gates and a license.')
    args = parser.parse_args()
    try:
        data, manifest = build_archive(args.source_root, release=args.release)
        print(f"Canonical runtime layout: {len(manifest['entries'])} files; headers, dependencies, CRC and exclusions verified.")
        if args.check:
            print('LAYOUT ONLY: no files written. Publication approval is separate.')
            return 0
        destination = args.output_dir or args.source_root / 'dist' / ('release' if args.release else 'candidate')
        write_artifacts(destination, data, manifest)
        print('RELEASE CANDIDATE — UNPUBLISHED' if args.release else 'RELEASE CANDIDATE — PENDING RECORDED APPROVALS')
        print(json.dumps({'file': str(destination / (SLUG + '.zip')), 'bytes': len(data),
                          'sha256': manifest['sha256'], 'blocked_requirements': manifest['blocked_requirements']}, indent=2))
        return 0
    except (PackageError, OSError, ValueError) as error:
        print('Package refused: ' + str(error), file=sys.stderr)
        return 1


if __name__ == '__main__':
    sys.exit(main())
