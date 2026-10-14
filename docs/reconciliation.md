# Durable attempts and merchant reconciliation

A per-order journal in the site's options table coordinates classic checkout, REST and refunds. Atomic database insert and compare-and-swap use the unique option name; a PHP worker cannot obtain a second claim through a stale cache. The row is not autoloaded, does not depend on transients and never expires automatically. A crashed worker may already have sent money instructions.

The journal stores a random attempt ID, operation, trusted minor-unit KYD amount, state, timestamps and safe financial reference. It stores no PAN, CVV, request/response body or credentials. Approved state is durable before WooCommerce completion. Verified card declines permit another charge; pending/timeout/problem/malformed/crashed results remain blocked. A new worker gets a generic error without issuing another request. Refund attempts observe the same shared boundary.

## Inspect and reconcile

WP-CLI must run as an explicit WordPress identity with both `manage_woocommerce` and permission to edit this order. Commands below are shapes with placeholders; they were not executed against production.

```text
wp --user=<AUTHORIZED_ADMIN> quixdevs plugnpay-attempt status --order=<ORDER_ID>
wp --user=<AUTHORIZED_ADMIN> quixdevs plugnpay-attempt reconcile --order=<ORDER_ID> --attempt=<CURRENT_UUID> --outcome=<OUTCOME> --reference=<VERIFIED_REFERENCE_OR_CASE> --evidence=<NON_SENSITIVE_CASE_ID> --confirm-worker-stopped
```

First stop/confirm termination of the original worker and check the merchant processor records. Match the exact order, transaction, amount and currency. Record only a non-sensitive case identifier; never paste card data or a processor body. The explicit flag acknowledges that manual verification, not a timeout/age estimate, justifies changing the record. Concurrent reconciliation uses a per-order database advisory lock plus same-process reentrancy protection, reloads the order, and rejects a stale attempt ID. The lock covers WooCommerce completion; a second administrator process cannot complete the same operation concurrently. Journal approval remains durable if a worker dies before/after local persistence, and manual recovery checks the fresh paid state before completing.

| Operation | Reviewed outcome | Local effect |
|---|---|---|
| Charge | `approved` | Record verified reference and complete the matching order; no new processor request |
| Charge | `not-charged` | Release only that reviewed unsuccessful attempt for a new charge; cannot reopen a paid order |
| Refund | `refunded` | Keep processor-refund replay blocked; record WooCommerce refund accounting manually without API submission |
| Refund | `not-refunded` | Release that reviewed refund intent after merchant verification |

This is an administrator reconciliation tool, not an automated settlement query or a customer bypass. Do not delete journal rows, use an expiry to unlock them, alter a paid order to force another charge, or clear locks merely because a customer sees an error. Existing historical orders without a new journal are validated from trusted order/reference data; no bulk migration is performed.

[Payment flow](payment-flow.md), [refund limitations](refunds.md), [REST migration](api-authentication.md), [security](security.md).
