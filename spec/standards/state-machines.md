# Standard — State machines (SM)

Every stateful entity has a closed set of states and transitions; code implements them as a pure `transition(state, event, ctx) -> Result<State, TransitionError>` function in `domain/` (CA-16) and a table-driven test that enumerates **all** (state × event) pairs (allowed ⇒ expected state; others ⇒ `invalid_transition`). The full tables live next to their behaviour; this index lists them and fixes the machines that have no other home.

| ID | Entity | Where specified |
|---|---|---|
| SM-01 | Instance (`uninitialised → ready`) | `modules/admin.md` §5 |
| SM-02 | Account status | `modules/identity.md` §4 |
| SM-03 | Login attempt / lockout | `modules/identity.md` §4 |
| SM-04 | Refresh token family and token | `modules/identity.md` §4, `standards/auth.md` §3 |
| SM-05 | Goal status | `modules/goals.md` §4 |
| SM-06 | Milestone status | §2 below |
| SM-07 | Task status | `modules/tasks.md` §4 |
| SM-08 | Workspace (active/archived) | §2 below |
| SM-09 | Schedule draft | `modules/scheduler.md` §5 |
| SM-10 | Scheduler run lease | `modules/scheduler.md` §5 |
| SM-11 | Schedule review state | `modules/scheduler.md` §5 |
| SM-12 | Assignment (current/superseded) | §2 below |
| SM-13 | Focus session | `modules/execution.md` §5 |
| SM-14 | AI proposal | `modules/ai.md` §6 |
| SM-15 | Offline operation (ledger) | `modules/sync.md` §5 |
| SM-16 | Subscription | `modules/billing.md` §5 |
| SM-17 | Job | §2 below |
| SM-18 | Outbox delivery | §2 below |
| SM-19 | Idempotency record | §2 below |
| SM-20 | Setup wizard session | §2 below |
| SM-21 | Account export | §2 below |
| SM-22 | One-time tokens (invite, reset, setup) | §2 below |
| SM-23 | Module switch | `03-module-system.md` §4 |
| SM-24 | Client sync-queue item | `modules/sync.md` §4 (SYN-C2) |

## 1. Rules for all machines

| ID | Rule |
|---|---|
| SM-R1 | A transition to the same state is a successful no-op (idempotent), changes nothing (no version bump, no event). |
| SM-R2 | Every transition names its **actor** (user, system job, another module) and is rejected if the actor is not allowed (`invalid_transition` or `forbidden`). |
| SM-R3 | Guards read only data in the same transaction; failing a guard leaves everything unchanged and returns a registry error with the current state. |
| SM-R4 | A transition that has side effects on another aggregate does them in the same transaction through a port (CA-08) and emits one event. |
| SM-R5 | Terminal states are explicit; any exit from a "terminal" state is a documented exception (task reopen, D-36). |
| SM-R6 | The set of allowed next transitions is exposed to clients (`allowed_transitions`) so UI never hard-codes rules (CA-15). |

## 2. Machines specified here

### SM-06 Milestone

| From | Event | Actor | Guard | To | Failure / recovery |
|---|---|---|---|---|---|
| `open` | mark done | user | goal exists | `done` | `version_conflict` ⇒ reload |
| `done` | reopen | user | — | `open` | — |
| any | delete | user | — | (removed) | tasks lose `milestone_id` in the same transaction |

Event: `MilestoneStatusChanged`; when the last open milestone becomes `done`, also `GoalMilestonesAllDone`.

### SM-08 Workspace

| From | Event | Actor | Guard | To | Failure / recovery |
|---|---|---|---|---|---|
| `active` | archive | user | not default | `archived` | default ⇒ `workspace_is_default` |
| `archived` | unarchive | user | name not used by an active workspace | `active` | `workspace_name_taken` ⇒ rename first |
| `active`/`archived` | delete | user | empty, not default | (removed) | `workspace_not_empty` with counts |

### SM-12 Assignment

