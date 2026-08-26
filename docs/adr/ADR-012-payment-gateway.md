# ADR-012 — Payment Gateway Selection (TASK-P24-001..003)

Status: ACCEPTED for implementation start · Date: 2026-08-26 · Decider: product owner via execution directive

## Context

Kinevo needs recurring subscription billing for IDR customers. P23 shipped the
entitlement foundation; this ADR selects the first web gateway behind the new
`PaymentGateway` adapter boundary (P24-004). Provider behavior below was
verified against CURRENT official documentation on 2026-08-26 (links inline);
sandbox behavior still requires live merchant credentials.

## Requirements

IDR-first market · recurring auto-charge · hosted checkout acceptable ·
webhook-driven state sync · refund support · verifiable sandbox before prod.

## Alternatives & verified capabilities

### Midtrans (docs.midtrans.com — Subscription API / API Methods, GoPay Tokenization pages)

- Subscription API exists: create / get / enable / disable / update
  (`/v1/subscriptions[...]`). Supported recurring methods **currently Card and
  GoPay Tokenization** (API Methods page).
- Recurring must be ACTIVATED on the merchant account by Midtrans Support/Sales
  (GoPay Tokenization page callout) — an onboarding prerequisite.
- GoPay tokenization has two flows (web OTP/PIN, app redirect) with
  single-access web linking URLs; updated sandbox since Oct 2024.
- Subscription creation idempotency documented for a limited period.
- Currency: IDR. Sandbox: api.sandbox.midtrans.com. Auth = HTTP Basic server key.
- Fees/settlement: NOT verified in this pass → UNKNOWN until merchant contract.

### Xendit (docs.xendit.co / help.xendit.co)

- Two viable models verified:
  1) Subscriptions product ("Collect payment on a regular basis");
  2) merchant-managed subscriptions via Payments API + card vaulting
     (`VERIFY_PAYMENT_METHOD` → `payment.verified` webhook; card-on-file MIT).
- Webhook authenticity = static `X-CALLBACK-TOKEN` header compared to the
  Dashboard verification token (help center). Idempotency: `Idempotency-key`
  header honored on money-in writes (e.g., refunds); `X-EXTERNAL-ID` reuse
  blocked for 24h (archive API reference).
- Methods breadth: cards, direct debit, e-wallets, VA, QR, OTB. IDR (+ multi-currency accounts).
- Recurring direct-debit webhook sample shows PAID/EXPIRED per cycle invoice.
- Fees/settlement: NOT verified in this pass → UNKNOWN.

## Decision

**Primary adapter: Midtrans (Core API Subscription)** for v1 web recurring,
because: (a) native Subscription API manages the charge schedule server-side
(no Kinevo cron-charging), (b) Card + GoPay cover the primary Indonesian
payment expectations, (c) explicit enable/disable/update operations map cleanly
onto our state machine, (d) documented sandbox for both methods.

**Adapter seam kept Xendit-ready**: the `PaymentGateway` contract +
capability-matrix pattern means adding a Xendit adapter later is additive
(its tokenization/MIT model differs and is documented above).

## Consequences

- Merchant activation prerequisite (Midtrans recurring + GoPay tokenization)
  becomes an OPERATIONS checklist item before production; implementation and
  tests proceed against SANDBOX only.
- Provider status mapping must treat Midtrans subscription statuses
  (created/active/inactive/pending per docs) as the source for our internal
  state machine — mapping table lives in the adapter, unit-tested.
- Refund/chargeback APIs were NOT verified this pass → those capabilities are
  marked UNKNOWN in the matrix and DEFERRED in scope until verified.

## Revisit criteria

Re-evaluate Xendit (or dual-provider) if: GoPay-tokenization activation stalls;
card-only recurring proves insufficient for target users; or Midtrans pricing/
settlement terms (to be obtained from merchant contract) are unfavorable.
