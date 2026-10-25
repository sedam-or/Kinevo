---
id: T-452
title: Implement signed webhooks, the entitlement port, grace and downgrade, reconcile and deletion hooks
milestone: M9
kind: feature
status: todo
depends_on: [T-451, T-029]
requirements: [BIL-FR-03, BIL-FR-04, BIL-FR-05, BIL-FR-06, BIL-FR-09, SEC-AC-41, SEC-AC-42, TST-12, TST-19, CA-10]
branch: m9-t452-billing-webhooks-entitlements-jobs
budget_lines: 420
assignee: ""
---

# T-452: Implement signed webhooks, the entitlement port, grace and downgrade, reconcile and deletion hooks

## Goal

Payment facts arrive only through verified, deduplicated, ordered webhooks; limits follow the plan without ever deleting data; drift is corrected from the gateway's facts.

## Context

> **Webhook** (`spec/modules/billing.md` §3, §6): `POST /billing/webhooks/{gateway}` — unauthenticated, signature-verified; 200 always for verified+known events (idempotent; duplicates answer 200 with outcome `ignored_duplicate`), 401 `signature_invalid`, 404 `gateway_unknown`, body > 256 KiB ⇒ 413. **BIL-FR-03** verify the signature with the adapter's scheme using `billing.webhook_secret` (constant-time compare); reject timestamps older than 5 minutes when the provider supplies one; dedupe by `(gateway, event_id)` in `billing_webhook_events`; process in one transaction; out-of-order events are ordered by the provider's event time/sequence and stale transitions are ignored (`outcome=ignored_stale`).
> **Context** (ADR-0011, `SystemTx` cannot become `Tx`): a `SystemTx` looks up the account from `subscriptions.gateway_ref` (or from the checkout reference carried by the event for the first payment), then a fresh `Tx::begin(account)` performs, atomically: insert the webhook row, apply `transition`, upsert `subscriptions`, insert/update `invoices` with `plan_snapshot`, write audit, outbox `SubscriptionChanged`. Unknown references are recorded as `outcome=unmatched` and return 200.
> **Entitlements** (BIL-FR-04): implement `EntitlementPort::value(account, key)` for `max_workspaces`, `ai_monthly_runs`, `byok_allowed` from the active plan snapshot, `Unlimited` when the module is off; replace the allow-all wiring (T-101) in `kinevo-app` when billing is enabled; a downgrade never deletes data — over-limit accounts can read and edit but not create more of that resource (the consumers already check the port: workspaces T-101, AI T-302 monthly runs); entitlement cache is invalidated on `SubscriptionChanged` (moka, TTL from policy, key by account).
> **BIL-FR-05** `past_due` + grace elapsed ⇒ `canceled` and free-plan limits (the job `billing.grace.sweep` evaluates it with the injected clock; registered here). **BIL-FR-06** job `billing.reconcile` (daily): compare open subscriptions with `fetch_subscription`; differences are audited (`billing.drift`) and corrected from the gateway's facts. **Jobs**: `billing.webhooks.prune` (daily; > 90 days). **BIL-FR-09** on account deletion the active subscription is cancelled at the gateway before the purge proceeds (an `AccountDeletionHook` from T-029 is implemented); invoice rows are deleted with the account except legally retained records — default per spec: retain anonymised invoice totals without account linkage in a `retained_invoice_totals` aggregate row set by the hook **only if** OWNER-05 enables retention via the policy `billing.retain_invoice_totals` (default off); with the default the rows are deleted.
> **Abuse**: SEC-AC-41 forged webhook (valid JSON, wrong signature) ⇒ 401, nothing changes; SEC-AC-42 replayed valid webhook ⇒ 200 `ignored_duplicate`.

## Scope

**In:**
- Webhook handler and use case, entitlement implementation and cache, jobs (`billing.reconcile`, `billing.webhooks.prune`, `billing.grace.sweep`), deletion hook, policy `billing.retain_invoice_totals`, audit actions (`billing.drift`), wiring, OpenAPI regeneration.

**Out:**
- Web: T-453.

## Files

| Path | Action | Notes |
|---|---|---|
| `crates/kinevo-mod-billing/src/{app,infra,http}/{webhook,entitlements,reconcile,grace,deletion}.rs` | create |  |
| `crates/kinevo-mod-billing/tests/{webhook_api,entitlements,jobs,deletion}.rs` | create | AC-1…AC-12 |
| `crates/kinevo-app/src/modules.rs` | modify | swap entitlement wiring |
| `contracts/openapi.json` | modify | regenerated |

## Interfaces

```rust
// POST /billing/webhooks/{gateway} → 200 { "outcome": "applied" | "ignored_duplicate" | "ignored_stale" | "unmatched" }
```

## Business rules

1. Signature verification precedes any parsing beyond what the scheme needs.
2. A transaction failure records nothing and returns 5xx so the provider retries.
3. Out-of-order events never move a subscription backwards.
4. Downgrade never deletes data.

## Security

- Constant-time signature compare; replay window of 5 minutes; stream body limit 256 KiB.
- The webhook handler logs event ids and outcomes only, never payload bodies.
- Account lookup uses a minimal `SystemTx` read.

## Implementation notes

- Tests mint webhooks with the fake adapter's helper and the test secret.

## Acceptance

| # | Given / When / Then | Test name |
|---|---|---|
| AC-1 | **Given** a valid signed event **When** posted **Then** state, invoice, audit and outbox are written in one transaction | `webhook_api::bil_fr_03_applies_verified_event` |
| AC-2 | **Given** a wrong signature **When** posted **Then** 401 and nothing changes | `webhook_api::sec_ac_41_forged_webhook` |
| AC-3 | **Given** a replayed event **When** posted **Then** 200 `ignored_duplicate` | `webhook_api::sec_ac_42_replay_ignored` |
| AC-4 | **Given** events delivered out of order **When** processed **Then** the stale one is `ignored_stale` | `webhook_api::bil_fr_03_out_of_order` |
| AC-5 | **Given** a body over 256 KiB or a timestamp older than 5 minutes **When** posted **Then** 413 / rejected | `webhook_api::body_and_timestamp_limits` |
| AC-6 | **Given** a plan with limits **When** the entitlement is read **Then** the limit; with billing off `Unlimited` | `entitlements::bil_fr_04_values_and_unlimited` |
| AC-7 | **Given** a downgrade below current usage **When** creating **Then** creation is refused but reads and edits work | `entitlements::bil_fr_04_over_limit_keeps_data` |
| AC-8 | **Given** `past_due` and an injected clock **When** the grace sweep runs after the deadline **Then** `canceled` and free limits | `jobs::bil_fr_05_grace_sweep` |
| AC-9 | **Given** gateway facts that differ **When** reconcile runs **Then** an audit `billing.drift` and correction | `jobs::bil_fr_06_reconcile_drift` |
| AC-10 | **Given** old webhook rows **When** the prune runs **Then** only rows over 90 days are deleted | `jobs::webhooks_prune_retention` |
| AC-11 | **Given** account deletion **When** the hook runs **Then** the subscription is cancelled at the gateway before purge; invoices follow the retention policy | `deletion::bil_fr_09_cancel_before_purge` |
| AC-12 | **Given** failure injected at each statement **When** a webhook is processed **Then** no partial state | `webhook_api::tst_19_fault_injection` |

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
