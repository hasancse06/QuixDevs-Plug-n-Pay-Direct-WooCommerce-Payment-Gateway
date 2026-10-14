# Production REST client versus the secure implementation

This comparison uses the preserved production `wooplugnpaydirect.php` REST controller and the current `Rest_Gateway`, `Rest_Authorization`, `Payment_Input`, `Provider_Response` and durable submission boundary. It describes inspected code and local regression evidence. It does not claim that the deployed application's HTTP traffic, release or processor settlement was observed.

**Merchant and application-developer approval is required before the coordinated client/plugin cutover.** The existing application authenticated only with an embedded client key. That application cannot call the hardened implementation unchanged. No old charging capability is restored without identity and order authorization.

## Contract comparison

| Concern | Preserved production implementation | Current secure implementation | Required client action |
|---|---|---|---|
| Endpoint and method | `POST /wp-json/wc/v3/plugnpay-payment` | Same endpoint and method | Keep the URL and method |
| Payment gateway | `plugnpaydirect` | Same identifier and existing merchant-setting keys | Keep the gateway value |
| WordPress identity | No route permission callback; matching body key was the operation's authentication gate | Authenticated WordPress user plus order permission callback, repeated inside the handler | Add a real WordPress identity; the embedded key alone is rejected |
| Existing client-key field | `plugnpaydirect-client-key`, loosely compared to the configured setting | Same field, nonempty string, constant-time `hash_equals` comparison | Continue supplying it; move it to the trusted backend if it must remain server-side |
| HTTPS | Provider URL used HTTPS; no equivalent REST-request HTTPS gate | WordPress must detect HTTPS; provider TLS verification stays enabled and redirects are blocked | Use correctly configured HTTPS, including trusted reverse-proxy handling |
| Customer-owned order | Order existence/pending checks; knowing a key was enough to reach another customer's order | Registered customer may pay only their own order | Authenticate the actual owner or use an explicitly authorized backend |
| Guest/other-customer order | No ownership/capability gate | Backend requires both `quixdevs_plugnpay_pay_orders` and `edit_shop_order` for the specific order | Provision explicit minimal backend scope and independently enforce the app customer's scope |
| Unknown order | Distinct invalid-order business message could disclose existence | Same generic HTTP403 error as an inaccessible order | Do not use the payment route to enumerate or test order existence |
| Request body | Eight existing payment fields | Same eight names; typed card/method/key/ID validation | Keep names; send card/expiry/CVC as strings and correct invalid formatting |
| Amount/currency | Amount from WooCommerce order; outgoing literal `KYD`; no client amount override | Same financial source, with positive two-decimal KYD/context validation | Ensure the server order is eligible; do not add amount/currency parameters |
| Order status | Requires literal `pending` | Same REST pending requirement | Do not infer payment approval from a generic not-pending rejection |
| Stored gateway context | No equivalent explicit wrong-gateway check | Different nonempty stored gateway rejected; unassigned order bound only after verified approval | Prepare the correct server-side order/payment method |
| Provider endpoint and financial fields | Plug'n Pay `pnpremote.cgi`, fresh-card charge fields, no checkout hash in REST | Same endpoint, field names/order and financial mode; each value encoded before concatenation | No processor credential or provider-field migration in the app |
| Charge approval | Parsed `FinalStatus=success`, then `payment_complete(orderID)` | HTTP2xx, unambiguous parsed success, usable safe reference and durable approval before completion | Treat the completed approved response as the gateway result; mocks do not establish settlement |
| Numeric response fragments | Unrestricted fragments from the processor body | Restricted decoded `FinalStatus`, `orderID`, `auth-code`, `resp-code`; known sensitive echoes redacted/omitted | Parse by field name, not array position; remove dependence on raw diagnostics |
| Business body codes | Integer `code` values `200`, `401`, `405`, plus `message` | Same code categories for normal authorized business results | Preserve business-code handling separately from transport/authentication errors |
| PHP response-object status | Controller constructs `WP_REST_Response(..., 123)` | Explicit completed HTTP200 for business results | Verify actual deployed wire behavior; support HTTP200 and proper WordPress errors |
| Permission/input failures | Usually business-body errors or unsafe malformed-input behavior | WordPress error envelope with actual HTTP400/401/403, plus financial-context HTTP409 | Add structured HTTP-error handling; avoid parsing every `code` as an integer |
| Overlapping/replayed charges | Pending check alone; parallel workers could both submit | Atomic shared SQL claim; approved/in-flight/uncertain attempts cannot be resubmitted | Serialize the UI and respect server refusal; do not use new orders as a retry bypass |
| Timeout/malformed/pending/problem/fraud | No reliable durable reconciliation boundary; transport failure could reach invalid array access | Generic uncertain HTTP409; original order remains blocked pending merchant review | Remove blind retries and route the existing order to merchant reconciliation |
| Explicit bad-card decline | Existing decline mapping, body `code: 401` | Same business mapping under HTTP200; a deliberate new attempt remains possible | Present the business decline; do not log out the user because body code is `401` |
| Refunds/saved-token REST APIs | Not implemented by this route | Still not implemented | Do not invent a refund, token or status endpoint |

