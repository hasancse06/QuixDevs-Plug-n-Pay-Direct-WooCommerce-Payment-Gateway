# GitHub release checklist

The existing public repository and configured remote are reused. QuixDevs version 1.0.0, owner-authorized GPL-2.0-or-later licensing, source, documentation and branding are prepared for the reviewed community release. No commit, push, tag, draft creation or publication is performed automatically.

## Repository and presentation

- [x] Preserve gateway/settings identifiers and the original source directories.
- [x] Use the supplied QuixDevs logo and ProductClock/StockNotifier banner family, with developer credits and genuine documentation imagery.
- [x] Retain source, tests, CI, Composer lockfiles, documentation and editable artwork; separate installer assets from promotional graphics.
- [x] Exclude local credentials, dependencies, private snapshots, reports and build artifacts through `.gitignore`; untracking current files does not erase their history.
- [x] Include the owner's GPL grant and qualified historical/external-source notices without a blanket authorship claim.

## Final source and artifact review

- [ ] Review actual results in [testing](testing.md), including independent workers and core posts/HPOS/refund accounting; resolve material quality findings.
- [ ] Run the clean-source snapshot/repository checks and review working-tree/index changes. Stage/commit only the concrete reviewed result yourself.
- [ ] Verify the frozen canonical ZIP/checksum against installed files after the last runtime or packaging change.
- [ ] Approve the REST client/backend migration and unmapped saved-token re-save path; never restore anonymous charges for compatibility.
- [ ] Confirm merchant configuration/reconciliation, single-primary database coordination, supported platform scope and any separately authorized provider acceptance.
- [ ] Review the narrow conditional provenance follow-up and preserve any additional obligations actually established.
- [ ] Align [release gates](release-state.json), final notes, version and approved source/tag before release preparation.

## Publish only after approval

Use [GitHub listing](github-listing.md), [branding upload instructions](branding/README.md), [release notes](github-release-notes.md) and [release distribution](release-distribution.md). `.gitignore` does not filter a folder dragged into GitHub's browser uploader; source archives and the WordPress installer are different artifacts.

The manual draft workflow validates immutable source/tooling references, a confirmed existing tag, a unique draft release ID and installer/checksum assets. It supports installation tokens, refuses published/mismatching releases and never rewrites tags or publishes. Inspect the final draft/assets and obtain approval for normal stable publication; no automatic deployment is enabled.
