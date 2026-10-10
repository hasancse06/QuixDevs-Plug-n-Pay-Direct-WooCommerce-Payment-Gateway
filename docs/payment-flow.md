# Payment request and response mapping

The distribution candidate retains the source's financial request construction. Fresh cards send no explicit transaction mode. Do not add `mode=auth`, `authtype=authpostauth`, rename `order_id` to `orderID`, or change hash ordering during rebranding.

| Provider parameter | Checkout | REST |
|---|---|---|
| `publisher-name` | `publisher_name` setting | Same |
| `publisher-email` | `publisher_email` setting | Same |
| `publisher-password` | `client_password` setting | Same |
| `card-number` | PAN with inter-digit whitespace removed | Same |
| `card-cvv` | `plugnpaydirect-card-cvc` | Same |
| `card-exp` | Supplied month + last two year digits | Same |
| `card-name` | Holder field | Same |
| `card-amount` | WooCommerce order total, major units, no conversion | Same |
| `ipaddress` | `REMOTE_ADDR` | Same; no forwarded-header handling |
| `currency` | Literal `KYD` | Literal `KYD` |
| `transacttime` | `gmdate('YmdHis')` | Absent |
| `authhash` | MD5(key + time + publisher name), required server-configured key (no default) | Absent |
| `email` | Order billing email | Same |
| `card-address1`, `card-address2` | Billing address lines | Same |
| `card-zip`, `card-city`, `card-state`, `card-country` | Billing fields; checkout trims state | REST does not trim state |
| `shipname` | Shipping first name + space + last name | Same |
| `address1`, `address2`, `zip`, `state`, `country` | Shipping fields | Same |
| `city` | Shipping **postcode** in both main payment paths | Same retained mapping |
| `order_id` | Display order number with leading `#` removed | Same |

Saved-card checkout first rejects missing/foreign/wrong-gateway tokens or missing transaction references, then substitutes `mode=authprev`, `prevorderid=user meta trans_id`, and an additional duplicate password field for fresh-card fields. No REST saved-token fields are implemented. Subscriptions build a separate request in the abstract gateway, including `publisher_email` with an underscore and shipping city correctly; this optional path is not covered by the standard-payment parity claim.

All provider requests use the original URL, raw concatenated body, 45-second timeout, five redirects, HTTP/1.0 and blocking transport. Raw values containing `&`, `=`, `+` or `%` can affect parsing/encoding; this is retained and documented, not silently repaired.

HTTP 2xx alone is insufficient for payment completion. The original parser decodes the body then splits key/value pairs. `FinalStatus=success` invokes `payment_complete(orderID)` and records the authorization reference. `badcard`, `fraud`, `problem` and other statuses use existing error branches. Checkout returns null on failure. REST returns code/message with HTTP status 123, with raw numeric provider fragments on provider-response branches. Refund `pending` is currently treated as success; see [refunds](refunds.md).

The provider's [verification hash documentation](https://docs.plugnpay.com/docs/general-documents/security-administration/verification-hash/) describes a different input order from this source. That discrepancy is explicitly unresolved; no hash or settlement change was made. [Authorize Previous documentation](https://docs.plugnpay.com/docs/integration-specifications-documents/remote-api-integration-specification/section-2.-remote-transaction-administration/transaction-administration---authorize-previous/) describes reuse of previous transactions. Merchant-specific validity and contemporary requirements need confirmation before changing the production flow.
