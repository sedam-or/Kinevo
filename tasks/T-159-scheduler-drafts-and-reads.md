---
id: T-159
title: Implement drafts, the run lease, Sync Now and the schedule read endpoints
milestone: M3
kind: feature
status: todo
depends_on: [T-158]
requirements: [SCH-FR-04, SCH-FR-05, SCH-FR-07, SCH-FR-08, SCH-FR-10, SEC-AC-19, SEC-AC-20, TST-19]
branch: m3-t159-scheduler-drafts-and-reads
budget_lines: 400
assignee: ""
---

# T-159: Implement drafts, the run lease, Sync Now and the schedule read endpoints

## Goal

Users can read the schedule, generate full and sync drafts safely under a per-account lease, and see why each placement was made; nothing changes until a draft is applied.

## Context

> **Endpoints** (`spec/modules/scheduler.md` §3): `GET /schedule/state` → `{version, review_state, review_reason}`; `GET /schedule/assignments?from=&to=` (≤ 62 days, `window_too_large`); `GET /schedule/input?from_date=&to_date=` → `scheduler_input_v1` plus `etag` (≤ `scheduler.sync_max_horizon_days`); `POST /schedule/drafts {kind: full|sync, from_date, to_date}` + `Idempotency-Key` (201 draft, or 200 `{result:"no_changes"}` for sync; `scheduler_busy` 409, `window_too_large` 422, `no_eligible_tasks` 422 full only); `POST /schedule/sync` alias of `kind=sync` with the default horizon; `GET /schedule/drafts?status=`; `GET /schedule/drafts/{id}`; `POST /schedule/drafts/{id}/dismiss` (204; `draft_not_pending`).
> **Lease** (SCH-FR-10): acquire the per-account run lease by inserting a `scheduler_runs` row `running` with `lease_until = now + scheduler.run_lock_seconds`; the unique running row serialises runs; an expired `running` row is marked `failed` with `error_code=lease_expired` by the next acquirer; a second call while running → 409 `scheduler_busy`; the lease is released on completion (`succeeded`/`failed`). A sync with nothing changed returns `no_changes` and sets `review_state='clean'`; otherwise a `sync` draft (status `pending`) is stored. Sync **never applies**.
> **Draft** (SCH-FR-05/07): `full` draft: `fixed` = locked + manual + running-session assignments, all other eligible tasks re-placed (kind column `manual` for user full drafts, `weekly` for the job, `sync` for sync); stores `policy_snapshot`, `input_hash` (SHA-256 of the canonical input), `algorithm_version`, `result` (assignments with reasons, unassigned with violations, moves, conflicts, warnings). Replaying `generate` on the stored input reproduces the stored result (the input is stored inside `result.input` canonical JSON). Drafts never change assignments. Newer draft of the same kind+horizon applied marks older ones `stale` (done by apply in T-160).
> **Validation** (§7): `from_date`/`to_date` `date`, `to ≥ from`, window ≤ sync max (sync) or ≤ 28 days (full). **Events**: `DraftCreated`. **SEC-AC-19**: input endpoint returns only the caller's rows. **SEC-AC-20**: 10 concurrent `POST /schedule/sync` → exactly one runs, the rest `scheduler_busy`. **TST-19**: fault injection around the draft insert leaves no `running` row stuck after lease expiry.

## Scope

**In:**
- Repositories, use cases (`GenerateDraft`, `SyncNow`, `GetDraft`, `ListDrafts`, `DismissDraft`, `GetState`, `ListAssignments`, `GetInput`), handlers, DTOs, OpenAPI regeneration.
- The running-sessions fixed set comes from a `SessionGuardPort` extension `running_slots(tx)` (the port exists since T-107; default no-op until M4).

**Out:**
- Apply, manual placement, lock, remove, export: T-160. Weekly job and subscribers: T-161.

## Files

| Path | Action | Notes |
|---|---|---|
| `crates/kinevo-mod-scheduler/src/{app,infra,http}/drafts.rs, runs.rs, reads.rs` | create |  |
| `crates/kinevo-ports/src/sessions.rs` | modify | `running_slots` with a no-op default |
| `crates/kinevo-mod-scheduler/tests/{drafts_api,sync_api,reads_api,lease}.rs` | create | AC-1…AC-10 |
| `contracts/openapi.json, web/src/api/schema.d.ts` | modify | regenerated |

## Interfaces

```rust
pub enum DraftResult { Created(Draft), NoChanges }
// POST /schedule/drafts → 201 Draft | 200 { "result": "no_changes" }
```

## Business rules

1. Generate and sync hold the lease for their whole computation; the lease row is closed in the same transaction as the draft insert.
2. A sync draft contains only moves and new placements for tasks that need them; unaffected assignments are untouched.
3. A full draft with zero eligible tasks is 422 `no_eligible_tasks`.
4. All draft reads and writes are in the account `Tx`.

## Security

- Only `draft_id` is trusted later; the result stored here is the only source apply uses (SEC-AC-18 is enforced in T-160).
- Rate limits: draft creation uses the standard write bucket; `scheduler_busy` includes `Retry-After` from the lease expiry.

## Implementation notes

- `GET /schedule/input` is read-only and does not take the lease.
- Draft `kind` mapping: user full → `manual`, job full → `weekly`, sync → `sync`; the API exposes `full|sync`.

## Acceptance

| # | Given / When / Then | Test name |
|---|---|---|
| AC-1 | **Given** eligible tasks **When** a full draft is created **Then** 201 with reasons per placement, a policy snapshot and an input hash, and no assignment rows exist | `drafts_api::sch_fr_04_draft_has_reasons_and_changes_nothing` |
| AC-2 | **Given** a stored draft **When** `generate` is replayed on its stored input after policies changed **Then** the result equals the stored result | `drafts_api::sch_fr_05_replay_reproduces_result` |
| AC-3 | **Given** no eligible tasks **When** a full draft is requested **Then** 422 `no_eligible_tasks` | `drafts_api::no_eligible_tasks` |
| AC-4 | **Given** nothing changed **When** Sync Now runs **Then** 200 `no_changes` and `review_state=clean` | `sync_api::sch_fr_10_no_changes_sets_clean` |
| AC-5 | **Given** a conflicting task **When** Sync Now runs **Then** a `sync` draft with only that task's move | `sync_api::sch_fr_07_sync_draft_moves_only_conflicts` |
| AC-6 | **Given** 10 concurrent sync requests **When** run **Then** exactly one succeeds and the others get `scheduler_busy` | `sync_api::sec_ac_20_one_runs` |
| AC-7 | **Given** an expired lease **When** a new run starts **Then** the old row is `failed` with `lease_expired` and the run proceeds | `lease::sch_fr_10_expired_lease_recovered` |
| AC-8 | **Given** failure injected before the draft insert **When** retried after expiry **Then** no stuck running row and no partial draft | `lease::tst_19_fault_injection` |
| AC-9 | **Given** two accounts **When** `GET /schedule/input` is called **Then** only own rows appear | `reads_api::sec_ac_19_input_isolated` |
| AC-10 | **Given** a window over 62 days or over the sync maximum **When** requested **Then** 422 `window_too_large` | `reads_api::window_limits` |

## Tests

```
cargo nextest run -p kinevo-mod-scheduler --features kinevo-db/testing   # DB tests environment-limited without Docker/PostgreSQL
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
