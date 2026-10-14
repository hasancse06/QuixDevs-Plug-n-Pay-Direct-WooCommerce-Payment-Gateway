=== QuixDevs Plug'n Pay Direct ===
Tags: woocommerce, payment gateway, plugnpay, credit card payments, rest api
Requires at least: 6.2
Tested up to: 6.9
Requires PHP: 8.2
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/old-licenses/gpl-2.0.html

A free, open-source Plug'n Pay payment gateway for WordPress and WooCommerce.

== Description ==

QuixDevs Plug'n Pay Direct integrates the Plug'n Pay remote gateway with WooCommerce Classic Checkout. It uses trusted KYD order amounts and requires verified processor approval and a valid transaction reference before completing an order.

Features include payment transaction handling, full/partial refunds through the existing Return operation, durable duplicate-charge safeguards, authorized manual order/payment reconciliation, authenticated REST payments and customer-owned saved methods with verified references. The provider Return operation permits one return per original transaction, including a partial return. Uncertain attempts do not expire or retry automatically.

Core payment, refund, reference lookup and reconciliation paths have been tested with legacy and HPOS order storage. Checkout Blocks is not implemented; full optional Subscriptions/Pre-Orders compatibility is not certified. Legacy saved methods without verified references require fresh card entry and re-saving.

Author and maintainer: QuixDevs, https://quixdevs.com/.
Developed by Md Abu Hasan. Portfolio: https://hasan.online/. GitHub: https://github.com/hasancse06.

== Requirements & Compatibility ==

* WordPress 6.2+ is the declared metadata minimum; WordPress 6.9 was tested. The minimum combination was not separately exercised.
* WooCommerce is required. Version 10.9.0 was tested; no numeric WooCommerce minimum is declared.
* PHP 8.2+ is required. PHP 8.2.4 and 8.4.13 were tested.
* HTTPS, a configured Plug'n Pay merchant account and server-controlled verification key are required.
* KYD order amounts and one authoritative primary database with connection affinity are assumed.

Tests used real WordPress/WooCommerce order stores and mocked processor responses. They do not establish live processor authorization, settlement or PCI certification. Complete test reports and compatibility limits are in the GitHub repository.

== Installation ==

Download Latest Stable Version:
https://github.com/hasancse06/QuixDevs-Plug-n-Pay-Direct-WooCommerce-Payment-Gateway/releases/latest/download/quixdevs-plugnpay-direct.zip

All releases and release notes:
https://github.com/hasancse06/QuixDevs-Plug-n-Pay-Direct-WooCommerce-Payment-Gateway/releases

Use quixdevs-plugnpay-direct.zip from the release Assets list. GitHub's automatically generated Source code archives contain the development repository and are not the WordPress installation package. The latest download link becomes available when a stable release is manually published with that exact asset attached. Use the release asset's checksum when available.

1. Download the plugin ZIP.
2. Open WordPress Admin.
3. Navigate to Plugins > Add New > Upload Plugin.
4. Select quixdevs-plugnpay-direct.zip.
5. Click Install Now.
6. Activate the plugin.
7. Open WooCommerce > Settings > Payments.
8. Configure QuixDevs Plug'n Pay Direct.

Activate only one gateway copy at a time. Existing stores retain gateway ID plugnpaydirect and merchant-setting identifiers. Follow the repository installation/version-history guide when replacing the historical plugin basename.

== Configuration ==

Open WooCommerce > Settings > Payments > QuixDevs Plug'n Pay Direct. Configure the publisher name/email, client key, remote client password, accepted cards and saved-card preference, then enable the gateway.

Set QUIXDEVS_PLUGNPAY_VERIFICATION_KEY in administrator-controlled server configuration before plugins load. There is no embedded fallback. Protect merchant credentials, verify HTTPS detection behind proxies and configure the intended Plug'n Pay account/verification hash.

Validate checkout, declines, refunds and reconciliation with the processor before handling production transactions. Mocked automated tests are not confirmed live processor acceptance.

== REST API ==

The retained payment endpoint is POST /wp-json/wc/v3/plugnpay-payment.

The distributed version requires HTTPS, WordPress-authenticated identity, the existing client-key field and order authorization. Registered customers may pay their own orders. A trusted backend paying guest/other orders requires explicit payment and order-edit permissions; the plugin grants no capabilities automatically.

Existing applications that used only an embedded client key require migration to a same-site WordPress session/REST nonce or a trusted backend with a server-held Application Password. Direct browser requests expose the required body key; strict server-only storage requires an application-owned authenticated bridge. Follow the documented error/output contract and never automatically retry uncertain payments.

API documentation:
https://github.com/hasancse06/QuixDevs-Plug-n-Pay-Direct-WooCommerce-Payment-Gateway#rest-api

The REST section links to the full reference at docs/rest-api.md in the source repository.

== Security & External Service ==

This direct-card gateway sends payment details, merchant credentials, trusted order amounts, KYD currency, connection IP and billing/shipping fields to Plug'n Pay at https://pay1.plugnpay.com/payment/pnpremote.cgi. Checkout includes its configured verification hash/time. Refunds and saved payments use transaction references. Provider documentation: https://docs.plugnpay.com/.

Card data passes through the merchant server. Merchants are responsible for applicable PCI DSS obligations; no PCI certification or SAQ eligibility is claimed. HTTPS, typed input validation, encoded requests, verified TLS, filtered provider output and restricted diagnostics are implemented.

Do not store/log full card numbers, card security codes, credentials or raw processor replies. Keep secrets out of source control, screenshots and browser/mobile bundles. Check processor records and use the documented authorized reconciliation procedure after uncertain outcomes; do not resubmit blindly.

== Changelog ==

= 1.0.0 =
* QuixDevs community distribution with GPL-2.0-or-later licensing and preserved attribution.
* Classic Checkout and existing Plug'n Pay gateway, merchant settings and transaction fields.
* Verified payment completion, full/partial Return refunds and duplicate-operation safeguards.
* Durable shared attempts and authorized manual payment/refund reconciliation.
* Retained REST endpoint with authenticated order scope, typed validation and protected output.
* Per-method verified saved references and core legacy/HPOS order-storage checks.
* Canonical classes, input/escaping/i18n hardening, regression tests and lightweight installation ZIP packaging.

== License & Attribution ==

License: GPL-2.0-or-later. Copyright (c) 2026 Md Abu Hasan / QuixDevs for developer-owned code. See LICENSE for the full GNU GPL v2 text and the option to use a later version.

Historical PatSaTECH attribution and any applicable independently owned notices remain in UPSTREAM-NOTICES.md and THIRD-PARTY-NOTICES.md. QuixDevs is an independent publisher; no official Plug'n Pay or WooCommerce endorsement is claimed.
