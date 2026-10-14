#!/usr/bin/env python3
"""Create or resume one immutable unpublished draft by numeric ID; never write tags."""
import argparse
import json
import os
from pathlib import Path
import re
import subprocess
import sys
from urllib.error import HTTPError, URLError
from urllib.parse import quote
from urllib.request import HTTPRedirectHandler, Request, build_opener

from release_package import PackageError, ROOT, SLUG, VERSION, digest, verify_artifacts


class ReleaseError(ValueError):
    """A release identity or API invariant failed. Never include response bodies."""


class NoRedirect(HTTPRedirectHandler):
    def redirect_request(self, request, fp, code, message, headers, new_url):
        return None  # Authorization is never forwarded to server-selected URLs.


class GitHub:
    def __init__(self, token):
        if not token or '\n' in token or '\r' in token:
            raise ReleaseError('A repository-scoped GitHub token is required.')
        self.token = token
        self.opener = build_opener(NoRedirect())

    def request(self, method, path, payload=None, upload=False):
        # Every endpoint is constructed locally. Never trust upload_url/API URLs from release JSON.
        if not re.fullmatch(r'repos/[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+(?:/[A-Za-z0-9_./?=&%-]+)?', path):
            raise ReleaseError('Unverified GitHub API endpoint.')
        host = 'https://uploads.github.com/' if upload else 'https://api.github.com/'
        body = payload if isinstance(payload, bytes) else json.dumps(payload).encode() if payload is not None else None
        headers = {'Authorization': 'Bearer ' + self.token, 'Accept': 'application/vnd.github+json',
                   'X-GitHub-Api-Version': '2026-03-10', 'User-Agent': SLUG + '-release-tooling',
                   'Content-Type': 'application/octet-stream' if upload else 'application/json'}
        request = Request(host + path, data=body, headers=headers, method=method)
        safe = path.split('?', 1)[0]
        try:
            with self.opener.open(request, timeout=45) as response:
                expected = 201 if method == 'POST' else 200
                if response.status != expected:
                    raise ReleaseError(f'GitHub {method} /{safe}: unexpected HTTP {response.status}.')
                try:
                    return json.loads(response.read())
                except ValueError as error:
                    raise ReleaseError(f'GitHub {method} /{safe}: malformed JSON; no state is assumed.') from error
        except HTTPError as error:
            hint = ''
            if method == 'POST' and safe.endswith('/releases') and error.code in (403, 404):
                hint = ' Verify Contents:write and, for workflow-file differences, an installation token with Workflows:write. This is not proof that no release exists.'
            raise ReleaseError(f'GitHub {method} /{safe}: HTTP {error.code}; refusing further mutations.' + hint) from None
        except (URLError, TimeoutError, OSError):
            raise ReleaseError(f'GitHub {method} /{safe}: transport failed; inspect inventory before retrying.') from None


def validate_arguments(repo, tag, commit):
    if not re.fullmatch(r'[A-Za-z0-9][A-Za-z0-9_.-]*/[A-Za-z0-9][A-Za-z0-9_.-]*', repo):
        raise ReleaseError('Use a confirmed OWNER/REPOSITORY.')
    if tag != 'v' + VERSION or not re.fullmatch(r'[0-9a-f]{40}', commit):
        raise ReleaseError('Use the existing v1.0.0 tag and its full reviewed source commit SHA.')


def numeric_id(record):
    identifier = record.get('id') if isinstance(record, dict) else None
    if type(identifier) is not int or identifier < 1:
        raise ReleaseError('GitHub returned an invalid numeric identity.')
    return identifier


def confirm_repository(client, repo):
    record = client.request('GET', 'repos/' + repo)
    if not isinstance(record, dict) or str(record.get('full_name', '')).lower() != repo.lower():
        raise ReleaseError('GitHub repository identity mismatch.')
    # Installation tokens need actual endpoint access, not collaborator permissions.push flags.


