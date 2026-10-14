# Gateway architecture

The bootstrap loads namespaced gateway/helper classes only when WooCommerce is present. The REST singleton initializes once during `init` and registers the existing route during `rest_api_init`. Customer runtime requires no Composer/autoloader or external payment SDK.

| Boundary | Responsibility |
|---|---|
| `Payment_Input` | Typed card fields, trusted positive KYD amounts, payment context and reference shape |
| `Rest_Authorization` | HTTPS, WordPress identity, constant-time client key and per-order authority |
| `Provider_Request` | Encode individual values while preserving Plug'n Pay field names/order |
| `Provider_Response` | Decode components without field injection; validate approval/reference and suppress known sensitive echoes |
| `Payment_Attempts` | Durable shared financial ownership/outcomes and serialized merchant reconciliation |
| `Attempt_Command` | Explicit capability-checked WP-CLI inspection/reconciliation with no provider retries |

Each financial request claims a unique per-order journal in the authoritative options table. Conditional SQL updates fence competing workers; no cache-only lock, automatic timeout expiry or stale-worker takeover is used. An uncertain result remains blocked until an authorized merchant reconciles the exact attempt after checking provider records and stopping the old worker. Approved outcomes are persisted before WooCommerce completion. Reconciliation additionally uses a connection-scoped database advisory lock plus an in-process reentrancy guard around fresh-order loading and completion hooks.

This coordination assumes one authoritative primary database and ordinary connection affinity for the relevant WordPress options/order writes. Asynchronously routed replicas or independently writable/sharded option stores do not provide the same guarantee. The journal is a bounded payment-control record, not a store of card fields, credentials or unrestricted debug bodies.

Immutable amount/currency/reference snapshots detect order drift before transport and after approval. A changed approved order keeps its durable financial outcome and requires merchant review instead of a second request or automatic accounting. Refunds use the same journal and the provider's one-Return limit. New saved-card tokens contain their own verified previous-transaction binding; legacy unmapped tokens require re-save.

Core order operations use WooCommerce CRUD and are tested with posts and HPOS storage. Optional helper/subscription/pre-order paths still contain incomplete APIs/direct postmeta, so core tests alone do not establish whole-plugin compatibility. No Checkout Blocks integration is included.

The existing public repository retains source, tests, documentation, lockfiles and branding. The installer uses a separate runtime allowlist with one canonical plugin directory. Source/tooling revisions are independently validated by manual draft automation; release publication requires approval. [Source comparison](functional-parity.md), [testing](testing.md), [release process](release-distribution.md).
