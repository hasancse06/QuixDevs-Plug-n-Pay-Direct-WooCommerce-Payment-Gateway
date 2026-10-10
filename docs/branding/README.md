# QuixDevs branding — StockNotifier presentation family

The social preview follows StockNotifier's navy/teal layout: supplied QuixDevs logo at the upper left, product title and subtitle below, an original payment-card illustration on the right, feature markers, and **Created by M A Hasan**. Palette: navy `#091827`, teal `#35e2cc`, white `#f4fbff` and muted blue `#b3c8d7`. No processor/network logo or official endorsement is used.

The maintainer-supplied [QuixDevs logo](quixdevs-logo.png) is preserved byte-for-byte at its original **164×45** pixels. Its colors and aspect ratio are unchanged. SVG compositions embed those exact bytes, so exports are self-contained and do not depend on a Desktop file or remote URL. Enlarged uses retain the supplied raster's original level of detail.

| Editable source | Export |
|---|---|
| [quixdevs-logo.png](quixdevs-logo.png) | Supplied original wordmark |
| [github-social-preview.svg](github-social-preview.svg) | [1280×640 social preview](github-social-preview.png) |
| [wordpress-banner.svg](wordpress-banner.svg) | [772×250 banner](../../wordpress-org-assets/banner-772x250.png), [1544×500 banner](../../wordpress-org-assets/banner-1544x500.png) |
| [plugnpay-direct-mark.svg](plugnpay-direct-mark.svg) | [128×128 icon](../../wordpress-org-assets/icon-128x128.png), [256×256 icon](../../wordpress-org-assets/icon-256x256.png) |

PNGs are rendered from SVG and visually reviewed. Typography uses Arial, Helvetica or sans-serif. Reproduce with Node.js and Sharp installed: `node tools/render-branding.mjs`. If Sharp is installed externally, set `QPD_SHARP_MODULE` to its absolute module path. `php tools/generate-branding.php` remains a compatibility entry point; `QPD_NODE_BINARY` can select Node. These are optional maintainer artwork dependencies, not plugin runtime dependencies. SVG editors can also export the documented sizes.

After approved publication, open GitHub repository → Settings → General → Social preview → Edit → Upload an image, select `github-social-preview.png`, inspect the crop and save. No repository setting was changed here. [WordPress.org artwork](../../wordpress-org-assets/README.md) stays separate. All promotional artwork is excluded from the installable ZIP; release status is unchanged.
