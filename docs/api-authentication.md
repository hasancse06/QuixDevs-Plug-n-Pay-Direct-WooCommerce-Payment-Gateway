# REST authentication and client migration

The production payment operation remains `POST /wp-json/wc/v3/plugnpay-payment`, with the same eight body-field names. The old application used only an embedded `plugnpaydirect-client-key`. The current implementation also requires a WordPress identity and authorization for the specific order. **An embedded-key-only application requires an approved client cutover before deployment.** No deployed application or merchant configuration is changed by this repository preparation.

Use the [old/current comparison](rest-client-comparison.md) for the exact compatibility changes and the [REST reference](rest-api.md) for fields, errors and responses.

## Identity, client key and order scope

These are separate checks:

1. WordPress establishes the calling user through its authentication middleware.
2. The gateway requires HTTPS and a nonempty client-key string matching the existing server setting with `hash_equals`.
3. A registered customer can pay their own order. A dedicated backend identity paying a guest or another customer's order requires both `quixdevs_plugnpay_pay_orders` and WooCommerce's `edit_shop_order` permission for that specific order.

The plugin grants no capabilities automatically. Being an administrator or knowing the shared key does not bypass the explicit backend-capability requirement. An unknown order and an inaccessible order receive the same generic HTTP403 error. A privileged application backend must authenticate its own customer and authorize that customer's order before using its WordPress identity; the plugin cannot infer an application's separate login session.

## Supported transports

| Transport | WordPress identity | Existing body key | Intended use |
|---|---|---|---|
| Same-site REST cookie request | Valid WordPress login cookie plus a current `wp_rest` nonce, normally in `X-WP-Nonce` | Still required | Customer operating inside the WordPress site |
| Trusted backend request | Dedicated WordPress user and server-held Application Password via HTTP Basic over HTTPS | Added by the backend | Existing mobile/web application with an authenticated backend |

A nonce is CSRF protection for the cookie session; it is not a standalone login, merchant credential or payment idempotency key. Without the required REST nonce, core can treat a cookie request as anonymous. Application Password requests use core authentication and do not need a cookie-session nonce. These mechanisms are described in the [WordPress authentication handbook](https://developer.wordpress.org/rest-api/using-the-rest-api/authentication/).

**Client-key placement must be an explicit architecture decision.** A direct browser request to this route necessarily contains the body key and exposes it to that browser user. Cookie/nonce authentication does not make that value server-only. If the merchant requires the key to remain strictly server-side, send the same-site application's payment intent through its own authenticated server adapter, which supplies the key and calls this existing route. Such an adapter is application work; this plugin does not invent or provide another endpoint. Never put Application Passwords, provider passwords or verification keys in a browser/mobile bundle. Moving the embedded key into an `Authorization` header does not establish a WordPress identity.

## Non-sending request specimens

These are transport specifications for a mocked integration. The `.invalid` host and placeholders are deliberately unusable. They are not commands and contain no usable PAN or CVV.

```text
POST https://store.example.invalid/wp-json/wc/v3/plugnpay-payment
Content-Type: application/json
Accept: application/json
Authorization: Basic <BASE64_OF_DEDICATED_WP_USER_AND_SERVER_HELD_APPLICATION_PASSWORD>

{
  "payment_method": "plugnpaydirect",
  "order_id": 42,
  "plugnpaydirect-client-key": "<SERVER_HELD_CLIENT_KEY>",
  "plugnpaydirect-card-number": "<PAN_FROM_PROTECTED_INPUT>",
  "plugnpaydirect-card-expiry-month": "<MM>",
  "plugnpaydirect-card-expiry-year": "<YYYY>",
  "plugnpaydirect-card-cvc": "<CVV_FROM_PROTECTED_INPUT>",
  "plugnpaydirect-card-name": "<CARDHOLDER_NAME>"
}
```

For a reviewed direct same-site cookie integration, replace the Basic header with the following transport context; the eight-field body, including the client key, is unchanged:

```text
Cookie: <CURRENT_WORDPRESS_LOGIN_COOKIE>
X-WP-Nonce: <CURRENT_NONCE_FOR_wp_rest>
```

Do not store real request bodies in example files, Postman environments, analytics, crash reports, server access logs or shell history. A backend that relays card fields also handles card data and must account for that in its PCI DSS scope. This is a direct-card integration, not a hosted or tokenized payment API.

## Concrete cutover plan requiring merchant and app-developer approval

1. Inventory the deployed application's actual request fields, transport status handling and response-fragment parsing using redacted staging observations. The preserved source uses a response-object status of `123`; that is not proof that the deployed HTTP stack actually emitted `123`.
2. Select same-site cookie/nonce handling or an authenticated application backend. If using a backend, provision a dedicated WordPress user and Application Password server-side. Grant the custom payment capability only if guest/other-customer orders are necessary, together with the appropriate order-edit scope; avoid a general administrator credential.
3. Keep the route and eight field names. Send card, expiry and CVC values as strings; ensure two-digit month, four-digit year and valid input. Send no provider password, amount or currency override. The server order must be pending, positive KYD and unassigned or assigned to `plugnpaydirect`.
4. Parse completed HTTP200 business responses by integer body `code` (`200`, `401`, `405`). A body `401` is a business rejection, not a reason to log the WordPress user out. Separately handle WordPress error envelopes and actual HTTP400/401/403/409/503. Do not rely on fixed numeric fragment positions or arbitrary provider diagnostics.
5. On timeout, lost response or an uncertain/blocked-attempt error, stop retries and ask the merchant to verify the existing order/processor record. Do not manufacture a new order to bypass the per-order guard. Follow [reconciliation](reconciliation.md) after stopping the original worker.
6. Test owned/foreign/guest orders, both authentication paths, invalid input, declines, approved charges, concurrency and uncertain outcomes with provider transport intercepted. Verify that no card or credential data enters logs.
7. Review the concrete application diff, authorization scope, credential placement, response parsing and rollback plan. Obtain merchant/app-developer approval, then deploy the coordinated plugin/client change. Do not install both gateway copies together. This task does not authorize or perform that deployment.

The server implementation and its local tests exist; an application's backend adapter, actual mobile release, real TLS deployment and processor settlement remain separate verification work. [Payment flow](payment-flow.md), [security responsibilities](security.md), [testing](testing.md).
