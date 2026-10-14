# Gateway and server configuration

Gateway ID `plugnpaydirect` and option `woocommerce_plugnpaydirect_settings` remain. Existing field IDs: `enabled`, `title`, `description`, `publisher_name`, `publisher_email`, `client_key`, `client_password`, `cardtypes`, `saved_cards`. Publisher name/remote password remain merchant authentication; the client key remains an additional REST credential. No settings migration, automatic credential replacement or production database operation occurred.

## Checkout verification key — required new server configuration

The source's literal verification key was removed before the target main file was written. There is **no hardcoded fallback**. Use an administrator-controlled environment/secret store and set the constant in server-side WordPress configuration before plugins load:

```php
// Configuration shape only. The environment must supply the merchant-approved value.
define('QUIXDEVS_PLUGNPAY_VERIFICATION_KEY', getenv('QUIXDEVS_PLUGNPAY_VERIFICATION_KEY'));
```

No value is supplied in this repository. Empty, false, nonstring or absent configuration stops checkout before the provider request; HTTPS is also required. The old key is treated as compromised for future public distribution. An authorized merchant must review rotation/configuration through Plug'n Pay; this task did not access that account or alter the live key. Do not put configuration files or environment exports in Git, ZIPs or screenshots.

The existing hash formula MD5(key + GMT timestamp + publisher name) is preserved exactly. The provider's [verification hash documentation](https://docs.plugnpay.com/docs/general-documents/security-administration/verification-hash/) differs in input order; no speculative correction was made. Merchant-specific confirmation remains necessary. REST originally sends no checkout hash and still does not; the new checkout constant is not an invented REST request field.

## Other retained behavior

Main payment/refund paths send literal KYD and order totals without currency conversion. Other store currencies are not supported claims. Existing stored title/description overrides remain; no historical label migration. Card selection fields remain, but unlicensed network-logo PNGs are excluded from this repository and optional icons are skipped by the original renderer. New saved methods bind their own verified reference; legacy unmapped tokens require a fresh card and re-saving. Optional renewals require an explicitly mapped order reference; the latest user-level reference is not a safe subscription-card mapping. Full subscription migration remains separately scoped. See [authentication migration](api-authentication.md) and [release limitations](security.md).

## Data handling and diagnostics

Card fields exist only for the current request and mocked tests; never place real PAN/CVV or credentials in examples, screenshots, logs or source. Plugin logger filters receive a constant diagnostic event rather than arbitrary payloads. Gateway exceptions omit debug bodies, raw provider error text is not copied to notes, and authorization codes are omitted from order notes. Stored provider references are checked against known request PAN/credential values. Existing token metadata remains last-four/type/expiry plus the inherited reference model.

Store operators must also configure their web server, PHP, proxies, analytics and APM to avoid recording request bodies or exception arguments. Review `zend.exception_ignore_args=On`, PHP error display, access logs and backups with the server administrator. These settings are outside the plugin's control. Full direct-card PCI DSS scope requires an acquirer/QSA assessment; this is not a hosted-fields integration or a compliance certification. [Security scope and primary references](security.md).

## Existing clients

The original shared-key-only application is incompatible with the existing WordPress identity/order-authorization guard. Approve the documented authenticated client cutover before deployment; do not restore anonymous payments or embed Application Passwords in a mobile bundle. The trusted backend must authenticate its customer and authorize the order. REST now rejects malformed card/method/order values, wrong currencies and nonpositive order totals before HTTP, omits undocumented diagnostic fragments, and uses explicit HTTP200 for retained legacy business bodies. The old PHP response declared123; historical wire status was not captured. [REST reference](rest-api.md), [authentication](api-authentication.md).
