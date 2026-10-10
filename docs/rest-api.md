# Existing REST payment API

**Implemented for isolated tests; unavailable for production deployment pending release blockers.** This is a description of the private candidate's actual code, including its shortcomings. This distribution adds permission/identity/order-scope checks to the existing route; no versioned route is added. [Security findings](security.md) and [authentication boundaries](api-authentication.md) are publication blockers.

## URL, method and content

Base: `https://YOUR-STORE.example.invalid/wp-json`. Operation: `POST /wc/v3/plugnpay-payment`. There is no GET payment, refund REST operation, status query, vault-token REST operation or QuixDevs v1 route.

WordPress receives normal request parameters through `$request->get_params()`. JSON with `Content-Type: application/json` is suitable; form request parameters also reach this handler. `Accept: application/json` expresses the desired response type. HTTPS is essential for direct card data, and this distribution checks WordPress `is_ssl()` before payment. WordPress-authenticated identity is now required; use a server-held Application Password or an appropriate same-site WordPress cookie/nonce session. There is no idempotency header. Site-wide authentication plugins or reverse proxies can impose additional requirements; none was supplied or tested.

## Actual authentication and authorization

The existing body client key is still required, now with strict string checks and `hash_equals`. It is not a merchant remote password or WooCommerce consumer key. HTTPS and validated WordPress identity are mandatory. The permission callback and PHP handler check registered-customer order ownership; other/guest orders require BOTH explicitly granted `quixdevs_plugnpay_pay_orders` and order-specific WooCommerce `edit_shop_order` permission. No capabilities are assigned automatically. Missing/forbidden orders share a generic error. See [the precise client migration](api-authentication.md).

The original embedded-key-only mobile application is denied. A trusted backend must hold credentials, authenticate its customer and enforce customer/order scope before using a privileged service identity. That backend is not provided by this plugin. No new API route is invented.

After authorization, the inherited processing branch still requires literal `pending` status and `payment_method=plugnpaydirect`. Amount and addresses come from the order. It does not verify order currency or the stored payment-method field, nor apply strict card validation. These and the missing shared payment lock remain release blockers.

## Required headers

Use `Content-Type: application/json` (or form-encoded parameters) and `Accept: application/json`. A trusted backend authenticating through WordPress Application Passwords uses `Authorization: Basic <SERVER_HELD_CREDENTIALS>` over HTTPS. Same-site authenticated WordPress sessions use cookies and `X-WP-Nonce`; do not embed server credentials or shared keys in a browser/mobile bundle. WordPress authentication behavior is documented, not integration-tested here. These headers establish identity; the new permission callback separately enforces order scope.

## Parameters

All examples below are **non-executable placeholders**. Never put real PAN, CVV or merchant secrets in documentation, Postman exports, logs or source control.

| Name | Type expected | Required | Purpose / accepted value | Placeholder |
|---|---|---|---|---|
| `payment_method` | string | Yes | Must be exactly `plugnpaydirect` to enter payment branch | `plugnpaydirect` |
| `order_id` | positive integer or digit-string ID | Yes | Existing WooCommerce order in `pending` state | `42` |
| `plugnpaydirect-client-key` | string | Yes | Existing shared client key, retained on the trusted server | `<CLIENT_KEY>` |
| `plugnpaydirect-card-number` | string | Yes for payment | Raw PAN; removes whitespace between digits; REST does **not** run Luhn/length validation | `<PAN_FROM_PROTECTED_INPUT>` |
| `plugnpaydirect-card-expiry-month` | string | Yes for payment | Intended two-digit month `01`–`12`; not checked by REST | `<MM>` |
| `plugnpaydirect-card-expiry-year` | string | Yes for payment | Intended four-digit year; only last two digits sent; not checked by REST | `<YYYY>` |
| `plugnpaydirect-card-cvc` | string | Yes for payment | CVC intended 3–4 digits; not checked by REST | `<CVV_FROM_PROTECTED_INPUT>` |
| `plugnpaydirect-card-name` | string | Yes for payment | Cardholder name; REST does not check length/content | `<CARDHOLDER_NAME>` |

The method has inherited checks; the new permission layer strictly checks key and order ID type and ownership. Missing card fields instead trigger PHP notices or malformed provider values. There is no formal route argument schema, strict type validation or reliable JSON validation-error envelope for all invalid inputs. Array inputs are not supported and may cause errors. Optional business/payment fields: **none implemented**. Unknown parameters are ignored. No client-supplied amount, currency, token, new-payment-method flag, idempotency key or provider credentials are supported by this REST payment branch.

## Responses and HTTP status

New permission failures return WordPress `WP_Error` with standard status data. The real WordPress serializer was not exercised, but the implemented code/data are tested. Example:

```json
{"code":"quixdevs_plugnpay_invalid_client_key","message":"Invalid payment client credentials.","data":{"status":401}}
```

Permission codes: `quixdevs_plugnpay_https_required` (403), `quixdevs_plugnpay_authentication_required` (401), `quixdevs_plugnpay_invalid_client_key` (401), `quixdevs_plugnpay_invalid_order` (400), `quixdevs_plugnpay_order_forbidden` (403). The order ID must be positive integer/digit-string; missing or inaccessible order IDs are not proof of ownership. No card data or supplied credential is included in these permission messages.

