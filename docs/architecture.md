# Distribution architecture

Repository root contains the canonical main PHP file and includes/assets/languages plus development documentation/tooling. The workspace suffix -github never becomes the plugin slug or archive root. Runtime remains the supplied class-based gateway: `Gateway`, `Rest_Gateway`, abstract `Payment_Gateway`, five helpers and two optional integration classes, under `QuixDevs\PlugNPayDirect`.

The new `Rest_Authorization` class performs caller/order permission checks. It does not build processor requests or replace the payment controller. `plugins_loaded` loads it before the existing source loaders. The route and direct handler use the same check. Checkout loads a required server constant and retains its original hash formula. Valid saved-token requests continue through the same authprev path.

The rest of the main payment, refund, response parsing, transaction recording and saved-reference implementation is retained. Two helper/subscription signatures remove defaults PHP 8 already treated as required; no argument order or financial calculation changed. No migrations, custom tables, automatic capability grants, Composer runtime loader, new route or historical adapter is added. Packaging uses an explicit allowlist and canonical quixdevs-plugnpay-direct root.
