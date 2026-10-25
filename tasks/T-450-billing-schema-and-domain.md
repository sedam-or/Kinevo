---
id: T-450
title: Create the billing module: migration, descriptor, money and plan domain, subscription state machine
milestone: M9
kind: feature
status: todo
depends_on: [T-403]
requirements: [BIL-FR-01, BIL-FR-02, BIL-FR-05, DOM-20, DOM-28, D-44, D-09, D-18, D-25, D-26, SEC-AC-01]
branch: m9-t450-billing-schema-and-domain
budget_lines: 420
assignee: ""
---

# T-450: Create the billing module: migration, descriptor, money and plan domain, subscription state machine

## Goal

The billing crate owns its tables and a pure, provider-neutral domain: money in minor units, plan snapshots, a verified-event subscription state machine and the grace rule, with no price or currency hard-coded anywhere.

## Context

> **Module** (`spec/modules/billing.md`): kind **toggleable**, default **off**; depends on `identity`; tables `subscriptions`, `invoices`, `billing_webhook_events`; permissions `billing.plan.read`, `billing.subscription.read|manage`, `billing.invoice.read`. Principle (D-25/D-26): billing funds only the hosted service; a self-hosted instance has no limits (module off ⇒ `EntitlementPort` answers `Unlimited`). **Nothing here is guessed**: `billing.currency`, `billing.plans` and `billing.grace_days` are OWNER-01 values entered by the operator as policies (04-policies §3); tests use fixture values injected through the test policy reader, never constants in production code.
> **DDL** (`06-domain-model.md` §9): `subscriptions(id, account_id UNIQUE → accounts CASCADE, plan_id, status in (trialing, active, past_due, canceled) as sub_status_ck, period_start, period_end, cancel_at_period_end, gateway, gateway_ref, plan_snapshot jsonb, created_at, updated_at, version)`; `invoices(id, account_id → accounts CASCADE, subscription_id → subscriptions SET NULL, amount_minor bigint ≥ 0, currency char(3) ~ '^[A-Z]{3}$', status in (open, paid, void, failed), period_start, period_end, gateway_ref, plan_snapshot, issued_at, paid_at)`; `billing_webhook_events(gateway, event_id, received_at, signature_ok, processed_at, outcome, PRIMARY KEY (gateway, event_id))` — **instance-level, no tenant column, no RLS** (like `invites`); the migration grants `kinevo_app` INSERT/SELECT/UPDATE on it and `kinevo_system` SELECT on `subscriptions (id, account_id, gateway, gateway_ref)` so a webhook can find its account (ADR-0011). DOM-28: one subscription row per account; invoices immutable once `paid` (a trigger rejects UPDATE of a `paid` row except `gateway_ref` null-to-value). Migration `crates/kinevo-mod-billing/migrations/20270701000000_billing_billing.sql`.
> **BIL-FR-01** amounts are `bigint` minor units + ISO-4217 code, no floats; plan prices come only from `billing.plans` (`billing_plans_v1`: `id`, `name_key`, `price: money`, `interval` month|year, `limits{max_workspaces, ai_monthly_runs, byok_allowed}`, `trial_days?`); invoices and subscriptions store a `plan_snapshot` so later price changes never alter past records. Register the `billing_plans_v1` validator for the policy key `billing.plans` and a currency validator for `billing.currency` with the policy registry (T-031/T-033 extension point; if none exists for `json` kinds, raise a blocking question).
> **State machine** (§5) over **verified** events only: (none)+paid→`active`; (none)+trial→`trialing`; `trialing`+first paid→`active`; `active`+renewal paid→`active` (period extended, invoice paid); `active`+renewal failed→`past_due`; `past_due`+recovered→`active`; `past_due`+grace elapsed→`canceled`; `active`+user cancels→`active` with `cancel_at_period_end`; `active`+period ends with flag→`canceled`; `canceled`+new checkout→`active`; duplicate or stale → unchanged (`ignored_stale`). **BIL-FR-05** `past_due` keeps full access for `billing.grace_days`, then the account is downgraded to free-plan limits (data retained).
> **Module refuses to enable** until `billing.currency` and `billing.plans` are set: `billing_not_configured` (CLD-01), enforced by the module-switch precondition hook (T-035 extension, or a check in the descriptor `enable_guard`).

