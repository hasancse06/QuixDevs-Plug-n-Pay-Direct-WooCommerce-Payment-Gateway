# GitHub release readiness — QuixDevs Plug'n Pay Direct

Target version **1.0.0**, local review dated **2026-10-10**. Repository workspace: `quixdevs-plugnpay-direct-github/`; canonical plugin/ZIP root: `quixdevs-plugnpay-direct/`.

| Status | Finding |
|---|---|
| **PREPARED LOCALLY** | ProductClock-style source, docs, artwork, exclusions, mocked tests, CI and gated packaging prepared |
| **PRIVATE GITHUB REVIEW** | Source/upload hygiene prepared; existing Git checkout reused, no push performed |
| **READY FOR PUBLIC GITHUB** | **NO — BLOCKED** by license and critical payment/security/runtime conditions |
| **READY FOR WORDPRESS.ORG** | **NO — BLOCKED** by the same conditions plus directory metadata/Plugin Check/integration review |

## A. Files copied

The [copy allowlist](copy-allowlist.json) names the main file, eight inherited include files, languages index, assets description, runtime readme and upstream notices. Development documents originated from the actual parent workspace `docs/`; the requested source-local report path was absent. [Source audit](source-audit.md). Original production inventory: **16 files unchanged**. Existing QuixDevs source inventory: **22 files unchanged**. `docs/source-hashes.json` (local only), `docs/verification/source-preservation.txt` (local only).

## B. Files excluded

No original production backup/snapshot, private secret/configuration, customer/transaction data, database dump, certificate, original credential-bearing main file, OS metadata or unrelated project was copied. All seven inherited PNGs are excluded because rights are unconfirmed. Fresh development `vendor/` and Composer cache exist locally but are ignored and excluded from runtime packaging; they were not copied from source. Promotional graphics, screenshots/docs/tests/CI/Composer tooling are excluded from the runtime package. Generated verification reports and the private source-hash manifest are also ignored and removed from the current Git index; all disk copies are retained. Existing historical commits still contain those reports and were not rewritten.

## C. Documentation created

Professional [README](../README.md), [merchant configuration](merchant-configuration.md), [REST reference](rest-api.md), [authentication/migration](api-authentication.md), [safe examples](api-examples/php.md), [guarded Postman](postman/README.md), architecture/payment/refund/compatibility/troubleshooting docs, [testing](testing.md), [security](security.md), source/parity/licensing reviews, contributing/security policies, changelog, GitHub metadata/release notes/checklists and future WordPress readme. Offline local links, screenshot existence, PNG dimensions, identity, Postman/CI command wiring are checked. External URLs are not claimed freshly verified. Local README/API previews are separately identified as documentation rendering, not GitHub/WordPress runtime evidence.

## D. GitHub metadata

Repository name, feature description, twelve topics, conditional v1.0.0 release title and installation instructions are in [github-listing.md](github-listing.md). Maintainer portfolio/social links are supplied attribution; no owner/repository/download URL or fake rating/count/badge is invented. An existing Git checkout and remote were found during final cleanup and reused. Local-only reports were removed from the index without deleting their disk copies; no commit, push, tag or release was made by this cleanup.

## E. REST API documentation status

Actual `POST /wp-json/wc/v3/plugnpay-payment`, eight fields, headers, validation, order state, transactions, response fragments/status 123, timeouts, duplicate/retry behavior and absence of REST refund are documented. This copy implements HTTPS, WordPress identity, existing-key validation and order scope in callback and handler. Customers require ownership; backend guest/other-order payments require both explicit custom capability and order edit permission. **Old embedded-key-only clients are intentionally denied.** They require a separately implemented trusted backend; no fictional adapter/endpoint is shipped. REST is retained for isolated tests and **unavailable for production deployment** while blockers remain. Examples/Postman do not send raw-card requests.

## F. Screenshots created / missing

A genuine local rendering of API documentation is supplied as `docs/screenshots/05-api-documentation.png` after visual verification. It is a documentation screenshot, not WordPress admin. Four WordPress gateway/settings/checkout captures are unavailable without a disposable installation. [Exact capture plan](screenshots/README.md) avoids credentials and uses intercepted HTTP/fake data. The README embeds only actual existing images. No fake admin capture is supplied.

## G. Branding assets created

Original navy/teal QuixDevs social preview **1280×640**, icons **128×128 / 256×256**, banners **772×250 / 1544×500**. Maintainer-supplied QuixDevs logo preserved byte-for-byte, original payment-card illustration and StockNotifier-style layout/creator credit; no copied network/processor logos or endorsement. Editable SVG source, Node/Sharp renderer and upload instructions are included. WordPress.org promotional assets are separate from runtime. Dimensions checked; artwork visually reviewed.

