# GitHub distribution implementation report

Target: `quixdevs-plugnpay-direct-github/`, product/slug `quixdevs-plugnpay-direct`, target version 1.0.0. **Prepared locally; public distribution blocked.**

The destination existed empty. A controlled allowlist copied the existing QuixDevs runtime plus relevant workspace-level development documents; the requested source-local report path did not exist. The actual source and earlier root reports were inspected. Both source directories remain byte-for-byte unchanged, including their file inventories. [Source audit](source-audit.md), [copy allowlist](copy-allowlist.json), [hash manifest](source-hashes.json).

The destination main file was written only after removing the embedded verification literal in memory. Checkout now requires a server-defined nonempty key and HTTPS without a fallback. The existing hash input order/provider field is preserved. No credential value is stored in reports, fixtures or the review diff. Merchant rotation remains future authorized work.

REST retains the existing route/fields and authorized handler. A new permission class/callback and repeated handler check require HTTPS, WordPress identity, constant-time existing-key validation, strict order ID and ownership or explicitly privileged order permission. The former embedded-key-only app is denied; backend migration is documented. Invalid/foreign/wrong-gateway saved tokens and missing transaction references stop before HTTP. Two ineffective optional defaults were removed to avoid PHP 8 deprecations. No financial parser, request amount, KYD, refund mapping or settlement behavior was rewritten. [Sanitized runtime diff](verification/runtime-changes.diff), [comparison](functional-parity.md).

The repository includes a professional README, REST reference/authentication, safe cURL/PHP/JavaScript/Ionic specimens, guarded Postman collection, merchant/refund/security/compatibility docs, attribution/license reviews, GitHub metadata/notes, original artwork, future directory readme, issue/PR templates, ignore rules, Composer lock/development tooling and GitHub Actions. No inherited PNGs are included. Packaging uses canonical root and an explicit runtime allowlist; no development/promotional files enter the in-memory archive.

[Current testing](testing.md) records 25 tests/127 assertions, syntax and scoped guards passing. Full standards checking reports 3,858 errors/328 warnings and is advisory in CI, not suppressed. Known defects are deliberately reproduced by characterization tests. No actual WordPress/WooCommerce/HPOS/provider/Plugin Check/install verification is claimed. No Git initialization, public push, release ZIP, live payment or database action occurred.

License confirmation, shared locking/replay, refund correctness, REST/input/output/transport/logging work and real integration remain blockers. [A–O release-readiness report](github-release-readiness.md) is the authoritative final status.
