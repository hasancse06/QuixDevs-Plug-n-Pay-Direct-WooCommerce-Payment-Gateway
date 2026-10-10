# Contributing

Keep this repository private until rights and critical payment release conditions are resolved. Use the existing checkout for review; publication requires the recorded legal and technical conditions, not just passing mocked tests.

```sh
composer install
composer lint
composer test
php tests/missing-dependency.php
php tests/missing-key.php
composer secrets
composer docs
composer package-check
composer repository-check
composer phpcs
composer release-check
```

`composer phpcs` currently reports inherited failures; no global rule exclusions hide them. `composer release-check` must currently fail. Package-check validates an in-memory canonical layout and writes no release ZIP. Tests mock every provider request and require no merchant account, private baseline or credentials. The synthetic key in the isolated test fixture is not a runtime default.

Keep financial request/mode/currency/reference changes separate from formatting and security changes. Preserve required REST support and document any caller-contract change. Add focused regression tests for security corrections; do not turn a demonstrated vulnerability into a claimed security pass. Run real WP/WC integration only on a disposable site/database with all processor calls intercepted. Never modify the two sibling source directories, copy private snapshots, include unlicensed assets or commit real customer/card data.

Pull requests should describe the user-facing change, protocol/client impact, actual validation and remaining risks. Public review is not authorized until upstream redistribution rights are established.
