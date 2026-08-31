# ADR-015 — Effective Schedule Resolution and Override Precedence

### Status
Accepted — 2026-08-30. Authoritative for Effective Schedule stabilization work
(P28 active phase doc execution order (formerly P28_STABILIZATION_PLAN) steps 1–5). No feature implementation is authorized by this
document; implementation proceeds only through the ES-IMPL slices it defines.

**Amendment (2026-08-30, ES-FIX-00):** during ES-IMPL-01 verification a narrow engine gap
was discovered: `RecurrenceOccurrenceGenerator` enforced window + COUNT + max-guard but NOT
UNTIL, while this ADR's test matrix (and the subset documented by `RecurrenceRule` and the
ICS importer) requires UNTIL to bound generation. Canonical interpretation adopted: UNTIL
is enforced inside the generator — inclusive; date-only UNTIL (`YYYYMMDD`) includes its
local date; datetime UNTIL includes its exact instant; a trailing `Z` is UTC normalized to
the DTSTART timezone; COUNT + UNTIL → whichever terminates first. Enforcing it changed ICS
export output only for UNTIL rules that previously leaked occurrences past their UNTIL —
treated as a BUG FIX against the already-documented recurrence contract, not an API break.
`RecurrenceRule::parseUntil` additionally normalizes UTC (`Z`) UNTIL values (previously
ignored). A second windowed-query normalization was required by the same investigation:
RFC-5545 COUNT counts occurrences from DTSTART (the series start), not from the query
window — the generator now scans from the series start only for COUNT-bound rules (all
other rules remain window-bounded as before) and emits only in-window occurrences. The
core Effective Schedule decision is unchanged.

### Context

The repository audit (frozen snapshot `docs/audit/KINEVO_IMPLEMENTATION_BASELINE_2026-08-30.md`)
established CURRENT facts about scheduling:

1. Recurring Hard Landscape is expanded **only** during ICS export
   (`ExportScheduleIcsUseCase::addRecurring` is the sole consumer of
   `RecurrenceRule` + `RecurrenceOccurrenceGenerator`).
2. `ScheduleQueryService::dayView` and `ScheduleDraftController::assemble` read raw
   `hard_landscape_events` rows via `HardLandscapeRepository::listForUserOnDate/InRange`
   — a `FREQ=WEEKLY` course created by KRS confirmation
   (`ConfirmKrsImportUseCase` writes `FREQ=WEEKLY`) never appears on future occurrence
   dates in Today, Week, Month, or draft inputs (BLOCKER-ES-01).
3. `ScheduleOverride` records (Permanent Shift, One-Time Exception) are stored and
   validated but never applied: `ScheduleOverrideRepository::listForSource` has zero
   callers; `ScheduleQueryService` never reads overrides (BLOCKER-ES-02/03).
4. `SchedulePrecedence` (an explicit, ordered placement-source precedence) has zero
   consumers (unused abstraction).
5. Superseded `task_assignments` rows are hard-deleted during draft/reschedule apply
   (`EloquentScheduleAssignmentRepository` delete path); there is no history table and
   `ActivityEventType` contains no schedule-change events (BLOCKER-ES-04).
6. Locked-task enforcement exists and is tested (`LockedTaskMoveRule`,
   `ScheduleAssignmentLockedConflict`, rescheduler skip) but no reachable producer sets
   `locked=true`; `ScheduleDraftController::assemble` hardcodes `isLocked: false`
   (BLOCKER-ES-05).
7. `weekView` and `monthView` do not surface Hard Landscape at all (summary rows only).

Existing, reusable abstractions that MUST be extended rather than duplicated:

- `RecurrenceRule` (bounded RFC-5545 subset: DAILY/WEEKLY, INTERVAL, BYDAY, COUNT/UNTIL)
  and `RecurrenceOccurrenceGenerator` (window-bounded, deterministic, time-of-day pinned
  to DTSTART timezone, excluded-dates support, max-1000 guard, `ksort` ordering).
- `ScheduleOverride` aggregate: `type` (`permanent` | `one_time`), `effective_from/to`
  (deactivation window; one_time requires a single date), `override_start_at/end_at`
  (replacement interval), `reason`; write-side conflict via `overlapsOverrideWith` → 409.
- `SchedulePrecedence`: `ORDINARY(0) < RECURRING(1) < EXPLICIT_OVERRIDE(2) <
  LOCKED_TASK(3) < HARD_LANDSCAPE(4)` with `dominates()`.
