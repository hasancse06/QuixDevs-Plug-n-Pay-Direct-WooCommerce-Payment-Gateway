# Release conditions

**PREPARED LOCALLY; public GitHub and WordPress.org release blocked.** [Machine-readable state](release-state.json) contains seven unresolved requirements. `composer release-check` must currently fail and `tools/build-release.py` must refuse to write a release ZIP.

- Confirm upstream modified redistribution rights and required license/notices.
- Resolve shared payment concurrency/replay across checkout and REST; verify uncertain-outcome reconciliation.
- Correct refund success/failure/pending contracts and repeated-return behavior with provider evidence.
- Resolve REST/card input validation, status 123, transport error/parser/output and sensitive logging disclosure.
- Verify disposable WordPress/WooCommerce integration, intended PHP versions and actually supported optional features.
- Complete Plugin Check and review all relevant findings.
- Verify canonical ZIP installation in a disposable site.

Also complete merchant-key rotation/configuration and backend client migration, review remaining PHPCS findings, capture genuine UI images and validate final metadata/notices. The in-memory archive check establishes paths/exclusions only; it is not installation approval. Update state only after evidence, and obtain authorization before any public push/submission. [Detailed readiness report](github-release-readiness.md).
