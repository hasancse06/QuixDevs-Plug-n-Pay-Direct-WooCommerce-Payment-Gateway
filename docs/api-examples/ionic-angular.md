# Ionic Angular integration boundaries

**Production integration unavailable while release blockers remain.** This is a non-sending contract specimen for an isolated mock. HTTPS, valid WordPress identity, the existing server-held key and ownership or explicit backend order permissions are implemented in this copy. The old embedded-key-only client is denied. [Actual contract](../rest-api.md), [exact migration](../api-authentication.md).

The existing application's embedded shared key is insufficient in this distribution. Keep merchant passwords, verification keys, WordPress Application Passwords and client keys out of Ionic/browser bundles. A separately implemented trusted backend must authenticate the customer, resolve/authorize its order and call WordPress with server-held credentials. That backend is not provided; no fictional URL is advertised.

The existing store operation retains exactly the eight fields documented in [REST reference](../rest-api.md). No order-key/JWT/idempotency parameter or REST refund route is added.

This specimen models UI intent only, without collecting/posting card data:

```typescript
import { Injectable } from '@angular/core';

export type PaymentUiState = 'idle' | 'awaiting-authorized-flow' | 'reconcile';

@Injectable({ providedIn: 'root' })
export class PaymentPreparationService {
  state: PaymentUiState = 'idle';

  prepareOrder(orderId: number): Readonly<{ orderId: number }> {
    if (!Number.isSafeInteger(orderId) || orderId <= 0) {
      throw new Error('A positive order identifier is required.');
    }
    this.state = 'awaiting-authorized-flow';
    // Continue only through a separately implemented, reviewed backend flow.
    return Object.freeze({ orderId });
  }

  onUncertainOutcome(): void {
    this.state = 'reconcile'; // Do not automatically repeat a charge.
  }
}
```

This is not a runnable payment integration. Do not add direct raw-card submission while the candidate is blocked. Backend key storage and endpoint authorization do not repair missing shared charge/refund locks, HTTP 123, parser/raw-output or refund defects. Mobile memory, analytics/crash reports and direct-card processing still need security review; no PCI certification is claimed.