- `ScheduleAssignment` with `locked`, `source`
  (`draft|manual|reschedule|override|quick_capture|auto_swap|recurring|mini_pause|emergency_pause`),
  optimistic `version` + `schedule_version` (409 conventions already tested).
- Repository ports: `HardLandscapeRepository`, `ScheduleOverrideRepository`.

### Problem

Kinevo's signature reality-first workflow (KRS import → weekly course → plan around it)
is broken: stored recurrence and override intent never reach the effective schedule a
user sees or the scheduler respects. Scheduling views, the deterministic draft generator,
the rescheduler, and ICS export each hold private, partially divergent notions of "what
is on the schedule". History of schedule state is destroyed on supersede. There is no
producer for user-fixed (locked) placements. The domain lacks one canonical answer to:
**"Given user U and time window W, what exactly occupies U's Hard Landscape?"**

### Decision

Introduce **one canonical resolution layer — the Effective Landscape Resolver — as a pure
domain service**, composing the existing recurrence and override abstractions, and make
every schedule consumer (Today, Week, Month, draft generator, rescheduler, ICS export)
consume its output. Extend (do not replace) `SchedulePrecedence` and the existing 409
conventions. Add a minimum history table for superseded assignments. Add a reachable
producer for locked assignments.

The pipeline:

```
SOURCE EVENT (hard_landscape_events row: permanent | recurring | one_time)
  → RECURRENCE EXPANSION (RecurrenceRule + RecurrenceOccurrenceGenerator, window-bounded)
  → BASE OCCURRENCES (HardLandscapeOccurrence, provenance=base)
  → OVERRIDE RESOLUTION (one_time exceptions, then latest applicable permanent shift)
  → EFFECTIVE OCCURRENCES (provenance = base | shifted:<override_id> |
     excepted:<override_id> | cancelled:<override_id>)
  → EFFECTIVE LANDSCAPE (resolved window set for a user)
  → CAPACITY / SLOT INPUT (SlotCalculator, unchanged)
  → DETERMINISTIC SCHEDULER (ScheduleDraftGenerator / DynamicRescheduler, unchanged engines)
  → TODAY / WEEK / MONTH READ MODELS
```

AI is outside this path entirely. The resolver is pure; the scheduler remains the only
flexible-placement authority; user review remains the only override/approval authority.

### Domain model

**HardLandscapeOccurrence (new immutable value object, `App\Domain\Scheduling\Resolution`)**

| Field | Meaning |
|---|---|
| `sourceEventId` | `hard_landscape_events.id` — provenance anchor |
| `originalStart` / `originalEnd` | the base occurrence window as generated from the rule (DTSTART timezone wall-clock) |
| `effectiveStart` / `effectiveEnd` | the window after override resolution (differs from original only when shifted/excepted) |
| `title` | source title (no per-occurrence mutation in v1) |
| `provenance` | `base` \| `shifted:<overrideId>` \| `excepted:<overrideId>` \| `cancelled:<overrideId>` |
| `precedence` | `SchedulePrecedence::explicitOverride()` when shifted/excepted, `recurring()` for base, `hardLandscape()` for permanent/one_time source rows — the existing closed precedence set, unchanged |

**Occurrence identity (Phase 2 decision).** The canonical identity of a recurring
occurrence is:

```
(source_event_id, original_occurrence_start)
```

- `original_occurrence_start` is the ISO-8601 datetime produced by
  `RecurrenceOccurrenceGenerator` for that occurrence (unique per day per source, already
  `ksort`-deduplicated by the generator).
- This identity is DERIVED, never materialized permanently: occurrences are computed
  per-request within a bounded window. No occurrence table is introduced.
- One-Time Exceptions target this identity by **occurrence date** (see Phase 5), matching
  the existing `schedule_overrides` contract where `one_time` requires
  `effective_from` date == `effective_to` date.
- `cancelled` occurrences resolve to NO effective block; the cancellation remains visible
  through the override record itself (audit source of truth), not through a persisted
  tombstone occurrence.

**EffectiveLandscapeResolver (new pure domain service)**

- INPUT: `array<int, HardLandscapeEvent> $sources`, `array<int, ScheduleOverride>
  $overrides`, `CarbonImmutable $from, $to` (window, UTC; converted per source rule TZ).
