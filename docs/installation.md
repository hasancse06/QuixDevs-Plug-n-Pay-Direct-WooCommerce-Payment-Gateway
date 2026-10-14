# Installation and isolated verification

The local archive is a **release candidate pending recorded approvals** for the existing production product. Mocked processor tests are a verification boundary, not its classification rationale; see the concrete conditions in [release status](github-release-readiness.md). The canonical plugin directory is `quixdevs-plugnpay-direct/`, not the repository workspace name with `-github`.

## Prerequisites

Use PHP 8.2+ as the intended baseline and WooCommerce providing `WC_Payment_Gateway_CC`. Only the recorded combinations in [testing](testing.md) have been exercised. Classic Checkout is the implemented checkout architecture; Checkout Blocks and optional Subscriptions/Pre-Orders integrations are not verified support claims.

Direct-card operation requires HTTPS, a properly configured merchant account and the server verification constant. Main payments/refunds retain KYD; new payments reject unsupported order currencies and nonpositive totals. [Configuration](configuration.md).

## Local candidate

From the repository root:

```sh
python3 tools/build-release.py
```

The checksum command must be run from the artifact directory because the checksum contains a relative ZIP name:

```sh
cd dist/candidate
shasum -a 256 -c quixdevs-plugnpay-direct.zip.sha256
unzip -t quixdevs-plugnpay-direct.zip
```

Review `package-manifest.json`: classification, bytes, SHA-256, runtime file hashes and unresolved gates remain explicit. Build tooling needs Python and PHP CLI; customer runtime needs neither Composer nor development dependencies. The archive contains the gateway, local PHP includes, runtime assets/language index, readme and required upstream notices. Tests/docs/vendor/CI/tools/promotional assets are excluded.

Install only on a disposable WordPress/WooCommerce site with synthetic products/accounts/orders and **all outbound HTTP intercepted**. Use Plugins → Add Plugin → Upload Plugin, choose the candidate ZIP, install and activate. Activate one gateway copy; both original and branded variants use `plugnpaydirect`. Review settings and smoke tests before using even a local mock checkout. No original configuration is automatically migrated/deleted.

[Recorded ZIP installation](testing.md) identifies the exact tested artifact. UI upload/browser coverage and actual processor authorization are separate verification scopes.

## Eventual approved release

After every gate is independently satisfied, use `python3 tools/build-release.py --release`. It additionally requires confirmed license text. Attach the reviewed canonical ZIP/checksum to an unpublished draft using the [manual workflow](release-distribution.md). Once a separately authorized human publishes it, merchants can download that asset from Releases and use the same WordPress upload procedure. No uncreated download URL is supplied.
