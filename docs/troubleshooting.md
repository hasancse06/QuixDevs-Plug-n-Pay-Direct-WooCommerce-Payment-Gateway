# Troubleshooting the isolated candidate

- **Gateway missing:** WooCommerce must provide `WC_Payment_Gateway_CC` before the initializer runs. Activate only one gateway copy; both use `plugnpaydirect`.
- **Checkout fails before HTTP:** configure HTTPS correctly and a nonempty server `QUIXDEVS_PLUGNPAY_VERIFICATION_KEY`. Never paste its value into tickets or source.
- **REST 401:** a shared key alone is no longer sufficient. WordPress must authenticate the caller; also verify the server-held key matches the existing `client_key` setting.
- **REST 403:** check HTTPS/order ownership. Guest/other orders require both explicitly granted backend capability and order-specific edit permission. Missing and forbidden orders intentionally share a response.
- **REST 400:** use a positive integer/digit-string order ID; arrays, decimals and malformed IDs are rejected.
- **Paid-order rejection:** only literal `pending` is accepted by the inherited handler. Do not reset paid orders to force a retry.
- **Legacy transport / timeout:** authorized business bodies now use completed HTTP200; support HTTP200 and structured WordPress errors. The old PHP object declared123; its historical wire status was not captured. A timeout leaves the durable attempt blocked; inspect merchant records and use the reconciliation procedure. Do not automatically retry.
- **Saved-card rejection:** selected token must exist, belong to the current user and gateway and have a stored previous-transaction reference. Existing user-level `trans_id` is not independent vault credentials per token.
- **Refund pending/failure:** the gateway now returns WP_Error rather than a truthy success array. Check merchant records before reconciliation; a pending/error response does not prove that no refund occurred.
- **Current-month expiry/AMEX:** current-month expiry and three/four-digit CVV are now accepted by shared validation; invalid scalar/shape fields stop before transport.
- **Names/addresses with special characters:** cardholder delimiter validation and component-wise reply parsing are implemented. General address/settings request encoding remains unresolved; do not log full bodies to investigate.
- **Optional extensions:** Pre-Orders APIs/loading are incomplete and Subscriptions remain unverified. Core posts/HPOS smoke checks passed, but optional direct-postmeta paths prevent a whole-plugin HPOS support claim. Classic Checkout only; no Blocks integration.

Use synthetic fixtures with intercepted transport. Exclude PAN, CVV, auth headers, passwords, verification/client keys and raw processor bodies from diagnostics. [Security review](security.md).
