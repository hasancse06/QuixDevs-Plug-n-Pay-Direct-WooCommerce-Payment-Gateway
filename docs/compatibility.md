# Compatibility and limitations

| Area | Evidence |
|---|---|
| Original production use | Maintainer reported; versions and merchant account not supplied |
| Distribution PHP | Intended minimum 8.2; local CLI 8.4.13; CI matrix 8.2/8.3/8.4 configured but not run on GitHub |
| WordPress/WooCommerce | No real runtime booted/installed for this copy; stub WC_VERSION selects modern API branch only |
| Classic Checkout | Existing fields/request/completion tested with synthetic fixtures; new HTTPS/server-key guard |
| REST client | Existing URL/fields retained; new WordPress identity/order permission is a breaking change to embedded-key-only clients |
| REST transport | Legacy authorized status 123/raw fragments/timeout error retained; public deployment unavailable |
| HPOS | Main path uses modern CRUD; helper/optional classes use raw postmeta; not declared or verified compatible |
| Checkout Blocks | No implementation; unsupported claim must not be made |
| Saved methods | Existing authprev/source model retained; ownership/gateway/reference guard added; no verified provider-vault claim |
| Subscriptions/pre-orders | Source retained, incomplete references/loading issues; not verified integrations |
| Currency | Main payments/refunds still send KYD; no silent conversion or configurable multicurrency added |
| Icons/assets | Unlicensed upstream network PNGs omitted; existing file-existence checks skip missing optional icons |
| Runtime dependencies | No Composer/autoloader requirement on customer servers; development tooling stays outside ZIP |

No Tested up to WordPress/WooCommerce value is invented. The old upstream declarations are historical, not a current validation result. Real authentication, plugin installation, refund consumers, HPOS and optional-plugin interactions require isolated tests before public claims. Absence of sandbox credentials is not treated as an automatic failure of unchanged production-reported behavior; material changes require proportional regression evidence.
