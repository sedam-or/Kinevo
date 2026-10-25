# Module `scheduler`

Kind: core. Depends on: `tasks`, `landscape`. Tables: `schedule_states`, `schedule_assignments`,
`schedule_drafts`, `scheduler_runs`. Crates: `kinevo-scheduler` (pure) + `kinevo-mod-scheduler` (application/infra/http). Prefix: `SCH`.
Behaviour is ported from legacy ADR-003/015/016 and `legacy-v0:server/app/Domain/Scheduling/*`
(read with `git show`); deviations are listed in §12. **The server is the only authority** (D-22):
clients may *preview* with the WASM build, never persist a preview.

## 1. Permissions

`scheduler.schedule.read`, `scheduler.draft.create|apply|dismiss`, `scheduler.assignment.place|lock|remove`, `scheduler.export.read` — `account_owner`, `instance_admin` (own data).

## 2. The pure crate `kinevo-scheduler`

### 2.1 Contract

`generate(input: SchedulerInput) -> SchedulerOutput` — a total, pure function. No I/O, no clock, no randomness, no
time zones, no floating point in decisions, no hash-map iteration order, no `async`. Compiles for native and `wasm32-unknown-unknown`.
Instants are `i64` seconds since the Unix epoch; durations are `u32` minutes. Canonical JSON wire shapes (`scheduler_input_v1`,
`scheduler_output_v1`) are in `standards/contracts.md` §Scheduler. `algorithm_version` = `"sched-1"`; any change that can alter an output bumps it.

**Input** (`scheduler_input_v1`)

| Field | Meaning |
|---|---|
| `horizon` | `[start, end)` |
| `days[]` | Pre-computed daily planning windows `[start, end)` inside the horizon, sorted, non-overlapping, each with `anchor_not_before` (instant ≥ start). Produced by the application layer from the account timezone and `scheduler.day_start/day_end/anchor.earliest_time` (this is how time zones stay out of the crate and the WASM build). |
| `busy[]` | Effective Hard Landscape occurrences as ranges (any order, may overlap). |
| `fixed[]` | Ranges that automation must not move or overlap: locked/manual assignments, assignments kept by a sync run, running sessions' slots. Each has `task_id`. |
| `tasks[]` | Tasks to place: `task_id`, `duration_minutes` (already defaulted), `priority_tier` 1–3, optional `goal_deadline`, `milestone_deadline`, `task_deadline`, `progress_pct`, `is_anchor`, optional `current_slot`. |
| `params` | `min_slot_minutes`, `reserve_bp`, `anchor_duration_minutes`, `daily_capacity_bp` (nullable). |

**Output** (`scheduler_output_v1`): `assignments[]` `{ task_id, start, end, reasons[] }`; `unassigned[]` `{ task_id, code, violations[] }`; `moves[]` `{ task_id, from?, to }` (only when `current_slot` was given); `conflicts[]` task ids that had a `current_slot` but could not be re-placed; `algorithm_version`.
`reasons` and `violations` are `{ code, params }` pairs; the UI renders them through message keys (`placement.reason.<code>`, `placement.violation.<code>`).

### 2.2 Algorithm (normative)

```
1. ranked  = sort(tasks excluding anchors, by RANK_KEY)                     -- §2.3
2. anchor  = the task with is_anchor (at most one); if it already appears in fixed[], skip step 4a
3. occupied = busy ∪ fixed ranges ; placed = {} ; unassigned_reason = {}
4. for each day D in days (ascending):
     slots   = free_slots(D, occupied)                                       -- §2.4
     ceiling = daily_ceiling(slots, params.daily_capacity_bp)                -- §2.5 (null = no cap)
     scheduled_today = 0
     a. if anchor not placed: find_anchor_slot(slots, D.anchor_not_before, params.anchor_duration_minutes)
          if found and fits_cap(ceiling, scheduled_today, anchor_duration): place anchor there (reason ANCHOR_FIRST_SLOT);
             occupied += slot ; scheduled_today += anchor_duration
     b. for each task T in ranked, not yet placed:
          if not fits_cap(ceiling, scheduled_today, T.duration): unassigned_reason[T] = CAPACITY_CAP ; continue
          for each slot S in slots (ascending, recomputed after each placement on this day):
               if S.length < T.duration: continue
               candidate = [S.start, S.start + T.duration)
               if feasible(candidate, T)  → place T at candidate (reasons §2.7) ; occupied += candidate ; scheduled_today += T.duration ; break
5. any anchor/ranked task not placed → unassigned with code:
     CAPACITY_CAP if it was ever blocked by the cap, else NO_AVAILABLE_ANCHOR_SLOT (anchor) or NO_AVAILABLE_SLOT
6. moves/conflicts computed from current_slot (§2.8)
7. output assignments sorted by (start, task_id); unassigned sorted by task_id
```

