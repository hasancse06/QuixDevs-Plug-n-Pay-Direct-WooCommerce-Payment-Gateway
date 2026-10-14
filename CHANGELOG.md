# Changelog

## 1.0.0 — community release preparation, 2026-10-15

- Retained the existing public QuixDevs version, gateway ID, merchant settings, checkout/REST route and Plug'n Pay transaction fields. Both preserved source folders remain unchanged.
- Added the owner-authorized GPL-2.0-or-later grant, full GNU GPL v2 text, metadata, developer credits and precise historical/provenance notices.
- Added durable per-order database attempts across checkout, REST, saved payments and refunds; no automatic expiry or retry for uncertain/crashed operations.
- Added capability-checked, serialized merchant reconciliation with explicit evidence and no processor lookup/retry API.
- Corrected WooCommerce refunds to true/WP_Error, validated amount/currency/reference, and enforced the provider's one-Return limit for full/partial returns.
- Preserved verified approval before local completion and rejected order/reference changes during HTTP without allowing a second charge.
- Encoded every provider value, enforced TLS verification, disabled redirects, required safe unambiguous success references and suppressed known sensitive output/diagnostics, including formatted card echoes.
- Bound new saved methods to their own provider references. Legacy unmapped methods require fresh card entry/re-saving; optional renewals require explicitly mapped order references instead of guessing from the latest user transaction.
- Kept the eight-field REST contract and route with WordPress identity/order scope and HTTP200 business responses while preserving body codes. The old PHP object declared123; historical wire status was not captured. Existing shared-key-only clients need the documented authenticated migration.
- Preserved ProductClock/StockNotifier-style QuixDevs presentation, supplied logo, editable artwork and historical attribution. Original network PNGs remain excluded.
- Added request/security/worker/refund-accounting/reconciliation tests, real WordPress/WooCommerce posts/HPOS checks, PHP8.2/8.4 verification, workflow lint and deterministic lightweight candidate ZIP/checksum.
- Prepared gated manual draft automation with immutable source/tooling identities, safe release discovery/resumption and repository-scoped installation tokens. No commit, push, tag, publication, deployment or live payment was executed.
- Eliminated all full-runtime WPCS findings (368 errors/41 warnings to zero) and actionable Plugin Check findings (40 errors/96 warnings to zero errors/two retained legal-notice warnings), with a complete categorized inventory and no file/rule exclusions.
- Added canonical class implementations and historical loader shims, typed/unslashed checkout and display input, nonce/order-bound optional returns, authoritative posts/HPOS reference lookup, correct canonical zero/delete metadata semantics, and controlled failures for missing optional-extension APIs.
- Added focused helper, optional-adapter and gateway boundary regressions. Required WPCS now runs in CI and the manually dispatched package verification job.

## Earlier preparation — 2026-10-10

The existing public QuixDevs branch already declared 1.0.0. Initial preparation established branding, documentation, required server-key configuration, REST ownership guards, tests and repository exclusions. The release report supersedes the earlier unfinished blocker lists; no higher public version/tag/release was found during the read-only history audit.