| From | Event | Actor | Guard | To | Failure / recovery |
|---|---|---|---|---|---|
| (none) | apply draft / manual place | scheduler | validate OK, no overlap (DB exclusion) | `current` | violation ⇒ `draft_infeasible` / `placement_invalid`; DB overlap error mapped to `placement_invalid` |
| `current` | superseded by newer apply / unschedule / delete task | scheduler | not locked unless user action | `superseded` | locked ⇒ untouched |
| `current` | lock / unlock | user | — | `current` (locked flag) | — |

### SM-17 Job

| From | Event | Guard | To | Failure / recovery |
|---|---|---|---|---|
| `queued` | claim | `run_at ≤ now`, module enabled | `running` (`locked_until = now + lease`) | claim race ⇒ one winner (`SKIP LOCKED`) |
| `running` | success | — | `succeeded` | — |
| `running` | error, attempts < max | — | `queued` (`run_at = now + backoff(attempts)`) | backoff = `base × 2^attempts`, capped |
| `running` | error, attempts ≥ max | — | `dead` | audit `job.dead`; visible in summary; admin can requeue by CLI |
| `running` | lease expired (worker crash) | sweeper | `queued` (attempts + 1) | handler idempotent |

### SM-18 Outbox delivery

| From | Event | Guard | To | Failure / recovery |
|---|---|---|---|---|
| `pending` | handler OK | — | `delivered` | — |
| `pending` | handler error, attempts < max | — | `pending` (`next_attempt_at` backoff) | — |
| `pending` | handler error, attempts ≥ max | — | `dead` | audit; admin retry (`not_dead` if not dead) |
| `dead` | admin retry | `admin.jobs.retry` | `pending` (attempts reset) | — |

### SM-19 Idempotency record

| From | Event | To | Notes |
|---|---|---|---|
| (none) | first request with key | `in_flight` (row inserted, `status_code` null) | concurrent same key ⇒ 409 `idempotency_in_progress` |
| `in_flight` | handler finishes with deterministic status (2xx/4xx except 429) | `stored` (status+body) | replay returns it |
| `in_flight` | 5xx/429/crash | row deleted (or expires at `api.idempotency.ttl`) | retry re-executes |
| `stored` | same key, same hash | replay | header `Idempotency-Replayed: true` |
| `stored` | same key, different hash | — | 422 `idempotency_key_reuse` |
| `stored` | ttl elapsed | (deleted by job) | key reusable |

### SM-20 Setup wizard session

`created (token ok, step 1) → step 2 → step 3 → finished`; back navigation allowed; `finished` and `expired` (after `KINEVO_SETUP_SESSION_TTL`) are terminal; only one `finished` per instance (SET-03).

### SM-21 Account export

| From | Event | To | Failure / recovery |
|---|---|---|---|
| (none) | request | `queued` | second request while active ⇒ `export_in_progress` |
| `queued` | worker picks | `running` | crash ⇒ lease expiry ⇒ `queued` |
| `running` | built | `ready` (bytes, `expires_at`) | error ⇒ `failed` (user may request again) |
| `ready` | `expires_at` elapsed | `expired` (bytes deleted) | download ⇒ `export_expired` |

### SM-22 One-time token

`issued → used` (atomic `UPDATE … WHERE used_at IS NULL AND expires_at > now` returning 1 row) or `issued → expired`. Reuse or expiry ⇒ `invite_invalid` / `reset_token_invalid` / `setup_token_invalid`. Tokens are hashes; issuing a new reset token invalidates earlier unused ones for that account.

## 3. Requirements

| ID | Requirement | Acceptance |
|---|---|---|
| SM-T1 | Each machine has an exhaustive table-driven test (all pairs) generated from its definition. | CI |
| SM-T2 | The task and goal machines are the single source for `allowed_transitions` in API responses; the web renders buttons from it. | Contract + UI test |
| SM-T3 | Transition events carry `from` and `to` as enums (no content). | Event schema test |
