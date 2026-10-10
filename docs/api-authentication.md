# REST authentication and client migration

## Implemented in this distribution copy

The retained route has an actual `permission_callback`, and its PHP handler repeats the same check before processing. `Rest_Authorization::check` requires:

1. HTTPS as reported by WordPress `is_ssl()`.
2. A positive authenticated WordPress user ID.
3. A nonempty string `plugnpaydirect-client-key` matching the nonempty configured gateway `client_key`, using `hash_equals`.
4. A positive integer/digit-string order ID and an existing WooCommerce order.
5. Either a registered-customer order owned by that WordPress user, **or** both `quixdevs_plugnpay_pay_orders` and the order-specific WooCommerce `edit_shop_order` permission.

Missing and unauthorized orders use the same generic 403 error. Guest orders have no registered owner and require the explicit trusted-backend capability branch. No arbitrary logged-in user/admin/manager is automatically allowed to charge someone else's order. No role/capability is granted automatically. No order key, JWT field, new route or new idempotency contract is invented.

WordPress validates caller authentication before the permission callback. [Official authentication documentation](https://developer.wordpress.org/rest-api/using-the-rest-api/authentication/) describes same-site cookies plus `X-WP-Nonce` and HTTPS Application Passwords. [Custom endpoint permissions](https://developer.wordpress.org/rest-api/extending-the-rest-api/adding-custom-endpoints/) explains the permission callback. No JWT plugin or actual WordPress authentication transport was installed/verified here.

## Intentional breaking change

The maintainer confirmed the existing mobile client uses only an embedded shared key. **That client is denied with authentication-required 401 in this copy.** Adding a header containing the same embedded key is not a valid migration. Neither existing source directory was changed.

For a trusted backend, provision a dedicated WordPress service identity with the needed WooCommerce order-edit permissions and explicitly grant the new custom capability. Example administrative command shape (not executed here):

```text
wp user add-cap <DEDICATED_BACKEND_USER> quixdevs_plugnpay_pay_orders
```

This does not assign `edit_shop_order` automatically; confirm the service identity's separately configured WooCommerce permissions for the specific order. Keep Application Passwords, gateway client key and merchant credentials only on the server. The backend must authenticate the mobile customer and authorize its order before using this privileged identity. Avoid granting broad capabilities to ordinary customers. No production user/role was modified.

The endpoint URL, payment fields and merchant processor request remain the same. Authorization errors now use the WordPress WP_Error envelope/status rather than the legacy business body and 123. Once authorized, inherited HTTP/output/concurrency/refund problems still prevent production use; see [REST reference](rest-api.md). The route is implemented for isolated tests, **unavailable for production deployment**, and the public release remains blocked. No safe executable raw-card example is supplied.
