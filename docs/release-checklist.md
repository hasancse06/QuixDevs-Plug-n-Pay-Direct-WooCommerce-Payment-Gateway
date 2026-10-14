# Release approval checklist

The repository is already public and the plugin has maintainer-reported production history. Local automated tests deliberately intercept provider HTTP; that practice does not classify the product as experimental or development-only. [Final report](github-release-readiness.md) records the frozen installer and actual verification results.

Before approving a stable release:

- Confirm version 1.0.0 across header/runtime constant, Stable tag, changelog and the eventual approved tag; do not overwrite the distinct legacy basename as an automatic downgrade.
- Keep the owner-authorized GPL-2.0-or-later text/metadata and historical notices; review the narrow conditional provenance observations without assuming blanket third-party ownership.
- Review current unit, independent-worker, posts/HPOS, refund-accounting, syntax, standards, Plugin Check, packaging and workflow evidence; address material remaining findings instead of hiding them.
- Verify the final canonical ZIP installs/activates and matches its checksum/file manifest. Re-run after any runtime or license/package change.
- Approve the existing REST client's identity/order-authorization, HTTP 200/error and safe-output migration. Legacy unmapped saved tokens require explicit re-entry/re-save.
- Verify merchant HTTPS, credentials/hash configuration, database topology and reconciliation procedures. Single-primary coordination and no automatic uncertain-attempt retry are operational requirements.
- Limit support claims to exercised core platform combinations; optional integrations and Checkout Blocks remain outside verified support. Conduct any real merchant acceptance separately with authorization.
- Review the exact intended source commit, existing tag, draft release identity and installer/checksum before executing release automation. Never move tags or overwrite a published release.

The implemented shared journal, refund contract, order-drift checks, known-sensitive-value protection, per-value request encoding and completed REST responses have regression coverage. Do not clear [machine-readable gates](release-state.json) solely because mocks are green, or leave already-fixed defects listed as unresolved. Manual release preparation never publishes automatically; a public release/deployment requires approval.
