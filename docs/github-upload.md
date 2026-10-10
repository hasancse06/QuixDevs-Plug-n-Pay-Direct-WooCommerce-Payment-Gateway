# GitHub upload handoff

The prepared repository root is the **contents of `quixdevs-plugnpay-direct-github/`**. Source, lockfiles, tests, CI, documentation, real screenshots, editable branding and WordPress.org artwork belong in the source repository. The installable plugin has a separate runtime allowlist and canonical root.

## Current handoff

The existing Git checkout and configured remote are reused. This cleanup changes no remote settings, creates no commit/tag/release and performs no push. Both original source directories and the distribution's runtime PHP remain unchanged by this presentation pass.

**Source hygiene is prepared for private repository review. Public distribution remains blocked** by upstream licensing and the critical conditions in [release readiness](github-release-readiness.md). A clean source tree does not clear payment security or grant redistribution permission. The visibility/state of any existing remote was not queried or changed.

## Files excluded

The `.gitignore` follows ProductClock's separation of source and local artifacts. It excludes `vendor/`, `node_modules/`, Composer/test caches, coverage, credentials and local WordPress/test configuration, certificates, databases, logs, backups, private snapshots, customer exports, archives, build directories, generated verification reports and the private source-hash inventory.

Local verification files are retained on disk but removed from the current Git index. **`.gitignore` alone does not untrack files already in Git**, and untracking does not erase historical commits. Existing history is retained without rewriting it. Raw reports in earlier commits may still be visible to anyone receiving that history; inspect the history and choose the appropriate history policy before public publication.

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

`composer phpcs` reports the remaining standards findings. `composer release-check` still fails for unresolved release conditions; do not hide that result.

Review `git status --short` and `git diff`. The index cleanup shows removals of local reports, although their disk copies still exist. Once satisfied with the concrete changes, stage the source cleanup with `git add --all`, then review `git diff --cached --stat` and `git diff --cached --check` before making your own commit. No commit is created automatically by this task.

## Git upload versus browser upload

For a private review repository, use the existing Git checkout and an appropriately configured private destination. Review repository visibility, history and permissions yourself before pushing. No push command is executed here.

Git uses `.gitignore` when selecting untracked files. **GitHub's browser uploader does not apply your local ignore file to a dragged folder.** Do not drag this entire working directory, its `.git/`, vendor/cache directories or local reports into the browser. If using browser upload, select only the source/upload candidates validated by `composer repository-check`, including hidden `.github/` and `.gitignore`, and keep the enclosing `-github` folder out of the repository root. No public upload is approved while release blockers remain.

For a fresh checkout, install development dependencies with Composer; ignored reports and private sibling directories are not required. A clean temporary source snapshot is checked by the command above.

## Public release after requirements are satisfied

Use [GitHub listing](github-listing.md) for About text/topics, [branding](branding/README.md) for Social Preview and [draft release notes](github-release-notes.md) for the reviewed version. Confirm licensing, security, real integration, Plugin Check and ZIP installation evidence before clearing release conditions.

Build only the approved runtime archive with `python3 tools/build-release.py`. Its root is `quixdevs-plugnpay-direct/`; development files and promotional assets are excluded. Attach that reviewed ZIP to a separately authorized GitHub Release. Repository source archives and artwork are separate from the installer. [Complete publishing checklist](github-publishing-checklist.md).
