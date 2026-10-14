# Production-to-QuixDevs comparison

The comparison uses preserved production `wooplugnpaydirect/`, not an earlier preparation candidate. The current product remains a Plug'n Pay Direct gateway, with its existing public QuixDevs version 1.0.0 and canonical slug `quixdevs-plugnpay-direct`.

| Retained contract | Current behavior |
|---|---|
| Gateway/settings | `plugnpaydirect`; existing `woocommerce_plugnpaydirect_settings` and enabled/title/description/publisher_name/publisher_email/client_key/client_password/cardtypes/saved_cards fields |
| Provider integration | `https://pay1.plugnpay.com/payment/pnpremote.cgi`, existing field names, major-unit amounts and KYD; no invented hosted/vault endpoint or settlement mode |
| Fresh-card checkout | Classic form, card fields and existing hash order retained; server configuration replaces the embedded key |
| Saved-card authorization | `authprev` and provider previous-transaction reference retained; selected tokens now carry their own verified reference |
| REST | POST `/wp-json/wc/v3/plugnpay-payment`, same eight input names and normal business body codes/messages |
| Payment recording | Verified provider success stores transaction ID through WooCommerce payment completion; diagnostic authorization codes are omitted from notes |
| Full/partial refunds | `mode=return`, original transaction reference, requested amount and KYD; provider permits one Return per original transaction, including a partial return |
| Merchant history | Existing settings/data keys remain; protected original folders are unchanged |

Security changes intentionally affect unsafe or ambiguous behavior. REST now requires authenticated WordPress identity, the retained shared key and order ownership or explicit backend/order-edit permissions. The embedded-key-only client needs the [documented migration](api-authentication.md); anonymous charging is not restored. Normal business bodies explicitly use HTTP200. The old PHP response object declared123; its historical wire status was not captured. New permission/input/journal failures use WP_Error status data, and unrestricted provider fragments are reduced to safe financial fields.

A durable shared journal prevents overlapping requests from submitting twice across workers. Uncertain/pending outcomes never authorize automatic retries; confirmed bad-card decline may permit a new explicit attempt. Merchant reconciliation is capability checked, records bounded evidence, serializes local completion and sends no provider request. Approval is persisted before WooCommerce completion, so a failed local save does not permit another charge. Order-total/currency/reference drift after approval requires review rather than fabricated local success.

Refunds now follow WooCommerce's `true|WP_Error` contract. Pending is uncertain, not a successful refund. Positive amount, paid gateway order, KYD, original reference and one-Return protections are enforced; WooCommerce accounting is completed only after confirmation. Component-wise request encoding, explicit provider TLS/no redirects, typed card/order validation and sensitive-output protection close the identified security gaps without changing valid financial units or settlement flags.

New saved tokens bind `_quixdevs_plugnpay_reference` to the selected card. Legacy tokens without that verified mapping require card re-entry/re-save; the latest user-level `trans_id` is not guessed as their card. That old key remains stored for historical compatibility, but is not used to guess a checkout or renewal card. Optional renewals require an explicitly mapped order reference; their complete extension lifecycle remains unverified.

QuixDevs class/hook/text-domain naming differs from historical PHP symbols, so external custom integrations need review. Promotional branding uses the supplied logo; unverified upstream network graphics are excluded. Metadata includes the owner-authorized GPL-2.0-or-later grant, with historical notices and a narrow conditional provenance follow-up.

[Payment flow](payment-flow.md), [refunds](refunds.md), [REST](rest-api.md) and [testing](testing.md) provide current behavior/evidence. No Checkout Blocks, provider-vault, optional-extension certification or live-settlement claim is made.
