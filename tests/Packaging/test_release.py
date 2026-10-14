"""Packaging and GitHub release invariants. All network mutations use an in-memory fake."""
import copy
import importlib.util
import io
import json
from pathlib import Path
import re
import subprocess
import sys
import tempfile
import unittest
from unittest.mock import patch
from urllib.error import HTTPError, URLError
import zipfile

ROOT = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(ROOT / 'tools'))
import release_package as package

SPEC = importlib.util.spec_from_file_location('draft_release', ROOT / 'tools/create-draft-release.py')
draft = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(draft)
REPO = 'example/reviewed-plugin'
COMMIT = 'a' * 40
TAG = 'v1.0.0'


def fixture(directory, approved=False):
    root = Path(directory)
    for name in ('includes', 'assets', 'languages', 'docs'):
        (root / name).mkdir()
    (root / (package.SLUG + '.php')).write_text("""<?php
/**
 * Plugin Name: QuixDevs Plug'n Pay Direct
 * Version: 1.0.0
 * Requires PHP: 8.2
 * Author: QuixDevs
 * Author URI: https://quixdevs.com/
 * Text Domain: quixdevs-plugnpay-direct
 */
define('QUIXDEVS_PLUGNPAY_VERSION', '1.0.0');
require_once __DIR__ . '/includes/runtime.php';
// require_once __DIR__ . '/disabled.php';
""")
    (root / 'includes/runtime.php').write_text('<?php\n// Synthetic runtime dependency.\n')
    (root / 'languages/index.php').write_text('<?php\n// Silence.\n')
    (root / 'assets/README.txt').write_text('Runtime assets.\n')
    (root / 'readme.txt').write_text('=== QuixDevs Plug\'n Pay Direct ===\nStable tag: 1.0.0\n')
    (root / 'UPSTREAM-NOTICES.md').write_text('Synthetic attribution notice.\n')
    (root / 'docs/github-release-notes.md').write_text('Synthetic reviewed release notes.\n')
    (root / 'docs/release-state.json').write_text(json.dumps({'release_requirements': {key: approved for key in package.REQUIREMENTS}}))
    if approved:
        (root / 'LICENSE').write_text('Synthetic unit-test license evidence; not a real distribution license.\n')
    return root


