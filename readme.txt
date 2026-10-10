=== QuixDevs Plug'n Pay Direct ===
Tags: woocommerce, payment gateway, plugnpay, credit card payments, rest api
Requires PHP: 8.2
Stable tag: 1.0.0
License: Pending upstream license confirmation — submission draft only

WooCommerce Payment Gateway with REST API Support.

== Description ==

Accept Payments. Integrate Easily. Manage Securely.

LOCAL CANDIDATE ONLY — PUBLICATION BLOCKED. This tagline is a product aim, not certification. The implementation retains classic card checkout, gateway ID plugnpaydirect, merchant settings, transaction references, refunds and an existing REST payment operation. Security/payment correctness and upstream redistribution rights remain unresolved.

Target public version: 1.0.0. Publisher: QuixDevs. Developer/maintainer: Md Abu Hasan. Portfolio: https://hasan.online/. Historical source: Plug'n Pay Direct Gateway for WooCommerce 1.3.2 by PatSaTECH; see UPSTREAM-NOTICES.md.

Requirements: intended PHP 8.2+, WooCommerce providing WC_Payment_Gateway_CC, HTTPS, server-configured verification key and appropriately configured Plug'n Pay account. Only PHP 8.4.13 with mocked dependencies was executed locally. No Tested up to, WordPress minimum or WooCommerce compatibility certification is invented.

== Installation ==

No public release ZIP is available. Keep local/private and review only in an isolated disposable site with all processor HTTP intercepted. After release conditions and ZIP installation checks pass, download the approved quixdevs-plugnpay-direct.zip attachment, upload through Plugins > Add Plugin > Upload Plugin, then activate and configure. Canonical install folder is quixdevs-plugnpay-direct, without -github. Activate only one copy using the same gateway ID.

Configure QUIXDEVS_PLUGNPAY_VERIFICATION_KEY on the server with no default and existing merchant settings in WooCommerce > Settings > Payments. Never place secrets in source/mobile bundles. Development documentation includes exact server setup and migration instructions.

== Frequently Asked Questions ==

= Is this release ready for public use? =
No. Redistribution rights, shared payment/refund locking, refund contracts, strict validation and REST response/transport/logging behavior remain blockers. Real WordPress/WooCommerce/provider integration is unverified.

= What is the REST endpoint? =
POST /wp-json/wc/v3/plugnpay-payment. It is retained for isolated testing but unavailable for production deployment in this candidate. HTTPS, valid WordPress identity, existing shared key and order authorization are now required. The former embedded-key-only client is denied; a trusted backend migration is required. No REST refund/status/v1 route exists.

= Are refunds and saved cards verified? =
Their existing source is retained and mocked tests characterize it. Refund failure arrays/pending/duplicates are unresolved. Invalid/foreign saved tokens are rejected, but prior-transaction reuse is not a certified independent vault feature.

= Does this support Blocks, HPOS or optional extensions? =
No Blocks integration is implemented. HPOS, Subscriptions and Pre-Orders are not certified by real integration tests.

== External Service Disclosure ==

Payment processing sends card number, CVV, expiry, holder, merchant credentials, amount, KYD currency, IP and billing/shipping fields to Plug'n Pay at https://pay1.plugnpay.com/payment/pnpremote.cgi. Checkout additionally sends the existing verification hash/time. Refunds and saved-payment paths reuse provider transaction references. Provider documentation: https://docs.plugnpay.com/. Review applicable account terms/privacy obligations with the provider; no unsupported policy URL or compliance claim is invented. No live requests were made during preparation.

== Security Considerations ==

Full card data passes through the store server. No PCI certification is claimed. Do not log/store PAN or CVV, expose shared/merchant credentials, automatically retry uncertain payments or deploy this blocked candidate. Missing checkout server-key configuration/non-HTTPS stops before submission. Authorized REST still retains HTTP 123/raw provider fragments and transport defects. No shared payment lock exists.

== Screenshots ==

No genuine WordPress screenshots are supplied. Manual capture instructions are in the development repository; promotional artwork is separate and is not a fake admin capture.

== Changelog ==

= 1.0.0 — unreleased local distribution candidate =
* QuixDevs presentation and actual-contract developer documentation.
* Required server verification configuration, REST identity/order guards and saved-token rejection.
* Original branding, mocked regression tests and allowlisted packaging.
* Public release blocked pending license/security/runtime checks.

== License ==

Pending authoritative upstream redistribution terms. No GPL grant is invented. This readme is a future-submission draft and must not be submitted with unresolved licensing/compatibility metadata.
