# QuixDevs Plug'n Pay Direct

![QuixDevs Plug'n Pay Direct with the supplied QuixDevs logo, payment illustration and creator credit](docs/branding/github-social-preview.png)

**WooCommerce Payment Gateway with REST API Support**

Accept Payments. Integrate Easily. Manage Securely.

A **Plug'n Pay payment gateway for WooCommerce**, with classic credit-card checkout, merchant configuration, transaction references and a documented REST payment workflow. This GitHub workspace packages the existing QuixDevs implementation with clear store instructions, developer documentation, original payment artwork and reproducible development checks.

- Accept card payments through the classic WooCommerce checkout.
- Keep merchant configuration in WooCommerce payment settings.
- Integrate the existing REST payment operation through an authenticated server boundary.
- Retain refund and saved-payment features with their verification limits documented.

**Release status: private review candidate, target v1.0.0.** Public distribution remains blocked by unconfirmed upstream redistribution rights and unresolved payment-correctness/security requirements. The tests below use mocked processor responses. [Release status and remaining work](docs/github-release-readiness.md).

[Installation](#installation) · [Merchant setup](#merchant-configuration) · [REST API](docs/rest-api.md) · [Developer docs](#developer-documentation) · [Contributing](CONTRIBUTING.md)

## Why QuixDevs Plug'n Pay Direct?

The gateway connects WooCommerce orders to the supplied Plug'n Pay remote-payment integration. This distribution keeps the existing gateway ID, settings, payment amounts, processor fields and transaction references while providing a consistent QuixDevs repository presentation and explicit integration guidance.

Direct-card processing passes sensitive payment data through the store server. Merchants and integrators need to review the documented configuration and release conditions before deployment. The product tagline expresses its aim; no payment-security certification is claimed.

## Key features

| Feature | Implemented behavior |
|---|---|
| Classic checkout | Existing cardholder, card number, expiry and CVC fields |
| Merchant settings | Existing publisher, password, shared-key and card-selection settings |
| Order completion | Provider `orderID` recorded through WooCommerce payment completion |
| REST operation | `POST /wp-json/wc/v3/plugnpay-payment`, retaining existing body field names |
| Caller permissions | HTTPS, WordPress identity, shared-key validation and per-order authorization |
| Checkout verification | Server-configured verification key with no embedded default |
| Refund source | Existing full/partial `mode=return` flow retained |
| Saved methods | Existing previous-transaction reuse, with invalid/foreign-token rejection |

Refund results, concurrent attempts and authorized REST responses still have inherited defects. [Implementation comparison](docs/functional-parity.md) · [Security review](docs/security.md).

## Screenshots

The preview above uses the supplied QuixDevs logo and original payment artwork, following the ProductClock/StockNotifier presentation family.

![Local API documentation showing the route, release status and caller authorization requirements](docs/screenshots/05-api-documentation.png)

*Genuine local documentation rendering. WordPress settings and checkout captures require a disposable test store; [capture instructions](docs/screenshots/README.md) describe the four remaining views.*

## Installation

**WooCommerce must provide `WC_Payment_Gateway_CC`. Customer runtime does not require Composer.** This candidate is for isolated review while the release conditions remain unresolved.

The repository workspace is `quixdevs-plugnpay-direct-github`; the canonical WordPress installation folder is **`quixdevs-plugnpay-direct/`**. Activate only one gateway copy because the original and branded copies share ID `plugnpaydirect`.

For development, use a disposable WordPress/WooCommerce site and intercept all processor HTTP requests with synthetic fixtures. Copy only the runtime allowlist into `wp-content/plugins/quixdevs-plugnpay-direct/`. [Testing and package checks](docs/testing.md).

Once an approved public release is published:

1. Open the repository's **Releases** section and download the attached **`quixdevs-plugnpay-direct.zip`**.
2. In WordPress, open **Plugins → Add Plugin → Upload Plugin**, install that ZIP and activate the gateway.
3. Configure HTTPS, the server verification key and WooCommerce merchant settings.

There is currently no approved public ZIP or download URL. Maintainers can run `composer package-check` to validate the canonical archive layout in memory. The release builder refuses to write a ZIP while requirements remain unresolved. [Publishing checklist](docs/github-publishing-checklist.md).

## Merchant configuration

Open **WooCommerce → Settings → Payments → QuixDevs Plug'n Pay Direct**. Existing stores retain the same option `woocommerce_plugnpaydirect_settings` and field identifiers.

| Setting | Purpose |
|---|---|
| Enable, title and description | Checkout availability and display text |
| Publisher name / email | Plug'n Pay merchant identification |
| Remote password | Merchant processor credential |
| Client key | Additional credential for the existing REST operation |
| Card types / saved cards | Existing checkout and saved-method settings |

Checkout requires `QUIXDEVS_PLUGNPAY_VERIFICATION_KEY` from administrator-controlled server configuration before plugins load. No default is included; absent/empty configuration or non-HTTPS checkout stops before submission. The inherited hash formula is retained and requires merchant-specific confirmation.

Keep merchant passwords, shared keys and WordPress Application Passwords on trusted servers. [Exact configuration](docs/merchant-configuration.md) · [Financial request mapping](docs/payment-flow.md).

## REST API integration

The existing operation is **`POST /wp-json/wc/v3/plugnpay-payment`**. JSON or form request parameters reach the controller. The actual reference covers all eight request fields, order state, provider flow, transaction references and response behavior. [Complete REST reference](docs/rest-api.md).

This copy requires HTTPS, an authenticated WordPress identity, the existing shared client key and order authorization. Customers are limited to their own registered-customer orders. A trusted backend paying guest/other orders requires both explicitly granted `quixdevs_plugnpay_pay_orders` and WooCommerce permission to edit the requested order. The plugin grants no capabilities automatically.

**The former embedded-key-only app is denied in this copy.** A separately implemented trusted backend must authenticate its customer, authorize the order and keep credentials server-side. [Authentication and migration](docs/api-authentication.md).

| Integration guide | What is supplied |
|---|---|
| [cURL](docs/api-examples/curl.md) | Non-sending request/header specimen |
| [PHP](docs/api-examples/php.md) | Trusted-server payload specimen with placeholders |
| [JavaScript](docs/api-examples/javascript.md) | Server-side/mock contract object |
| [Ionic Angular](docs/api-examples/ionic-angular.md) | UI intent/state without embedded secrets or raw-card submission |
| [Postman](docs/postman/README.md) | Collection with an always-on network guard |

Permission failures use `WP_Error` with 400/401/403 status data. Authorized legacy processing still asks for HTTP **123**, exposes numeric provider fragments and can throw on transport errors. REST production deployment remains unavailable pending the documented corrections. Never automatically retry an uncertain charge.

## Refunds and saved payments

The inherited refund implementation sends `mode=return`, the transaction reference, amount and KYD. Mock tests characterize full/partial requests. Truthy failure arrays, pending-as-success and repeated-return behavior remain release blockers. [Refund details](docs/refunds.md).

Saved methods use a previous transaction associated with the user. Missing, foreign or wrong-gateway tokens and missing references are rejected before submission. This source model is not verified independent provider-vault tokenization. No tested token-save path persists full PAN or CVV. [Security and implementation limits](docs/security.md).

## Requirements and verification

| Component | Baseline / recorded evidence |
|---|---|
| PHP | Intended minimum 8.2; locally executed 8.4.13 |
| WordPress / WooCommerce | WooCommerce gateway class required; no actual installed-version certification |
| Merchant account / transport | Appropriate Plug'n Pay account and HTTPS; no live processor requests made |
| Currency | Main payments/refunds retain literal KYD |
| Local automated checks | 25 mocked tests, 127 assertions; PHP syntax passed |
| Coding standards | Full WPCS checking remains nonzero; see current counts and review limits |

Actual WordPress/WooCommerce authentication/serialization, HPOS, Plugin Check, ZIP activation and processor integration are unverified. GitHub Actions is configured; no hosted CI success badge is claimed. [Testing guide](docs/testing.md) · [Compatibility matrix](docs/compatibility.md).

## Compatibility and limitations

- **Checkout:** classic Checkout; Blocks integration is not implemented.
- **Concurrent payments:** sequential paid-order repeats are rejected, but overlapping attempts can submit twice. A shared payment/refund attempt boundary remains required.
- **Payment input/output:** encoding, strict card validation, response parsing and sensitive output/logging need further review.
- **Optional extensions:** Subscriptions and Pre-Orders source is retained; real integration is unverified.
- **Assets:** unlicensed upstream card-network PNGs are excluded. QuixDevs artwork stays outside the runtime ZIP.

[Security findings](docs/security.md) · [Troubleshooting](docs/troubleshooting.md) · [Release conditions](docs/release-checklist.md).

## Frequently asked questions

**Does this change the original production plugin?**
Both sibling source directories remain untouched. This folder is the separate distribution workspace; its scoped authentication/configuration changes are documented.

**Can I use the old mobile application's embedded key?**
Key possession alone is insufficient in this copy. Follow the trusted-backend migration and WordPress order-permission requirements.

**How should I handle a timeout?**
Reconcile the provider transaction and WooCommerce order before another attempt. A timeout does not establish that no charge occurred.

**Is this ready for a public release?**
Repository presentation and upload hygiene are prepared. Redistribution rights, critical correctness/security conditions and real platform checks still need evidence. [Readiness](docs/github-release-readiness.md).

## Developer documentation

- [Architecture](docs/architecture.md), [payment flow](docs/payment-flow.md), [merchant setup](docs/merchant-configuration.md) and [refunds](docs/refunds.md)
- [REST API](docs/rest-api.md), [authentication](docs/api-authentication.md), [examples](docs/api-examples/php.md) and [Postman](docs/postman/README.md)
- [Testing](docs/testing.md), [functional comparison](docs/functional-parity.md), [source audit](docs/source-audit.md) and [changelog](CHANGELOG.md)
- [GitHub listing](docs/github-listing.md), [release notes](docs/github-release-notes.md), [publishing checklist](docs/github-publishing-checklist.md) and [upload guide](docs/github-upload.md)
- [Branding sources](docs/branding/README.md), [WordPress.org assets](wordpress-org-assets/README.md) and [release-readiness report](docs/github-release-readiness.md)

## Contributing

Follow [CONTRIBUTING.md](CONTRIBUTING.md), use synthetic data and mocked processor responses, and keep financial changes focused and separately reviewed. Report suspected vulnerabilities through the private channel described in [SECURITY.md](SECURITY.md). Include versions and reproducible steps without payment/customer credentials.

## Upstream attribution and license

Derived from the supplied Plug'n Pay Direct Gateway for WooCommerce 1.3.2, attributed to **PatSaTECH**. Current QuixDevs presentation and maintenance do not establish exclusive ownership of inherited source. [Upstream notices](UPSTREAM-NOTICES.md).

No authoritative upstream modified-source redistribution grant was supplied. Public distribution remains **blocked pending license confirmation**. No GPL grant is invented. [License review](docs/licensing-review.md).

## 👨‍💻 Developer & Creator

**[Md Abu Hasan (M A Hasan)](https://hasan.online/)**

Full-Stack Software Engineer with **10+ years of experience** building web applications, SaaS platforms, WordPress and WooCommerce plugins, and hybrid mobile applications.

**Technical Expertise:** PHP, Laravel, WordPress, WooCommerce, Ionic Angular, TypeScript, REST APIs, and AI integrations.

- 🌐 **Portfolio & About Me:** [hasan.online](https://hasan.online/)
- 🎓 **Udemy Instructor:** [View Profile](https://www.udemy.com/user/m-a-hasan-2/)
- 🛠️ **Envato Author:** [View Portfolio](https://themeforest.net/user/hasanonline)
- ✍️ **Technical Blog:** [blog.hasan.online](https://blog.hasan.online)

## 🏢 Published & Maintained by

**QuixDevs**

QuixDevs Plug'n Pay Direct is maintained under the **QuixDevs** brand. It is an independent plugin; no official endorsement by Plug'n Pay or WooCommerce is claimed. Publication is pending the documented release conditions.