def tag_commit(client, repo, tag):
    record = client.request('GET', f'repos/{repo}/git/ref/tags/{tag}')
    seen = set()
    for _ in range(10):
        obj = record.get('object', {}) if isinstance(record, dict) else {}
        sha = obj.get('sha', '')
        if not isinstance(sha, str) or not re.fullmatch(r'[0-9a-f]{40}', sha) or sha in seen:
            break
        seen.add(sha)
        if obj.get('type') == 'commit':
            return sha
        if obj.get('type') != 'tag':
            break
        record = client.request('GET', f'repos/{repo}/git/tags/{sha}')
    raise ReleaseError('Existing tag does not resolve safely to a commit.')


def assert_tag(client, repo, tag, commit):
    if tag_commit(client, repo, tag) != commit:
        raise ReleaseError('Existing remote tag differs from the reviewed source; no tags will be created or moved.')


def inventory(client, repo, tag):
    identifiers = set()
    for page in range(1, 101):
        rows = client.request('GET', f'repos/{repo}/releases?per_page=100&page={page}')
        if not isinstance(rows, list) or any(not isinstance(row, dict) for row in rows):
            raise ReleaseError('Release inventory is malformed.')
        for row in rows:
            if row.get('tag_name') == tag:
                identifiers.add(numeric_id(row))
        if len(rows) < 100:
            break
    else:
        raise ReleaseError('Release pagination limit reached; no absence is assumed.')
    records = []
    for identifier in sorted(identifiers):
        record = client.request('GET', f'repos/{repo}/releases/{identifier}')
        if numeric_id(record) != identifier or record.get('tag_name') != tag or type(record.get('draft')) is not bool:
            raise ReleaseError('Release identity changed during inventory verification.')
        records.append(record)
    if len(records) > 1:
        raise ReleaseError('Multiple distinct release IDs match this tag; resolve them manually: ' + ', '.join(map(str, sorted(identifiers))))
    return records[0] if records else None


def source_identity(source, commit):
    for arguments, expected in ((['rev-parse', 'HEAD'], commit),
                                (['status', '--porcelain', '--untracked-files=normal'], '')):
        result = subprocess.run(['git', *arguments], cwd=source, capture_output=True, text=True, timeout=30)
        if result.returncode or result.stdout.strip() != expected:
            raise ReleaseError('Release source must be the clean checkout of the exact reviewed commit.')


def release_notes(source, commit, manifest):
    path = Path(source) / 'docs/github-release-notes.md'
    if not path.is_file() or path.is_symlink():
        raise ReleaseError('Reviewed source release notes are missing.')
    return (path.read_text().strip() + '\n\nVerified source commit: `' + commit + '`.\n\n'
            + f"Runtime archive: `{SLUG}.zip` ({manifest['bytes']} bytes).\n\n"
            + 'SHA-256: `' + manifest['sha256'] + '`.\n\n'
            + 'This workflow creates an unpublished draft only. Publication requires a separate human review.\n')


def assert_draft(record, identifier, tag, commit, notes):
    if numeric_id(record) != identifier or record.get('draft') is not True or record.get('tag_name') != tag:
        raise ReleaseError('Published or mismatching release detected; it will never be modified.')
    if record.get('target_commitish') != commit or record.get('body') != notes:
        raise ReleaseError('Existing draft source or notes differ; maintainer changes will not be overwritten.')
    if not isinstance(record.get('assets'), list):
        raise ReleaseError('Draft asset inventory is unavailable.')


def asset_matches(asset, path):
    if not isinstance(asset, dict) or asset.get('name') != path.name:
        return False
    numeric_id(asset)
    return (asset.get('state') == 'uploaded' and asset.get('size') == path.stat().st_size
            and asset.get('digest') == 'sha256:' + digest(path.read_bytes()))


def validate_assets(record, paths, require_all=False):
    for path in paths:
        matches = [asset for asset in record['assets'] if isinstance(asset, dict) and asset.get('name') == path.name]
        if len(matches) > 1 or (matches and not asset_matches(matches[0], path)) or (require_all and not matches):
            raise ReleaseError('Draft asset bytes/identity differ or cannot be verified; no overwrites are permitted.')


