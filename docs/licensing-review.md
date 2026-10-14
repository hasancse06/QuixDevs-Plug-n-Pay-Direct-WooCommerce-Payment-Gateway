# Licensing and provenance review

Md Abu Hasan explicitly confirms that he wrote and owns the applicable plugin code and authorizes free GPL-2.0-or-later distribution. This trusted owner statement is the basis for the scoped owned-code grant. It is not rejected merely because helper code resembles another WordPress extension. QuixDevs Plug'n Pay Direct processes through Plug'n Pay; no Stripe integration, Stripe SDK or Stripe API endpoint is implemented in the runtime.

## Scope and actual dependencies

The owner-authorized code is distributed under GPL-2.0-or-later with the full GNU GPL v2 text in `LICENSE`. Current branding identifies QuixDevs and Md Abu Hasan. Historical strings in the preserved source identify PatSaTECH and version 1.3.2; preserving those supplied notices does not adjudicate ownership of independently written code.

Runtime code uses WordPress/WooCommerce APIs and the WordPress HTTP API. It loads no runtime Composer package, bundled external payment SDK or Stripe library. Composer packages are development-only, retain their own licenses and are excluded from the installer. Incomplete optional compatibility references do not establish that an external library is bundled. The inherited runtime inventory is the main PHP file plus eight original helper/compatibility files; none is automatically declared third-party-owned solely because its old header lacked licensing metadata.

## Targeted source-similarity observations

An audit compared the preserved production helper files with the official WooCommerce Stripe extension at commit `95cffcc08f0db90b3168015436f7d067f8c8c9fd` (tag 4.1.0, April 2018). Earlier normalized coverage percentages were investigative signals, not proof of copyright ownership, copying direction, protected originality or a license obligation for entire files. They must not be treated as a blanket GPLv3 classification.

The following observations are reproducible raw-line matches, without processor-name or whitespace normalization. Line numbers on the local side refer to the preserved original helper files.

| Local file / original lines | Official pinned counterpart | Observation / limit |
|---|---|---|
| `includes/class-wc-plugnpay-helper.php`, 283–331 | [helper 283–331](https://github.com/woocommerce/woocommerce-gateway-stripe/blob/95cffcc08f0db90b3168015436f7d067f8c8c9fd/includes/class-wc-stripe-helper.php#L283-L331) | 49 equal consecutive lines, including a currency-minimum switch; currency facts/standard operations are not by themselves an ownership determination |
| `includes/compat/class-wc-plugnpay-subs-compat.php`, 41–89 | [subscriptions 41–89](https://github.com/woocommerce/woocommerce-gateway-stripe/blob/95cffcc08f0db90b3168015436f7d067f8c8c9fd/includes/compat/class-wc-stripe-subs-compat.php#L41-L89) | 49 equal consecutive lines across helpers/comments; matching APIs/boilerplate must be distinguished from protected expression |
| `includes/compat/class-wc-plugnpay-pre-orders-compat.php`, 80–102 | [pre-orders 80–102](https://github.com/woocommerce/woocommerce-gateway-stripe/blob/95cffcc08f0db90b3168015436f7d067f8c8c9fd/includes/compat/class-wc-stripe-pre-orders-compat.php#L80-L102) | 23 equal consecutive lines; nearby custom retry-callback flow warrants a focused provenance check |
| `includes/class-wc-plugnpay-exception.php`, 15–40 | [exception 15–40](https://github.com/woocommerce/woocommerce-gateway-stripe/blob/95cffcc08f0db90b3168015436f7d067f8c8c9fd/includes/class-wc-stripe-exception.php#L15-L40) | Simple localized exception wrapper; weak standalone evidence |
| `includes/class-wc-plugnpay-logger.php`, 38–49 | [logger 30–41](https://github.com/woocommerce/woocommerce-gateway-stripe/blob/95cffcc08f0db90b3168015436f7d067f8c8c9fd/includes/class-wc-stripe-logger.php#L30-L41) | Common logging/time formatting; weak standalone evidence |
| `includes/abstracts/abstract-wc-plugnpay-payment-gateway.php` | [abstract gateway](https://github.com/woocommerce/woocommerce-gateway-stripe/blob/95cffcc08f0db90b3168015436f7d067f8c8c9fd/includes/abstracts/abstract-wc-stripe-payment-gateway.php) | Longest raw equal block is a nine-line documentation block; a normalized percentage does not establish derivation |

The helper's SOFORT error entry and subscriptions' custom source/3DS patterns are additional similarities worth reviewing, rather than proof that the actual Plug'n Pay payment protocol belongs to another processor. The audit has not established a chain of provenance that overrides the maintainer's statement. No six-file blanket third-party ownership claim is made.

## Conditional third-party obligations

The examined external project has GPLv3 notices: [4.1.0 readme](https://github.com/woocommerce/woocommerce-gateway-stripe/blob/4.1.0/readme.txt), [3.1.0 main header](https://github.com/woocommerce/woocommerce-gateway-stripe/blob/3.1.0/woocommerce-gateway-stripe.php) (2017 WooCommerce, version 3 or later), and [3.0.0 main header](https://github.com/woocommerce/woocommerce-gateway-stripe/blob/3.0.0/woocommerce-gateway-stripe.php) (2016 Automattic, version 3 or later). `COPYING.GPL3` preserves that external license text as a reference for possible applicable portions; its presence does not automatically relicense this plugin or establish that those portions were copied.

If a focused provenance review establishes retained protected expression governed by that GPLv3 grant, preserve its copyright/license notices and choose a compatible combined distribution grant, obtain separate permission, or independently replace the specific expression. Until then, do not infer an automatic aggregate GPLv3 requirement from similarity percentages or another project's readme. [Conditional replacement plan](rights-replacement-plan.md), [scoped external-source notice](../THIRD-PARTY-NOTICES.md).

The current [PatSaTECH terms](https://www.patsatech.com/terms/) print `09-12-2025` and restrict reuse/public redistribution. They are not assumed to govern a different acquisition date, independently owned code or a separate authentic GPL grant. The matching [product page](https://www.patsatech.com/product/plugn-pay-direct-gateway-woocommerce/) establishes a historical product name, not current ownership of every local component.

All seven supplied network/composite PNGs remain excluded because their asset terms were not identified. Maintainer-supplied QuixDevs branding and original payment artwork are separate. A targeted provenance-review flag may remain without falsely denying the owner's GPL authorization or silently deleting working functionality. [Public history/version evidence](version-history.md), [historical notices](../UPSTREAM-NOTICES.md).
