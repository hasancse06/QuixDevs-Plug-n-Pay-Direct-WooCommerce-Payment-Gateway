# cURL contract specimen — network disabled

**Production integration unavailable while release blockers remain.** This is a non-sending contract specimen for an isolated mock. HTTPS, valid WordPress identity, the existing server-held key and ownership or explicit backend order permissions are implemented in this copy. The old embedded-key-only client is denied. [Actual contract](../rest-api.md), [exact migration](../api-authentication.md).

Actual operation: `POST /wp-json/wc/v3/plugnpay-payment`. JSON and form request parameters reach the handler. A trusted server using WordPress Application Passwords supplies the Basic auth header over HTTPS; same-site sessions use WordPress cookies/nonce instead. Never embed either credential transport or the body key in a mobile/browser bundle.

```json
{
  "payment_method": "plugnpaydirect",
  "order_id": 42,
  "plugnpaydirect-client-key": "<SERVER_SIDE_CLIENT_KEY>",
  "plugnpaydirect-card-number": "<PAN_FROM_PROTECTED_INPUT>",
  "plugnpaydirect-card-expiry-month": "<MM>",
  "plugnpaydirect-card-expiry-year": "<YYYY>",
  "plugnpaydirect-card-cvc": "<CVV_FROM_PROTECTED_INPUT>",
  "plugnpaydirect-card-name": "<CARDHOLDER_NAME>"
}
```

Transport shape, deliberately non-executable:

```text
curl --request POST <PROTECTED_STORE_BASE>/wp-json/wc/v3/plugnpay-payment
     --header 'Authorization: Basic <SERVER_HELD_WORDPRESS_CREDENTIALS>'
     --header 'Content-Type: application/json'
     --header 'Accept: application/json'
     --data-binary @<EPHEMERAL_PROTECTED_PAYLOAD>
```

No actual raw-card payload file should be written to disk or recorded in shell history. Configure WordPress identity/capabilities separately and enforce mobile customer/order scope on the trusted backend. Do not remove the specimen guard to use this blocked candidate. Use intercepted transport only; no real request is demonstrated. Inherited HTTP 123, raw output and missing payment lock prevent reliable public use. Never automatically retry an uncertain outcome.
