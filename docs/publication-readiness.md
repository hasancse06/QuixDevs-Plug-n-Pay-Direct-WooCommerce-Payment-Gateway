# Publication and deployment status

| Item | Status / evidence |
|---|---|
| Existing GitHub repository | Already public; current QuixDevs history/version recorded in [version history](version-history.md) |
| Owned-code license | GPL-2.0-or-later authorized by Md Abu Hasan; full text/metadata included; narrow conditional provenance follow-up retained |
| Runtime installer | Canonical ZIP/checksum prepared; exact frozen bytes and installation results in [release report](github-release-readiness.md) |
| Local quality/security checks | Results and remaining findings in [testing](testing.md); implemented fixes include worker serialization, refund correctness, drift handling and sensitive-data protection |
| Stable GitHub release | Awaiting final recorded approvals and an explicitly authorized release action; no automatic publication |
| Production cutover | Requires merchant configuration/reconciliation procedures and client migration acceptance; live settlement is not inferred from local mocks |
| WordPress.org submission | Separate review of metadata, quality findings and applicable submission requirements; not automatically submitted |

Both preserved source folders remain unchanged. Real core WordPress/WooCommerce posts/HPOS and independent-worker tests complement mocked processor outcomes; the maintainer's production-use history is acknowledged separately. The plugin is not classified as development-only merely because external payments are mocked during automated tests.

Do not reopen repaired defects as generic blockers: the shared durable journal, WooCommerce refund result handling, completed REST HTTP 200, per-value encoding, component parsing and safe diagnostics are implemented. Remaining acceptance items are concrete: client/backend migration, merchant/provider configuration and live acceptance when separately authorized, optional-extension scope, two documented legal-notice Markdown warnings, database topology and the narrow provenance follow-up. Machine gates must match actual evidence/approval. [Release state](release-state.json), [release checklist](release-checklist.md), [distribution process](release-distribution.md).
