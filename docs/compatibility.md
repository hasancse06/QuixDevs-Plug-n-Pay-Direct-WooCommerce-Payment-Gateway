# Compatibility and boundaries

| Area | Current scope |
|---|---|
| Existing product | Maintainer-reported production use is acknowledged; it is separate from independently executed checks on the current source |
| WordPress / PHP requirements | Metadata minimum WordPress 6.2 and PHP 8.2; WooCommerce is required. Executed versions/results are recorded in [testing](testing.md) |
| Verified local combination | WordPress 6.9 / WooCommerce 10.9.0 / PHP 8.4.13, isolated with synthetic processor responses; configured CI versions are not claimed as executed hosted runs |
| Classic Checkout | Existing form/request/completion architecture, with typed validation, server-key/HTTPS guards, shared attempts and verified approval |
| REST clients | Same route/eight inputs and normal integer business codes; WordPress identity/order authorization, completed HTTP 200 and restricted output require documented client cutover |
| HPOS | Core posts/HPOS order, concurrent-worker and accounting checks are documented; optional direct-postmeta helpers prevent a blanket compatibility declaration |
| Checkout Blocks | No Blocks payment-method implementation; unsupported |
| Saved cards | New tokens bind their own verified references; unmapped legacy tokens require card re-entry/re-save; no invented provider-vault API |
| Subscriptions / pre-orders | Code retained, guarded renewal submission explicitly uses KYD; full optional-plugin lifecycles and legacy renewal-reference behavior are not certified |
| Currency | Trusted positive KYD amounts; no silent conversion or general multicurrency support |
| Refunds | Confirmed `true` or WP_Error, full/partial amount mapping and one Return per original transaction; pending/timeout require reconciliation |
| Database topology | Shared journal/advisory locks assume one authoritative primary database and connection affinity; no active-active multi-primary guarantee |
| Assets/dependencies | Runtime excludes development packages/promotional art; unverified network graphics remain omitted |
| Custom PHP integrations | QuixDevs namespaces/hooks differ from historical names; stored gateway/settings identifiers remain |

Official [HPOS guidance](https://developer.woocommerce.com/docs/features/orders/high-performance-order-storage/recipe-book/) explains why direct postmeta paths can bypass the active datastore. Core coverage does not certify every optional integration. No broad historical migration is introduced to change a label.

Platform tests exercise real WooCommerce data stores and mocked processor outcomes. They cannot establish merchant account configuration, actual settlement, PCI certification, external mobile-backend behavior or a browser/TLS matrix. [Compatibility testing guidance](https://developer.woocommerce.com/docs/extensions/best-practices-extensions/compatibility), [security/PCI responsibilities](security.md), [final release evidence](github-release-readiness.md).
