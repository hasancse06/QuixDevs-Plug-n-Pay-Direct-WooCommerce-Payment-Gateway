# External-source references and scoped notices

Md Abu Hasan confirms ownership of the applicable independently written QuixDevs Plug'n Pay Direct code and authorizes GPL-2.0-or-later distribution; see `LICENSE`. This gateway uses Plug'n Pay. No Stripe SDK, Stripe runtime library or Stripe API endpoint is included. Runtime code relies on separately installed WordPress/WooCommerce and its HTTP APIs, with no runtime Composer dependency.

## Potential helper-source overlap under review

Source comparison found helper/compatibility similarities with the official [WooCommerce Stripe extension](https://github.com/woocommerce/woocommerce-gateway-stripe). This is a provenance-review observation, not a conclusion that this plugin is a Stripe gateway or that whole local files are owned by that project. Processor-name/whitespace normalization and matching APIs cannot establish copyright ownership or license scope by themselves.

The specific local files examined are:

- `includes/class-wc-plugnpay-helper.php`: a 49-line raw match in the original currency-minimum/helper section.
- `includes/compat/class-wc-plugnpay-subs-compat.php`: a 49-line raw match across helper/comment structure and nearby matching source/3DS patterns.
- `includes/compat/class-wc-plugnpay-pre-orders-compat.php`: a 23-line raw match and nearby matching retry-callback structure.
- `includes/class-wc-plugnpay-exception.php`: a simple common exception wrapper, weak standalone evidence.
- `includes/class-wc-plugnpay-logger.php`: common logging/time formatting, weak standalone evidence.
- `includes/abstracts/abstract-wc-plugnpay-payment-gateway.php`: no substantial raw match beyond a short documentation block; normalized coverage is not conclusive.

[Exact pinned sources, line references and qualifications](docs/licensing-review.md) distinguish observations from legal conclusions. Adaptations/namespaces and later security edits do not answer the source-provenance question either way. The owner's statement remains the basis for applicable owned-code licensing; no blanket third-party claim is imposed on these six files.

The external project's [3.0.0 header](https://github.com/woocommerce/woocommerce-gateway-stripe/blob/3.0.0/woocommerce-gateway-stripe.php) records Copyright (c) 2016 Automattic, and its [3.1.0 header](https://github.com/woocommerce/woocommerce-gateway-stripe/blob/3.1.0/woocommerce-gateway-stripe.php) records Copyright (c) 2017 WooCommerce; both grant GPL version 3 or later. Its [4.1.0 readme](https://github.com/woocommerce/woocommerce-gateway-stripe/blob/4.1.0/readme.txt) declares GPLv3. These are notices of the external reference project, not assertions of its ownership over independently written local code. `COPYING.GPL3` retains the complete external license text as a reference.

If actual retained protected expression from that project is established, preserve the applicable notices and license, and resolve combined-distribution compatibility or replace the specific expression independently. This conditional obligation does not automatically change the plugin's aggregate license. [Review and alternatives](docs/rights-replacement-plan.md).

## Development dependencies and assets

Composer packages are development-only and retain the licenses/notices in their upstream distributions. `composer.lock` identifies versions; `vendor/` is excluded from the installer. No external payment API library is bundled.

The seven supplied legacy network/composite PNGs are excluded because their asset terms have not been established. Maintainer-supplied QuixDevs artwork is separate. Historical PatSaTECH labels from the supplied source remain recorded in [UPSTREAM-NOTICES.md](UPSTREAM-NOTICES.md). No processor, card-network or upstream-author endorsement is claimed.
