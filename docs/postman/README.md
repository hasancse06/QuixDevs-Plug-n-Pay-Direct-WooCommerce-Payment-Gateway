# Guarded Postman documentation

Import [quixdevs-plugnpay-direct.postman_collection.json](quixdevs-plugnpay-direct.postman_collection.json) for documentation/local mock review only. The collection-level pre-request script always throws before sending. Do not remove it to deploy the blocked candidate. No real requests or credentials are supplied.

| Variable | Purpose / safe default |
|---|---|
| `base_url` | `https://store.example.invalid`; `/wp-json` appended |
| `order_id` | Synthetic pending order `42`; use an already-paid mock for that scenario |
| `payment_method` | `plugnpaydirect` |
| `client_auth` | `<CLIENT_KEY>` for existing body key; server-held only |
| `backend_user` | `<DEDICATED_WORDPRESS_USER>` |
| `backend_application_password` | `<SERVER_HELD_APPLICATION_PASSWORD>` |

Basic authentication models WordPress Application Passwords on a trusted server over HTTPS. It does not assign permissions: ownership or both explicit backend/order-edit capabilities remain mandatory. The old embedded-key-only app is denied. No real Application Password, merchant credential, PAN/CVV or customer/transaction data is stored. Card fields are nonfunctional placeholders. No idempotency variable is invented.

Three requests describe the existing payment operation, missing-method and paid-order branches. They add no refund/status/v1 endpoint. `order_id` remains a digit-string after substitution and is validated by the new permission check. Completed business responses use HTTP 200. Original source declares HTTP 123; the production wire status has not been captured and may already be 200 after WordPress or proxy handling. The authenticated client migration, restricted output and durable attempt errors require staging verification and approval. The send guard remains intentional: this collection is a contract specimen, not a live card-submission tool. [Actual contract](../rest-api.md), [authentication](../api-authentication.md), [detailed client comparison](../rest-client-comparison.md).
