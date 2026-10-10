# Distribution security review

No live charge, refund, merchant login, database change or credential rotation was performed. This is a source/mocked-test review, not PCI certification or complete security clearance.

## Targeted corrections implemented only in the GitHub copy

- Removed the embedded checkout verification secret before writing the target file. It is now a required server constant, with no default. Non-HTTPS/missing/empty-key checkout stops before network submission.
- Added an actual REST permission callback and repeated it inside the handler. HTTPS, WordPress identity, constant-time shared-key comparison, positive scalar order ID and order ownership or explicit backend/order-edit capabilities are required.
- Ordinary authenticated users/managers cannot execute other customers' or guest-order payments merely by possessing the embedded key. No capabilities are granted automatically.
- Missing, wrong-gateway or foreign saved tokens stop before submission. An owned token with no saved transaction reference also stops; existing valid authprev request mapping remains.
- Permission/configuration errors contain no supplied key/card values. Repository pattern scanning plus local comparison against the old secret checks all distributable text files; actual values are never printed.

The existing mobile application used only an embedded key. That contract intentionally breaks in this copy and requires the [documented backend/authentication migration](api-authentication.md). Both source directories remain unchanged.

## Critical unresolved release conditions

| Finding | Evidence / consequence | Disposition |
|---|---|---|
| No shared payment lock/idempotency | Two authorized overlapping pending requests can submit twice; Classic and REST do not share attempts | Reproduced in mocks; production/public release blocked |
| Refund API result mismatch | Truthy associative failure arrays; pending reported as success; repeated returns possible | Financial logic retained; separate scoped fix required |
| REST HTTP 123 | Authorized handler asks for nonfinal informational-class status; actual server/client behavior not verified | Preserved; release blocked |
| REST timeout failure | WP_Error transport object is later used as an array and throws | Reproduced; release blocked |
| Card input validation | REST bypasses checkout validator; request values not encoded individually | No request/protocol redesign; release review needed |
| Sensitive output/logging | Numeric provider fragments remain in REST replies; raw MErrMsg can enter notes; logger accepts arbitrary text | No safe-output/redaction guarantee; release blocked |
| Saved/reference model | Tokens use fabricated identifiers and user-level latest trans_id, not provider-issued per-card vault tokens | Preserved; not advertised as verified vault tokenization |
| Optional integrations | Pre-orders loading and absent API/method references remain | Source retained, integrations unverified |
| Runtime verification | Real WP authentication, WooCommerce calls, HPOS and ZIP activation not exercised | Required evidence outstanding |

No CVV persistence was found in the tested card-save paths, and full PAN is not stored by those token methods. That does not establish infrastructure compliance or arbitrary-log safety. Merchant passwords remain in ordinary WC settings. The new checks do not justify publicly deploying this candidate while these defects remain. No needed payment route was removed as a substitute for correction.
