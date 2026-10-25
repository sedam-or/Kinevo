# ADR-0004 — One schema, `account_id` on every row, PostgreSQL row-level security forced

- Status: **Accepted** (2026-10-25) — D-08; owner chose "Toggle + RLS".

## Context
The cloud hosts many single-user accounts; self-hosters usually have one or a few. Legacy isolation was a hand-written `where user_id = ?` in ≈ 149 places (`00-verdict.md` §5.1). One missed filter leaks another person's plans, notes or schedule.

## Decision
- All tenant tables carry `account_id` and have RLS **enabled and forced** with a policy comparing to `current_setting('app.account_id')` (ARCH-20…22, `06-domain-model.md` §2).
- The application connects as a non-superuser, non-owner role without `BYPASSRLS`; migrations run as a separate owner role; a third narrowly-granted system role handles login lookups and worker bookkeeping.
- `kinevo-db::Tx::begin(account_id)` sets the context with `SET LOCAL` and is the only way to reach tenant tables; the tenant comes only from the authenticated context.
- Startup refuses to run if isolation checks fail; CI generates isolation tests for every tenant table and runs them as the application role.

## Alternatives
| Option | For | Against |
|---|---|---|
| Repository-level filters only | simplest | one bug = data leak; no defence in depth |
| Schema per tenant | strong isolation | migration fan-out, connection management, poor fit for thousands of tiny accounts |
| Database per tenant | strongest | operational cost, not "one binary, one DB" |

## Consequences
- Every query path must run inside a `Tx`; background jobs switch into the account context; instance-level tables are accessed through the system role.
- Minor query overhead; indexes lead with `account_id`.
- Pooled-connection hygiene is critical: `SET LOCAL` scopes the setting to the transaction, so it cannot leak across pool reuse (tested, RM-R5).
- **Revisit if** a feature needs cross-account queries (e.g., teams) — that would be a new product decision (PRD-X1).
