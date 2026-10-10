# Troubleshooting the isolated candidate

- **Gateway missing:** WooCommerce must provide `WC_Payment_Gateway_CC` before the initializer runs. Activate only one gateway copy; both use `plugnpaydirect`.
- **Checkout fails before HTTP:** configure HTTPS correctly and a nonempty server `QUIXDEVS_PLUGNPAY_VERIFICATION_KEY`. Never paste its value into tickets or source.
- **REST 401:** a shared key alone is no longer sufficient. WordPress must authenticate the caller; also verify the server-held key matches the existing `client_key` setting.
- **REST 403:** check HTTPS/order ownership. Guest/other orders require both explicitly granted backend capability and order-specific edit permission. Missing and forbidden orders intentionally share a response.
- **REST 400:** use a positive integer/digit-string order ID; arrays, decimals and malformed IDs are rejected.
- **Paid-order rejection:** only literal `pending` is accepted by the inherited handler. Do not reset paid orders to force a retry.
- **HTTP 123/timeout:** retained defects prevent deployment. Do not automatically retry; reconcile merchant records to avoid duplicate charges. A timeout can throw rather than return JSON.
- **Saved-card rejection:** selected token must exist, belong to the current user and gateway and have a stored previous-transaction reference. Existing user-level `trans_id` is not independent vault credentials per token.
- **Refund failure displayed as success:** associative failure arrays are truthy and pending maps to success. Reconcile before another return; public release remains blocked.
- **Current-month expiry/AMEX:** inherited expiry-first-day and three-digit form/four-digit validator mismatch require review.
- **Names/addresses with special characters:** raw concatenation/parser issues remain; do not log full bodies to investigate.
- **Optional extensions:** Pre-Orders APIs/loading are incomplete; Subscriptions/HPOS are not integration verified. Classic Checkout only; no Blocks integration.

Use synthetic fixtures with intercepted transport. Exclude PAN, CVV, auth headers, passwords, verification/client keys and raw processor bodies from diagnostics. [Security review](security.md).