Slots are recomputed from `occupied` after every placement (placing a task splits the slot), so the next task takes the next free minute. An early exit when everything is placed is an optimisation, not behaviour.

### 2.3 Ranking (replaces the 9-component engine; same effective order)

`RANK_KEY` is compared lexicographically; all keys are integers:

1. `priority_tier` ascending (1 first)
2. `goal_deadline` ascending, absent last
3. `milestone_deadline` ascending, absent last
4. `task_deadline` ascending, absent last
5. `progress_pct` **descending** (finish what is started)
6. `task_id` ascending (UUIDv7 ⇒ roughly creation order; total tie-break)

Legacy had nine components, but four (context fit, fragmentation penalty, continuity preference, duration fit) were constants
in practice because nothing ever populated them (`legacy-v0:server/app/Application/Scheduling/AssembleScheduleInput.php`); they are dropped (simplicity first). Reintroducing one requires an RFC and a `sched-2` bump.

### 2.4 Free slots

`free_slots(D, occupied)`: take the day range, subtract the merged `occupied` ranges (adjacent and overlapping ranges merge), keep gaps whose length ≥ `min_slot_minutes`.
Ranges are half-open, so a task may start exactly when a busy range ends.

### 2.5 Daily capacity ceiling

`ceiling = floor(sum(slot_minutes) * daily_capacity_bp / 10000)` using integer arithmetic on the day's free minutes **before** step 4a; `null` ⇒ no ceiling. `fits_cap(c, used, d) = c is null OR used + d ≤ c`.

### 2.6 Hard constraints (checked for every candidate, all rules evaluated, order = precedence)

| # | Code | Violated when |
|---|---|---|
| 1 | `HARD_LANDSCAPE_COLLISION` | candidate overlaps any `busy` range |
| 2 | `LOCKED_TASK_MOVE` | task is locked/fixed and candidate differs from its fixed range (cannot occur in `generate`, kept for the validator in §5) |
| 3 | `ANCHOR_VIOLATION` | anchor candidate: duration ≠ `anchor_duration_minutes`, or starts before its day's `anchor_not_before`, or is not marked locked on apply |
| 4 | `TEMPORAL_VALIDITY` | candidate not inside the horizon, or not inside one planning window |
| 5 | `DEADLINE_FEASIBILITY` | `task_deadline` set and candidate end > deadline |
| 6 | `DURATION_FIT` | task duration > candidate length (never true for generated candidates; used by the validator) |
| 7 | `ILLEGAL_OVERLAP` | candidate overlaps any `fixed`/already-placed range or another candidate |
| 8 | `SAFETY_RESERVE` | merged busy minutes (busy ∪ fixed ∪ placed ∪ candidate, **clipped to the planning windows**) > `floor(capacity_minutes * (10000 − reserve_bp) / 10000)`, where `capacity_minutes` = total minutes of all planning windows in the horizon |

The same rule set is exposed as `validate(candidate, context) -> Vec<Violation>` and used by apply (§5) and manual placement.

### 2.7 Placement reasons

Each placed task gets, in order: `RANK_PRIORITY{tier}`, `RANK_GOAL_DEADLINE{at}` / `RANK_MILESTONE_DEADLINE{at}` / `RANK_TASK_DEADLINE{at}` (only those present), `RANK_PROGRESS{pct}` if > 0, then exactly one of `ANCHOR_FIRST_SLOT`, `FIRST_FITTING_SLOT{day_index}`. No free text.

### 2.8 Reschedule (Sync Now) — `propose_reschedule`

