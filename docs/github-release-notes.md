# QuixDevs Plug'n Pay Direct v1.0.0 — release notes draft

**LOCAL CANDIDATE ONLY. No published release, production approval or downloadable ZIP exists.**

This candidate adds QuixDevs presentation, merchant setup instructions, an actual-contract REST reference, safe non-sending examples, guarded Postman documentation, original branding assets, development CI and allowlisted canonical packaging. Gateway ID `plugnpaydirect`, classic checkout, provider field mapping, amount/KYD behavior, transaction references, saved-payment request and refunds are retained.

The checkout verification secret is removed and replaced by required server configuration. REST now requires HTTPS, WordPress identity, the existing shared key and order authorization. Embedded-key-only applications must migrate to a trusted backend; they are intentionally denied in this copy. Invalid or foreign saved tokens are rejected before processor submission.

Local PHP 8.4.13 mocked tests are the only executed runtime evidence; see [current test results](testing.md). WordPress/WooCommerce/provider integration, HPOS, Plugin Check and ZIP installation have not been verified. Refund return semantics, payment locking/replay, REST HTTP 123/transport/raw output and input/logging weaknesses remain blockers. [Security review](security.md).

Once all conditions are independently cleared, attach `quixdevs-plugnpay-direct.zip` with canonical root `quixdevs-plugnpay-direct/`. Users will download that attachment from the approved Releases page and use Plugins → Add Plugin → Upload Plugin, then configure HTTPS and server/gateway settings. Do not publish a generic source archive as an installer. No broken future download link is provided.

Derived from PatSaTECH's supplied 1.3.2 plugin; license confirmation is outstanding. [Upstream notices](../UPSTREAM-NOTICES.md), [license review](licensing-review.md), [complete readiness](github-release-readiness.md).
