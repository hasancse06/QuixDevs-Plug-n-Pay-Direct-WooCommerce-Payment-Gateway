# QuixDevs Plug'n Pay Direct v1.0.0

Unpublished release notes for the existing public QuixDevs repository. Version 1.0.0 matches its available public history; no higher public tag/release was found. The legacy 1.3.2 plugin has a different basename and requires a reviewed manual replacement, not an automatic downgrade.

This release preparation retains the Plug'n Pay CGI endpoint, gateway/settings IDs, classic Checkout, transaction recording, full/partial Return operation and POST /wp-json/wc/v3/plugnpay-payment. It adds the owner-authorized GPL-2.0-or-later grant, full license text, QuixDevs/Md Abu Hasan credits and preserved historical/source notices.

Payment safeguards include a durable database journal shared across workers, correct WooCommerce refund true/WP_Error results, one-Return protection, individually encoded fields, verified TLS without redirects, safe response references, sensitive-output protection and approval-aware order-drift handling. Manual merchant reconciliation is capability checked and serialized, without an invented processor status API or automatic financial retry.

Client migration requires approval: the retained REST payload now needs authenticated WordPress identity/order scope; authorized business JSON keeps its integer codes under explicit HTTP200 (the old PHP object declared123; historical wire status was not captured), and arbitrary diagnostic fragments are omitted. New saved methods carry their own verified reference; legacy unmapped tokens need fresh-card re-saving. Optional renewals require an explicitly mapped order reference and do not guess the customer's latest card.

The canonical installer is **quixdevs-plugnpay-direct.zip**, with its SHA-256 sidecar. Candidate artifacts live under `dist/candidate/`; approved `--release` builds require the recorded approvals and complete licensing files. The final report records exact bytes, digest, installed-file equality and actual unit/platform/worker/quality results. Source archives, tests, tooling, dependencies and promotional artwork are excluded from the installer.

The maintainer reports production use. Automated provider responses are intercepted; no actual merchant authorization/settlement or PCI certification is claimed. Classic Checkout is implemented; Blocks and full optional-extension certification are not. Single-primary WordPress database coordination, merchant/hash setup, client cutover and the narrow conditional provenance observations remain documented. Full runtime WPCS is clean; Plugin Check retains only two legal-notice Markdown warnings. See the complete quality inventory and final release report.

No commit, push, tag, draft creation, public release, deployment or live payment was executed during preparation. [Final report](github-release-readiness.md), [tests](testing.md), [installation](installation.md), [client migration](api-authentication.md), [reconciliation](reconciliation.md).
