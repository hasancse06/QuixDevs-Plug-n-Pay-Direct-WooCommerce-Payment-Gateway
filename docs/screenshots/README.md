# Genuine screenshot capture plan

No WordPress/WooCommerce screenshots were created: there is no provisioned disposable site for this plugin. The first four filenames below are planned, not existing images. `05-api-documentation.png` exists: a genuine local Marked/Chrome rendering of the REST documentation at 1440×1100, visually reviewed and embedded in the README. It is documentation, not an admin capture. Branding graphics are not admin screenshots.

Use a disposable local installation with fake products/accounts/orders. Copy only the allowlisted runtime under the canonical slug, activate one gateway copy, configure synthetic server/gateway values and intercept all Plug'n Pay HTTP requests with fixtures. Never use a production site or complete a real charge. Confirm no live transport is possible before capture.

| Planned filename | Exact capture |
|---|---|
| `01-gateway-settings.png` | WooCommerce → Settings → Payments → QuixDevs gateway; top settings/enable/title area, excluding credential controls |
| `02-payment-methods.png` | WooCommerce → Settings → Payments list showing the registered gateway |
| `03-checkout-payment-form.png` | Classic Checkout on a fake order with the gateway selected; empty card fields and no personal details |
| `04-merchant-configuration.png` | Gateway settings explanation/configuration controls with credential rows fully omitted, not merely password bullets |
| `05-api-documentation.png` | Rendered local REST documentation, with placeholder request fields and local-blocked status visible; label as documentation, never WordPress admin |

Set a consistent browser viewport (for example 1440×1000), use readable scaling, crop to the relevant interface, inspect every pixel for passwords, auth keys, production URLs, account names and card/transaction data, then save genuine PNGs in this folder. Capture only blank card fields. Review screenshots manually before adding actual image links and captions to README. Record actual WP/WC/PHP versions and mocked-transport setup with the captures. No fabricated UI is supplied.
