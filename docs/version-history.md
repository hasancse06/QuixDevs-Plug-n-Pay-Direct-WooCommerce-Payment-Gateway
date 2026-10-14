# Public repository and version history

Read-only audit date: 2026-10-15 (Asia/Dhaka). The repository is already public; it is not awaiting its first source upload. No remote setting, commit, branch, tag, release or production installation was changed during inspection.

| Evidence | Observed value |
|---|---|
| Existing remote | `git@github.com:hasancse06/QuixDevs-Plug-n-Pay-Direct-WooCommerce-Payment-Gateway.git` |
| Public repository | [QuixDevs Plug'n Pay Direct WooCommerce Payment Gateway](https://github.com/hasancse06/QuixDevs-Plug-n-Pay-Direct-WooCommerce-Payment-Gateway) |
| GitHub visibility/default branch | Public / `master` |
| Public branch HEAD | `faf606190e99637777eba280fc0e777d68e1f0b1` |
| Local `master` / `origin/master` at inspection | Same commit |
| Public tags | None returned by GitHub API |
| Public releases/drafts | No public releases returned; no published release/tag history found |
| GitHub license detection | Repository metadata `license: null`; license endpoint HTTP 404 at inspection |
| Preserved original production-source header | `wooplugnpaydirect/wooplugnpaydirect.php`: 1.3.2 |
| Public QuixDevs source header/runtime constant | 1.0.0 in all three available commits |
| Candidate branded slug / main file | `quixdevs-plugnpay-direct` / `quixdevs-plugnpay-direct.php` |

The local public-history commits are `39cebaf`, `0edaf52` and `faf6061`, all dated 2026-10-10. Each declares the QuixDevs header/runtime version 1.0.0. No LICENSE/COPYING grant appeared in those commits. Public API observations used the existing repository's [metadata](https://api.github.com/repos/hasancse06/QuixDevs-Plug-n-Pay-Direct-WooCommerce-Payment-Gateway), [branches](https://api.github.com/repos/hasancse06/QuixDevs-Plug-n-Pay-Direct-WooCommerce-Payment-Gateway/branches), [tags](https://api.github.com/repos/hasancse06/QuixDevs-Plug-n-Pay-Direct-WooCommerce-Payment-Gateway/tags) and [releases](https://api.github.com/repos/hasancse06/QuixDevs-Plug-n-Pay-Direct-WooCommerce-Payment-Gateway/releases). An unauthenticated release listing cannot reveal inaccessible private drafts; no such private state is asserted.

## Exact recommendation

Retain **1.0.0** for the existing QuixDevs product's first tagged community release: its public same-brand history already declares 1.0.0 and has no higher tags/releases. Keep the main PHP header, runtime constant, `readme.txt` Stable tag, changelog, package metadata and eventual approved `v1.0.0` tag consistent. No tag is created or moved automatically.

Original 1.3.2 belongs to the separately named `wooplugnpaydirect.php` source. WordPress plugin identity includes the directory/main-file basename; this candidate is installed under the different `quixdevs-plugnpay-direct/quixdevs-plugnpay-direct.php` basename. This is a reviewed manual replacement, not an automatic in-place update that overwrites the old plugin as version 1.0.0. Activate only one copy because both use gateway ID `plugnpaydirect` and the same merchant settings. Preserve the original folder and review backups/staging/client migration before switching.

Actual deployed-site metadata and any private custom updater were not independently inspected. If an operator intends an in-place update under the original basename or a custom updater compares those histories, 1.0.0 is numerically below 1.3.2; that separate upgrade path must use a higher version such as 1.3.3 (or an agreed major migration) and align all metadata. Do not silently rename the ZIP into the old basename or claim automatic legacy updating.

The final stable release still requires reviewed security/client behavior and accurate license scope. Production-use history is distinct from independent test coverage; mocked processor replies do not turn this existing product into a fictional experiment. [Release process](release-distribution.md), [licensing scope](licensing-review.md).
