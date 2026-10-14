# REST payment API

**Existing production route; community-release client cutover requires review.** This document describes implemented behavior; it is not a proposed API. The original embedded-key-only client cannot use the hardened candidate unchanged. See the [precise old/current comparison](rest-client-comparison.md) and the [approval-based migration guide](api-authentication.md).

## Endpoint and authentication

`POST https://YOUR-STORE.example.invalid/wp-json/wc/v3/plugnpay-payment`

This is the only implemented payment REST operation. There is no refund, GET-payment, transaction-query, saved-token or alternate QuixDevs v1 endpoint. JSON (`Content-Type: application/json`) and normal form parameters reach `$request->get_params()`; `Accept: application/json` is appropriate. HTTPS is checked through WordPress `is_ssl()`.

A WordPress-authenticated identity and the existing `plugnpaydirect-client-key` body field are required. Same-site sessions use the WordPress cookie/REST nonce mechanism; a trusted backend may use a server-held Application Password over HTTPS. Authentication establishes identity; the permission callback separately authorizes the order. Never embed Application Passwords or provider secrets in a browser/mobile bundle. A direct browser call necessarily exposes the required body key to that browser; keeping it strictly server-side requires an application-owned authenticated server adapter, which this plugin does not provide. [WordPress authentication](https://developer.wordpress.org/rest-api/using-the-rest-api/authentication/).

A registered customer can pay their own order. Other customers' or guest orders require both explicitly granted `quixdevs_plugnpay_pay_orders` and WooCommerce `edit_shop_order` permission for that order. No capability is granted automatically. Missing/inaccessible orders share a generic 403; ordinary managers do not gain cross-customer access merely by knowing the client key. A privileged backend must independently authenticate its customer and enforce scope before making a call. That backend is not implemented here. [Migration details](api-authentication.md).

After permission checks, the handler requires `payment_method=plugnpaydirect` and literal order status `pending`. It validates a trusted positive KYD order total and fresh-card fields before transport. Amount, addresses and currency come from the server order; client amount/currency overrides are not implemented. A different nonempty stored payment method is rejected; an authorized unassigned order is assigned to this gateway only after verified approval.

## Request fields

All eight names are retained from the original route. Examples use non-sending placeholders, not real payment data.

| Name | Required type / validation | Example placeholder |
|---|---|---|
| `payment_method` | String; supported value `plugnpaydirect` | `plugnpaydirect` |
| `order_id` | Positive integer or digit string; caller-authorized pending order | `42` |
| `plugnpaydirect-client-key` | Nonempty string matching the server setting via `hash_equals` | `<CLIENT_KEY>` |
| `plugnpaydirect-card-number` | String; permitted inter-digit whitespace removed; 13–19 digits and valid Luhn | `<PAN_FROM_PROTECTED_INPUT>` |
| `plugnpaydirect-card-expiry-month` | Two-digit string `01`–`12` | `<MM>` |
| `plugnpaydirect-card-expiry-year` | Four-digit string `20xx`; current month or later | `<YYYY>` |
| `plugnpaydirect-card-cvc` | String of 3–4 digits | `<CVV_FROM_PROTECTED_INPUT>` |
| `plugnpaydirect-card-name` | Nonempty string, at most 100 bytes, no control characters; delimiters are safely encoded | `<CARDHOLDER_NAME>` |

```json
{
  "payment_method": "plugnpaydirect",
  "order_id": 42,
  "plugnpaydirect-client-key": "<CLIENT_KEY>",
  "plugnpaydirect-card-number": "<PAN_FROM_PROTECTED_INPUT>",
  "plugnpaydirect-card-expiry-month": "<MM>",
  "plugnpaydirect-card-expiry-year": "<YYYY>",
  "plugnpaydirect-card-cvc": "<CVV_FROM_PROTECTED_INPUT>",
  "plugnpaydirect-card-name": "<CARDHOLDER_NAME>"
}
```

The PAN permits ASCII spaces/tabs between digits; expiry and CVC must remain strings to preserve leading zeroes. Missing/array/non-string card fields fail before provider submission; invalid key/order/method types also fail. No optional amount, currency, token, saved-method flag, idempotency key or provider credentials are supported. Unknown request parameters do not add capabilities. There is no formal route argument schema; the handler/permission helpers perform validation.

## Response behavior and compatibility changes

Permission and new validation/parser failures return WordPress `WP_Error` with status data. A bad key has this shape:

```json
{"code":"quixdevs_plugnpay_invalid_client_key","message":"Invalid payment client credentials.","data":{"status":401}}
```

| Error code | HTTP status data | Trigger |
|---|---|---|
| `quixdevs_plugnpay_https_required` | 403 | WordPress does not detect HTTPS |
| `quixdevs_plugnpay_authentication_required` | 401 | No authenticated WordPress user |
| `quixdevs_plugnpay_invalid_client_key` | 401 | Missing, wrong or non-string client key |
| `quixdevs_plugnpay_invalid_order` | 400 | Missing/nonpositive/malformed order ID |
| `quixdevs_plugnpay_order_forbidden` | 403 | Order missing or outside caller scope |
| `quixdevs_plugnpay_invalid_request` | 400 | Malformed direct-handler request object or parameter container |
| `quixdevs_plugnpay_invalid_method` | 400 | Supplied payment method is not a string |
| `quixdevs_plugnpay_unsupported_order` | 400 | Nonpositive/invalid total or currency other than KYD |
| `quixdevs_plugnpay_invalid_card` | 400 | Missing, wrongly typed or invalid fresh-card fields |
| `quixdevs_plugnpay_wrong_gateway` | 409 | Order already assigned to a different payment gateway |
| `quixdevs_plugnpay_attempt_blocked` | 409 | Existing in-flight/uncertain/approved or repeated-return attempt |
| `quixdevs_plugnpay_attempt_unavailable` | 503 | Database journal unavailable; no unlocked fallback |
| `quixdevs_plugnpay_payment_uncertain` | 409 | Unverified provider outcome requiring merchant reconciliation |
| `quixdevs_plugnpay_order_changed` | 409 | Trusted order changed before submission |
| `quixdevs_plugnpay_order_changed_after_approval` | 409 | Approval retained but amount/currency/gateway/reference changed during submission |

Generic errors never echo supplied card/key values. The parser helper uses `quixdevs_plugnpay_invalid_provider_response` with status 502 internally; the normal REST submission boundary records a malformed processor reply as `quixdevs_plugnpay_payment_uncertain`/409. Clients should not expect HTTP502 as the normal malformed-reply contract. Parser errors leave the order unpaid and add a reconciliation note. A provider timeout is an uncertain outcome, not a verified decline.

Authorized normal business results retain the legacy integer body `code`/`message` under **completed HTTP200**. The preserved controller constructed response objects with status `123`; the current controller explicitly completes these responses with HTTP200. The original deployed wire status was not captured: WordPress can ignore an unknown status header when it has no description, and a server/proxy can affect the observed status. Verify the existing client against its actual staging transport rather than assuming it received `123`. Clients must parse the business body and also handle WordPress error envelopes. [WordPress status-header implementation](https://developer.wordpress.org/reference/functions/status_header/). This transport correction and authentication/output safeguards require a reviewed client cutover.

For a minimal provider success fixture, the response body remains:

```json
{
  "0": "FinalStatus=success",
  "1": "orderID=DEMO-REFERENCE",
  "2": "auth-code=DEMO-AUTH",
  "code": 200,
  "message": "Your Payment was Successful"
}
```

Numeric fragments now come from a restricted, component-decoded selection of `FinalStatus`, `orderID`, `auth-code` and `resp-code`. Unknown provider fields and sensitive values are omitted; arbitrary raw fragments, error-message fields and transport objects are not returned. Fragment positions/count depend on included provider fields; there is no new named `transaction_id` response field. This deliberate security tightening changes the old unrestricted-output contract and requires client review. Transaction references and authorization codes remain financial metadata and should not be publicly logged.

A minimal declined-card fixture retains:

```json
{
  "0": "FinalStatus=badcard",
  "1": "resp-code=51",
  "code": 401,
  "message": "Transaction Failed. Credit Card Authorization failed - Insufficient funds."
}
```

| Authorized business trigger | Retained body code / behavior |
|---|---|
| Missing method | `401`, wrong-data message |
| Empty string method | `401`, `Payment Method is required.` |
| Status other than pending | `401`, retained not-pending message; failed/cancelled/on-hold may also be unpaid, so the message is not proof of settlement |
| Unsupported nonempty method | `405`, select an available method |
| Definite provider bad-card decline | `401`, existing decline mapping under HTTP200; no payment completion |
| Pending/problem/fraud/malformed or ambiguous provider result | WP_Error `quixdevs_plugnpay_payment_uncertain`, HTTP409, durable replay block |
| Provider HTTP failure or WordPress transport `WP_Error` | Same generic uncertain WP_Error/409; no transport object leakage or fatal array access |

Retained business-body results use HTTP **200**, independently of their integer code. The uncertain-operation rows above are WordPress errors with HTTP409. A business body `401` under HTTP200 is distinct from an actual authentication HTTP401. WordPress cookie-nonce, Application Password, invalid-JSON, proxy and network errors outside this controller can differ. The original REST provider request has no checkout verification hash; the [field mapping](payment-flow.md) documents that retained difference.

## Order completion, duplicates and retry policy

Only HTTP 2xx plus unambiguous provider `FinalStatus=success` and a usable provider `orderID` permits `payment_complete(orderID)`. The reference becomes WooCommerce's transaction ID; an authorization code is omitted from order notes. Malformed/declined/pending charge replies do not mark paid.

The handler checks pending and trusted order context, then claims a durable per-order SQL journal shared with checkout/refunds. Overlapping workers and sequential replays stop before a second provider request. Approved state is saved before WooCommerce completion; uncertain or interrupted attempts never expire automatically. No new idempotency header or payment/status endpoint is required. A timeout can follow a real processor action: use the [capability-checked reconciliation procedure](reconciliation.md) after checking merchant records and stopping the original worker. Do not automatically retry or create a new order to bypass the per-order journal. Explicit bad-card declines permit a deliberate new attempt; uncertain, interrupted and approved journal states do not expire automatically.

## Examples and troubleshooting

The [cURL](api-examples/curl.md), [PHP](api-examples/php.md), [JavaScript](api-examples/javascript.md) and [Ionic Angular](api-examples/ionic-angular.md) specimens are deliberately non-sending. The [Postman collection](postman/quixdevs-plugnpay-direct.postman_collection.json) has a pre-request send guard. No mobile backend is supplied, and moving the same embedded key into a header does not solve identity/order authorization.

Check the exact method/field spelling, HTTPS detection, WordPress identity, capability/ownership, matching server key, pending state and positive KYD total. Diagnose with fixed error codes rather than payment-body logging. Merchant publisher/password/hash configuration is distinct from the client key. Review [security](security.md), [refunds](refunds.md), [compatibility](compatibility.md) and [testing](testing.md) before interpreting local checks as readiness.

Malformed direct-handler request objects/parameter containers return `quixdevs_plugnpay_invalid_request` (400). Order IDs larger than PHP_INT_MAX are rejected with the existing invalid-order error before lookup; normal positive digit-string IDs retain their contract.
