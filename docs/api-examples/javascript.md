# JavaScript trusted-server contract specimen

**Production integration unavailable while release blockers remain.** This is a non-sending contract specimen for an isolated mock. HTTPS, valid WordPress identity, the existing server-held key and ownership or explicit backend order permissions are implemented in this copy. The old embedded-key-only client is denied. [Actual contract](../rest-api.md), [exact migration](../api-authentication.md).

No `fetch` or browser-to-store raw-card operation is supplied. This specimen records existing fields for server-side mock review only:

```javascript
const contract = Object.freeze({
  payment_method: 'plugnpaydirect',
  order_id: 42,
  'plugnpaydirect-client-key': '<SERVER_SIDE_CLIENT_KEY>',
  'plugnpaydirect-card-number': '<PAN_FROM_PROTECTED_INPUT>',
  'plugnpaydirect-card-expiry-month': '<MM>',
  'plugnpaydirect-card-expiry-year': '<YYYY>',
  'plugnpaydirect-card-cvc': '<CVV_FROM_PROTECTED_INPUT>',
  'plugnpaydirect-card-name': '<CARDHOLDER_NAME>'
});
// Do not send, persist or log this documentation object.
```

A future approved server transport needs HTTPS WordPress authentication and configured order permissions in addition to the body key. The separately implemented backend must scope each mobile customer to the correct order before using a privileged identity. No new backend URL, JWT endpoint, idempotency field or refund operation exists in this plugin.

Authorized handler success uses HTTP200 and retained body `code: 200` with selected safe numeric financial fragments. Transport failures are generic; durable unresolved attempts use WordPress errors and stop repeated submission. Handle both business bodies and error envelopes. Do not log raw replies or automatically retry; follow the authenticated client migration and merchant reconciliation procedure.
