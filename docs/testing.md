# Executed checks and verification limits

Local execution: **PHP 8.4.13**, PHPUnit **9.6.38**, PHP_CodeSniffer **3.13.6**, WordPress Coding Standards **3.4.1**. Composer development dependencies were installed from the lockfile. No live Plug'n Pay requests, real credentials or real customer data were used.

| Check | Actual result |
|---|---|
| PHPUnit | **25 tests, 127 assertions passed** |
| PHP syntax | All 16 project PHP files passed (11 runtime, four test files, artwork generator) |
| Missing WooCommerce | Safe initializer return passed |
| Missing server verification key | Fails before mocked HTTP; passed |
| Source preservation | Original 16 files and QuixDevs source 22 files unchanged |
| Repository secret policy | Zero findings, including comparison to private inherited value; see recorded final scan |
| Full WordPress standards | **3,858 errors, 328 warnings**, 3,740 fixable; nonzero exit, not clean |
| New REST authorization class standards | Zero errors/warnings under full configured WordPress rules |
| Documentation | Offline links, image dimensions, identity, guarded Postman fields and CI wiring checked |
| Canonical archive layout | In-memory paths/CRC/exclusions checked; no ZIP written |
| Publication/release gates | Expected refusal while seven requirements remain false |
| Composer metadata | Valid; missing-license warning intentionally retained |

## Regression scope

The self-contained mocks load this target plugin only. Tests cover initialization/registration/settings/fields, full fresh-card provider mapping and hash ordering with synthetic server configuration, completion/transaction references, decline/timeout, HTTPS/configuration denial, route/handler permissions, anonymous and cross-customer denial, explicit backend capability requirements, malformed IDs/keys, pending/paid orders, valid/invalid saved tokens, refund mapping and token persistence. No tested token-save path persists full PAN or CVV.

Sequential paid-order repetition is rejected. **Concurrent authorized requests still submit twice.** Characterization cases intentionally reproduce this defect, unsafe refund failure/pending arrays, repeated returns, raw sensitive provider fragments and REST WP_Error transport crashes. Tests asserting these inherited defects are documentation/regression evidence, not successful duplicate prevention, redaction or payment security clearance.

## Reproduce locally

```sh
composer install --no-interaction --prefer-dist
composer lint
composer test
php tests/missing-dependency.php
php tests/missing-key.php
composer secrets
composer docs
composer package-check
composer phpcs
composer release-check
```

`composer phpcs` must currently report inherited findings. `composer release-check` and `python3 tools/build-release.py` must fail while release requirements are unresolved. A local maintainer can additionally run `python3 tools/secret-scan.py --compare-source ../quixdevs-plugnpay-direct` without printing the private value, and `python3 tools/verify-sources.py` to compare both private siblings. CI has no access to those siblings and does not claim source-preservation verification.

## Unavailable or not executed

Actual WordPress/WooCommerce authentication and REST serialization, gateway activation, HPOS, optional extensions, Plugin Check, installed ZIP and processor sandbox/production integration were **not run**. WP-CLI is unavailable and no disposable WordPress/database environment is provisioned. The `WC_VERSION` value in the bootstrap is only a mock branch selector, never an installed version claim. Local PHP 8.2/8.3 tests were not run; the configured 8.2/8.3/8.4 GitHub matrix has not executed on GitHub.

[Plugin Check helper](../tools/plugin-check.sh) requires an explicitly disposable site, WooCommerce, Plugin Check and WP-CLI. It is not a substitute for provisioning/testing them. The README/API render preview is local Markdown rendered with Marked and a temporary headless Chrome profile, not a hosted GitHub run or WordPress screenshot.

## Evidence

[PHPUnit](verification/phpunit.txt), [syntax](verification/lint.txt), [missing dependency](verification/missing-dependency.txt), [missing key](verification/missing-key.txt), [source preservation](verification/source-preservation.txt), [PHPCS summary](verification/phpcs-summary.txt), [full PHPCS JSON](verification/phpcs.json), [final scan](verification/secret-scan.txt), [documentation checks](verification/docs.txt), [layout checks](verification/package-layout.txt), [release refusal](verification/publication-gate.txt), [build refusal](verification/build-gate.txt), [development dependency licenses](verification/development-licenses.json).

The previous private report's 22 tests/124 assertions and older standards counts are historical, not these results. No standards rules were globally disabled to obtain a green result.
