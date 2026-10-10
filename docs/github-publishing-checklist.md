# GitHub publishing checklist

Public publication is blocked. No repository/remote was initialized, staging performed or source pushed.

Before a future authorized publication:

1. Obtain and record modified-source redistribution rights, correct licensing metadata and required notices.
2. Resolve shared charge/refund locking and replay, refund contract, strict input validation, HTTP status, transport/error/parser/output/logging defects; regression-test each change.
3. Complete the mobile/backend migration: server-held keys, authenticated customer/order scope and explicitly provisioned WordPress backend permissions. Never embed Application Passwords or merchant credentials in apps.
4. Verify real disposable WordPress/WooCommerce installation, Plugin Check, supported PHP matrix and provider sandbox behavior; produce sanitized evidence.
5. Review `.gitignore`, `composer.lock`, every file to be staged, original artwork and upstream notices. Run `composer secrets`; locally also compare the private inherited value without printing it. The destination main file is no longer ignored or credential-bearing.
6. Run documented tests/docs checks and full PHPCS; review residual findings rather than globally suppressing them. GitHub Actions configuration is not proof of a hosted run.
7. Set release-state conditions true only after supporting evidence exists. Validate the canonical allowlist/archive and actual ZIP installation; obtain final release approval.
8. Create the correctly named repository only when authorized. Apply [listing](github-listing.md), upload [social preview](branding/README.md), and publish approved notes/archive. Attach no development vendor/cache, source snapshots or unlicensed artwork.

No publication/deployment action is automated by this workspace. [Current status](github-release-readiness.md).
