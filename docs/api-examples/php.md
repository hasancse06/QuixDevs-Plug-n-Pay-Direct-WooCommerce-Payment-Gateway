# PHP trusted-server specimen — no HTTP call

**Production integration unavailable while release blockers remain.** This is a non-sending contract specimen for an isolated mock. HTTPS, valid WordPress identity, the existing server-held key and ownership or explicit backend order permissions are implemented in this copy. The old embedded-key-only client is denied. [Actual contract](../rest-api.md), [exact migration](../api-authentication.md).

```php
<?php
// Placeholder contract only. No request, logging, persistence or retry.
$contract = [
    'payment_method' => 'plugnpaydirect',
    'order_id' => 42,
    'plugnpaydirect-client-key' => '<SERVER_SIDE_CLIENT_KEY>',
    'plugnpaydirect-card-number' => '<PAN_FROM_PROTECTED_INPUT>',
    'plugnpaydirect-card-expiry-month' => '<MM>',
    'plugnpaydirect-card-expiry-year' => '<YYYY>',
    'plugnpaydirect-card-cvc' => '<CVV_FROM_PROTECTED_INPUT>',
    'plugnpaydirect-card-name' => '<CARDHOLDER_NAME>',
];
// Pass only to a mocked transport during local contract review.
unset($contract);
```

The eventual trusted transport must authenticate to WordPress, hold shared/merchant/Application Password credentials only on the server, authenticate the mobile caller and independently authorize the order. Guest/other orders require both configured backend capabilities. No backend adapter is supplied here. Merchant password, checkout verification key, amount and currency are not additional REST body parameters.

The gateway now implements strict input validation, shared durable attempt controls, restricted output and manual uncertain-outcome reconciliation. Completed business responses use HTTP 200. Original source declares HTTP 123; the production wire status has not been captured and may already be 200 after WordPress or proxy handling. The authenticated client cutover and response handling still require merchant approval and staging verification. Do not log CVV, PAN or raw processor replies. The specimen deliberately contains no `wp_remote_post`, cURL execution, output or automatic retry. [Detailed client comparison](../rest-client-comparison.md).
