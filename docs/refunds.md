# Refunds and reconciliation

The gateway retains the existing Plug'n Pay `mode=return` operation for full or partial amounts. It does not invent a refund REST route or switch to `returnprev`, credits or a different settlement operation. Merchant credentials, `orderID`, `card-amount` and KYD remain the provider fields; each value is form-encoded before transport.

## Validated refund operation

An order, a safe existing provider transaction reference, KYD currency and a positive supported two-decimal amount are required. Amounts cannot exceed the trusted order total. The same durable per-order SQL journal protects checkout, REST and refunds across PHP workers. The first refund claims the operation before HTTP; overlapping/replayed calls stop before transport. A successful or uncertain refund is not automatically retried.

`process_refund()` now returns **true only for verified provider success** and `WP_Error` for rejection, invalid input, unavailable journal, pending or uncertain outcomes. The inherited associative failure/pending arrays were truthy to WooCommerce and could falsely record success; they were corrected. Success need not repeat `orderID` in the response because this provider operation differs from charge authorization.

WooCommerce's normal refund UI/core endpoints enforce their own nonce/capabilities. The gateway validates financial context again; it does not add a blanket interactive-user check that would break legitimate server/cron callers. There is no unauthenticated refund endpoint in this plugin.

## Provider limits and uncertain results

Official Plug'n Pay documentation permits only **one `return` per original transaction**, even for a partial amount. This plugin blocks a second return rather than silently switching transaction type. A return against an unsettled authorization may void it; this task did not verify actual merchant settlement. See the [official Return specification](https://docs.plugnpay.com/docs/integration-specifications-documents/remote-api-integration-specification/section-2.-remote-transaction-administration/return/).

Pending, transport errors, malformed responses, nonfinal failures and interrupted workers retain a durable blocked record. A merchant must compare processor and WooCommerce records before deciding whether money moved. A failure to receive a response does not prove that no refund occurred.

Use the capability-checked [reconciliation procedure](reconciliation.md) only after the original worker has stopped and the processor result is confirmed. Confirmed `refunded` records must be reflected in WooCommerce accounting manually **without sending another processor request**. Confirmed `not-refunded` can release that reviewed attempt for a legitimate retry. The command does not query, charge or refund the processor.

The automated tests intercept all provider HTTP. They establish WooCommerce return contracts, request mapping, accounting/authorization paths and worker exclusion; they do not prove settlement. [Test evidence](testing.md), [security/PCI](security.md).
