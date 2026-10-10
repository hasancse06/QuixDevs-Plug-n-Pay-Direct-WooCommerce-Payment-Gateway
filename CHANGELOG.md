# Changelog

## 1.0.0 — local distribution candidate, 2026-10-10

- Prepared the existing QuixDevs source as a controlled GitHub workspace; both source trees preserved.
- Removed the embedded verification secret in favor of required server-side configuration, retaining the original hash/protocol.
- Added HTTPS, authenticated caller and per-order REST permission checks; embedded-key-only clients must migrate.
- Rejected unavailable/foreign/wrong-gateway saved tokens before provider submission.
- Removed two ineffective optional defaults before required parameters to avoid PHP 8 deprecations; both arguments were already required on the intended PHP baseline.
- Added reproducible locked development dependencies, focused mocked tests, policy/secret/package-layout checks, CI, safe API specimens, README/release documentation and original card branding artwork.
- Matched ProductClock/StockNotifier repository presentation with the supplied QuixDevs logo, editable artwork and creator credit.
- Excluded local verification reports, private hash inventories, dependencies, caches and generated archives from Git; retained source, lockfiles, tests, documentation and artwork.
- Excluded upstream card-network PNGs with unconfirmed redistribution terms.
- Kept unresolved concurrency, refund, parser/output/HTTP and optional-integration conditions explicit. No public release or ZIP, production change or real charge.