## Scope

**In:**
- Crate skeleton, descriptor (toggleable default off, `requires: ["identity"]`, emits `SubscriptionChanged`, jobs `billing.reconcile`, `billing.webhooks.prune` registered in T-452), migration, grants, `domain/{money,plans,subscription,grace}.rs`, policy validators, enable guard, wiring: Register the crate in the workspace `Cargo.toml`, as a dependency of `kinevo-app`, and in the composition root list (`kinevo-app/src/modules.rs`), in dependency order; the architecture test of T-042 must stay green.

**Out:**
- Gateway port, endpoints: T-451. Webhooks and entitlements: T-452.

## Files

| Path | Action | Notes |
|---|---|---|
| `crates/kinevo-mod-billing/**` | create | skeleton |
| `crates/kinevo-mod-billing/migrations/20270701000000_billing_billing.sql` | create |  |
| `crates/kinevo-mod-billing/tests/{isolation,descriptor,money,plans,subscription,grace}.rs` | create | AC-1…AC-11 |
| `Cargo.toml, crates/kinevo-app/**` | modify | wiring |

## Interfaces

```rust
pub struct Money { pub amount_minor: i64, pub currency: Currency }
pub fn transition(sub: Option<&Subscription>, ev: &VerifiedEvent, now: Instant, grace: Days) -> Outcome;   // Outcome::{Changed(Subscription), IgnoredStale, Unchanged}
pub fn access_level(sub: &Subscription, now: Instant, grace: Days) -> Access;                              // Full | GraceEnded
```

## Business rules

1. Every transition in the table has a test; every other (state, event) pair is `IgnoredStale` or invalid, never a panic.
2. Snapshots are copied at creation and never recomputed.
3. No currency code, price or plan id appears in non-test code.

## Security

- `run_isolation` covers `subscriptions` and `invoices`; `billing_webhook_events` is verified to be tenant-less by an explicit test.
- Paid invoices are immutable.

## Implementation notes

- Tests build plans from a fixture JSON using a test-only currency such as `XTS` (ISO reserved testing code) so no real price leaks into the repository.

## Acceptance

| # | Given / When / Then | Test name |
|---|---|---|
| AC-1 | **Given** a migrated database **When** compared with the DDL **Then** equal including the paid-invoice trigger | `isolation::dom_billing_tables_match_ddl` |
| AC-2 | **Given** two accounts **When** `run_isolation` runs **Then** clean for tenant tables | `isolation::sec_ac_01_billing_isolated` |
| AC-3 | **Given** a second subscription for one account **When** inserted **Then** the UNIQUE rejects it | `isolation::dom_28_one_subscription_per_account` |
| AC-4 | **Given** a paid invoice **When** updated **Then** the trigger rejects it | `isolation::dom_28_paid_invoice_immutable` |
| AC-5 | **Given** line items **When** summed **Then** equal the total (property test) and amounts are integers | `money::bil_fr_01_sum_property` |
| AC-6 | **Given** a plans document **When** validated **Then** bad intervals, negative prices, unknown limit keys are rejected | `plans::bil_fr_01_plans_schema` |
| AC-7 | **Given** each row of the §5 table **When** transitioned **Then** the expected state | `subscription::bil_fr_02_transition_table` |
| AC-8 | **Given** duplicate and out-of-order events **When** transitioned **Then** `IgnoredStale` | `subscription::stale_and_duplicate_ignored` |
| AC-9 | **Given** `past_due` with an injected clock **When** grace elapses **Then** access ends exactly at the boundary | `grace::bil_fr_05_grace_boundary` |
| AC-10 | **Given** billing without currency or plans **When** enabling **Then** `billing_not_configured` | `descriptor::cld_01_enable_guard` |
| AC-11 | **Given** the descriptor **When** validated **Then** toggleable, default off | `descriptor::mod_08_billing_descriptor_valid` |

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
