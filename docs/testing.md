# Verification report — 2026-10-15

The maintainer reports production use of the original gateway. These results independently verify the release candidate with intercepted provider HTTP. No live charge/refund, merchant login or deployment occurred. Original16-file and intermediate22-file source inventories remain unchanged.

| Check | Executed final result |
|---|---|
| PHPUnit 9.6.38 on PHP 8.2.4 and PHP 8.4.13 | **102 tests /755 assertions passed on each** |
| Python packaging/draft tests | **38 passed**, mocked GitHub API |
| Syntax on PHP 8.2.4 and PHP 8.4.13 | **48 project PHP files passed on each** |
| Direct main access / missing WooCommerce / missing server verification key | Passed on both PHP versions; no provider submission |
| Real WordPress 6.9 / WooCommerce 10.9.0 / MariaDB 10.4.28 / PHP 8.4.13 | **149 posts +149 HPOS =298 assertions passed** |
| Additional actual PHP 8.2.4 HPOS platform run | **39 assertions passed** |
| Final ZIP installation and activation | Real WP-CLI; all **32** archived files match installed hashes before/after tests |
| Official WordPress/WooCommerce/Plugin Check code checksums | Verified |
| Full PHPCS 3.13.6 / WPCS 3.4.1 | **368 errors/41 warnings →0 errors/0 warnings** |
| Plugin Check2.1.0 including experimental checks | **40 errors/96 warnings →0 errors/2 warnings**; actual messages counted |
| actionlint 1.7.12 | Both workflows pass; official binary archive checksum verified |
| Secret/source/docs/repository/snapshot/archive policies | Pass; no private values printed; source inventories unchanged |
| Approved-release gate | Expected refusal for **REST client cutover acceptance only** |

Final installer: **77,662 bytes**, SHA-256 `5079dae17b83a5669d2a344f74105927c0c26121468d1f0584aafa58536b0d4a`. The manifest and sidecar accompany `dist/candidate/quixdevs-plugnpay-direct.zip`. [Complete release report](github-release-readiness.md).

## Payment, refund and authorization coverage

The original 80 tests/593 assertions still pass. Added 22 tests/162 assertions cover helper metadata/input, optional adapters and gateway boundaries. No expectations were changed to conceal a regression. Valid cardholder punctuation survives WordPress slashing and component encoding; malformed token arrays/IDs cannot reach lookup, journal or HTTP.

Contracts cover gateway/settings/bootstrap, classic form, provider field/hash ordering, trusted positive KYD amounts, exact success/reference, definite declines, uncertain results, HTTPS/identity/order permissions, missing/foreign tokens and per-token references. Parser cases include encoded status overrides, duplicate/malformed fields, missing references, formatted known PAN/CVV echoes and private diagnostics. Legitimate numeric provider IDs remain usable even when they happen to pass a card checksum.

The journal is exercised against database failures, stale compare-and-swap, overlapping checkout/REST/refund operations, reset paid-order status, uncertain timeouts/pending/problem replies, worker crashes, durable approval before local completion and order/reference drift during HTTP. No automatic uncertain-outcome retry is enabled. Reconciliation checks exact attempts, permissions, immutable context, lock contention/reentrancy and recovery before/after WooCommerce persistence. The six protected financial/input/authorization implementations have identical executable-token hashes to the frozen baseline; [proof and limits](quality-protected-runtime.json).

Each real PHP 8.4 storage run contains:

| Suite | Assertions per store |
|---|---:|
| Core platform, REST dispatch and Application Password identity | 39 |
| Independent worker INSERT/CAS races and crash recovery | 26 |
| Native WooCommerce refund accounting | 27 |
| Actual WP-CLI authorization and reconciliation overlap | 16 |
| Helper canonical/legacy metadata and literal reference lookup | 13 |
| Nonce/order-bound legacy return, typed IP/token and slashed-name boundary | 28 |
| Total | **149** |

Separate PHP workers/mysqli connections race the first INSERT and declined-state CAS. A forced exit leaves an attempt blocked. CLI reconciliation overlap pauses at the real completion hook and denies the second worker without duplicate completion or transport.

Real `wc_create_refund` creates its child before the gateway callback. Verified full/partial requests retain one confirmed child; decline/pending/timeout rolls back unconfirmed accounting without booking a false refund. The one-Return limit remains. Saved-card tests choose older mapped cardA despite newer user/cardB metadata; unmapped methods are denied. Actual WordPress REST dispatch tests owner, foreign/guest orders, generic forbidden/unknown errors and explicit backend permissions.

Helper tests caught a real CPT incompatibility while replacing the old postmeta query: HPOS supports `meta_query`, while this WooCommerce CPT adapter needs its supported scoped custom-query mapping. The corrected implementation passes the unchanged 13 assertions in both stores, including quoted/%/_/& literal references, canonical zero precedence, caller-saved updates and persistent canonical/legacy deletions.

## Quality findings and limits

The [complete Markdown inventory](quality-inventory.md) and [machine-readable inventory](quality-inventory.json) embed all **545 baseline tool occurrences**, grouped by rule/file/severity/classification with every original line/column/message, count, correction and behavior impact. Both tools can report the same source issue; their counts are not unique defect counts. CategoryE has no runtime findings.

Only two final findings remain: Plugin Check `unexpected_markdown_file` on root `UPSTREAM-NOTICES.md` and `THIRD-PARTY-NOTICES.md`, file-level line 0. Full legal/provenance notices are deliberately retained in this GitHub distribution. These warnings remain visible; no checker rule is disabled for them. Full runtime WPCS has no findings. All 23 narrow statement annotations are recorded, including pre-existing required SQL uniqueness/CAS/advisory-lock queries, delegated nonce/custom-validator boundaries, supported metadata queries and public method/named-argument compatibility. No runtime file/directory or global rule is excluded.

A marked disposable site and socket-only MariaDB instance were used; existing sites/databases were untouched. The temporary database was shut down after final checks. All integration runs had zero stderr, new PHP diagnostics and live processor HTTP.

Browser ZIP-upload/admin captures, real TLS web-server authentication, merchant authorization/settlement and hosted GitHub runs were not exercised. PHP 8.3 is configured in CI but not installed locally. Core posts/HPOS checks do not certify optional Subscriptions/Pre-Orders, and Checkout Blocks is not implemented. Single-primary database coordination with same-connection lock affinity is assumed. PHPStan is not configured. Direct-card PCI obligations and separately authorized processor acceptance remain operational work, not a claim that a tested defect is unresolved.

## Reproduce local checks

```sh
composer install --no-interaction --prefer-dist
composer lint
composer test
composer test-packaging
php tests/direct-access.php
php tests/missing-dependency.php
php tests/missing-key.php
composer secrets
composer docs
composer repository-check
python3 tools/check-repository.py --snapshot-check
composer package-check
composer phpcs
composer release-check
```

The last command intentionally returns nonzero until client acceptance is recorded. Both workflows enforce WPCS. [Plugin Check helper](../tools/plugin-check.sh) needs a disposable WP/WC site. [Platform helper](../tools/integration-check.py) and scripts under `tests/integration/` require the marked installation and full HTTP interception; never point them at production.

Raw before/after commands, installed hashes and per-assertion reports remain ignored under `docs/verification/quality-2026-10-15/`. Public inventories retain complete portable findings. With those reports present, `python3 tools/quality-inventory.py` reproduces the inventory. A clone does not need private sibling sources, local sites or ignored reports for CI/package verification. [Release handoff](release-distribution.md).
