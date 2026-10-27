---
id: T-451
title: Implement the PaymentGateway port, the fake adapter, plans, checkout and subscription endpoints
milestone: M9
kind: feature
status: todo
depends_on: [T-450]
requirements: [BIL-FR-02, BIL-FR-07, BIL-FR-08, SEC-AC-43, SEC-AC-44, CA-10, D-40]
branch: m9-t451-billing-gateway-and-checkout
budget_lines: 400
assignee: ""
---

# T-451: Implement the PaymentGateway port, the fake adapter, plans, checkout and subscription endpoints

## Goal

Users can see plans, start a hosted checkout and cancel or resume, all through a provider-neutral gateway port with a fake adapter; a browser redirect never changes state.

## Context

> **Endpoints** (`spec/modules/billing.md` §3): `GET /billing/plans` (plans from `billing.plans` with money `{amount_minor, currency}` and limits); `GET /billing/subscription` (200 `{plan_id, status, period_end, cancel_at_period_end, entitlements}` or 204 for free); `POST /billing/checkout {plan_id}` + `Idempotency-Key` (201 `{checkout_url, expires_at}`; `plan_unknown` 404, `billing_not_configured` 409, `gateway_unavailable` 503, `already_subscribed` 409); `POST /billing/subscription/cancel` and `/resume {base_version}` (200; `version_conflict`, `invalid_transition`); `GET /billing/invoices` (page).
> **Port** (`kinevo-mod-billing::ports`, provider-neutral; first real adapter is OWNER-04 and is **not** part of this milestone): `PaymentGateway { create_checkout(plan, account_ref, return_urls) -> Checkout; cancel_subscription(gateway_ref); resume_subscription(gateway_ref); verify_webhook(headers, body) -> Result<VerifiedEvent, SignatureError>; fetch_subscription(gateway_ref) -> GatewayFacts }`. **BIL-FR-08** the fake adapter `infra::fake` (deterministic, in-memory facts, programmable failures, a method to mint signed webhook payloads with a test secret) is the only adapter compiled as active; real adapters stay inactive unless configured. Adapter selection is by instance setting `billing.gateway` (a policy of kind enum with only `fake` available now; adding a real adapter later is a new task, not a change here).
> **BIL-FR-02** Nivaras derives state only from verified webhook events (T-452); the checkout success redirect returns to the app and the UI polls `GET /billing/subscription`. **BIL-FR-07** secrets `billing.gateway_key` and `billing.webhook_secret` are instance secrets via `SecretStore` (AEAD), never logged; card data never touches Nivaras (hosted checkout only). **SEC-AC-43** an unlisted `plan_id` ⇒ 404 `plan_unknown`. **SEC-AC-44** cancelling another account's subscription id ⇒ 404. Cancel sets `cancel_at_period_end=true` at the gateway and locally; resume clears it.
> **Money in responses**: `{amount_minor, currency}` only; no formatted strings from the server (clients use `Intl`).

## Scope

**In:**
- Port, fake adapter, repositories, use cases (`ListPlans`, `GetSubscription`, `StartCheckout`, `CancelSubscription`, `ResumeSubscription`, `ListInvoices`), handlers, DTOs, policy `billing.gateway`, OpenAPI regeneration.

**Out:**
- Webhooks, entitlements, jobs: T-452.

## Files

| Path | Action | Notes |
|---|---|---|
| `crates/kinevo-mod-billing/src/{ports,app,infra,http}/**` | create |  |
| `crates/kinevo-mod-billing/src/infra/fake.rs` | create | fake gateway |
| `crates/kinevo-mod-billing/tests/{checkout_api,subscription_api,fake_gateway}.rs` | create | AC-1…AC-9 |
| `contracts/openapi.json, web/src/api/schema.d.ts` | modify | regenerated |

## Interfaces

```rust
#[async_trait] pub trait PaymentGateway: Send + Sync {
    async fn create_checkout(&self, req: CheckoutRequest) -> Result<Checkout, GatewayError>;
    fn verify_webhook(&self, headers: &HeaderMap, body: &[u8], now: Instant) -> Result<VerifiedEvent, SignatureError>;
    async fn fetch_subscription(&self, gateway_ref: &str) -> Result<GatewayFacts, GatewayError>;
}
```

## Business rules

1. A checkout creates no subscription; only a verified event does.
2. Prices come from `billing.plans` and the response never recomputes totals.
3. Cancel and resume are guarded by the state machine and `base_version`.

## Security

- Foreign subscription ids → 404.
- Secrets are `Secret<String>`; the webhook secret is compared in constant time (T-452).
- `checkout_url` is produced by the adapter and returned verbatim only if it is an `https` URL (the fake returns a local test URL flagged for tests).

## Implementation notes

- The fake adapter's webhook minting helper is `cfg(feature = "testing")` only.

## Acceptance

| # | Given / When / Then | Test name |
|---|---|---|
| AC-1 | **Given** configured plans **When** listed **Then** money is `{amount_minor, currency}` with limits | `checkout_api::bil_fr_01_plans_listing` |
| AC-2 | **Given** an unlisted plan id **When** checking out **Then** 404 `plan_unknown` | `checkout_api::sec_ac_43_plan_unknown` |
| AC-3 | **Given** unconfigured billing **When** checking out **Then** 409 `billing_not_configured` | `checkout_api::billing_not_configured` |
| AC-4 | **Given** an active subscription **When** checking out again **Then** 409 `already_subscribed` | `checkout_api::already_subscribed` |
| AC-5 | **Given** the same idempotency key **When** checking out twice **Then** one gateway call and the same response | `checkout_api::checkout_idempotent` |
| AC-6 | **Given** a gateway outage **When** checking out **Then** 503 `gateway_unavailable` | `checkout_api::gateway_unavailable` |
| AC-7 | **Given** a success redirect without a webhook **When** the subscription is read **Then** state is unchanged | `subscription_api::bil_fr_02_redirect_changes_nothing` |
| AC-8 | **Given** another account's subscription **When** cancelled **Then** 404 | `subscription_api::sec_ac_44_foreign_subscription_404` |
| AC-9 | **Given** cancel and resume **When** applied **Then** `cancel_at_period_end` toggles with version checks | `subscription_api::cancel_resume_flow` |

## Tests

```
cargo nextest run -p kinevo-mod-billing --features kinevo-db/testing   # DB tests environment-limited without Docker/PostgreSQL
just check
```

## Definition of done

- [ ] Every acceptance criterion has its named test, and it passes (or is marked environment-limited with the exact command not run).
- [ ] `just check` passes locally.
- [ ] Only the files listed under *Files* changed; diff within budget.
- [ ] Report below is filled in.

## Report

_Filled in by the implementer (WORKFLOW.md §4)._

## Questions

_None yet._
