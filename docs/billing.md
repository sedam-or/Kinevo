# Kinevo — Billing Architecture & Operations (TASK-P24)

Status: ACTIVE · TASK-P24-038 · Updated 2026-08-26

Governance: provider selection is recorded in `docs/adr/ADR-012-payment-gateway.md`
(ACCEPTED); provider capability data lives in `docs/billing-capability-matrix.md`.
This document is the ENGINEERING + OPERATIONS contract for the live web billing
path that P24 implemented and that P24-035 proved end-to-end against the
Midtrans sandbox.

## Mission

Let an IDR-first user buy and renew a paid Kinevo plan with a recurring
provider-managed subscription, and let ONLY that provider's verified
notifications move billing state — never the browser, never a client-supplied
success flag. Money moves in integer minor units (IDR ×100). No card/CVV/bank
data ever touches Kinevo storage.

## Pricing Delta (owner decision 2026-08-28 — `revisi-finance.md`)

Commercial delta patch supersedes ADR-013 prices. Authoritative statuses:

- `PRICING DECISION = LOCKED` — Free = IDR 0/month; **Pro = IDR 49,900/month**;
  **Power = IDR 89,900/month**. These are LAUNCH PRICE HYPOTHESES subject to
  beta validation (activation; conversion; retention; cancellation;
  willingness-to-pay; AI COGS; contribution margin). Never claim "final market
  price".
- `AI QUOTA NUMBERS = NOT YET LOCKED (DECISION_REQUIRED)` — the 20/150/500
  credit baselines are DEPRECATED BASELINE; the new allowance MUST be derived
  from the AI cost simulation (provider/model pricing; cache behavior; P50–P99
  usage; target contribution margin), never invented. Current runtime values
  in `server/config/saas.php` remain functional but are not final policy.
- AI credits are an internal economic abstraction: a credit is NOT a token and
  NOT a provider billing unit; tokens are telemetry. Markup ≠ margin (25%
  markup = 20% margin); AI contribution margin target ~30–50% is CONFIGURABLE —
  no hardcoded universal markup.
- TWO SEPARATE LEDGERS — never merge: the SUBSCRIPTION ledger (plan; billing
  period; amount; payment state) and the AI USAGE ledger (included allowance;
  consumed credits; optional prepaid balance; hosted vs BYOK usage;
  provider/model; token telemetry; estimated provider cost with a versioned
  price catalog). BYOK never consumes hosted credits and remains subject to
  rate/request/context/output/timeout/abuse safeguards; BYOK: Free = NO,
  Pro/Power = YES.
- AI request budget firewall (BEFORE any provider call): auth → entitlement →
  available allowance → rate limit → estimated request budget → max
  input/output token guard → provider. Insufficient budget ⇒ do not call the
  provider. Reserve the maximum permitted budget, settle ACTUAL usage after
  the response, release the unused reservation.
- Downgrade safety: Power→Pro and Pro→Free never silently delete user data —
  creation/edit limits apply, advanced capabilities become unavailable, history
  follows entitlement, existing data stays readable where safe.
- Power positioning: higher capacity/deeper history/deeper intelligence/richer
  reflection/advanced Wrapped & share — NEVER "Pro + random features" and never
  Teams/Organizations/RBAC/Enterprise.
- Pricing/upgrade UX must let the user answer: What do I get? Why upgrade? Why
  is Power worth the extra Rp40,000? No deceptive urgency, fake scarcity, or
  destructive lockouts.

Implementation of this delta is tracked in `TASK.md` § COMMERCIAL PRICING DELTA
(D-001…D-008).

## Normative links

- `docs/adr/ADR-012-payment-gateway.md` — why Midtrans (Core API Subscription), Xendit-ready seam.
- `docs/adr/ADR-013-product-tiers-pricing.md` — tiers & prices AS RECORDED 2026-08-26 (Free Rp0;
  Pro IDR 34,900/mo; Power IDR 49,900/mo). **Prices SUPERSEDED 2026-08-28 by `revisi-finance.md`
  (see §Pricing Delta below)**; ADR body is preserved history. Annual unpriced until an explicit
  owner decision; web-first billing — no Google Play checkout in Android v1; one subscription
  covers Web + Android.
- `docs/billing-capability-matrix.md` — verified per-provider capabilities.
- `docs/api/openapi.yaml` — API contract of record (checkout / webhook / cancel / resume / subscription snapshot).
- `database/migrations/` — `billing_subscriptions`, `billing_transactions`, `billing_events` (P24).
- `docs/domain-model.md` § subscription lifecycle / entitlement (P23–P24).
- `server/config/billing.php` — prices (product data, integer minor IDR) + Midtrans env wiring.

## Domain boundary

The browser is never authoritative for payment state. `BillingController` exposes:
- `POST /api/v1/billing/checkout` (auth, `throttle:api`) — idempotent checkout creation. An existing `pending`
  row for the same user+plan is returned as-is; otherwise a Midtrans subscription is created and a
  `pending` `BillingSubscription` row is persisted.
- `GET /api/v1/billing/subscription` (auth) — safe snapshot: latest subscription + ≤20 recent transactions
  (amount/currency/status/date only — no PII, no card data, no choice data).
- `POST /api/v1/billing/cancel` / `POST /api/v1/billing/resume` (auth) — disable/enable the provider-managed
  subscription; cancel downgrades entitlement to free, resume restores the paid plan.
- `POST /api/v1/billing/webhook/midtrans` (machine-to-machine, `throttle:60,1`) — signature-verified notifier entry.

## Webhook contract

Request: raw JSON body ≤64 KiB (413 if larger) with at least
`order_id`, `status_code`, `gross_amount`, `signature_key`, `transaction_status`, `transaction_id`.

