# Payment request and response mapping

The candidate preserves the original production request protocol for valid KYD orders. Fresh-card checkout and REST send no explicit transaction mode. No `authtype`, settlement flag, currency conversion or new transaction API is introduced.

| Provider parameter | Checkout | REST |
|---|---|---|
| `publisher-name` | `publisher_name` setting | Same |
| `publisher-email` | `publisher_email` setting | Same |
| `publisher-password` | `client_password` setting | Same |
| `card-number` | Validated PAN, inter-digit whitespace removed | Same |
| `card-cvv` | Validated `plugnpaydirect-card-cvc` | Same |
| `card-exp` | Two-digit month + last two year digits | Same |
| `card-name` | Validated holder field | Same |
| `card-amount` | Positive WooCommerce order total in major units | Same |
| `ipaddress` | `REMOTE_ADDR` | Same; no forwarded-header handling |
| `currency` | Literal `KYD`; trusted order currency must match | Same |
| `transacttime` | `gmdate('YmdHis')` | Absent |
| `authhash` | MD5(server key + timestamp + publisher name) | Absent |
| `email` | Billing email | Same |
| `card-address1`, `card-address2` | Billing address lines | Same |
| `card-zip`, `card-city`, `card-state`, `card-country` | Billing fields; state trimmed | Same |
| `shipname` | Shipping first name + space + last name | Same |
| `address1`, `address2`, `zip`, `state`, `country` | Shipping fields | Same |
| `city` | Shipping **postcode** | Same inherited mapping |
| `order_id` | Display order number with leading `#` removed | Same |

The incoming card fields are required strings. Number validation accepts 13–19 digits after permitted inter-digit whitespace removal and requires Luhn validity. Expiry must be a two-digit month and four-digit year in the current month or later. CVV is 3–4 digits; holder input is bounded and rejects control characters; delimiters are safely encoded. These checks stop invalid inputs without changing valid provider names or values.

Checkout first requires HTTPS and a configured server verification key. It resolves the order, rejects unsupported/nonpositive totals and already-paid orders, and validates a fresh card before transport. REST separately requires identity/order permission and literal pending status. Both amounts derive from the server order; no client amount or currency is accepted.

Saved checkout requires a token owned by the current user, belonging to this gateway, plus that token's own verified provider reference. The existing request substitutes `mode=authprev`, `prevorderid=<token reference>` and an additional password field for fresh-card fields. New tokens bind their own safe transaction reference. Legacy tokens without that mapping require a fresh card and re-saving; the user's latest reference is never guessed as a mapping. This remains previous-transaction reuse, not certified provider-vault tokenization. REST has no saved-token inputs. Optional subscription renewals use the shared durable boundary with an explicitly mapped renewal-order reference and explicit KYD; full extension behavior remains unverified.

Requests retain the CGI URL, field order, 45-second timeout, HTTP/1.0 and blocking transport. Each value is encoded independently before concatenation, as the provider [requires](https://docs.plugnpay.com/docs/integration-specifications-documents/remote-client-integration-specification/section-1.-remote-authorization/). TLS certificate verification is explicit and redirects are disabled to prevent forwarding payment bodies. The inherited optional renewal builder now explicitly sends KYD, because [Authorize Previous defaults to USD without a currency](https://docs.plugnpay.com/docs/integration-specifications-documents/remote-api-integration-specification/section-2.-remote-transaction-administration/authorize-previous/); its other financial fields/mode are retained.

HTTP 2xx is followed by component-wise URL decoding. The decoder preserves `resp-code` and `auth-code`, rejects duplicate/array-style/malformed fields, and keeps encoded `&`/`=` inside values. Charge success requires exact `FinalStatus=success` and a safe nonempty provider `orderID`; only then does WooCommerce receive `payment_complete(orderID)`. A malformed reply leaves the order unpaid and requests merchant reconciliation. A definite bad-card decline permits another explicit attempt. Pending, problem/fraud, malformed and transport results remain durably uncertain; no order completion or automatic retry occurs. Approved state/reference are saved before local completion. A changed total/currency/gateway or refund reference during HTTP requires merchant reconciliation rather than completing changed accounting.

Authorized REST replies use completed HTTP200 with retained legacy body codes, and numeric fragments are restricted to selected safe financial fields. Transport failure no longer accesses `WP_Error` as an array. A durable shared per-order journal blocks overlapping/replayed operations; uncertain outcomes require [merchant reconciliation](reconciliation.md). [REST compatibility](rest-api.md) explains the authenticated cutover; [refunds](refunds.md) explains true/WP_Error and one-return behavior.

The shared per-order journal uses atomic insert/compare-and-swap, with no automatic expiry. Manual reconciliation is additionally serialized across database connections and guarded against same-process reentrancy. There is no invented provider query API and no automatic financial retry.

The provider's [verification-hash page](https://docs.plugnpay.com/docs/general-documents/security-administration/verification-hash/) differs from the supplied hash input order. The production-reported input order is retained pending merchant-specific verification. [Authorize Previous](https://docs.plugnpay.com/docs/integration-specifications-documents/remote-api-integration-specification/section-2.-remote-transaction-administration/transaction-administration---authorize-previous/) and contemporary stored-card requirements also require merchant confirmation before changing financial behavior.