- OUTPUT: `array<int, HardLandscapeOccurrence>` sorted by effectiveStart (deterministic,
  stable tie-break on sourceEventId then originalStart).
- MUST NOT: persist, mutate sources, call AI, place flexible tasks, or read repositories
  (data is fetched by the calling use case through existing ports).

### Resolution algorithm (Phase 3)

For each source event overlapping the window:

1. `one_time` / `permanent` source rows (non-recurring): emit as a single base occurrence
   if `overlapsWindow` (matches current export behavior).
2. `recurring` rows: parse `recurrence` via `RecurrenceRule::parse`; on parse failure,
   DEGRADE to the base single occurrence and surface a visible warning in the API payload
   (`recurrence_warning`) — identical to the export contract; never silently drop.
3. Expand via `RecurrenceOccurrenceGenerator::generate($rule, $from, $to)` — the SAME
   generator, semantics, bounds, and timezone behavior used today by ICS export. No
   separate recurrence implementation may exist anywhere else (export migrates to the
   resolver in ES-IMPL-01/02 and keeps its current output byte-compatible, proven by the
   existing `ScheduleExportApiTest` + `IcsCalendarTest` suites).
4. Apply overrides per occurrence (precedence below).
5. Sort deterministically.

Determinism: same inputs → same outputs, always. No randomness, no row-order dependence
(all override selection uses explicit sort keys below).

### Precedence (Phase 6)

Two levels, both preserved:

**Level 1 — within one recurring source family (resolution precedence):**

```
ONE-TIME EXCEPTION (targets the occurrence date)  wins over
LATEST APPLICABLE PERMANENT SHIFT (effective_from ≤ occurrence < effective_to)  wins over
BASE RECURRENCE
```

- **Multiple applicable shifts**: the shift with the GREATEST `effective_from` wins;
  tie-break: greatest `id`. (Latest-intent-wins; deterministic, order-independent.)