Verification (`MidtransGateway::verifyAndNormalizeWebhook`):
`sha512(order_id + status_code + gross_amount + server_key)` compared with `hash_equals`;
`server_key` exists only server-side (environment). Every rejected payload is refused with 403 and never
mutates state. When `billing.midtrans.webhook_verify` is off the endpoint refuses (503) — verification
cannot be silently disabled.

Event normalization maps `transaction_status` to domain events:
`settlement|capture → PaymentSucceeded`, `deny|cancel → SubscriptionCanceled`,
`expire → SubscriptionExpired`, `pending → PaymentFailed` (provisional).

Application (`BillingService::applyEvent`) is:
- **Idempotent** — duplicate `(provider, provider_event_id)` is a safe no-op (`duplicate`).
- **Out-of-order safe** — an event older than the last applied one is recorded but cannot regress state (`out_of_order`).
- **State-machine driven** — transitions go through `InvoiceState`-style explicit permission; illegal
  transitions mark the row `uncertain` instead of guessing.
- **Entitlement-syncing** — on apply, the P23 resolver row (`subscriptions`) is flipped to the paid
  plan/active (or free-fallback on cancel/expire), so every device sees the plan through the same resolver.
- **Auditable** — every notification leaves a `billing_events` row with only a `payload_hash` (no PII/choice data).

Transaction ledger `billing_transactions` records succeeded/failed attempts in minor units, one row per
provider transaction id.

## State machine

`billing_subscriptions.state ∈ pending | active | past_due | canceled | expired`.
Transitions are enforced by `SubscriptionState::canTransitionTo` (domain, unit-tested); first activation
from `pending` is the only exception (P24-035-proven).

## Entitlement synchronization

Web purchase → same-account entitlement across devices (P24-036): after settlement the P23 resolver returns
the paid plan for ANY session of the account (`GET /api/v1/saas/plan`), because it reads the same
`subscriptions` row the webhook writes. Mobile restore is deferred with mobile billing.

## Security footprint (P24-030)

- No raw card / CVV / bank stored anywhere — Midtrans owns tokenization; Kinevo stores only provider refs.
- Secrets (server key / client key) are server-side environment values; never committed (`.env` ignored).
- Webhook replay protected by idempotency + signature; checkout/webhook rate-limited.
- PII minimized: `billing_events.payload_hash` only; snapshot endpoints return safe projection.
- IDOR: all authed billing endpoints use `request->user()` scopes (tested).

## Operations

CLI diagnostics (no web admin surface for billing — P24-037):
- `php artisan billing:status <userId>` — human-readable subscription + entitlement state.
- `php artisan billing:reconcile` — operator reconciliation aid; read-only, table-safe.

Sandbox → production checklist (from ADR-012 consequences):
1. Merchant activation: Midtrans recurring + GoPay Tokenization must be ON for the merchant (Support/Sales).
2. Flip `MIDTRANS_ENV=production`; swap server/client keys; keep `MIDTRANS_WEBHOOK_VERIFY=true`.
3. Re-verify refund/chargeback behavior in PRODUCTION (sandbox-capability verified 2026-08-27 — P24-022/023; Core API refund for settled credit_card + webhook capture of `refund`/`chargeback`/`partial_*`) and sign-off
   fees/settlement from the merchant contract.
4. Point the production webhook URL at the public endpoint; confirm 60/min throttle is adequate.
5. Run the applicable P24 test set (BillingWebhookTest, BillingCheckoutTest, BillingDomainTest, adapter suite).
6. Record cross-device entitlement evidence; update `docs/implementation-status.md`.

## Sandbox E2E evidence (P24-035, 2026-08-26)

Live against `api.sandbox.midtrans.com` with real sandbox credentials:
- Checkout `POST /api/v1/billing/checkout` (plan `personal`) created provider subscription
  `2d60abaa-583c-4797-b191-db4b826d8a43` (IDR 49.000, `credit_card`, metadata `kinevo_user_id=17`,
  plan `personal`); local row `pending`. (Adapter payload required `payment_type`, `token`, and the
  `schedule{interval,interval_unit,start_time}` shape — the pre-fix payload was rejected 400 with
  `subscription.token / subscription.schedule / subscription.payment_type is required`.)
- Settlement webhook (real sha512 signature, `status_code=200`, `gross_amount=49000.00`) → `applied`.
- Verified state: `billing_subscriptions.state=active`; `billing_transactions.amount_minor=4_900_000`
  succeeded; P23 `subscriptions` = plan `personal` / `active` / provider `midtrans`; `billing_events`
  `processed`.
- Idempotent replay → `duplicate`; snapshot endpoint reflects active subscription + history.
- Cross-device (P24-036): a second session token for the same account resolves `personal`/`active`
  on `GET /api/v1/saas/plan`.
- Sandbox card token (`MIDTRANS_TEST_CARD_TOKEN`) exists only in local `.env`; never committed.

## Out of scope / deferred

- Refund / chargeback (P24-022/023): `MIDTRANS_REFUND` via Core API `POST /v2/{order_id}/refund` (credit_card, sandbox-simulated); webhook maps `refund`/`partial_refund` → transaction `refunded`, `chargeback`/`partial_chargeback` → refunded + `uncertain` (no silent entitlement change).
- Apple IAP / Google Play adapters (P24-039..041): separate adapters, deferred with mobile (P26+).
- Cross-platform purchase restoration (P24-042) and duplicate-subscription policy (P24-043): with mobile.
- Proration on upgrade/downgrade (P24-021): provider-verification dependent; current behavior is
  upgrade = new checkout on the higher plan, downgrade = cancel + free fallback (data preserved).
- Explicit grace-period duration (P24-019): Midtrans auto-retry handles dunning; duration is a P30 product decision.