Not a separate algorithm: the application layer builds the input so that `fixed` contains every locked/manual assignment **and every non-locked current assignment that is still feasible** (re-validated with §2.6 against current landscape/policies, excluding the task's own range from overlap checks), and `tasks` contains only tasks that are unscheduled or whose slot became infeasible, with `current_slot` set when they had one. Then `moves` = tasks with `current_slot` placed elsewhere; `new placements` = tasks with no `current_slot`; `conflicts` = tasks with a `current_slot` and no new placement.

## 3. Endpoints

| Method / path | Success | Errors |
|---|---|---|
| `GET /schedule/state` | 200 `{ version, review_state, review_reason }` | — |
| `GET /schedule/assignments?from=&to=` | 200 current assignments with task summary | `window_too_large` (> 62 days) |
| `GET /schedule/input?from_date=&to_date=` | 200 `scheduler_input_v1` for client preview (offline); includes `etag = hash` | `window_too_large` (> `scheduler.sync_max_horizon_days`) |
| `POST /schedule/drafts` `{ kind: "full"\|"sync", from_date, to_date }` + `Idempotency-Key` | 201 draft (or 200 `{ result: "no_changes" }` for `sync`) | `scheduler_busy` 409, `window_too_large` 422, `no_eligible_tasks` 422 (full only) |
| `POST /schedule/sync` | alias of `kind="sync"` with the default horizon | same |
| `GET /schedule/drafts?status=` / `GET /schedule/drafts/{id}` | 200 | `not_found` |
| `POST /schedule/drafts/{id}/apply` `{ base_version, task_ids? }` + `Idempotency-Key` | 200 `{ version, applied, skipped }` | `schedule_version_conflict` 409, `draft_infeasible` 409 (`details.violations[]`), `draft_not_pending` 409, `task_ids_not_in_draft` 422 |
| `POST /schedule/drafts/{id}/dismiss` | 204 | `draft_not_pending` |
| `PUT /schedule/assignments/by-task/{task_id}` `{ start_at, end_at, locked?, base_version }` | 200 | `placement_invalid` 422 (`details.violations`), `schedule_version_conflict`, `task_not_schedulable` 409 |
| `POST /schedule/assignments/{id}/lock` `{ locked, base_version }` | 200 | `version_conflict` |
| `DELETE /schedule/assignments/{id}` | 204; task → `backlog` | `task_in_progress` 409 |
| `GET /schedule/export.ics?from=&to=` | 200 `text/calendar` | — |

## 4. Functional requirements

| ID | Requirement | Acceptance |
|---|---|---|
| SCH-FR-01 | **Determinism.** Same `SchedulerInput` ⇒ byte-identical `SchedulerOutput` (canonical JSON) on every run, platform and build target, including `wasm32`. | Golden fixtures + property test (input permutation of `tasks`/`busy`/`fixed` yields identical output) + native-vs-WASM equality test (TST-05). |
| SCH-FR-02 | **Purity.** `kinevo-scheduler` has no dependency on I/O, clock, rand, tz, sqlx, tokio (CI dependency allow-list, CA-06). | `cargo-deny` + dependency-tree test. |
| SCH-FR-03 | Ranking, slots, ceiling, hard rules and anchor placement are exactly §2.2–§2.6. | Table tests per rule; fixtures derived from legacy tests with the planning window set to the full day and integer reserve (see §12.4). |
| SCH-FR-04 | **Explainability.** Every placement has `reasons[]`, every unassigned task a `code` and the violations of its last attempt. `GET /schedule/drafts/{id}` returns them; the UI shows "Why here?" for each. | Contract test; UI test. |
| SCH-FR-05 | **Policy snapshot.** A draft stores `policy_snapshot` (all `params` and window inputs actually used) and `input_hash` (SHA-256 of the canonical input). Re-running `generate` on the stored input reproduces the stored result. | Replay test after changing policies. |
| SCH-FR-06 | **Assembly (application layer).** Eligible tasks: status ∈ {`backlog`,`scheduled`,`partial`,`continued`,`missed`,`conflict`} and (no goal or goal `active`); excluded: `in_progress` (its slot is `fixed`), terminal. `duration_minutes = estimate_minutes ?? scheduler.default_task_minutes` (anchor: `scheduler.anchor.duration_minutes`). Deadlines: goal/milestone `target_date` → the instant at the **start of the next local day** (exclusive end of the target date); task `due_at` as is. Days: for each local date intersecting the horizon, window = `[date@day_start, date@day_end)` ∩ horizon; `anchor_not_before = max(window.start, date@anchor.earliest_time)`. | Table tests incl. DST days and a UTC+7 account. |
| SCH-FR-07 | **Full draft** (`kind=full`): `fixed` = locked + manual + running-session assignments; all other eligible tasks are re-placed. **Sync draft** (`kind=sync`): §2.8. | Tests for both. |
| SCH-FR-08 | **Draft lifecycle** (§5): `pending → applied | dismissed | stale`. One pending weekly draft per `(account, horizon_start)`. Drafts never change assignments until applied. | State tests. |
| SCH-FR-09 | **Apply is safe.** In one transaction: lock `schedule_states` row; `base_version` must equal current → else 409 `schedule_version_conflict` and the draft becomes `stale`; **re-validate every draft assignment with `validate`** against the *current* landscape, fixed set and policies (not against the client-supplied draft) → any violation: 409 `draft_infeasible`, draft `stale`, nothing applied; supersede replaced assignments, insert new (anchor with `locked=true`), set tasks `scheduled` via `TaskCommandPort`, bump `version`, set `review_state='clean'`, mark draft `applied`. Re-applying an already-applied draft returns 200 with the same body (idempotent). | Concurrency test (two applies race → exactly one succeeds, the other replays or conflicts); infeasible-draft test; DB overlap constraint never fires in normal flow (but a test proves it would). |
| SCH-FR-10 | **Sync Now** (`POST /schedule/sync`): acquires the per-account run lease (`scheduler_runs` unique running row; lease = `scheduler.run_lock_seconds`; a second call while running → 409 `scheduler_busy`); builds a sync input; if nothing changes, responds `no_changes` and sets `review_state='clean'`; otherwise stores a `sync` draft (status `pending`) and returns it. It never applies anything. The lease is released on completion or expiry (crash safety). | Concurrency + crash-recovery test (lease expiry). |
| SCH-FR-11 | **Weekly draft job** `scheduler.weekly_draft`: per account, at `scheduler.weekly_draft.weekday` + `local_time` in the account timezone (if `scheduler.weekly_draft.enabled`), creates a `weekly` `full` draft for the next planning week unless a pending one exists or the week has no eligible tasks; creates an in-app notice `schedule.weekly_draft_ready`. Never auto-applies. Uses the unique key `(kind, account_id, scheduled_for)`. | Job test with injected clock; no double draft after a worker restart. |
| SCH-FR-12 | **Needs review.** On `LandscapeChanged`, `TaskSchedulingInputsChanged`, `GoalStatusChanged`, `PolicyChanged` (scheduler keys), `TaskStatusChanged`: re-validate current assignments (not locked ones' *positions*, but all rules); if any violation exists set `review_state='needs_review'` with `review_reason` = first violation code; **never move anything**. | Test per trigger. |
| SCH-FR-13 | **Locks.** `locked=true` assignments (and `source='manual'`) are never moved or superseded by `full`/`sync` drafts; only the user can unlock/remove them. Locking a past assignment is allowed. | Test. |
| SCH-FR-14 | **Manual placement** validates rules 1, 4 (horizon n/a; must be a valid range), 7 and DB overlap; ignores 5 and 8 but returns `warnings` `deadline_missed`/`reserve_exceeded`. Result `source='manual'`, `locked` per request (default true). | Tests. |
| SCH-FR-15 | **Scope.** The schedule is account-wide: every workspace's tasks compete for the same time (WS-FR-06). | Multi-workspace test. |
| SCH-FR-16 | **Past.** The horizon start is clamped to `now` rounded **up** to the next `scheduler.min_slot_minutes` boundary (the Clock is injected); assignments are never created in the past. (Legacy could place tasks in the past.) | Test with injected clock. |
| SCH-FR-17 | **Performance budget** `[ASSUMED]`: ≤ 500 eligible tasks over 28 days generate in < 1 s on a 2-vCPU server; budget verified by a benchmark task, not a CI gate. | Bench report. |
| SCH-FR-18 | **Offline preview.** The WASM build exposes `generate(input_json) -> output_json`; the PWA caches the last `GET /schedule/input` and may show a clearly labelled "preview (not saved)" result offline; Apply requires connectivity. | E2E offline test. |

## 5. State machines

**Draft**

| From | Event | Guard | To | Failure/recovery |
|---|---|---|---|---|
| (new) | generate | run lease acquired | `pending` | lock busy → 409 `scheduler_busy` |
| `pending` | apply | version ok, all assignments valid | `applied` | version differs → `stale` + 409; invalid → `stale` + 409 `draft_infeasible` (user generates a new draft) |
| `pending` | dismiss | — | `dismissed` | — |
| `pending` | newer draft of same kind+horizon applied | — | `stale` | informational |
| `applied` | apply again | — | `applied` | idempotent 200 |

**Run lease**: `running` (lease_until) → `succeeded | failed` (terminal); expired `running` rows are marked `failed` with `error_code=lease_expired` by the next acquirer.

**Review state**: `clean ↔ needs_review` (set by SCH-FR-12; cleared by apply or sync `no_changes`).

## 6. Data flow — Apply draft (one transaction)

```
BEGIN (account context)
  SELECT schedule_states FOR UPDATE ; SELECT draft FOR UPDATE
  guard: draft.status = pending (or applied → return stored result) ; base_version = state.version
  rebuild context: effective landscape (landscape port), fixed set, policies at "now" (NOT snapshot)
  for a in assignments (task_ids filter): violations = validate(a, context) ; if any → mark draft stale, COMMIT that marker, return 409
  UPDATE schedule_assignments SET state='superseded', superseded_at, superseded_by_draft WHERE replaced set
  INSERT schedule_assignments (...) ; tasks → scheduled via TaskCommandPort (same tx)
  UPDATE schedule_states SET version = version+1, review_state='clean'
  UPDATE draft SET status='applied', decided_at ; INSERT outbox(AssignmentsApplied)
COMMIT
```
Failure: any error rolls back everything; the draft stays `pending`; the client may retry with the same idempotency key.

## 7. Validation

| Field | Kind | Rule |
|---|---|---|
| `from_date`/`to_date` | `date` | `to ≥ from`; window ≤ `scheduler.sync_max_horizon_days` (sync) or ≤ 28 days (full) |
| `start_at`/`end_at` | `instant` | `end > start`, duration ≤ 24 h, ≥ `scheduler.min_slot_minutes` |
| `task_ids` | `uuid[]` | ≤ 500 |
| `base_version` | `int` | ≥ 0 |

## 8. Events / jobs

Emits: `DraftCreated`, `AssignmentsApplied`, `AssignmentSuperseded`, `AssignmentLockChanged`, `ScheduleNeedsReview`. Consumes: `LandscapeChanged`, `TaskSchedulingInputsChanged`, `TaskStatusChanged`, `TaskDeleted`, `GoalStatusChanged`, `GoalDeleted`, `PolicyChanged`.
Jobs: `scheduler.weekly_draft` (tick), `scheduler.lease.sweep` (every tick: expire stale leases), `scheduler.drafts.prune` (daily: delete non-pending drafts older than 30 days).

## 9. Failure modes

| Failure | Behaviour |
|---|---|
| Landscape changes while a draft is pending | Draft still pending; apply re-validates and may return `draft_infeasible` |
| Worker dies during sync | Lease expires; next sync works; no partial writes (sync writes only the draft row) |
| Two devices apply different drafts | One wins; the other gets `schedule_version_conflict`; UI shows "schedule changed — review again" |
| Input window contains > 5 000 landscape occurrences | Truncated with warning (LAND-FR-04); draft carries `warnings` |

## 10. UI states

`schedule.draft.pending`, `schedule.draft.applied`, `schedule.draft.stale`, `schedule.review.needed`, `schedule.sync.no_changes`, `schedule.sync.busy`, `placement.explain` (`standards/ux-states.md`).

## 11. Abuse cases

SEC-AC-18: apply a forged draft (client edits `result` JSON) → server ignores the client body; only `draft_id` and `base_version` are accepted, and re-validation runs on the **stored** draft. SEC-AC-19: scheduler input endpoint returns only the caller's rows (RLS test). SEC-AC-20: 10 concurrent `POST /schedule/sync` → exactly one runs.

## 12. Differences from legacy (intentional) and parity strategy

1. Day windows (D-33) and "no scheduling in the past" (SCH-FR-16).
2. Ranking reduced to the 5 effective keys + deterministic id tie-break (§2.3).
3. Apply re-validates server-side (legacy trusted the client draft and checked the version outside the transaction).
4. Safety reserve uses integer basis points; legacy used floating point `floor(capacity * (1 − p/100))`, which can differ by one minute for some capacities. Parity fixtures are generated by running the Rust crate and **reviewing diffs against the legacy expectations**; each differing fixture is listed in `tasks/` with the reason. Fixtures never silently follow either implementation.
5. Anchor duration/earliest time/window are policies; anchor assignments are created `locked=true`; an anchor with no qualifying slot is reported `NO_AVAILABLE_ANCHOR_SLOT` and is **never** placed by the ranked loop (legacy could fall through to a rule violation).
6. Boost-derived daily capacity is replaced by the optional policy `scheduler.daily_capacity_bp` (D-24).