class PackagingTests(unittest.TestCase):
    def setUp(self):
        self.temporary = tempfile.TemporaryDirectory()
        self.addCleanup(self.temporary.cleanup)
        self.root = fixture(self.temporary.name)

    def build(self, **kwargs):
        return package.build_archive(self.root, scan=False, **kwargs)

    def test_candidate_zip_is_canonical_and_runtime_only(self):
        for name in ('tests', '.github', 'vendor', 'node_modules', '.git'):
            (self.root / name).mkdir()
            (self.root / name / 'excluded.php').write_text('excluded')
        (self.root / 'docs/report.md').write_text('excluded')
        data, manifest = self.build()
        self.assertFalse(manifest['release_candidate'])
        self.assertIn('RELEASE CANDIDATE', manifest['classification'])
        self.assertEqual(len(manifest['blocked_requirements']), 7)
        with zipfile.ZipFile(io.BytesIO(data)) as archive:
            self.assertIsNone(archive.testzip())
            self.assertTrue(all(name.startswith(package.SLUG + '/') for name in archive.namelist()))
            self.assertEqual(len(archive.namelist()), 6)
            self.assertFalse(any(part in {'tests', '.github', 'vendor', 'docs', '.git', 'node_modules'} for name in archive.namelist() for part in Path(name).parts))

    def test_bytes_and_metadata_are_deterministic(self):
        first, manifest = self.build()
        (self.root / 'includes/runtime.php').touch()
        second, other = self.build()
        self.assertEqual(first, second)
        self.assertEqual(manifest, other)
        with zipfile.ZipFile(io.BytesIO(first)) as archive:
            self.assertEqual(archive.namelist(), sorted(archive.namelist()))
            for entry in archive.infolist():
                self.assertEqual(entry.date_time, package.ZIP_TIME)
                self.assertEqual(entry.external_attr >> 16, 0o100644)
                self.assertEqual(entry.create_system, 3)

    def test_runtime_symlink_is_rejected(self):
        (self.root / 'includes/escape.php').symlink_to('/etc/hosts')
        with self.assertRaisesRegex(package.PackageError, 'symlinks'):
            self.build()

    def test_missing_static_dependency_is_rejected(self):
        (self.root / 'includes/runtime.php').unlink()
        with self.assertRaisesRegex(package.PackageError, 'dependency absent'):
            self.build()

    def test_dynamic_dependency_is_rejected(self):
        with (self.root / (package.SLUG + '.php')).open('a') as stream:
            stream.write('\nrequire_once $unresolved_path;\n')
        with self.assertRaisesRegex(package.PackageError, 'dynamic PHP dependency'):
            self.build()

    def test_header_and_readme_version_must_match(self):
        (self.root / 'readme.txt').write_text('Stable tag: 2.0.0\n')
        with self.assertRaisesRegex(package.PackageError, 'stable tag'):
            self.build()

    def test_gateway_metadata_must_match(self):
        path = self.root / (package.SLUG + '.php')
        path.write_text(path.read_text().replace('Author: QuixDevs', 'Author: Someone Else'))
        with self.assertRaisesRegex(package.PackageError, 'Author'):
            self.build()

    def test_release_refuses_blocked_state_before_scan(self):
        with patch.object(package, 'run_secret_scan') as scanner:
            with self.assertRaisesRegex(package.PackageError, 'BLOCKED'):
                package.build_archive(self.root, release=True)
        scanner.assert_not_called()

    def test_empty_truthy_or_incomplete_gate_inventory_never_approves(self):
        for requirements in ({}, {key: 'true' for key in package.REQUIREMENTS}, {key: 1 for key in package.REQUIREMENTS}):
            (self.root / 'docs/release-state.json').write_text(json.dumps({'release_requirements': requirements}))
            with self.subTest(requirements=requirements), self.assertRaises(package.PackageError):
                self.build(release=True)

    def test_approved_release_still_requires_license(self):
        (self.root / 'docs/release-state.json').write_text(json.dumps({'release_requirements': {key: True for key in package.REQUIREMENTS}}))
        with self.assertRaisesRegex(package.PackageError, 'license file'):
            self.build(release=True)

    def test_runtime_secret_policy_failure_prevents_packaging(self):
        failure = subprocess.CompletedProcess([], 1, 'PRIVATE_MATCH', 'PRIVATE_STDERR')
        with patch.object(package.subprocess, 'run', return_value=failure):
            with self.assertRaises(package.PackageError) as raised:
                package.build_archive(self.root)
        self.assertNotIn('PRIVATE', str(raised.exception))

    def test_checksum_and_manifest_detect_archive_tampering(self):
        data, manifest = self.build()
        output = self.root / 'dist'
        package.write_artifacts(output, data, manifest)
        with patch.object(package, 'run_secret_scan'):
            self.assertEqual(package.verify_artifacts(self.root, output), manifest)
            (output / (package.SLUG + '.zip')).write_bytes(data + b'UNEXPECTED')
            with self.assertRaisesRegex(package.PackageError, 'Archive bytes differ'):
                package.verify_artifacts(self.root, output)

    def test_checksum_tampering_is_rejected(self):
        data, manifest = self.build()
        output = self.root / 'dist'
        package.write_artifacts(output, data, manifest)
        (output / (package.SLUG + '.zip.sha256')).write_text('bad checksum\n')
        with patch.object(package, 'run_secret_scan'), self.assertRaisesRegex(package.PackageError, 'Checksum'):
            package.verify_artifacts(self.root, output)

    def test_comments_and_include_words_in_strings_are_not_dependencies(self):
        with (self.root / 'includes/runtime.php').open('a') as stream:
            stream.write("\n// include '/not-real.php';\n$notice = 'Please include your reference';\n")
        self.build()

    def test_nested_directory_dependency_is_resolved_relative_to_its_file(self):
        (self.root / 'includes/runtime.php').write_text("<?php\nrequire_once __DIR__ . '/nested/child.php';\n")
        (self.root / 'includes/nested').mkdir()
        (self.root / 'includes/nested/child.php').write_text('<?php\n// Child.\n')
        data, manifest = self.build()
        self.assertIn('includes/nested/child.php', manifest['entries'])

    def test_symlink_output_does_not_overwrite_other_files(self):
        data, manifest = self.build()
        output = self.root / 'dist'
        output.mkdir()
        protected = self.root / 'untouched.txt'
        protected.write_text('untouched')
        (output / (package.SLUG + '.zip')).symlink_to(protected)
        with self.assertRaisesRegex(package.PackageError, 'outputs may not be symlinks'):
            package.write_artifacts(output, data, manifest)
        self.assertEqual(protected.read_text(), 'untouched')

    def test_cli_tooling_revision_packages_only_the_explicit_source_checkout(self):
        # The executable lives in this reviewed tooling checkout; runtime data
        # lives in a separate synthetic source checkout, just like Actions.
        marker = '// exact-source-only-marker\n'
        source_file = self.root / 'includes/runtime.php'
        source_file.write_text(source_file.read_text() + marker)
        result = subprocess.run([sys.executable, str(ROOT / 'tools/build-release.py'), '--source-root', str(self.root)],
                                cwd=ROOT, capture_output=True, text=True)
        self.assertEqual(result.returncode, 0, result.stderr)
        self.assertIn('RELEASE CANDIDATE', result.stdout)
        with zipfile.ZipFile(self.root / 'dist/candidate' / (package.SLUG + '.zip')) as archive:
            self.assertIn(marker, archive.read(package.SLUG + '/includes/runtime.php').decode())
            self.assertNotIn(package.SLUG + '/includes/class-rest-authorization.php', archive.namelist())

    def test_cli_check_never_writes_a_zip_and_release_refuses_blocked_gates(self):
        base = [sys.executable, str(ROOT / 'tools/build-release.py'), '--source-root', str(self.root)]
        checked = subprocess.run(base + ['--check'], capture_output=True, text=True)
        self.assertEqual(checked.returncode, 0, checked.stderr)
        self.assertFalse((self.root / 'dist').exists())
        refused = subprocess.run(base + ['--release'], capture_output=True, text=True)
        self.assertEqual(refused.returncode, 1)
        self.assertIn('BLOCKED', refused.stderr)
        self.assertFalse((self.root / 'dist').exists())