The old controller's response-object value `123` is a source-code fact, not a captured wire trace. WordPress's [status-header implementation](https://developer.wordpress.org/reference/functions/status_header/) can skip a status without a recognized description; web servers/proxies may also affect the result. Some deployed clients may already have observed HTTP200. The new controller's explicit HTTP200 avoids that ambiguity without claiming an observed production outage.

## All eight request fields

| Field | Old handling | Current requirement |
|---|---|---|
| `payment_method` | Sanitized scalar in controller; expected gateway identifier | String `plugnpaydirect`; missing/empty/unsupported values retain separate business rejections, non-string is HTTP400 |
| `order_id` | Sanitized value, then existence/pending lookup | Positive integer or digit string within PHP's integer range, authorized pending order |
| `plugnpaydirect-client-key` | Embedded shared key compared against server setting | Nonempty matching string, in addition to WordPress identity |
| `plugnpaydirect-card-number` | Whitespace normalization before processor request; no equivalent complete REST card validation | String containing 13–19 Luhn-valid digits, with supported spaces/tabs removed |
| `plugnpaydirect-card-expiry-month` | Concatenated into processor expiry | Two-digit string `01`–`12` |
| `plugnpaydirect-card-expiry-year` | Last two characters sent to processor | Four-digit string `20xx`, not expired in the supplied month |
| `plugnpaydirect-card-cvc` | Forwarded to processor | String of 3–4 digits; never persisted or logged |
| `plugnpaydirect-card-name` | Forwarded without per-value encoding | Nonempty string up to 100 bytes, no control characters; punctuation encoded safely |

The route accepts WordPress JSON/form parameters. There is no optional client amount, currency, merchant password, idempotency key or saved-token parameter. [Complete request example and validation reference](rest-api.md#request-fields).

## Response specimens and parsing changes

For this synthetic processor reply:

```text
FinalStatus=success&orderID=DEMO-REFERENCE&auth-code=DEMO-AUTH&diagnostic=synthetic-debug
```

The old controller could retain the undocumented diagnostic as another numeric fragment. The current minimal public response is:

```json
{
  "0": "FinalStatus=success",
  "1": "orderID=DEMO-REFERENCE",
  "2": "auth-code=DEMO-AUTH",
  "code": 200,
  "message": "Your Payment was Successful"
}
```

The optional fragment keys depend on which allowed fields the processor supplied. There is no new named `transaction_id` field. Do not rely on the order of numeric keys, processor diagnostic strings, authorization-code order notes or error text as an API identifier. Localized message wording can vary; use documented codes and statuses.

A definite bad-card response remains a business failure under HTTP200:

```json
{
  "0": "FinalStatus=badcard",
  "1": "resp-code=51",
  "code": 401,
  "message": "Transaction Failed. Credit Card Authorization failed - Insufficient funds."
}
```

A missing WordPress identity is instead a real HTTP401 with a string error code:

```json
{
  "code": "quixdevs_plugnpay_authentication_required",
  "message": "An authenticated payment caller is required.",
  "data": { "status": 401 }
}
```

A processor timeout/uncertain outcome is a real HTTP409:

```json
{
  "code": "quixdevs_plugnpay_payment_uncertain",
  "message": "The processor result requires merchant reconciliation. Do not submit another payment or refund.",
  "data": { "status": 409 }
}
```

Other important errors are `quixdevs_plugnpay_invalid_client_key` (401), `quixdevs_plugnpay_order_forbidden` (403), invalid typed card/order/method input (400), `quixdevs_plugnpay_attempt_blocked` (409) and `quixdevs_plugnpay_attempt_unavailable` (503). See the [error table](rest-api.md#response-behavior-and-compatibility-changes). Core nonce/authentication, malformed JSON and infrastructure errors can have their own envelopes. A malformed processor reply normally becomes uncertain/409 at the shared submission boundary, even though the internal parser helper has a 502 error type.

## Migration acceptance criteria

The [implementation guide](api-authentication.md) gives non-sending Basic-auth and cookie/nonce specimens. A merchant/app-developer should approve the concrete changes below together:

- Add WordPress authentication, decide how the required body key is supplied, and authorize the application's own customer before a privileged backend call.
- Keep the endpoint/eight names; correct string formats; preserve trusted server-side amount/currency/order ownership.
- Distinguish integer business codes from string WordPress error codes and actual HTTP statuses. Handle restricted fragments by name.
- Replace automatic charge retries with a clear uncertain-payment state tied to the same order. Merchant reconciliation is explicit and uses existing processor records, not a fabricated lookup API.
- Verify owned/foreign/guest orders, missing key/nonce, invalid input, success/decline, concurrent workers and lost/uncertain responses under intercepted provider transport. Confirm card bodies and credentials are absent from logs.
- Review and approve the client/backend diff, credential provisioning and coordinated rollout/rollback. Local repository edits do not deploy that migration.

The production-use history is acknowledged. Local regression mocks and WordPress/WooCommerce integration tests establish specific code behavior; they do not establish real processor settlement, a completed application release or PCI compliance. [Security responsibilities](security.md), [payment flow](payment-flow.md), [testing evidence](testing.md).
