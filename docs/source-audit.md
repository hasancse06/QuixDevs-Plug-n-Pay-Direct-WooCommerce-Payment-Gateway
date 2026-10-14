# Production source audit

The preserved production source is `wooplugnpaydirect/` (historical header 1.3.2). Changes are confined to the existing QuixDevs GitHub project. Its public history already declares version 1.0.0; [version history](version-history.md) explains the separate plugin basename and manual legacy replacement. Neither preserved source directory is modified.

| Area | Production parity and current correction |
|---|---|
| Identity/settings | Gateway `plugnpaydirect` and all nine merchant setting identifiers retained; current brand, namespace and developer credits use QuixDevs |
| Checkout/provider | Same Plug'n Pay CGI endpoint, payment fields, trusted order totals, KYD and existing checkout hash input ordering; component value encoding prevents parameter injection |
| REST | Same POST route/eight request names and legacy integer business codes; completed HTTP 200 replaces 123, with standard permission/validation/journal errors |
| Approval | Component decoding prevents encoded-status override; charge success needs a usable non-sensitive provider reference; approval is journaled before local completion |
| Concurrent requests | Database-unique journal and compare-and-swap serialize checkout, REST, renewal and refund attempts across workers; no automatic expiry/takeover |
| Uncertain outcomes | Timeout, pending and ambiguous replies block another financial submission until explicit merchant reconciliation |
| Refunds | Existing `mode=return`, original reference and full/partial amount mapping retained; confirmed success returns true, failure/pending use WP_Error; one-Return and amount/context checks implemented |
| Order drift | Immutable total/currency/reference snapshots checked before submission and after approval; changed orders require reconciliation without another charge |
| Card data | Known PAN/CVV/credential echoes are rejected/redacted, including formatting variants; raw provider diagnostics and authorization-code notes are omitted |
| Saved methods | New saved tokens bind the selected card to its own verified transaction reference; unmapped legacy tokens require re-entry/re-save rather than guessing from the user's latest reference |
| Transport | HTTPS provider endpoint, explicit certificate verification, no redirects carrying card data; no automatic retries |
| Optional integrations | Source retained, with shared guarded renewal submission and explicit KYD; incomplete optional APIs/direct postmeta still prevent broad certification; no Checkout Blocks claim |

Md Abu Hasan confirms ownership and authorizes GPL-2.0-or-later for applicable owned code; full text/metadata are included. Historical notices are preserved. Specific helper-source similarities remain a narrow conditional provenance follow-up, not a blanket third-party ownership conclusion. [Licensing review](licensing-review.md), [notices](../THIRD-PARTY-NOTICES.md). Unverified legacy network PNGs remain excluded; the supplied QuixDevs logo and original artwork provide branding.

The merchant's existing production use is acknowledged. Independent checks use real WordPress/WooCommerce and intercepted processor responses, including separate database workers; they verify local behavior rather than live settlement. See [testing](testing.md) for final execution results and [release readiness](github-release-readiness.md) for artifact/approval details.

Official protocol references: [Remote Authorization](https://docs.plugnpay.com/docs/integration-specifications-documents/remote-client-integration-specification/section-1.-remote-authorization/), [Authorize Previous](https://docs.plugnpay.com/docs/integration-specifications-documents/remote-api-integration-specification/section-2.-remote-transaction-administration/authorize-previous/), [Return](https://docs.plugnpay.com/docs/integration-specifications-documents/remote-api-integration-specification/section-2.-remote-transaction-administration/return/).
