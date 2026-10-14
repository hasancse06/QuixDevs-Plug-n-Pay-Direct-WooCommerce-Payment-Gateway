# Targeted provenance and replacement options

Md Abu Hasan's ownership statement and GPL-2.0-or-later authorization are accepted for applicable independently written code. No rewrite, feature removal or aggregate GPLv3 change is required merely because common helper code resembles another WordPress plugin. This gateway's actual processor is Plug'n Pay; no Stripe integration is being added.

The [licensing review](licensing-review.md) identifies exact raw similarities and their limits. If protected third-party expression is established through a focused provenance review, preserve its actual notices/license and resolve only that component. Options are an applicable separate permission, a compatible combined distribution grant approved by the maintainer, or an independently written replacement of the specific expression. No replacement is claimed as already implemented, and no automatic ownership conclusion follows from normalized similarity percentages.

| Component, if a replacement becomes necessary | Required behavior |
|---|---|
| Gateway / bootstrap | Same gateway/settings identifiers, classic fields, merchant credentials, provider URL/fields/amount/hash semantics, valid completion references and reviewed REST migration |
| Abstract gateway / helpers | Required order metadata, safe notices, saved-method interfaces, return URL and actual extension hooks; use supported WordPress/WooCommerce APIs |
| Customer / saved-card helpers | Preserve data keys and legitimate transaction-reference behavior; never invent a vault API or persist PAN/CVV |
| Currency/fee helpers | Preserve only applicable behavior and data keys; use public protocol and reviewed specifications rather than copying unrelated processor rules |
| Logger / exception | Keep callable interfaces and fixed safe diagnostics; small independently written equivalents are feasible if required |
| Subscriptions / pre-orders | Establish legitimate production workflows and hooks before changing incomplete historical branches; do not silently remove optional functionality |
| Network assets | Use original artwork, maintainer-owned assets or separately licensed graphics while preserving optional-icon behavior |

Write any needed replacement from public API/protocol documentation and an agreed behavior specification, with author/provenance and explicit license records. Do not call work by contributors who have read the source certified clean-room development. Regression tests and isolated WordPress/WooCommerce posts/HPOS checks should verify behavioral/data compatibility before replacing a component. Intercept processor HTTP; no live charges are needed.

Retain refunds, saved methods, optional production workflows and the REST endpoint during license review. Preserve Plug'n Pay behavior and provider limits from [official Return documentation](https://docs.plugnpay.com/docs/integration-specifications-documents/remote-api-integration-specification/section-2.-remote-transaction-administration/return/) and the actual [payment mapping](payment-flow.md).

The owner-authorized code can retain GPL-2.0-or-later. Preserve additional verified third-party obligations if any are established; do not impose them as conclusive solely from common code patterns. [Scoped external-source notice](../THIRD-PARTY-NOTICES.md), [functional comparison](functional-parity.md).
