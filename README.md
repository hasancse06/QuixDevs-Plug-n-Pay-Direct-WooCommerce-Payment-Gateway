# QuixDevs Plug'n Pay Direct

![QuixDevs Plug'n Pay Direct — supplied QuixDevs logo and StockNotifier-style payment preview](docs/branding/github-social-preview.png)

**WooCommerce Payment Gateway with REST API Support**  
Accept Payments. Integrate Easily. Manage Securely.

Accept Plug'n Pay credit card payments in WooCommerce with direct checkout integration and a documented REST API. This distribution candidate keeps the existing gateway ID, merchant settings, financial request mapping, order references and refund implementation.

- Classic WooCommerce credit-card checkout.
- Existing `POST /wp-json/wc/v3/plugnpay-payment` integration.
- Merchant configuration and provider transaction references.
- Refund and saved-payment source retained, with limitations documented.

**Version 1.0.0 target — PREPARED LOCALLY, PUBLIC RELEASE BLOCKED.** License confirmation and critical payment-correctness/security work remain outstanding. REST processing exists for isolated tests but is **unavailable for production deployment** in this candidate. The tagline is a product aim, not a security certification. [Release readiness](docs/github-release-readiness.md).

[Installation](#installation) · [Merchant setup](#plugn-pay-merchant-configuration) · [REST reference](docs/rest-api.md) · [Testing](docs/testing.md) · [Release conditions](docs/release-checklist.md)

## Overview

A controlled GitHub distribution copy of the existing QuixDevs gateway. The repository lives in `quixdevs-plugnpay-direct-github`; a future approved ZIP must install as **`quixdevs-plugnpay-direct/`**, without the GitHub-folder suffix. Both existing source directories are untouched. No live payment, credential rotation, database change or deployment was performed.

## Why QuixDevs Plug'n Pay Direct?

The project brings clear merchant instructions, an actual-contract API reference, testable developer tooling and consistent QuixDevs presentation to the supplied production-used implementation. It does not replace the processor integration or invent a new settlement flow.

## Key Features

| Area | Implemented behavior and evidence |
|---|---|
| Classic Checkout | Existing card form and provider request mapping; synthetic local tests |
| REST integration | Existing URL and payment fields; new HTTPS, identity and order-scope checks |
| Merchant setup | Same gateway/settings identifiers; checkout verification key moved to server configuration |
| Order confirmation | Provider `orderID` recorded through WooCommerce payment completion |
| Refunds | Existing `mode=return` flow retained; result-contract/pending/duplicate defects remain |
| Saved methods | Existing `authprev` flow retained; invalid/foreign tokens now stop before submission |

No processor, WordPress runtime, secure-vault, HPOS, optional-extension or PCI certification is implied by mocked tests. [Functional comparison](docs/functional-parity.md).

## Screenshots

The header image uses the supplied QuixDevs logo and original payment artwork in the StockNotifier presentation style. The image below is a genuine local rendering of the API documentation. Four WordPress admin/checkout captures remain unavailable because no disposable WordPress environment was provisioned. [Exact capture instructions](docs/screenshots/README.md) describe the remaining views.

![Local API documentation preview — release status and actual authentication requirements](docs/screenshots/05-api-documentation.png)

## Requirements

PHP 8.2+ is the intended distribution baseline; local execution is PHP 8.4.13. WooCommerce must provide `WC_Payment_Gateway_CC`; no actual WordPress/WooCommerce version is currently certified by this project. Direct-card processing requires HTTPS and an appropriately configured Plug'n Pay merchant account. The retained financial flow sends KYD. [Compatibility and limitations](docs/compatibility.md).

## Installation

**No public release ZIP exists yet.** Keep this repository local/private while blockers remain. Development requires Composer only for tests/standards; customer runtime uses no Composer autoloader.

For isolated review, copy the allowlisted runtime files into a disposable installation under `wp-content/plugins/quixdevs-plugnpay-direct/`, intercept processor HTTP requests, and activate only one copy of this gateway. Both copies share `plugnpaydirect`; do not activate them together. No source settings or historical tokens are migrated/deleted.

After release approval and ZIP installation verification, the public instructions will be:

1. Open the approved repository's Releases page and download the attached `quixdevs-plugnpay-direct.zip`, not GitHub's generic source archive.
2. In WordPress, choose Plugins → Add Plugin → Upload Plugin, select that ZIP, install and activate.
3. Configure HTTPS, the server-side verification key and existing WooCommerce payment settings.

No unpublished download URL is invented. Maintainers can validate package paths now with `composer package-check`; this constructs an **in-memory layout only** and writes no ZIP. `python3 tools/build-release.py` currently refuses to produce a release archive. [Publishing checklist](docs/github-publishing-checklist.md).

## Plug'n Pay Merchant Configuration

Gateway ID and setting IDs are unchanged: `enabled`, `title`, `description`, `publisher_name`, `publisher_email`, `client_key`, `client_password`, `cardtypes`, `saved_cards`.

The embedded checkout verification secret was removed **before writing the distribution copy**. Configure `QUIXDEVS_PLUGNPAY_VERIFICATION_KEY` on the server with no default. Missing/empty configuration or non-HTTPS checkout stops before submission. The existing MD5 input ordering and provider parameters remain unchanged. The historical key must be treated as compromised for future distribution; authorized merchant rotation/configuration is not performed automatically. [Merchant setup](docs/merchant-configuration.md).

## WooCommerce Checkout Integration

Classic Checkout submits the existing name, PAN, separate expiry month/year and CVC fields. Successful provider confirmation records transaction `orderID`, adds existing notes and calls `payment_complete`. The main implementation, currency, amount calculation, parser and settlement semantics are preserved. Inherited validation and output issues are documented. Checkout Blocks is not implemented.

## REST API Integration

The only operation is `POST /wp-json/wc/v3/plugnpay-payment`. Existing request names remain. No QuixDevs v1 route, API refund, status lookup or idempotency field is added. The endpoint is retained for integration testing but production deployment remains blocked. [Complete API reference](docs/rest-api.md).

## API Authentication

The old embedded-key-only client can no longer execute payments in this copy. A valid WordPress identity and HTTPS are required **in addition to** the existing shared key. Customers are restricted to their own registered-customer orders. A trusted backend paying other/guest orders needs both the explicitly granted `quixdevs_plugnpay_pay_orders` capability and WooCommerce permission to edit the requested order. No capability is granted automatically, including to administrators.

A mobile integration must move secrets to its trusted backend and implement customer/order authorization there. This authentication change is intentional and documented; it does not solve the retained duplicate-charge and response defects. [Exact migration requirements](docs/api-authentication.md).

## API Request & Response

Authorization failures now use `WP_Error` with 400/401/403 status data. Once authorized, the original handler retains HTTP status **123**, body code 200/401/405 and numeric provider fragments. Timeout handling can still throw. These are explicit release blockers, not a standardized public API. [Fields, synthetic responses and retry rules](docs/rest-api.md).

## cURL Integration

[Guarded cURL contract specimen](docs/api-examples/curl.md) documents the actual URL, headers and all payment fields without submitting a raw-card request. Do not enable a production transport while this candidate remains blocked.

## PHP Integration

[PHP specimen](docs/api-examples/php.md) describes a trusted-server boundary and synthetic payload assembly. No real merchant credentials, automatic retry or card logging is supplied.

## Ionic Angular Integration

[Ionic Angular example](docs/api-examples/ionic-angular.md) keeps merchant credentials/shared keys out of app code and prepares UI state only. A separately implemented backend is required; no fictional backend URL is advertised. [Server-side JavaScript specimen](docs/api-examples/javascript.md), [guarded Postman collection](docs/postman/README.md).

## Refunds

Existing full/partial `mode=return` requests are preserved. Pending is currently treated as success and associative failure arrays remain truthy to WooCommerce callers. Repeated calls are not protected internally. These behaviors are characterized, not endorsed as a correct public release. [Refund details](docs/refunds.md).

## Payment Security

Full card data passes through the store server. No PCI certification is claimed. Do not log PAN, CVV, passwords, auth headers or complete payment bodies. New REST authorization and saved-token guards are tested locally; locking/replay protection, strict card validation, output/log redaction and refund correctness remain unresolved. [Security review](docs/security.md), [SECURITY.md](SECURITY.md).

## Compatibility and Limitations

The supplied implementation has maintainer-reported production history. That is distinct from this copy's mocked verification. Real WordPress/WooCommerce installation, HTTP serialization, HPOS, Blocks and optional extensions are not verified. Original card-logo assets were excluded because redistribution terms were unavailable; missing optional icon files are skipped by the existing renderer. [Compatibility matrix](docs/compatibility.md).

## Troubleshooting

See [troubleshooting](docs/troubleshooting.md) for missing server-key configuration, denied order scope, backend capabilities, pending-only REST orders, HTTP 123, timeout reconciliation and refund issues. Do not reset a paid order simply to force another attempt.

## Developer Documentation

- [Source audit](docs/source-audit.md), [architecture](docs/architecture.md), [financial request mapping](docs/payment-flow.md)
- [REST reference](docs/rest-api.md), [authentication](docs/api-authentication.md), [safe examples](docs/api-examples/php.md)
- [Testing](docs/testing.md), [parity comparison](docs/functional-parity.md), [implementation report](docs/implementation-report.md)
- [GitHub listing](docs/github-listing.md), [release notes](docs/github-release-notes.md), [readiness report](docs/github-release-readiness.md)

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md). Work only on synthetic fixtures and mocked HTTP. Keep financial changes separately reviewed, and never copy private merchant configuration into the repository. No Git repository or remote has been initialized automatically.

## Upstream Attribution

Derived from the supplied Plug'n Pay Direct Gateway for WooCommerce 1.3.2, attributed to PatSaTECH. QuixDevs is the intended current publisher, not the original author of all source. Historical notices remain in [UPSTREAM-NOTICES.md](UPSTREAM-NOTICES.md). [License review](docs/licensing-review.md).

## Developer & Maintainer

**Md Abu Hasan (M A Hasan)**

Full-Stack Software Engineer with 10+ years of experience in WordPress, WooCommerce, PHP, Laravel, Ionic Angular and REST API development.

- Portfolio: https://hasan.online/
- Udemy: https://www.udemy.com/user/m-a-hasan-2/
- Envato: https://themeforest.net/user/hasanonline
- Technical Blog: https://blog.hasan.online

## Published & Maintained by

**QuixDevs**

QuixDevs Plug'n Pay Direct is maintained under the QuixDevs brand. This candidate has not been publicly published.

## License

No applicable upstream redistribution grant was supplied. Public distribution is **BLOCKED pending license confirmation**. No GPL grant, exclusive ownership or asset redistribution rights are invented. Composer's license field is intentionally omitted; WordPress readme licensing metadata remains a draft.