- **Exception after shift**: an exception targeting the occurrence date always wins over
  any shift covering that date (the exception is the user's most specific statement).
- **Invalid overlap**: unchanged at write time — `ScheduleOverrideConflict` (409) when
  two overrides of the same source have overlapping override intervals
  (`overlapsOverrideWith`). A shift whose override interval collides with an effective
  occurrence of ANOTHER source is rejected by the existing
  `HardLandscapeCollisionRule`/`ScheduleOverrideConflict` write checks; the resolver
  itself never silently drops a colliding block (see Failure semantics).
- **Cancelled occurrence**: resolves to no block; provenance `cancelled:<id>` is reported
  in the resolution diagnostics only.
- **Historical reconstruction**: because resolution is a pure function of (sources,
  overrides, window), any past window is reconstructable from the immutable source +
  override rows. No snapshotting is required.

**Level 2 — across placement sources (unchanged):** the existing
`SchedulePrecedence` rank (`ORDINARY < RECURRING < EXPLICIT_OVERRIDE < LOCKED_TASK <
HARD_LANDSCAPE`) governs slot conflicts between resolved landscape blocks, locked
assignments, and generated flexible placements. The effective landscape participates as
`HARD_LANDSCAPE` (source rows) / `EXPLICIT_OVERRIDE`+`RECURRING` metadata is carried for
display. `SchedulePrecedence` gains its first consumers in the collision/conflict
reporting path; its contract is unchanged.

The candidate conceptual precedence in the task brief (EXCEPTION > LATEST SHIFT > BASE)
is ADOPTED for level 1 exactly because it is compatible with — and strictly inside — the
existing `SchedulePrecedence` contract.

### History model (Phase 11)

**Chosen: Option A (dedicated snapshot table) + minimal activity events.** Not
soft-delete, not event sourcing.

- New table `schedule_assignment_history` (one row per superseded/deleted assignment):
  full column snapshot of the assignment (`user_id`, `task_id`, `date`, `start_at`,
  `end_at`, `duration_minutes`, `status`, `source`, `schedule_version`, `locked`,
  `version`), plus `superseded_by_schedule_version`, `superseded_by` (draft|reschedule|
  override), `reason` (nullable), `acted_at`.
- Written in the SAME transaction as the supersede/delete in
  `ApplyScheduleDraftUseCase` / `ApplyRescheduleProposalUseCase`.
- `ActivityEventType` gains `SCHEDULE_DRAFT_APPLIED`, `SCHEDULE_RESCHEDULE_APPLIED`,
  `SCHEDULE_OVERRIDE_APPLIED` (idempotent per `operation_id`, like existing activity).
- **Reconstructable (the contract):** for any task, the full placement timeline (what was
  placed where, when, by which mechanism, which schedule_version superseded it); and for
  any schedule_version, the set of placements it established. NOT promised: field-level
  diffs of tasks, or time-travel queries over arbitrary past windows beyond placement
  history.
- Existing behavior preserved: overrides never rewrite source series; locked/manual/
  override placements are never superseded by automation (already implemented).

### Locked-task invariant (Phase 12)

- **Semantic invariant:** a locked scheduled task is a USER-FIXED placement and MUST NOT
  be automatically moved by the scheduler or rescheduler. (Matches existing tested
  behavior: `LockedTaskMoveRule`, `DynamicRescheduler` locked-skip, apply-time
  `ScheduleAssignmentLockedConflict` → 422.)
- **Who sets it:** the user, through a new explicit API pair
  `POST /tasks/{taskId}/assignment/lock` and `POST /tasks/{taskId}/assignment/unlock`
  (owner-scoped, optimistic `version` bump via existing `withLocked`, activity-recorded).
- **Persistence:** existing `task_assignments.locked` boolean — no schema change.
- **Surfaces:** Today event card (lock indicator + action) and Task detail (lock toggle).
  No AI path may set or clear `locked`.
- **Scheduler:** unchanged behavior — generator keeps locked tasks in their existing slot
  (tested) and never overlaps effective landscape.
- **Rescheduler:** unchanged — locked tasks are skipped and reported, never moved.
- **Conflict:** an apply that would move a locked assignment → existing 422
  `ScheduleAssignmentLockedConflict`; user must unlock first.
- `isSacredAnchor` producer remains out of scope for this ADR (deferred; the hardcoded
  `false` in `assemble` is left untouched and noted).

### Workspace interaction (Phase 13)

- `hard_landscape_events` and `schedule_overrides` have NO `workspace_id` and gain none:
  Hard Landscape is **global personal reality** (user-scoped).
- `EffectiveLandscapeResolver` is keyed by user only; no workspace parameter exists in its
  signature, so global HL can never be accidentally scoped to an active workspace.
- Today/Week/Month remain cross-workspace (TARGET_DECISION_REGISTER #3–5).
- Flexible tasks keep their workspace context; the resolver never filters or scopes them.

### Scheduler interaction (Phase 9)

`ScheduleDraftController::assemble` and the rescheduler input assembly replace
`hardLandscape->listForUserInRange(...)` (raw rows) with
`EffectiveLandscapeResolver` output (effective occurrences → `TimeRange[]`, provenance
carried for explanation). `HardLandscapeCollisionRule`, `SlotCalculator`,
`ScheduleDraftGenerator`, `TaskRankingEngine`, `DynamicRescheduler` engines are
UNCHANGED — they already consume abstract `TimeRange` boundaries. The draft therefore
becomes correct by consuming correct inputs; determinism is preserved (pure inputs,
total-order ranking).

Contract (unchanged shape, new correctness):

```
effective landscape + fixed/locked work + flexible candidates + capacity policy
  → deterministic draft (preview) → user Apply (transactional, versioned)
```

### API / read-model impact (Phase 8)

- `GET /today`: `hard_landscape` array becomes **effective occurrences** (additive fields:
  `source_event_id`, `provenance`, `original_start` when shifted; existing `title`,
  `start_at`, `end_at` preserved). Recurring sources now appear on occurrence dates —
  this is the intended fix.
- `GET /week` / `GET /calendar`: additive per-day landscape fields
  (`landscape_count`, `landscape_minutes`) computed from the SAME resolver output; no
  custom per-view recurrence logic permitted.
- `GET /schedule` (range): landscape blocks included per day, same source.
- `POST /schedule/draft|reschedule` previews: unchanged response shape; unassigned/conflict
  reason codes unchanged.
- ICS export: refactored to consume the resolver; output remains compatible (existing
  tests must stay green; recurring VEVENTs additionally respect overrides once resolution
  lands — documented behavior change, additive).
- OpenAPI (`docs/api/openapi.yaml`): additive field documentation; the new lock endpoints
  are added to the contract in their implementing slice (per AGENTS.md API rule).

### Migration impact (Phase 16)

Backward-compatible by design. Existing stored data remains valid, unchanged:

| Store | Compatibility |
|---|---|
| `hard_landscape_events` | unchanged; recurring rows simply start resolving on future dates (the fix) |
| `schedule_overrides` | one narrow additive migration: nullable `cancels_occurrence` boolean (default `false`) to support occurrence cancellation (Phase 5). All existing rows interpret identically. No backfill required |
| `task_assignments` | unchanged (`locked` column already exists); new optional `schedule_assignment_history` table |
| KRS imports | unchanged; confirmed `FREQ=WEEKLY` rows become visible on future dates (the fix) |
| ICS imports | unchanged |

Release note required: users with recurring Hard Landscape rows will suddenly see them on
occurrence dates in Today/Week/Month — expected behavior restoration, must be
communicated. No migration code is written in this ADR.

### Time / timezone semantics (Phase 14)

- Canonical storage: UTC (`config/app.php timezone = UTC`), as today.
- Occurrence wall-clock: `RecurrenceOccurrenceGenerator` pins each occurrence to the
  DTSTART time-of-day **in the rule start's timezone** — this is retained as the canonical
  behavior (a 09:00 weekly class stays 09:00 local across DST transitions; absolute-UTC
  drift is explicitly rejected).
- Day boundaries: read models bucket occurrences by the requesting context's calendar day
  (controller-converted date, as today); windows passed to the resolver are
  `[user-day start, user-day end)` converted to UTC. Profile timezone (already stored and
  exercised by ICS import tests) is the user-day authority.
- DST: wall-clock pinning means an occurrence's absolute UTC instant may shift across DST
  boundaries — accepted and documented; no DST-specific recurrence logic is introduced.
- ICS imports: TZID/UTC/floating resolution is already handled at import time
  (`IcsParser`); imported rows store concrete instants and recur via the same rule
  subset (RRULEs outside the supported subset degrade per existing import contract).
- KRS: interpreted in the profile timezone at import time (current behavior, now
  formalized).
- Non-single-timezone future users are supported by the profile-TZ day bucketing; no
  per-user TZ is assumed global.

### Idempotency / concurrency (Phase 15)

- Resolver: pure function — no versioning needed; recomputed per request.
- Override writes: existing `ScheduleOverrideConflict` 409 (overlapping override intervals
  per source); duplicate one-time exception for the same occurrence date = overlapping
  override interval → 409 (deterministic rejection).
- Apply endpoints: unchanged optimistic `schedule_version` + assignment `version` → 409 on
  stale; repeated Apply is idempotent (existing tests).
- Previews: stale preview (base_version older than current) → existing 409/`stale`
  contract; reschedule proposals revalidate at apply (existing).
- Concurrent source edits: resolver recomputes from current rows each request; no cache
  invalidation problem exists (see Performance).
- History writes are part of the apply transaction — a failed apply leaves no history row.

### Failure semantics (Phase 19)

Visible-over-silent, always:

| Condition | Behavior |
|---|---|
| Unparseable recurrence rule | degrade to base single occurrence + visible `recurrence_warning` in API payload (never drop; matches export contract) |
| Override targeting missing/removed source | impossible via FK cascade; delete of a source with overrides cascades (existing FK) — overrides die with the series |
| Stale occurrence (apply after landscape changed) | existing schedule_version 409 |
| Shifted occurrence overlaps another effective occurrence | 409 at override write (existing collision checks extended to effective set in ES-IMPL-04/05); resolver itself never drops colliding blocks — read models surface overlap as conflict metadata |
| Locked assignment conflicts | existing 422 `ScheduleAssignmentLockedConflict` |
| No scheduling slot | existing unassigned reason codes (`NO_AVAILABLE_SLOT`, `CAPACITY_CAP`, `NO_AVAILABLE_ANCHOR_SLOT`) — task retained, conflict visible |
| Malformed imported recurrence | existing import degrade-with-warning contracts (ICS) / parse-failure fallback (KRS) |

### Performance requirements (Phase 18)

- Windows are bounded by request shape: Today = 1 day, Week = 7 days, Month = 31 days,
  draft horizon = requested range (UI-capped 7 days today), ICS export = requested range.
- Complexity: O(sources × window-days) with the generator's existing max-1000-occurrence
  guard and `maxDays` ceiling per source. Expected occurrence counts are tiny
  (≤ 31/source/month typical).
- Query strategy: existing indexes suffice — `hard_landscape_events(user_id, start_at,
  end_at)`, `schedule_overrides(user_id, hard_landscape_event_id)`. Overrides are fetched
  once per user per request (small set) rather than per source.
- NO persistence of future occurrences; NO caching in v1. Caching may be introduced only
  with measured evidence (benchmark note; consistent with the BENCHMARK_REQUIRED posture).

### Alternatives considered

1. **Materialize occurrences into a table on write** (expanded-rows model): rejected —
   duplicates the recurrence engine's authority, requires row synchronization on every
   source/override edit, and violates "no premature persistence for performance
   convenience" at Kinevo's scale.
2. **Event-sourcing the schedule**: rejected — premature; the history requirement is
   placement-timeline auditability, not full event replay (ADR History model).
3. **Per-view recurrence logic** (each read model expands its own): rejected — this is the
   CURRENT defect pattern; one resolver, many consumers.
4. **Soft-delete assignments**: rejected — pollutes live queries (every consumer would
   need filtering), keeps history coupled to live table shape; snapshot table is cleaner
   and queryable.
5. **Adopt precedence as a replacement for `SchedulePrecedence`**: rejected — the existing
   closed precedence set is sound; level-1 resolution composes inside it.

### Consequences

- BLOCKER-ES-01/02/03 are resolved by construction (resolver consumed everywhere).
- BLOCKER-ES-04 resolved by the history table + activity events.
- BLOCKER-ES-05 resolved by the lock producer (engines unchanged).
- `SchedulePrecedence` gains consumers without contract change; `RecurrenceRule`/
  `RecurrenceOccurrenceGenerator` are promoted from export-only to canonical.
- ICS export output gains override awareness (additive behavior change, tested).
- Users see recurring blocks on future dates (product-correct behavior restoration).
- The rescheduler/preview/apply flow gains an automatic trigger surface later (scheduler
  trigger / Sync Now remains a SEPARATE decision — BLOCKER-SCHED-01, out of scope here).

### Testing requirements (Phase 17)

Defined BEFORE implementation (tests-first slices):

**RECURRENCE**
- weekly KRS course appears next week (resolver unit)
- appears in Today on occurrence day (dayView feature)
- appears in Week; appears in Month (additive fields)
- appears in scheduler collision inputs (draft preview blocks the slot)
- non-occurrence day remains clear
- recurrence boundary respected (UNTIL/COUNT end)
- bounded expansion (window + guard respected; no unbounded generation)

**PERMANENT SHIFT**
- occurrences before effective boundary unchanged
- occurrences covered by the shift moved to override interval
- source series preserved (no mutation)
- multiple historical shifts → latest applicable wins, deterministic

**ONE-TIME EXCEPTION**
- target occurrence moved/modified per override
- previous occurrence unchanged; next occurrence unchanged
- target identity (source_event_id + occurrence date) stable across windows
- cancellation (cancels_occurrence) removes exactly that occurrence

**PRECEDENCE**
- exception over shifted occurrence
- latest applicable shift selected (two overlapping-window shifts, tie-break by id)
- invalid duplicate/overlap rejected (409 contract unchanged)

**SCHEDULER**
- flexible task never overlaps effective occurrence (draft + apply)
- locked task never moves (existing tests stay green)
- no available slot → visible conflict, task retained

**HISTORY**
- previous accepted placement remains auditable after new apply
- applying a new plan never silently erases required history (rows in
  `schedule_assignment_history` within same transaction)

**KRS (signature journey)**
- import → confirm weekly recurrence → future Week shows it → future Today shows it →
  scheduler respects it (E2E-compatible contract for P28 JOURNEY B)

**REGRESSION**
- full existing scheduling suite green unchanged (`ScheduleDraftGeneratorTest`,
  `HardConstraintEngineTest`, `ScheduleSimulationSuiteTest`, export/import suites).

### Rollout order (Phase 21 — implementation slices, NOT yet executed)

Each slice is independently verifiable (tests + `composer analyse` + `composer test`),
documented, and reversible. Registration into TASK.md happens per AGENTS.md when a slice
starts.

| Slice | Scope | Likely files | Domain invariant | Tests / acceptance | Risk |
|---|---|---|---|---|---|
| **ES-IMPL-01** Canonical recurrence resolver | `HardLandscapeOccurrence` VO + `EffectiveLandscapeResolver` (base expansion only; overrides pass-through) in `App\Domain\Scheduling\Resolution`; unit-tested pure | new `Domain/Scheduling/Resolution/*`; no consumer change yet | pure, deterministic, window-bounded, no IO | resolver unit tests (recurrence matrix above); existing suite green | none (additive) |
| **ES-IMPL-02** Read-model integration | `ScheduleQueryService` (day/week/month/range) consume resolver; additive API fields; ICS export refactor onto resolver (output-compatible) | `Application/Scheduling/ScheduleQueryService.php`, `Application/Exports/ExportScheduleIcsUseCase.php`, openapi additive | all views share ONE semantics; export byte-compat | dayView/weekView/monthView feature tests (occurrence visible / clear day / boundary); export suites green | behavior change (recurring blocks appear) — release note |
| **ES-IMPL-03** Scheduler integration | `ScheduleDraftController::assemble` + rescheduler input consume resolver output; provenance carried to explainer | `Http/Controllers/Api/ScheduleDraftController.php`, `Domain/Scheduling/DraftInput` consumers | scheduler consumes effective landscape, never raw rows | draft collision tests (flexible task never overlaps future occurrence); existing scheduling suite green | low (engines untouched) |
| **ES-IMPL-04** Permanent Shift resolution | resolver applies `permanent` overrides (latest-effective_from-wins + tie-break); write-side overlap checks extended to effective set | `Resolution/*`, `Application/Scheduling/{Create,Update}ScheduleOverrideUseCase.php`, `Infrastructure/Scheduling/EloquentScheduleOverrideRepository.php` | source preserved; deterministic precedence; conflicts 409 | shift matrix tests (before/after boundary, multiple shifts) | medium (first override consumer) |
| **ES-IMPL-05** One-Time Exception resolution | resolver applies `one_time` overrides incl. cancellation (`cancels_occurrence` migration); targeting by occurrence date | as ES-IMPL-04 + migration `*_add_cancels_occurrence_to_schedule_overrides_table.php` | only target occurrence changes; identity stable | exception matrix tests; migration rollback verified | medium (small migration) |
| **ES-IMPL-06** Schedule history | `schedule_assignment_history` migration + same-transaction snapshot writes in both apply use cases; 3 new ActivityEventType values | migration, `Infrastructure/Scheduling/EloquentScheduleAssignmentRepository.php`, `Application/Scheduling/Apply*UseCase.php`, `Domain/ActivityLogs/ValueObjects/ActivityEventType.php` | no silent destruction; timeline reconstructable | history feature tests (apply → history row exists; rollback-safe) | low-medium |
| **ES-IMPL-07** Locked-task producer | lock/unlock endpoints + activity record; Today/TaskDetail surfacing; openapi | `Http/Controllers/Api/TaskController.php` (or new controller), routes, `js/today|task` components, openapi | user is the only producer; AI excluded | lock/unlock API tests; rescheduler/apply locked tests stay green | low |
| **ES-IMPL-08** Integration/E2E regression | KRS journey (B), shift journey (C), exception journey (D) E2E contracts; full-suite regression; docs sync (scheduling-engine.md, SRS narrow deltas) | `tests/e2e/tests/*`, docs | end-to-end correctness of the pipeline | journeys B/C/D green; entire suite green | low |

Dependency chain: 01 → 02 → 03; 04 and 05 after 01 (parallelizable); 06 and 07 independent
of 04/05; 08 last. This matches P28 active phase doc execution order (formerly P28_STABILIZATION_PLAN) steps 2–5 and feeds JOURNEY B/C/D.

### Blocker mapping

| Blocker | Planned resolution |
|---|---|
| BLOCKER-ES-01 (recurrence not expanded into effective schedule) | ES-IMPL-01/02/03 (resolver + all consumers) |
| BLOCKER-ES-02 (Permanent Shift never applied) | ES-IMPL-04 |
| BLOCKER-ES-03 (One-Time Exception never applied) | ES-IMPL-05 |
| BLOCKER-ES-04 (history destroyed) | ES-IMPL-06 |
| BLOCKER-ES-05 (locked-task producer unreachable) | ES-IMPL-07 |

### Convergence deltas recorded for later (out of scope here)

- SRS FR-25/FR-27/FR-29 restatement + scheduling-engine.md update: only the narrow deltas
  needed per slice; full SRS/architecture rewrite remains a convergence-phase task
  (docs/roadmap/archive/convergence/DOCUMENT_DRIFT_TRIAGE.md).
- Scheduler trigger / Sync Now (BLOCKER-SCHED-01): separate decision after slices 2–5.
