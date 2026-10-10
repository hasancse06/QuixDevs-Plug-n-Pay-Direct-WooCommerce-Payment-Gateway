# Distribution comparison

This copy preserves the existing provider integration rather than rewriting its financial behavior. The comparison is against the supplied `quixdevs-plugnpay-direct/` source, with original-production hashes also recorded separately.

| Retained area | Evidence / limitation |
|---|---|
| Gateway registration and ID | Synthetic registration test; ID `plugnpaydirect` |
| Settings and classic form | Existing identifiers/fields; tested with doubles |
| Fresh-card provider mapping | Full request fields, KYD, order total, no explicit mode, same hash input ordering |
| Payment completion and references | Successful fixtures call `payment_complete(orderID)` and preserve auth notes |
| REST URL/body fields | Same route/eight payment fields; authorization intentionally changes |
| Refunds | Same `mode=return`, amounts and legacy success/failure/pending return arrays |
| Saved payment request | Same `authprev`/user `trans_id`; invalid token cases now stop |
| Transport/parser | Same CGI URL, timeout, redirects, HTTP version, raw body and parser |

## Deliberate changes in this copy only

- Remove the embedded verification credential before writing the destination file. Checkout uses the required server constant with no default; missing configuration/non-HTTPS returns failure before HTTP.
- Require HTTPS, authenticated WordPress identity, the existing shared key and ownership or explicitly privileged order permission for REST. The old embedded-key-only client is denied. New permission errors use `WP_Error`; authorized legacy output remains unchanged.
- Reject missing/foreign/wrong-gateway saved tokens and missing previous transaction references before submission.
- Remove two optional-before-required parameter defaults that PHP 8 already treats as required. This removes deprecation notices without changing the effective PHP 8 argument requirement.
- Add PHP 8.2 baseline metadata and development documentation/tooling. Omit unlicensed upstream PNGs; the existing optional-icon renderer skips absent files.

Six of eight inherited include files remain byte-identical to the QuixDevs source; Helper and Subscriptions include only the signature correction above. A new authorization include is added. The main file contains the scoped guards, configuration and permission wiring. `docs/verification/runtime-changes.diff` (local only) lists these changes without the removed source key.

Mocked regression and characterization tests are described in [testing](testing.md). Characterization cases intentionally reproduce duplicate submission, unsafe refund results, transport failure and raw output; green tests do not clear those defects. Real WordPress/provider/HPOS/optional-extension parity is unverified. No financial protocol, amount, currency, settlement, refund result or parser correction is claimed.