class FakeGitHub:
    def __init__(self, notes, paths, existing=False):
        self.notes = notes
        self.paths = paths
        self.mutations = []
        self.tag = COMMIT
        self.records = {}
        if existing:
            self.records[101] = self.record()

    def record(self):
        return {'id': 101, 'tag_name': TAG, 'draft': True, 'target_commitish': COMMIT,
                'body': self.notes, 'assets': []}

    def request(self, method, path, payload=None, upload=False):
        if method != 'GET':
            self.mutations.append((method, path, payload, upload))
        if path == 'repos/' + REPO:
            return {'full_name': REPO, 'permissions': {'push': False}}
        if '/git/ref/tags/' in path:
            return {'object': {'type': 'commit', 'sha': self.tag}}
        if '/releases?' in path:
            return copy.deepcopy(list(self.records.values()))
        if path.endswith('/releases') and method == 'POST':
            self.records[101] = dict(self.record(), **payload)
            return copy.deepcopy(self.records[101])
        match = re.search(r'/releases/(\d+)(/assets)?', path)
        if match:
            record = self.records[int(match[1])]
            if match[2] and method == 'POST':
                name = path.split('name=')[1]
                asset = {'id': 200 + len(record['assets']), 'name': name, 'state': 'uploaded',
                         'size': len(payload), 'digest': 'sha256:' + package.digest(payload)}
                record['assets'].append(asset)
                return copy.deepcopy(asset)
            return copy.deepcopy(record)
        raise AssertionError('Unexpected fake endpoint: ' + method + ' ' + path)


