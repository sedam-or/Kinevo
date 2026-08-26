# Payment Gateway Capability Matrix (TASK-P24-003)

Verified 2026-08-26 against official documentation. `UNKNOWN` ≠ supported.
Sources: docs.midtrans.com (Subscription API / API Methods / GoPay Tokenization),
docs.xendit.co + help.xendit.co (Subscriptions, Cards recurring/MIT,
webhook validation). Fees/settlement require merchant contracts → UNKNOWN.

| Capability | Midtrans | Xendit |
|---|---|---|
| API/protocol | REST, HTTP Basic server key | REST, Basic API key (+callback token for webhooks) |
| Currency | IDR | IDR primary; multi-currency accounts |
| Hosted checkout | Snap ✓ | Payment Links / Checkout ✓ |
| Recurring model | Provider-managed Subscription API | Subscriptions product OR merchant-managed MIT |
| Recurring methods (verified) | Card, GoPay Tokenization only | Cards (MIT); Direct Debit cycles; e-wallet channels vary → per-channel UNKNOWN until enabled |
| Tokenization | Card registration; GoPay pay-account linking | Card vaulting via VERIFY_PAYMENT_METHOD (`payment.verified`) |
| Retry behavior | Provider auto-deducts per schedule | Merchant-initiated per Payments API (MIT) |
| Cancel | disable endpoint ✓ | product-level cancel (Subscriptions) / merchant stop (MIT) |
| Resume | enable endpoint ✓ | re-create/reactivate — semantics UNKNOWN |
| Update subscription | PATCH endpoint ✓ | Subscriptions product supports update; MIT = new payment requests |
| Refund | UNKNOWN (not verified this pass) | Refund APIs exist per channel (verified endpoints) |
| Dispute/chargeback | UNKNOWN | UNKNOWN |
| Webhook mechanism | HTTP notification w/ signature_key (sha512) verification | POST + `X-CALLBACK-TOKEN` static compare |
| Idempotency | Subscription create idempotent for limited period | Idempotency-key on writes; X-EXTERNAL-ID 24h uniqueness |
| Sandbox | api.sandbox.midtrans.com; GoPay sandbox updated Oct 2024 | Dashboard test mode keys |
| Merchant prerequisites | Recurring + GoPay tokenization activation by Midtrans team | Account/business verification; channel activation varies |
| Reconciliation/reporting | Dashboard + transaction status API | Dashboard + reports; detailed reconciliation UNKNOWN |

## Kinevo adapter capability flags (P24-004)

```php
// MidtransAdapter::capabilities()
supports: create_customer(NO*), checkout(YES hosted+subscription),
          subscription_lifecycle(create/get/update/enable/disable),
          refund(UNKNOWN), dispute(UNKNOWN), out_of_order_guard(via provider lookup)
* Midtrans customer object is implicit in transaction/customer_details.
```
