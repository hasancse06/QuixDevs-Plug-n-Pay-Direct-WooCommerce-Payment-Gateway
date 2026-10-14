=== QuixDevs Plug'n Pay Direct ===
Tags: woocommerce, payment gateway, plugnpay, credit card payments, rest api
Requires at least: 6.2
Tested up to: 6.9
Requires PHP: 8.2
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/old-licenses/gpl-2.0.html

WooCommerce Payment Gateway with REST API Support.

== Description ==

Accept Payments. Integrate Easily. Manage Securely.

QuixDevs Plug'n Pay Direct prepares the maintainer's existing production gateway for free community distribution. It retains classic card checkout, gateway ID plugnpaydirect, merchant settings, transaction references, full/partial return processing and POST /wp-json/wc/v3/plugnpay-payment. This product tagline is not a payment-security or PCI certification.

Publisher: QuixDevs, https://quixdevs.com/. Developer: Md Abu Hasan, https://hasan.online/. Version 1.0.0 matches the existing public QuixDevs history. The separately named preserved production source declares 1.3.2; replacement is manual, not an automatic downgrade/update under its old basename.

The owner authorizes GPL-2.0-or-later for developer-owned code. LICENSE contains the full GNU GPL v2 text. Historical attribution and the separately reviewed helper-source correspondence are documented in UPSTREAM-NOTICES.md and THIRD-PARTY-NOTICES.md; no exclusive ownership of independently owned third-party expression is claimed.

Requirements: WooCommerce providing WC_Payment_Gateway_CC, PHP8.2+, WordPress6.2+ for prepared database identifier support, HTTPS, a properly configured Plug'n Pay account and the server verification key. WordPress6.9/WooCommerce10.9.0 core paths were tested; see the repository for the executed PHP/storage matrix and remaining acceptance scope.

== Installation ==

Download the approved quixdevs-plugnpay-direct.zip release asset when published, verify its SHA-256 checksum, then upload it through Plugins > Add Plugin > Upload Plugin and activate. Canonical folder: quixdevs-plugnpay-direct. Only one copy using gateway plugnpaydirect may be active. The existing public source repository is not the lightweight installation ZIP.

Configure existing merchant settings under WooCommerce > Settings > Payments and set QUIXDEVS_PLUGNPAY_VERIFICATION_KEY from administrator-controlled server configuration before plugins load. No embedded fallback is supplied. Keep credentials out of Git and mobile/browser bundles.

A reviewed release candidate and checksum are prepared locally; no release is automatically published. Use staging with intercepted processor HTTP before approving production migration. Check release notes for concrete remaining conditions and the authenticated client cutover.

== Frequently Asked Questions ==

= Is the legacy REST operation retained? =
Yes: POST /wp-json/wc/v3/plugnpay-payment, with the original eight body-field names. It now requires HTTPS, WordPress identity, the existing shared key and order authorization. The former embedded-key-only application needs an authenticated backend or same-site WordPress session/REST nonce migration. No fabricated hosted payment, vault, refund/status or alternative v1 endpoint is supplied.

= Are refunds and duplicates protected? =
Checkout, REST and refunds use one durable per-order SQL journal across workers. Pending, timeout or interrupted results remain blocked for explicit merchant reconciliation. A refund returns true only for verified success; failures/uncertainty return WP_Error. The provider's existing return operation permits one return per transaction, including a partial return. Saved methods retain the original previous-transaction model with owned-token validation; this is not a certified per-card vault.

= What checkout and storage are supported? =
Classic Checkout is implemented. Core posts/HPOS paths are tested; full optional-extension certification is not claimed. No Checkout Blocks implementation exists. Inherited optional subscription/pre-order scaffolding is separately documented rather than advertised as a newly implemented API.

== External Service Disclosure ==

Payment processing sends card number, CVV, expiry, holder, merchant credentials, amount, KYD currency, IP and billing/shipping fields to Plug'n Pay at https://pay1.plugnpay.com/payment/pnpremote.cgi. Checkout also sends the inherited verification hash/time. Refunds and saved payments use provider transaction references. Provider documentation: https://docs.plugnpay.com/. Review applicable account terms/privacy responsibilities with Plug'n Pay. No live processor requests were made during automated verification.

== Security Considerations ==

Card data traverses the merchant server. No PCI certification or SAQ eligibility is claimed. Do not log/store full PAN/CVV or credentials. Outgoing values are encoded individually, TLS verification remains enabled, redirects are disabled and response components are validated before completion. Raw diagnostics and authorization-code notes are suppressed. Authorized legacy business JSON retains its body codes under HTTP200; the original PHP response declared 123, but its actual historical wire status was not captured. Input/authentication/attempt errors use WordPress errors. Never automatically retry an uncertain charge/refund. Use the documented capability-checked WP-CLI reconciliation procedure after checking processor records and confirming the original worker stopped.

== Changelog ==

= 1.0.0 =
* Existing production payment/REST/settings features prepared under QuixDevs branding.
* Owner-authorized GPL-2.0-or-later grant, full license text and source notices.
* Durable worker-shared charge/refund attempts and explicit reconciliation.
* Correct WooCommerce refund results, validated amounts and one-return protection.
* Authenticated order-scoped REST, typed input, safe encoded requests and verified response references.
* Canonical class files with historical include compatibility; validated/unslashed input, safe legacy-return authorization and WooCommerce metadata queries for both order stores.
* Full runtime WPCS passes; Plugin Check has zero errors and two documented legal-notice Markdown warnings.
* Lightweight reproducible installer, checksum, regression/platform tests and manual unpublished draft automation.

== License ==

Copyright (c) 2026 Md Abu Hasan / QuixDevs for developer-owned code, licensed under GNU GPL version 2 or (at your option) any later version. See LICENSE and the preserved source notices for exact scope and any independently owned third-party portions. New branding does not remove historical attribution.
