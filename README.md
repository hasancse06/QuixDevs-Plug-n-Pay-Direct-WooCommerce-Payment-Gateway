# QuixDevs Plug'n Pay Direct

![QuixDevs Plug'n Pay Direct with the supplied QuixDevs logo and original payment artwork](docs/branding/github-social-preview.png)

**WooCommerce Payment Gateway with REST API Support**

Accept Payments. Integrate Easily. Manage Securely.

QuixDevs Plug'n Pay Direct is the maintainer's existing production WooCommerce gateway, prepared for free community distribution. It provides classic card checkout, Plug'n Pay processing, transaction references, full/partial returns and the existing REST payment operation. Production use is maintainer-reported; independent verification and remaining review conditions are documented separately.

**Version 1.0.0 · GPL-2.0-or-later for developer-owned code.** The existing [public repository](https://github.com/hasancse06/QuixDevs-Plug-n-Pay-Direct-WooCommerce-Payment-Gateway) is retained. This checkout prepares an unpublished release candidate; nothing is automatically pushed, tagged, deployed or published. [Final release report](docs/github-release-readiness.md).

[Installation](docs/installation.md) · [Configuration](docs/configuration.md) · [REST API](docs/rest-api.md) · [Tests](docs/testing.md) · [Release process](docs/release-distribution.md)

## Features

| Feature | Implemented behavior |
|---|---|
| Classic WooCommerce Checkout | Existing cardholder, card number, expiry and CVC form |
| Compatible merchant settings | Same gateway `plugnpaydirect` and `woocommerce_plugnpaydirect_settings` identifiers |
| Plug'n Pay protocol | Existing HTTPS CGI endpoint, financial modes, KYD order totals and checkout hash ordering; individually encoded values |
| Verified order completion | Exact provider approval and safe transaction reference required |
| REST payments | Same `POST /wp-json/wc/v3/plugnpay-payment` route and eight body fields; authenticated order scope |
| Refunds | Full or partial `return`, true/WP_Error results and provider one-return limit |
| Duplicate protection | Durable shared SQL journal across checkout, REST, saved payments and refunds |
| Uncertain outcomes | No automatic lock expiry/retry; explicit capability-checked merchant reconciliation |
| Saved methods | Owned token with its own verified previous-transaction reference; old unmapped methods require re-saving |
| Distribution | Lightweight installer, SHA-256 checksum and manually triggered unpublished draft automation |

This is direct-card processing: card data passes through the store server. The tagline is a product aim, not PCI certification. [Security and PCI responsibilities](docs/security.md).

## Screenshots and branding

The supplied QuixDevs logo and original payment artwork follow the ProductClock/StockNotifier presentation family. Editable sources and correctly sized social/WordPress graphics are included outside the runtime ZIP.

![Genuine local rendering of the actual REST API documentation](docs/screenshots/05-api-documentation.png)

*Documentation rendering, not a WordPress admin screenshot. Settings/checkout captures remain planned; the disposable test site is stopped after verification. [Capture plan](docs/screenshots/README.md).*

## Installation

Use the attached **`quixdevs-plugnpay-direct.zip`** asset from an approved release, verify its checksum, then open WordPress **Plugins → Add Plugin → Upload Plugin**, install and activate. The canonical folder is `quixdevs-plugnpay-direct/`; repository source archives include development files and are not the lightweight installer.

No release has been published by this task. Maintainers can prepare a candidate locally:

```sh
python3 tools/build-release.py
cd dist/candidate
shasum -a 256 -c quixdevs-plugnpay-direct.zip.sha256
unzip -t quixdevs-plugnpay-direct.zip
```

The manifest lists approval status, exact files, bytes, digest and concrete pending conditions. Candidate labeling reflects those conditions, not the fact that automated processor tests use mocks. `--release` additionally requires all recorded approvals. [Installation details](docs/installation.md).

Activate only one copy with gateway ID `plugnpaydirect`. The preserved production plugin declares 1.3.2 under a different basename; this is a reviewed manual replacement, not an automatic version downgrade under that basename. No production folder/settings are changed automatically. [Version history and migration](docs/version-history.md).

## Merchant configuration

Open **WooCommerce → Settings → Payments → QuixDevs Plug'n Pay Direct**. Existing stores retain the same option and field IDs: enabled, title, description, publisher name/email, remote password, client key, card types and saved cards.

Set `QUIXDEVS_PLUGNPAY_VERIFICATION_KEY` through administrator-controlled server configuration before plugins load. There is no embedded default. Checkout requires HTTPS and that configured key; the existing merchant-specific hash formula is preserved for acceptance review. Never put merchant credentials, Application Passwords or shared keys in mobile/browser bundles, screenshots or source control. [Configuration guide](docs/configuration.md).

## REST compatibility and client cutover

The existing route **`POST /wp-json/wc/v3/plugnpay-payment`** remains implemented with the eight original request names. It requires HTTPS, WordPress-authenticated identity, the existing client-key field and order authorization. Customers may pay their own orders; a trusted backend paying guest/other orders needs explicit `quixdevs_plugnpay_pay_orders` and order-edit permission. The plugin grants no capabilities automatically.

The former embedded-key-only application needs a reviewed authenticated cutover. Supported paths are a same-site WordPress customer session with REST nonce, or a server-held Application Password on a backend that independently authenticates its customer and authorizes the order. No new hosted/tokenized payment API or application backend is fabricated.

Authorized legacy business JSON keeps integer body codes and selected safe numeric provider fragments under HTTP200. The original PHP response declared status123; its historical wire status was not captured. Security/input/attempt failures use WordPress error envelopes. Unknown/sensitive processor output is omitted. The durable journal blocks overlapping/replayed attempts and preserves uncertain outcomes for merchant review. [Exact API](docs/rest-api.md), [precise old/current comparison and migration](docs/rest-client-comparison.md).

The [cURL](docs/api-examples/curl.md), [PHP](docs/api-examples/php.md), [JavaScript](docs/api-examples/javascript.md), [Ionic](docs/api-examples/ionic-angular.md) and [Postman](docs/postman/README.md) specimens remain non-sending and use placeholders.

## Refunds, saved methods and reconciliation

Refunds retain `mode=return`, trusted amounts/reference and KYD. Only verified success returns true; pending/declined/uncertain outcomes return WP_Error. Plug'n Pay permits one Return per original transaction, including a partial return; this plugin does not silently switch to another financial mode for subsequent refunds. [Refund behavior](docs/refunds.md).

New saved methods retain display metadata and a per-token verified provider transaction reference. Choosing an older mapped token uses that token's reference. Legacy tokens cannot safely be mapped from the user's latest transaction; use a fresh card and re-save. The original `authprev` operation remains, without a claim of independently certified per-card vault tokenization.

A timeout, worker crash or changed order during approval blocks further submission. Inspect the durable attempt and processor record, confirm the original worker stopped, then use the explicit administrator WP-CLI reconciliation procedure. Confirmed refunds need separate WooCommerce accounting without resending a processor request. [Reconciliation commands](docs/reconciliation.md).

## Compatibility and verification

WordPress6.2+ is the intended feature minimum for prepared SQL identifiers; PHP8.2+ is the intended PHP baseline. Actual executed combinations, unit assertions, independent-worker tests, real WooCommerce refund accounting, HPOS, ZIP installation, Plugin Check, full WPCS and workflow lint results are recorded in [testing](docs/testing.md). Provider HTTP is intercepted; no live transaction or merchant settlement is claimed.

Full runtime WPCS now reports zero errors/warnings. Plugin Check reports zero errors and two retained legal-notice Markdown warnings. The [complete quality inventory](docs/quality-inventory.md) records all 545 baseline tool occurrences, corrections, behavior impact and every narrow statement exception. Existing payment locking and authorization implementations remain unchanged.

Classic Checkout is implemented. Checkout Blocks is not implemented. Core posts/HPOS verification does not certify every inherited optional Subscriptions/Pre-Orders branch. A standard single-primary WordPress database connection is required for the shared journal and reconciliation locks; custom read-routing/multi-primary clusters need separate verification. [Compatibility matrix](docs/compatibility.md).

## License and attribution

Copyright (c) 2026 **Md Abu Hasan / QuixDevs** for developer-owned code. The owner explicitly authorizes **GNU GPL version 2 or any later version**; the complete text is in [LICENSE](LICENSE). This supersedes the earlier generic missing-owner-authorization assumption.

Historical PatSaTECH attribution is preserved in [upstream notices](UPSTREAM-NOTICES.md). Specific helper-source similarities and any applicable independently owned portions are reviewed separately in [third-party notices](THIRD-PARTY-NOTICES.md) and [licensing review](docs/licensing-review.md); no automatic ownership conclusion or blanket relicense follows from a similarity score. Runtime contains no Stripe SDK/API or Composer dependency. Unverified legacy network PNGs are excluded; the supplied QuixDevs branding remains.

## Developer documentation

- [Architecture](docs/architecture.md), [payment protocol](docs/payment-flow.md), [refunds](docs/refunds.md) and [reconciliation](docs/reconciliation.md)
- [REST reference](docs/rest-api.md), [authentication/client cutover](docs/api-authentication.md), [configuration](docs/configuration.md) and [troubleshooting](docs/troubleshooting.md)
- [Production comparison](docs/functional-parity.md), [source audit](docs/source-audit.md), [security](docs/security.md) and [tests](docs/testing.md)
- [Release notes](docs/github-release-notes.md), [packaging/automation](docs/release-distribution.md), [version history](docs/version-history.md) and [changelog](CHANGELOG.md)

Follow [CONTRIBUTING.md](CONTRIBUTING.md); report sensitive issues using [SECURITY.md](SECURITY.md), without card/customer/credential data.

## 👨‍💻 Developer & Creator

**[Md Abu Hasan (M A Hasan)](https://hasan.online/)**

Full-Stack Software Engineer with **10+ years of experience** building web applications, SaaS platforms, WordPress and WooCommerce plugins, and hybrid mobile applications.

**Technical Expertise:** PHP, Laravel, WordPress, WooCommerce, Ionic Angular, TypeScript, REST APIs, and AI integrations.

- 🌐 **Portfolio & About Me:** [hasan.online](https://hasan.online/)
- 🎓 **Udemy Instructor:** [View Profile](https://www.udemy.com/user/m-a-hasan-2/)
- 🛠️ **Envato Author:** [View Portfolio](https://themeforest.net/user/hasanonline)
- ✍️ **Technical Blog:** [blog.hasan.online](https://blog.hasan.online)

## 🏢 Published & Maintained by

**[QuixDevs](https://quixdevs.com/)**

QuixDevs Plug'n Pay Direct is maintained under the **QuixDevs** brand. It is an independent plugin; no official endorsement by Plug'n Pay or WooCommerce is claimed. Release approval follows the documented verification and client migration conditions.