## H. Test results

Local **PHP 8.4.13**, **PHPUnit 9.6.38: 25 tests / 127 assertions passed**, all **16 project PHP syntax checks passed**, safe missing-WooCommerce and missing-server-key cases passed. Source inventories/hashes unchanged. Tests use synthetic fixtures and intercepted HTTP only. No live payment/refund/merchant login was performed.

**PHPCS 3.13.6 / WPCS 3.4.1: 3,858 errors, 328 warnings** across 11 runtime PHP files; 3,740 fixable. New authorization include has zero findings. Full standards remain nonzero and advisory in CI. No broad formatting rewrite or global suppression. Composer validates with the expected missing-license warning; development dependency license inventory is recorded.

Characterization tests reproduce concurrent duplicate submission, repeat refunds, truthy failure/pending results, sensitive raw reply fragments and REST timeout errors. Their passing result does not clear those defects. Real WordPress/WooCommerce serialization/authentication, HPOS, optional extensions, installed ZIP and processor integration are **unverified**. PHP 8.2/8.3/8.4 CI is configured but not executed on GitHub. [Evidence and commands](testing.md).

## I. Security findings

Embedded checkout secret removed before target write; server configuration/no-default/HTTPS checks added. New order-scoped REST permissions and invalid-token guards have regression coverage. Both source copies preserve their production behavior and original credentials privately.

Critical unresolved: shared payment/refund locking and replay, uncertain outcomes, refund result/pending semantics, strict card/body encoding/validation, REST HTTP 123/transport/parser/raw output and sensitive logging/error disclosure. Saved methods are previous-transaction reuse, not certified vault tokenization. Optional APIs and real platform behavior are not verified. No PCI or complete security clearance claim. [Security review](security.md), [release conditions](release-checklist.md).

## J. Secret scanning result

Final dependency-free policy scan reports **zero findings**, including local comparison with the removed inherited source value. Values are never printed. It scans repository text including PHP/docs/examples/Postman/CI/lockfile/reports; development vendor/cache/temp builds are excluded, PNGs inspected visually. No actual merchant/card/customer data was used in tests. This is scoped policy evidence, not a guarantee that arbitrary future content is safe. Read-only review of two reachable commits and 103 historical UTF-8 blobs found zero credential-policy findings; raw reports remain in historical commits and history was not rewritten. Review all staged files again before any future public commit. `docs/verification/secret-scan.txt` (local only).

## K. Licensing status

**BLOCKED.** No authoritative grant covers modified source redistribution. Original PatSaTECH attribution is preserved. No GPL grant or exclusive QuixDevs ownership is invented; readme metadata is a draft. Original seven PNGs are omitted. Development dependencies retain separate licenses and are not runtime dependencies. [License review](licensing-review.md), [notices](../UPSTREAM-NOTICES.md).

## L. Plugin Check result

**UNAVAILABLE / NOT RUN.** WP-CLI and a disposable WordPress/WooCommerce/Plugin Check environment are absent. [Explicit disposable-site helper](../tools/plugin-check.sh) is provided for later use; no unprovisioned decorative CI job claims a result. No activation/install/HPOS compatibility certification.

## M. ZIP status

Canonical deterministic in-memory archive validates root, 15 allowlisted files (including CHANGELOG.md), exclusions, fixed entry metadata and CRC. This is **layout only**, not installation or release approval. No release/diagnostic ZIP is written. Both publication gate and build command intentionally return failure while seven [machine-readable requirements](release-state.json) remain false. Required license text must be added once known. `docs/verification/package-layout.txt` (local only), `docs/verification/build-gate.txt` (local only).

## N. GitHub release readiness

**CLEAN SOURCE HANDOFF PREPARED FOR PRIVATE GITHUB REVIEW; PUBLIC GITHUB BLOCKED.** Resolve legal/security/payment/runtime requirements and verify a real disposable install before release approval. Do not publish a source archive merely because a release ZIP is blocked. [Publishing checklist](github-publishing-checklist.md), [draft release notes](github-release-notes.md). No push, release, messages or production changes were performed by this cleanup; remote visibility was not queried or changed. [GitHub upload handoff](github-upload.md) documents source selection and existing-history limits.

## O. WordPress.org submission readiness

**BLOCKED.** Artwork and a readme draft are prepared; license/minimum/tested compatibility metadata, genuine platform captures, security/correctness, Plugin Check and real integration remain outstanding. No submission was attempted. Version 1.0.0 is a target, not a production-readiness claim.
