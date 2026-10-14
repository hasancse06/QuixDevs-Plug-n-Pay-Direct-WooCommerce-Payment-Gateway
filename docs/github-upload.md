# GitHub upload handoff

The prepared repository root is the **contents of `quixdevs-plugnpay-direct-github/`**. Source, lockfiles, tests, CI, documentation, real screenshots, editable branding and WordPress.org artwork belong in the source repository. The installable plugin has a separate runtime allowlist and canonical root.

## Current handoff

The existing Git checkout and configured remote are reused. This cleanup changes no remote settings, creates no commit/tag/release and performs no push. Both original source directories remain unchanged. This candidate contains the scoped validation/response/data-handling changes recorded in the source audit and final report.

**The source is prepared for review in the existing public repository.** Md Abu Hasan authorizes GPL-2.0-or-later for developer-owned code. Quality remediation and core integration verification are recorded in [release readiness](github-release-readiness.md). Stable release/deployment still requires acceptance of the authenticated REST client migration. The existing remote/public history was inspected read-only; no repository visibility or remote setting was changed.

## Files excluded

The `.gitignore` follows ProductClock's separation of source and local artifacts. It excludes `vendor/`, `node_modules/`, Composer/test caches, coverage, credentials and local WordPress/test configuration, certificates, databases, logs, backups, private snapshots, customer exports, archives, build directories, generated verification reports and the private source-hash inventory.

Local verification files remain on disk and are excluded from the source-upload inventory. No current tracked file conflicts with the ignore policy; the Git index is unchanged. **`.gitignore` alone does not untrack files already in Git**, and no historical commit was rewritten. Review source history before any future publication action.

## Check and review the intended commit

From this repository root:

```sh
composer install --no-interaction --prefer-dist
composer lint
composer test
php tests/missing-dependency.php
php tests/missing-key.php
composer secrets
composer docs
composer repository-check
python3 tools/check-repository.py --snapshot-check
composer package-check
```

`composer phpcs` passes with zero errors/warnings and is required in CI and manual package verification. Plugin Check has zero errors/two documented legal-notice Markdown warnings. `composer release-check` intentionally refuses while the REST client cutover approval remains pending.

Review `git status --short`, `git diff` and the exact file inventory in the release report. Nothing was staged. Once satisfied with the concrete changes, stage the intended source files yourself and inspect the staged diff before committing. No commit is created automatically by this task.

## Git upload versus browser upload

The existing repository is public and its remote/branch were verified read-only. Keep this checkout and remote; review the diff, source history and destination permissions before any separately authorized push. No push command is executed here.

Git uses `.gitignore` when selecting untracked files. **GitHub's browser uploader does not apply your local ignore file to a dragged folder.** Do not drag this entire working directory, its `.git/`, vendor/cache directories or local reports into the browser. If using browser upload, select only the source/upload candidates validated by `composer repository-check`, including hidden `.github/` and `.gitignore`, and keep the enclosing `-github` folder out of the repository root. No public upload is approved while release blockers remain.

For a fresh checkout, install development dependencies with Composer; ignored reports and private sibling directories are not required. A clean temporary source snapshot is checked by the command above.

## Public release after requirements are satisfied

Use [GitHub listing](github-listing.md) for About text/topics, [branding](branding/README.md) for Social Preview and [draft release notes](github-release-notes.md) for the reviewed version. Confirm licensing, security, real integration, Plugin Check and ZIP installation evidence before clearing release conditions.

After release gates are cleared, build the approved runtime archive with `python3 tools/build-release.py --release`. The default command produces an approval-pending candidate ZIP. Approved draft mode requires the recorded gates. Its root is `quixdevs-plugnpay-direct/`; development files and promotional assets are excluded. Attach that reviewed ZIP to a separately authorized GitHub Release. Repository source archives and artwork are separate from the installer. [Complete publishing checklist](github-publishing-checklist.md).