class DraftTests(unittest.TestCase):
    def setUp(self):
        self.temporary = tempfile.TemporaryDirectory()
        self.addCleanup(self.temporary.cleanup)
        self.root = fixture(self.temporary.name, approved=True)
        self.output = self.root / 'dist'
        self.scan = patch.object(package, 'run_secret_scan')
        self.scan.start()
        self.addCleanup(self.scan.stop)
        data, self.manifest = package.build_archive(self.root, release=True)
        package.write_artifacts(self.output, data, self.manifest)
        self.notes = draft.release_notes(self.root, COMMIT, self.manifest)
        self.paths = [self.output / (package.SLUG + '.zip'), self.output / (package.SLUG + '.zip.sha256')]
        self.identity = patch.object(draft, 'source_identity')
        self.identity.start()
        self.addCleanup(self.identity.stop)

    def run_release(self, fake):
        return draft.create_or_resume(fake, REPO, TAG, COMMIT, self.root, self.output)

    def test_create_is_draft_only_and_uploads_two_assets_by_id(self):
        fake = FakeGitHub(self.notes, self.paths)
        self.assertEqual(self.run_release(fake), 101)
        self.assertEqual(len(fake.mutations), 3)
        create = fake.mutations[0]
        self.assertTrue(create[2]['draft'])
        self.assertEqual(create[2]['target_commitish'], COMMIT)
        self.assertEqual(create[2]['make_latest'], 'false')
        self.assertTrue(all('/releases/101/assets?name=' in call[1] for call in fake.mutations[1:]))
        self.assertTrue(all(call[0] == 'POST' for call in fake.mutations))
        self.assertFalse(any('/git/' in call[1] for call in fake.mutations))

    def test_resuming_same_draft_reuses_verified_assets(self):
        fake = FakeGitHub(self.notes, self.paths)
        self.run_release(fake)
        fake.mutations.clear()
        self.assertEqual(self.run_release(fake), 101)
        self.assertEqual(fake.mutations, [])

    def test_published_release_is_never_modified(self):
        fake = FakeGitHub(self.notes, self.paths, existing=True)
        fake.records[101]['draft'] = False
        with self.assertRaisesRegex(draft.ReleaseError, 'Published'):
            self.run_release(fake)
        self.assertEqual(fake.mutations, [])

    def test_changed_source_notes_and_assets_require_manual_review(self):
        for changes in ({'target_commitish': 'b' * 40}, {'body': 'Maintainer edit'},
                        {'assets': [{'id': 201, 'name': self.paths[0].name, 'state': 'uploaded', 'size': 1, 'digest': 'sha256:bad'}]}):
            fake = FakeGitHub(self.notes, self.paths, existing=True)
            fake.records[101].update(changes)
            with self.subTest(changes=changes), self.assertRaises(draft.ReleaseError):
                self.run_release(fake)
            self.assertEqual(fake.mutations, [])

    def test_missing_digest_is_not_treated_as_matching(self):
        asset = {'id': 5, 'name': self.paths[0].name, 'size': self.paths[0].stat().st_size, 'state': 'uploaded'}
        self.assertFalse(draft.asset_matches(asset, self.paths[0]))

    def test_distinct_duplicate_release_ids_prevent_all_mutations(self):
        fake = FakeGitHub(self.notes, self.paths, existing=True)
        fake.records[102] = dict(fake.record(), id=102)
        with self.assertRaisesRegex(draft.ReleaseError, 'Multiple distinct'):
            self.run_release(fake)
        self.assertEqual(fake.mutations, [])

    def test_repeated_same_numeric_id_is_deduplicated(self):
        fake = FakeGitHub(self.notes, self.paths, existing=True)
        original = fake.request
        def duplicate(method, path, *args, **kwargs):
            value = original(method, path, *args, **kwargs)
            return value + value if '/releases?' in path else value
        fake.request = duplicate
        self.assertEqual(self.run_release(fake), 101)
        self.assertEqual(len(fake.mutations), 2)

    def test_inventory_follows_pages_and_confirms_unique_ids(self):
        record = FakeGitHub(self.notes, self.paths, existing=True).record()
        client = unittest.mock.Mock()
        client.request.side_effect = [[record] + [{'tag_name': 'other'}] * 99, [record], record]
        self.assertEqual(draft.inventory(client, REPO, TAG)['id'], 101)
        self.assertIn('page=2', client.request.call_args_list[1].args[1])
        self.assertEqual(client.request.call_args_list[-1].args[1], f'repos/{REPO}/releases/101')

    def test_inventory_pagination_limit_never_proves_absence(self):
        client = unittest.mock.Mock()
        client.request.return_value = [{'tag_name': 'other'}] * 100
        with self.assertRaisesRegex(draft.ReleaseError, 'pagination limit'):
            draft.inventory(client, REPO, TAG)
        self.assertEqual(client.request.call_count, 100)

    def test_publication_during_upload_stops_remaining_assets(self):
        fake = FakeGitHub(self.notes, self.paths, existing=True)
        original = fake.request
        def publish_after_upload(method, path, *args, **kwargs):
            result = original(method, path, *args, **kwargs)
            if method == 'POST' and '/assets?' in path:
                fake.records[101]['draft'] = False
            return result
        fake.request = publish_after_upload
        with self.assertRaisesRegex(draft.ReleaseError, 'Published'):
            self.run_release(fake)
        self.assertEqual(len(fake.mutations), 1)

    def test_existing_conflicting_checksum_preflights_before_any_upload(self):
        fake = FakeGitHub(self.notes, self.paths, existing=True)
        fake.records[101]['assets'] = [{'id': 5, 'name': self.paths[1].name, 'state': 'uploaded', 'size': 1, 'digest': 'sha256:bad'}]
        with self.assertRaisesRegex(draft.ReleaseError, 'no overwrites'):
            self.run_release(fake)
        self.assertEqual(fake.mutations, [])

    def test_moved_tag_prevents_creation(self):
        fake = FakeGitHub(self.notes, self.paths)
        fake.tag = 'b' * 40
        with self.assertRaisesRegex(draft.ReleaseError, 'remote tag differs'):
            self.run_release(fake)
        self.assertEqual(fake.mutations, [])

    def test_blocked_gates_prevent_first_network_request(self):
        (self.root / 'docs/release-state.json').write_text(json.dumps({'release_requirements': {key: False for key in package.REQUIREMENTS}}))
        fake = FakeGitHub(self.notes, self.paths)
        with patch.object(fake, 'request') as api, self.assertRaisesRegex(package.PackageError, 'BLOCKED'):
            self.run_release(fake)
        api.assert_not_called()

    def test_archive_tampering_prevents_first_network_request(self):
        self.paths[0].write_bytes(b'UNVERIFIED')
        fake = FakeGitHub(self.notes, self.paths)
        with patch.object(fake, 'request') as api, self.assertRaises(package.PackageError):
            self.run_release(fake)
        api.assert_not_called()

    def test_tag_peels_annotated_refs_and_rejects_cycles(self):
        client = unittest.mock.Mock()
        client.request.side_effect = [{'object': {'type': 'tag', 'sha': 'b' * 40}},
                                      {'object': {'type': 'commit', 'sha': COMMIT}}]
        self.assertEqual(draft.tag_commit(client, REPO, TAG), COMMIT)
        client.request.side_effect = [{'object': {'type': 'tag', 'sha': 'b' * 40}}] * 2
        with self.assertRaises(draft.ReleaseError):
            draft.tag_commit(client, REPO, TAG)

    def test_numeric_ids_reject_boolean_string_and_zero(self):
        for identifier in (True, '101', 0, None):
            with self.subTest(identifier=identifier), self.assertRaises(draft.ReleaseError):
                draft.numeric_id({'id': identifier})

    def test_installation_token_does_not_require_collaborator_push_flag(self):
        fake = FakeGitHub(self.notes, self.paths)
        draft.confirm_repository(fake, REPO)
        self.assertEqual(fake.mutations, [])

    def test_http_post_404_is_not_missing_release_and_never_leaks_values(self):
        client = draft.GitHub('synthetic-api-token')
        failure = HTTPError('https://api.github.com/repos/' + REPO + '/releases', 404, 'PRIVATE_BODY', {}, None)
        with patch.object(client.opener, 'open', side_effect=failure), self.assertRaises(draft.ReleaseError) as raised:
            client.request('POST', 'repos/' + REPO + '/releases', {'body': 'PRIVATE_REQUEST'})
        self.assertIn('Workflows:write', str(raised.exception))
        self.assertNotIn('PRIVATE', str(raised.exception))
        self.assertNotIn(client.token, str(raised.exception))

    def test_transport_error_does_not_expose_arbitrary_server_text(self):
        client = draft.GitHub('synthetic-api-token')
        with patch.object(client.opener, 'open', side_effect=URLError('PRIVATE_TRANSPORT')), self.assertRaises(draft.ReleaseError) as raised:
            client.request('GET', 'repos/' + REPO)
        self.assertNotIn('PRIVATE', str(raised.exception))

    def test_workflow_separates_source_sha_from_workflow_sha_and_write_scope(self):
        workflow = (ROOT / '.github/workflows/release.yml').read_text()
        readonly, write_job = workflow.split('\n  draft:\n')
        self.assertNotIn('contents: write', readonly)
        self.assertIn('contents: write', write_job)
        self.assertIn('ref: ${{ github.workflow_sha }}', readonly)
        self.assertIn('ref: ${{ inputs.source_sha }}', readonly)
        self.assertIn('--source-root source', readonly)
        self.assertIn('--root source', readonly)
        self.assertIn('permission-workflows: write', write_job)
        self.assertIn('[[ -n "$APP_TOKEN" ]]', write_job)
        self.assertNotIn('push:', workflow)
        self.assertNotIn('gh release publish', workflow)
        self.assertNotIn('git tag ', workflow)


if __name__ == '__main__':
    unittest.main()
