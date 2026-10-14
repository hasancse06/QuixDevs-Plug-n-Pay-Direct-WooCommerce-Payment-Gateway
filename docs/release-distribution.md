# Runtime packaging and unpublished draft releases

The repository retains source, tests, documentation, branding and release tools. The installer is a separate deterministic runtime-only ZIP. Both original source folders remain untouched. No commit, push, tag or live GitHub release mutation is executed during local preparation.

## Build modes

```sh
python3 tools/build-release.py --check
python3 tools/build-release.py
python3 tools/build-release.py --release
```

`--check` inspects headers, static PHP include dependencies, canonical paths, exclusions and CRC in memory. The default writes `dist/candidate/quixdevs-plugnpay-direct.zip`, `.zip.sha256` and `package-manifest.json`, explicitly classified **RELEASE CANDIDATE — PENDING RECORDED APPROVALS**. `--release` refuses unless all seven strict boolean gates are true and verified license text is present; it would use `dist/release/`.

The ZIP root is `quixdevs-plugnpay-direct/`. A fixed timestamp/permissions, sorted entries and fixed compression produce repeatable bytes. The manifest is external to the runtime ZIP and records classification, byte count, digest, file hashes and blockers. Verify from its directory with `shasum -a 256 -c quixdevs-plugnpay-direct.zip.sha256` and `unzip -t quixdevs-plugnpay-direct.zip`. The [final report](github-release-readiness.md) records the tested bytes/hash.

Only main PHP, local runtime includes/assets/languages, readme, LICENSE, applicable third-party license text and notices are selected. No repository metadata, tests, Composer/vendor/tooling, docs, reports, screenshots or promotional artwork is shipped. PHP tokenization reads include expressions as data and never evaluates the gateway during package inspection. Secret scanning uses the exact selected source checkout with reviewed tooling.

## CI and workflow identities

`.github/workflows/ci.yml` runs local contracts, syntax, policy/docs/repository checks and mocked packaging/draft tests with read-only permissions. Full PHPCS is required in both workflows: CI retains its report even after a failure, and the release workflow checks the exact source checkout before packaging or draft preparation.

`.github/workflows/release.yml` is **workflow_dispatch only**. Inputs:

| Input | Meaning |
|---|---|
| `source_sha` | Exact full 40-character reviewed source commit SHA |
| `tag` | Existing `v1.0.0` tag, required to resolve to that source for draft mode |
| `prepare_draft` | False by default: build candidate artifacts only; true requires every release gate |
| `use_app_token` | Explicit selection of configured repository-scoped installation-token authentication |

The workflow/tooling checkout uses `github.workflow_sha`. The runtime checkout uses `source_sha`, independently. Thus a workflow repair can run from its reviewed revision while packaging the exact intended source commit. A manual run packages committed source; uncommitted local edits are not uploaded or substituted. Both jobs confirm immutable identity; the draft helper rebuilds and compares archive/checksum/manifest bytes before network mutations.

## Tokens and permissions

Tests/builds receive only `contents: read`; only the dependent draft job receives `contents: write`. Checkout credentials are not persisted. The optional App path uses `RELEASE_APP_CLIENT_ID` repository variable and `RELEASE_APP_PRIVATE_KEY` secret. Scope the App installation to this repository and grant both Contents:write and Workflows:write: this workflow explicitly requests both in App mode. Workflows permission covers release operations involving source commits with workflow changes. Selecting App mode and failing to mint a token is a hard failure, never a fallback.

Repository access is tested through authenticated API operations; collaborator `permissions.push` is not used as a substitute for installation-token scope. A 403/404 during release creation is not treated as proof that no release exists. [GitHub create-release API](https://docs.github.com/en/rest/releases/releases#create-a-release), [official installation-token action](https://github.com/actions/create-github-app-token).

## Safe draft resume

The helper requires an already-existing lightweight/annotated tag resolving to the exact source commit. It calls no tag-create/update/delete endpoint and never moves a tag. GitHub's release-create API can implicitly create a missing tag; the helper checks existence immediately before creation. An independent concurrent tag deletion cannot be made atomic with release creation, so maintainers must freeze tag changes during a run and review this external race before enabling draft writes.

Release inventory is paginated, matches the requested tag and deduplicates numeric IDs. Distinct matching IDs cause refusal. An existing release must still be an unpublished draft with identical source/notes. Published releases, mismatching drafts and mismatching assets are never overwritten/deleted. Asset uploads address the confirmed numeric release ID and verify names, sizes and SHA-256 digests. Existing identical assets are retained; partial drafts can resume. API redirects and server-provided upload URLs are not trusted for credential forwarding. Ambiguous transport failures stop for inventory review.

There is no publish operation, no tag-creation job and no automated push. Workflow correctness is covered by mocked API tests; a hosted GitHub run and installation-token/API side effects have not been exercised here. Mocked processor responses describe the verification scope; they do not make the existing production product experimental. Candidate labeling records outstanding approvals, not the use of mocks. The current report lists concrete release conditions.
