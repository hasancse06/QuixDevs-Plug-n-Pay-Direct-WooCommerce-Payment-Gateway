# GitHub publishing checklist

ProductClock-style source, documentation and artwork preparation is complete for private review. The existing Git checkout and remote were reused; no commit, push, remote change, tag or release is created by this cleanup. Public publication remains blocked by the documented license/security conditions.

## Local presentation and cleanup

- [x] Match the ProductClock README structure, clear feature overview, installation guidance, requirements, developer documentation and maintainer attribution.
- [x] Use the supplied QuixDevs logo, original payment illustration and ProductClock/StockNotifier banner family with creator credit.
- [x] Keep actual documentation captures and manual instructions for unavailable WordPress screenshots.
- [x] Retain runtime source, tests, CI, Composer manifests/lock, documentation, SVG/PNG branding and separate WordPress.org assets.
- [x] Expand `.gitignore` for dependencies, caches, credentials, databases, archives, generated reports and editor/OS artifacts.
- [x] Remove already-tracked local verification reports/private hash inventory from the current index, retaining disk copies and history.
- [x] Keep documentation independent of ignored reports so a fresh clone can pass available checks.
- [x] Validate a temporary clean source snapshot and canonical runtime package paths without writing a release ZIP.

[Upload handoff](github-upload.md) explains Git versus browser upload and the existing-history limitation. Raw local reports are ignored; the [testing guide](testing.md) records current results and limits.

## Review the intended Git commit

- [ ] Run the documented checks, including `composer repository-check` and `python3 tools/check-repository.py --snapshot-check`.
- [ ] Review `git status`, working-tree diff and index removals; confirm ignored local reports remain on disk.
- [ ] Stage the reviewed changes yourself with `git add --all`; inspect the cached diff/stat before committing.
- [ ] Inspect existing history before any public upload. Untracking files does not remove historical reports; no history rewrite is performed automatically.
- [ ] Confirm destination permissions and visibility before a private review push. `.gitignore` does not filter a folder dragged into GitHub's browser uploader.

## Before public publication

- [ ] Obtain authoritative modified-source redistribution rights and the correct license/notices. Keep original PatSaTECH attribution; do not copy ProductClock's GPL grant into this unrelated upstream code.
- [ ] Resolve shared charge/refund locking, replay/uncertain outcomes, refund return/pending semantics, strict validation, HTTP 123, transport/parser/output/logging defects and test the corrections.
- [ ] Complete the authenticated backend migration for the former embedded-key-only application; verify WordPress identity and order permissions in a real disposable store.
- [ ] Verify supported PHP/WP/WC integration, Plugin Check and actual ZIP activation with intercepted processor transport; conduct authorized provider sandbox checks separately.
- [ ] Review remaining PHPCS findings and confirm a private security reporting channel.
- [ ] Clear recorded release requirements only after supporting evidence exists, then review the final source/archive before approval.

## Release and presentation handoff

Use [GitHub listing](github-listing.md), [branding upload instructions](branding/README.md), [draft release notes](github-release-notes.md) and [WordPress.org artwork](../wordpress-org-assets/README.md). Keep Website unset until a confirmed product page is appropriate. No fake CI badge, download count, review or compatibility claim is added.

After approval, build the canonical runtime-only ZIP with `python3 tools/build-release.py`, inspect its contents, verify it installs, record its hash and attach it to a separately authorized GitHub Release. Source archives are separate from the installer. No publish/deploy job is enabled.