After authorization, the controller explicitly constructs `WP_REST_Response(..., 123)` for both success and normal failure branches. This is a nonstandard informational-class status and is **not** a conventional completed HTTP response. Mock tests verify the literal constructor value; an actual server/proxy/client may reject, clamp or mishandle it. Do not claim wire-level HTTP success from the body alone. No authorized-handler transport rewrite was introduced in this distribution.

On a minimal provider success fixture, the body is:

```json
{
  "0": "FinalStatus=success",
  "1": "orderID=DEMO-REFERENCE",
  "2": "auth-code=DEMO-AUTH",
  "code": 200,
  "message": "Your Payment was Successful"
}
```

The numeric keys are **actual inherited behavior**, because the variable containing `explode('&', urldecode(body))` is reused as the response array. Their number/content depends on the provider body. There is no stable named `transaction_id` or `order_id` response field. Provider `orderID` is saved to the WooCommerce transaction ID; `auth-code` is placed in an order note. The code calls WooCommerce payment completion after provider success. Consumers should not log these raw provider fragments or assume they are sanitized.

A bad-card fixture with provider `resp-code=51` produces:

```json
{
  "0": "FinalStatus=badcard",
  "1": "resp-code=51",
  "code": 401,
  "message": "Transaction Failed. Credit Card Authorization failed - Insufficient funds."
}
```

The prior source's early shared-key business response is superseded by the new permission error above. Other authorized-handler business responses retain their legacy shape.

| Trigger | Body code | Actual body message / behavior |
|---|---|---|
| Empty/wrong client key | WP_Error string code | New 401 permission error before processing |
| Missing method (valid authorized order) | `401` | `We were unable to process your transaction. Requesting with wrong data.` |
| Empty method | `401` | `Payment Method is required.` |
| Empty/zero/malformed order ID | WP_Error string code | New 400 permission error |
| Order missing or unauthorized | WP_Error string code | Same generic 403 permission error |
| Status other than pending | `401` | `Order status is NOT 'pending', meaning order had already received payment. Multiple payment to the same order is not allowed.` |
| Unsupported nonempty method | `405` | Request to select an available method; old unrelated-plugin link replaced by gateway-settings wording |
| Provider decline/fraud/problem | `401` | Existing mapped error message; numeric response fragments retained |
| Non-2xx HTTP provider response | `401` | Gateway-error message; inherited HTTP response array may remain in body |
| WordPress transport `WP_Error` | No guaranteed JSON | On PHP 8.4 the original code attempts array access on an object and throws `Error`; reproduced locally |
| Missing or wrongly typed card fields | No guaranteed JSON | PHP warnings/errors or invalid values forwarded; not a standardized 400 response |

Authorized legacy handler returns above use literal status **123**, not body `code` as the HTTP status. WordPress, authentication middleware, webserver and network errors outside the handler may have different statuses/bodies.

## Duplicate, concurrency, replay and retry behavior

The handler checks pending status before sending. A second request **after** successful completion is rejected; the local regression suite confirms one provider call for a sequential retry. Two requests that both pass the pending check can each submit a charge. A deterministic nested mocked call reproduces this overlap. No lock, persistent attempt record, idempotency key or provider reconciliation operation exists. Checkout and REST have no shared duplicate protection. Replay is prevented only incidentally by a completed order's new status.

The outbound timeout is 45 seconds; no automatic retries are implemented. A timeout cannot establish that the processor declined or failed to charge. Do not automatically retry uncertain results. A merchant must reconcile the provider transaction and WooCommerce order through authorized administration before another attempt. There is no API to clear a payment-attempt lock because no lock exists.

## Integration boundaries and examples

The revised contract is suitable for isolated local tests and migration review only. It must not be presented as a secure public mobile-card endpoint. [cURL](api-examples/curl.md), [PHP](api-examples/php.md), [JavaScript](api-examples/javascript.md) and [Ionic Angular](api-examples/ionic-angular.md) examples are deliberately non-sending contract specimens. The [Postman collection](postman/quixdevs-plugnpay-direct.postman_collection.json) has a pre-request guard that prevents sending.

Caller identity and per-order authorization checks are implemented in this copy. Production integration still needs strict payment fields, shared concurrency/idempotency protection, safe output/transport and verified refund handling. A trusted backend must hold credentials, verify the customer's session and order ownership, and prevent direct bypass of that boundary. Those responsibilities cannot be fulfilled by moving the same key from a mobile app into a header. No mobile backend or replacement endpoint is implemented here.

## Troubleshooting

Check the exact `plugnpaydirect` method and field spellings, nonempty matching client-key configuration and pending order state. Inspect merchant-side order/transaction references after uncertain outcomes. Code 401 is a business body value, not necessarily an HTTP authentication status. Verify server/client handling of status 123 in an isolated environment before compatibility claims. Do not enable request-body logging to debug PAN/CVV or paste raw processor replies into tickets. Merchant username/password and verification-hash configuration are distinct from the client key. The original REST branch sends no checkout verification hash. See [troubleshooting](troubleshooting.md) for inherited edge cases.
