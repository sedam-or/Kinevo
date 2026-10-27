# Module `billing` (Nivaras Cloud)

Kind: toggleable (default **off**; cloud template turns it on after OWNER-01/02 are answered). Depends on: `identity`. Tables: `subscriptions`, `invoices`, `billing_webhook_events`; secrets through the `SecretStore` port (D-40). Prefix: `BIL`.
Principle (D-25, D-26): billing exists only to fund the hosted service. **A self-hosted instance has every feature with no limits**; this module is off there and `EntitlementPort` answers "unlimited". The module is provider-neutral: it talks to payment providers only through the `PaymentGateway` port. Choosing the first adapter is an owner decision (OWNER-04); the legacy gateway was Midtrans (`legacy-v0:server/app/Infrastructure/Billing/MidtransGateway.php`) and its design (signed idempotent webhooks, Nivaras-owned subscription state) is kept.

## 1. Open owner decisions (nothing here is guessed)

| ID | Decision | Needed for |
|---|---|---|
| OWNER-01 | currency, plan list, prices (`billing.currency`, `billing.plans`), grace days | enabling the module |
| OWNER-02 | hosted AI allowance per plan | AI limits |
| OWNER-04 | first payment provider (and whether it needs a merchant account/legal entity) | adapter task |
| OWNER-05 | tax/invoice obligations in the operating jurisdiction | invoice contents, receipts |
| OWNER-06 | refund and cancellation policy text | UI copy, `cancel` semantics |

## 2. Permissions

`billing.plan.read`, `billing.subscription.read|manage`, `billing.invoice.read` — `account_owner`, `instance_admin` (own data). Webhooks are unauthenticated but signature-verified.

## 3. Endpoints

| Method / path | Success | Errors |
|---|---|---|
| `GET /billing/plans` | 200 plans from `billing.plans` with money `{amount_minor, currency}`, limits | — |
| `GET /billing/subscription` | 200 `{ plan_id, status, period_end, cancel_at_period_end, entitlements }` or 204 (free) | — |
| `POST /billing/checkout` `{ plan_id }` + `Idempotency-Key` | 201 `{ checkout_url, expires_at }` | `plan_unknown` 404, `billing_not_configured` 409, `gateway_unavailable` 503, `already_subscribed` 409 |
| `POST /billing/subscription/cancel` / `…/resume` `{ base_version }` | 200 | `version_conflict`, `invalid_transition` |
| `GET /billing/invoices` | 200 page | — |
| `POST /billing/webhooks/{gateway}` | 200 always for verified+known events (idempotent); 401 for bad signature | `signature_invalid` 401, `gateway_unknown` 404 |

## 4. Functional requirements

| ID | Requirement | Acceptance |
|---|---|---|
| BIL-FR-01 | Amounts are `bigint` minor units + ISO-4217 code; no floats; totals never recomputed from display strings. Plan prices come only from `billing.plans`; invoices and subscriptions store a `plan_snapshot` so later price changes do not alter past records (D-09, D-18). | Property test: sum of line items = total; snapshot immutability test. |
| BIL-FR-02 | The subscription is a Nivaras-owned state machine (§5). The gateway is the source of *payment facts*; Nivaras derives state from verified events and never trusts a browser redirect. | Test: success redirect without webhook ⇒ state unchanged. |
| BIL-FR-03 | Webhooks: verify signature with the provider's scheme using the secret `billing.webhook_secret` (constant-time compare); reject timestamps older than 5 minutes when the provider supplies one; dedupe by `(gateway, event_id)` in `billing_webhook_events`; process in a transaction; out-of-order events are ordered by the provider's event time / sequence and stale transitions are ignored (recorded `outcome=ignored_stale`). | Tests: replay, reorder, bad signature, huge body (> 256 KiB ⇒ 413). |
| BIL-FR-04 | Entitlements: `EntitlementPort::value(account, key)` returns the plan limit for keys `max_workspaces`, `ai_monthly_runs`, `byok_allowed`; with billing disabled returns `Unlimited`. A downgrade never deletes data: if usage exceeds the new limit the account is "over limit" (can read/edit, cannot create more of that resource). | Tests. |
| BIL-FR-05 | `past_due` keeps full access for `billing.grace_days`, then the account becomes `downgraded` to the free plan limits (data retained). | Test with injected clock. |
| BIL-FR-06 | Reconciliation job `billing.reconcile` (daily) compares open subscriptions with the gateway; differences are logged as audit entries (`billing.drift`) and corrected from the gateway's facts. | Job test with a fake gateway. |
| BIL-FR-07 | Secrets (`billing.gateway_key`, `billing.webhook_secret`) are instance secrets (AEAD), never logged. Card data never touches Nivaras (hosted checkout only; PCI scope = SAQ-A style). | Scan test; architecture review. |
| BIL-FR-08 | Fake gateway adapter (`kinevo-mod-billing::infra::fake`) exists for tests and local demos; real adapters are compiled but inactive unless configured. | CI uses the fake only. |
| BIL-FR-09 | On account deletion, the active subscription is cancelled at the gateway before the purge job proceeds; invoice rows are deleted with the account **except** records the owner must retain legally (OWNER-05 decides; default: retain anonymised invoice totals without account linkage). | Journey J7 variant. |

## 5. State machine — subscription

| From | Event (verified) | To | Notes |
|---|---|---|---|
| (none) | checkout completed (paid) | `active` | invoice `paid` |
| (none) | checkout completed with trial (if plan has trial) | `trialing` | |
| `trialing` | first payment paid | `active` | |
| `active` | renewal paid | `active` | `period_end` extended, invoice `paid` |
| `active` | renewal failed | `past_due` | grace timer starts |
| `past_due` | payment recovered | `active` | |
| `past_due` | grace elapsed | `canceled` | entitlements → free |
| `active` | user cancels | `active` + `cancel_at_period_end=true` | |
| `active` | period ends with flag | `canceled` | |
| `canceled` | new checkout | `active` | new period |
| any | duplicate/stale event | unchanged | `ignored_stale` |

## 6. Data flow — checkout

User picks a plan → `POST /billing/checkout` (idempotent) → adapter creates a hosted checkout → client redirects → user pays at the provider → provider sends signed webhook → verified, deduped, transaction: update `subscriptions`, insert `invoices`, audit, outbox `SubscriptionChanged` → entitlement caches invalidate. If the webhook is delayed, the UI shows "processing" for ≤ 5 minutes with a manual refresh (`GET /billing/subscription`), then "contact support".

## 7. Events / jobs

Emits `SubscriptionChanged`. Jobs: `billing.reconcile` (daily), `billing.webhooks.prune` (daily; > 90 days), `billing.grace.sweep` (tick; BIL-FR-05).

## 8. Abuse cases

SEC-AC-41 forged webhook (valid JSON, wrong signature) ⇒ 401, nothing changes. SEC-AC-42 replayed valid webhook ⇒ 200 `ignored_duplicate`. SEC-AC-43 user edits plan id in checkout request to a non-listed id ⇒ 404 `plan_unknown`. SEC-AC-44 account A cancels account B's subscription id ⇒ 404.
