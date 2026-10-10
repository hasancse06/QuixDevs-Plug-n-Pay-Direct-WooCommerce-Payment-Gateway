# Supplied implementation audit

The supplied production plugin is Plug'n Pay Direct Gateway for WooCommerce 1.3.2, attributed to PatSaTECH. The existing QuixDevs source is a mechanical namespace/branding adaptation. Both private source trees remain unchanged; `docs/source-hashes.json` (local only) support the local preservation check.

The gateway registers `plugnpaydirect`, uses classic credit-card fields, sends fresh-card requests to the existing Plug'n Pay remote CGI, completes orders using returned `orderID`, and retains full/partial refunds. Saved methods reuse a previous transaction through `authprev`; they are not verified independent vault tokens. Optional Subscriptions/Pre-Orders code exists but is not certified.

The source has an embedded checkout verification key, key-only REST access, no shared charge/refund lock, a fragile response parser, status 123 REST responses, raw provider fragments, unsafe refund return arrays and missing optional APIs. Production-use history does not establish safe public-distribution behavior. Source card-network artwork has no supplied redistribution terms and is omitted from this distribution.

See [financial mapping](payment-flow.md), [distribution differences](functional-parity.md), [security](security.md) and [licensing](licensing-review.md). No original private snapshot or credential is bundled here.
