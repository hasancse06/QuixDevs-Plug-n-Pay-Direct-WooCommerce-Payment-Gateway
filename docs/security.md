# Payment security and PCI responsibilities

This is the existing production Plug'n Pay gateway prepared for community distribution. Production-use history is reported by its maintainer; independent verification is recorded separately in [testing](testing.md). Mocked provider responses test application behavior and cannot establish actual authorization/settlement or PCI compliance. The preserved production source is unchanged.

## Implemented safeguards

- Checkout requires HTTPS and a nonempty administrator-controlled verification key; no embedded fallback is shipped. The merchant-specific inherited hash input order is preserved for review.
- The existing REST route requires WordPress-authenticated identity, HTTPS, constant-time existing-key comparison and order ownership or explicit backend/order-edit permissions. Missing/inaccessible orders share a generic error. No capability is granted automatically.
- Fresh-card validation checks scalar input, PAN/Luhn/length, valid current-month-or-later expiry, 3/4-digit CVV and bounded holder text. Positive trusted KYD totals are required; client amount/currency overrides are ignored.
- Provider field values are encoded individually; a delimiter inside an address/credential cannot add financial fields. The fixed HTTPS provider endpoint uses TLS certificate verification and disables redirects that could forward card data elsewhere.
- Component-wise response decoding rejects malformed/duplicate fields. Payment completion requires verified provider success and a safe reference. Sending a request, a pending result or an ambiguous timeout never marks an order paid.
- A durable database journal coordinates checkout, REST and refunds across workers, rejects replays and persists uncertain/crashed attempts without expiry. [Reconciliation](reconciliation.md) is an explicit capability-checked merchant action, not an automatic retry.
- WooCommerce refunds use true/WP_Error semantics, validated amounts/currency/reference and one-return protection. Pending/uncertain outcomes are not recorded as success.
- Missing/foreign/wrong-gateway saved tokens are denied. The existing `authprev` operation uses the selected token's own verified reference. Legacy unmapped tokens need fresh-card re-saving; optional renewals require an explicitly mapped order reference. This is not an independently certified provider vault.
- Arbitrary provider/log/exception payloads and authorization-code order notes are suppressed. Restricted REST fragments omit unknown data and known secrets. Full PAN/CVV cannot be used as persisted references in reviewed paths.

## Direct-card data flow

Classic Checkout sends card fields to merchant WordPress/PHP, which forwards them over HTTPS to Plug'n Pay. REST card input also traverses its trusted backend and WordPress. These servers process/transmit account data; this is not a hosted-fields/browser-to-processor design. Reviewed saved-method paths retain display metadata/reference, not full PAN/CVV. The attempt journal also contains no card data.

Never log payment bodies, CVV, full PAN, provider credentials or WordPress Application Passwords. Protect WooCommerce credential settings, database/backups, web/PHP/proxy/APM logs, error handlers, support exports and third-party plugins. Review PHP `zend.exception_ignore_args=On` and disable public error display. Application safeguards cannot certify those systems.

PCI DSS scope and validation must be determined with the acquirer/payment brands or qualified assessor. No PCI certification, SAQ eligibility, 3DS or hosted/tokenized API support is claimed. CVV must not be retained after authorization, even encrypted. [PCI SSC FAQ1439](https://www.pcisecuritystandards.org/faqs/1439/), [FAQ1443](https://www.pcisecuritystandards.org/faqs/1443/), [FAQ1319](https://www.pcisecuritystandards.org/faqs/1319/).

## Concrete review boundaries

The shared-key-only mobile client needs the [documented authenticated migration](api-authentication.md). No client deployment was performed. Merchant/hash configuration and actual processor acceptance remain external acceptance work; no live test request was sent. Blocks is not implemented; inherited optional subscription/pre-order code is not certified. Full runtime WPCS is clean and Plugin Check has only two documented legal-notice Markdown warnings. The [final release report](github-release-readiness.md) separates verified fixes, client acceptance and external test limits.

[Refunds](refunds.md), [configuration](configuration.md), [security reporting](../SECURITY.md).
