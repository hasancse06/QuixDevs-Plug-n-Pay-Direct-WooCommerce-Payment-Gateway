# Refund implementation retained

`Gateway::process_refund(order_id, amount = null, reason = '')` is unchanged in the distribution copy. WooCommerce provides normal caller authorization; no independent refund REST route exists. Do not expose this PHP callback through an unauthenticated wrapper.

The processor request remains `publisher-name`, `publisher-password`, `mode=return`, `orderID=order transaction_id`, `card-amount=amount`, `currency=KYD`, at the original URL/transport settings. Reason is not sent. Full and partial amounts are forwarded unchanged; tests verify mapping, not merchant-specific settlement support.

The inherited condition enters when a reference exists OR amount <= 0. Success and pending return `['result'=>'success']`; failure/transport errors return `['result'=>'failure']`; skipped branch returns true. Both arrays are truthy and do not match WooCommerce's [documented bool/WP_Error contract](https://woocommerce.github.io/code-reference/classes/WC-Payment-Gateway.html). Pending does not prove a completed refund. Repeated calls are not locked/idempotent. These are release blockers preserved for explicit financial review, not deleted functionality or verified safe behavior.

Do not automatically retry ambiguous/pending outcomes. Reconcile authorized merchant records and WooCommerce refunds before another attempt. The [provider Return specification](https://docs.plugnpay.com/docs/integration-specifications-documents/remote-api-integration-specification/section-2.-remote-transaction-administration/transaction-administration---return/) is a follow-up reference; no new processor restriction, protocol field or settlement assumption was introduced.
