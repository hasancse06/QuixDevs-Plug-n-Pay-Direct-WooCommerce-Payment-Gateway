# Merchant configuration for the distribution copy

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

Main payment/refund paths send literal KYD and order totals without currency conversion. Other store currencies are not supported claims. Existing stored title/description overrides remain; no historical label migration. Card selection fields remain, but unlicensed network-logo PNGs are excluded from this repository and optional icons are skipped by the original renderer. Saved methods retain their existing user-level transaction reference model. See [authentication migration](api-authentication.md) and [release limitations](security.md).