def create_or_resume(client, repo, tag, commit, source, artifacts):
    validate_arguments(repo, tag, commit)
    source_identity(source, commit)
    # Gates/license and byte-for-byte re-build are checked before the first network request.
    manifest = verify_artifacts(source, artifacts, release=True)
    notes = release_notes(source, commit, manifest)
    paths = [Path(artifacts) / (SLUG + '.zip'), Path(artifacts) / (SLUG + '.zip.sha256')]
    confirm_repository(client, repo)
    assert_tag(client, repo, tag, commit)
    record = inventory(client, repo, tag)
    if record is None:
        assert_tag(client, repo, tag, commit)
        # GitHub provides no atomic "release only if this tag still exists"
        # precondition. No tag mutation endpoint is called, but maintainers must
        # prevent concurrent external tag deletion/recreation during this step.
        created = client.request('POST', f'repos/{repo}/releases', {
            'tag_name': tag, 'target_commitish': commit, 'name': "QuixDevs Plug'n Pay Direct " + tag,
            'body': notes, 'draft': True, 'prerelease': False, 'make_latest': 'false',
            'generate_release_notes': False,
        })
        identifier = numeric_id(created)
        record = inventory(client, repo, tag)
        if record is None or numeric_id(record) != identifier:
            raise ReleaseError('Created release could not be uniquely confirmed; inspect inventory before retrying.')
    identifier = numeric_id(record)
    assert_draft(record, identifier, tag, commit, notes)
    validate_assets(record, paths)
    for path in paths:
        assert_tag(client, repo, tag, commit)
        record = client.request('GET', f'repos/{repo}/releases/{identifier}')
        assert_draft(record, identifier, tag, commit, notes)
        validate_assets(record, paths)
        if any(asset.get('name') == path.name for asset in record['assets'] if isinstance(asset, dict)):
            continue
        # ID-addressed uploads avoid ambiguous by-tag release resolution.
        result = client.request('POST', f'repos/{repo}/releases/{identifier}/assets?name={quote(path.name, safe="")}',
                                path.read_bytes(), upload=True)
        if not asset_matches(result, path):
            raise ReleaseError('Uploaded asset digest could not be verified; inspect the draft before retrying.')
    assert_tag(client, repo, tag, commit)
    record = client.request('GET', f'repos/{repo}/releases/{identifier}')
    assert_draft(record, identifier, tag, commit, notes)
    validate_assets(record, paths, require_all=True)
    return identifier


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--repo', required=True)
    parser.add_argument('--tag', required=True)
    parser.add_argument('--expected-commit', required=True)
    parser.add_argument('--source-root', type=Path, default=ROOT)
    parser.add_argument('--artifact-dir', type=Path)
    parser.add_argument('--verify-tag-only', action='store_true', help='Authenticated read-only repository/tag check.')
    args = parser.parse_args()
    try:
        validate_arguments(args.repo, args.tag, args.expected_commit)
        client = GitHub(os.environ.get('GH_TOKEN', ''))
        if args.verify_tag_only:
            confirm_repository(client, args.repo)
            assert_tag(client, args.repo, args.tag, args.expected_commit)
            print('Existing tag resolves to the exact reviewed source commit. No mutation performed.')
        else:
            if not args.artifact_dir:
                raise ReleaseError('--artifact-dir is required for draft preparation.')
            identifier = create_or_resume(client, args.repo, args.tag, args.expected_commit,
                                          args.source_root.resolve(), args.artifact_dir.resolve())
            print('Verified unpublished draft release ID: ' + str(identifier) + '. No publication or tag mutation performed.')
        return 0
    except (ReleaseError, PackageError, OSError, ValueError) as error:
        print('Draft preparation refused: ' + str(error), file=sys.stderr)
        return 1


if __name__ == '__main__':
    sys.exit(main())
