# LIFESYNC OS — Master Execution Task Board

### Purpose
`TASK.md` adalah execution control document. Ia memecah implementation roadmap menjadi unit kerja yang dapat dieksekusi AI/human, mencatat dependency, status, evidence, dan blocking condition.

### Status vocabulary
- `TODO`: belum dimulai.
- `READY`: dependency terpenuhi dan dapat dikerjakan.
- `IN_PROGRESS`: sedang dikerjakan.
- `BLOCKED`: terhenti karena dependency/decision/technical blocker.
- `IN_REVIEW`: implementation selesai, menunggu review/verifikasi.
- `DONE`: acceptance criteria terpenuhi dan evidence tersedia.
- `DEFERRED`: sengaja ditunda dengan alasan eksplisit.
- `CANCELLED`: tidak lagi berlaku melalui keputusan terdokumentasi.

### Priority vocabulary
- `P0`: wajib untuk baseline/core release.
- `P1`: important, setelah P0 domain stabil.
- `P2`: enhancement.
- `P3`: optional/future.

### Task format
```markdown
### TASK-ID — Title
- Status: TODO
- Priority: P0
- Depends On: TASK-...
- SRS: FR-xx / NFR-xx
- Files: ...
- Acceptance:
  - [ ] ...
- Verification:
  - [ ] Unit
  - [ ] Integration
  - [ ] E2E
- Evidence: link/path/commit/test result
- Notes: ...
```

### Phase 0 — Foundation
#### TASK-001 — Repository skeleton
- Status: DONE
- Priority: P0
- Acceptance:
  - [x] repository folders exist (server/, resources/, tests/, infrastructure/, database/migrations/)
  - [x] Laravel app exists (server/, Laravel 13, PHP 8.5, PostgreSQL default)
  - [x] docs baseline exists (validate-repo.sh passes)
  - [x] tests baseline exists (PHPUnit 2 tests pass)
- Verification:
  - [x] Unit/Feature baseline: `./vendor/bin/phpunit` → OK (2 tests)
  - [x] Repo validation: `./scripts/validate-repo.sh .` → VALIDATION PASSED
  - [x] Migrations resolve from root `database/migrations`
- Evidence: server/ scaffold, commit reference (initial implementation commit)
- Notes: Laravel app scaffolded via composer:2 image (local PHP not installed); migrations canonical at repo root `database/migrations/` (loaded via AppServiceProvider).

#### TASK-002 — CI/lint/typecheck/test pipeline
- Status: DONE
- Priority: P0
- Acceptance:
  - [x] GitHub Actions workflow exists (`.github/workflows/ci.yml`)
  - [x] Lint check fails on violations and passes on baseline (Pint)
  - [x] Static analysis passes on baseline (Larastan/PHPStan level 5)
  - [x] Test suite passes on baseline (PHPUnit)
  - [x] Repository baseline validation runs in CI
- Verification:
  - [x] `vendor/bin/pint --test` → PASS (23 files)
  - [x] `vendor/bin/phpstan analyse` → No errors
  - [x] `vendor/bin/phpunit` → OK (2 tests)
  - [x] `scripts/validate-repo.sh .` → VALIDATION PASSED
- Evidence: workflow file, composer scripts (`lint`, `analyse`, `test`, `ci`), local check output
- Notes: CI PHP 8.3 matrix; composer scripts are the single source for local/CI parity.

#### TASK-003 — Docker development environment
- Status: DONE
- Priority: P0
- Acceptance:
  - [x] app + PostgreSQL boot from clean checkout
  - [x] migrations run automatically against PostgreSQL
  - [x] app reachable on http://localhost:8000
  - [x] tests pass inside container
- Verification:
  - [x] `docker compose -f infrastructure/docker-compose.yml up -d --build` → both services up, postgres healthy
  - [x] `curl localhost:8000` → HTTP 200, title "LIFESYNC OS"
  - [x] `psql \dt` → 9 tables (users, cache, jobs, sessions, ...) present in PostgreSQL
  - [x] `./vendor/bin/phpunit` in container → OK (2 tests)
- Evidence: infrastructure/docker/{Dockerfile,app-entrypoint.sh,docker-compose.yml}, Makefile up/down/migrate/logs/shell targets
- Notes: PHP 8.4-FPM alpine (composer.lock requires >=8.4.1); entrypoint applies container DB_* env over .env; migrations live at repo-root database/migrations and are mounted at /var/www/database.

#### TASK-004 — Environment/config/secrets baseline
- Status: DONE
- Priority: P0
- Acceptance:
  - [x] `server/.env.example` annotated with secrets vs non-secret defaults
  - [x] `docs/environment.md` documents secret rules (SRS NFR-02) and non-secret defaults
  - [x] secret scan script (`scripts/check-secrets.sh`) enforced in CI
- Verification:
  - [x] `./scripts/validate-repo.sh .` → VALIDATION PASSED (incl. docs/environment.md)
  - [x] `./scripts/check-secrets.sh .` → SECRET SCAN PASSED
  - [x] app boots from compose with annotated `.env.example` → HTTP 200
  - [x] tests in container → OK (2 tests)
- Evidence: docs/environment.md, server/.env.example, scripts/check-secrets.sh, CI secret-scan step
- Notes: real `.env` remains gitignored; production secrets injected via platform secret store per SRS NFR-02.

#### TASK-005 — Open-source repository hardening & governance
- Status: DONE
- Priority: P0
- SRS: NFR-02 (security disclosure), open-source governance; no requirement change.
- Acceptance:
  - [x] LICENSE replaced with MIT (approved decision); server/composer.json license aligned
  - [x] CONTRIBUTING.md, CODE_OF_CONDUCT.md, SECURITY.md, SUPPORT.md, CHANGELOG.md, CITATION.cff added
  - [x] `.github/` hardened: issue templates (bug/feature/architecture), PR template, dependabot.yml, CODEOWNERS, security.yml, release.yml
  - [x] third-party provenance updated (docs/third-party/licenses.md, attributions.md) for current runtime/dev deps
  - [x] root config files added: .editorconfig, .gitattributes, .dockerignore; root .gitignore expanded
  - [x] README rewritten as navigation surface; server/README replaced (no misleading Laravel boilerplate)
  - [x] scripts/check-doc-links.sh + scripts/check-openapi.sh added and wired into Makefile + CI
  - [x] docs synchronized: implementation-status, environment (SANCTUM_STATEFUL_DOMAINS)
  - [x] stale `lifesync-bootstrap-kit.tar.gz` removed from tree
- Verification:
  - [x] `./scripts/validate-repo.sh .` → VALIDATION PASSED (incl. new governance files)
  - [x] `./scripts/check-secrets.sh .` → SECRET SCAN PASSED
  - [x] `./scripts/check-doc-links.sh .` → PASSED (15 links)
  - [x] `./scripts/check-openapi.sh .` → PASSED (18 paths, bearerAuth present)
  - [x] Pint → PASS; PHPStan → No errors; PHPUnit → 17 tests OK
  - [x] TASK-001..TASK-010 implementation preserved (no code/architecture regression)
- Evidence: LICENSE, CONTRIBUTING.md, CODE_OF_CONDUCT.md, SECURITY.md, SUPPORT.md, CHANGELOG.md, CITATION.cff, .github/*, scripts/check-*.sh, README.md
- Notes: MIT selected by product owner for public release. CODEOWNERS uses repo owner handle with a note to replace as the maintainer team grows. Frontend typecheck/build Make targets intentionally deferred until frontend sources exist (per "no fake commands" rule).

### Phase 1 — Core Domain
#### TASK-010 — Identity/profile
- Status: DONE
- Priority: P0
- SRS: security/access requirements (NFR-02, SRS §15.1 ownership), profile/settings (SRS §7.1).
- Acceptance:
  - [x] Sanctum bearer-token auth wired (OpenAPI `bearerAuth`, NFR-02 token management)
  - [x] First-owner registration creates user + default profile; further registration rejected (409)
  - [x] Login issues token; logout revokes it; `/auth/me` returns authenticated identity
  - [x] `profiles` migration (locale, timezone, week_start_day, display_name) with ownership `user_id`
  - [x] Domain: `Profile` entity + `ProfileSettings` value object + `ProfileRepository` contract
  - [x] Application use cases: RegisterUser, LoginUser, LogoutUser, GetProfile, UpdateProfile
  - [x] HTTP: AuthController + ProfileController under `api/v1` (Identity tag)
  - [x] All profile mutations require auth (401) and are scoped to the owner (SRS §15.1)
- Verification:
  - [x] tests in container → 17 passed (auth + profile feature tests)
  - [x] `composer analyse` → PHPStan no errors
  - [x] `composer lint` → Pint PASS
  - [x] migrations applied (users, profiles, personal_access_tokens)
- Evidence: server/app/Domain/Identity, server/app/Application/Identity, server/app/Infrastructure/Identity, server/routes/api.php, database/migrations/2026_08_17_135300_create_profiles_table.php, docs/api/openapi.yaml (Identity paths/schemas)
- Notes: single-owner model enforced at registration; profile settings validated server-side via ProfileSettings value object.

#### TASK-011 — Goal aggregate
- Status: DONE
- Priority: P0
- SRS: FR-50, FR-19, FR-20; SRS §7.2 (goals table), domain-model Goal entity/horizon/state.
- Acceptance:
  - [x] `goals` migration: user ownership, title 1–200, description, horizon enum (yearly|quarterly|monthly|custom), start/target date, target_metric, status, priority_tier 1–3, progress_mode, derived progress
  - [x] Domain: `Goal` entity + `GoalHorizon` + `GoalStatus` VOs + `GoalRepository` contract; explicit status state machine (draft→active→paused→completed/archived/dropped)
  - [x] FR-50: custom-horizon goal stands alone (no parent); deadline-bound goal exposes remaining calendar days (isDeadlineBound/remainingDays)
  - [x] FR-19/FR-20 active limits: max 5 yearly, max 7 monthly goals enforced at create (422)
  - [x] Application use cases: Create/List/Get/Update/SetGoalStatus
  - [x] HTTP: `/goals` GET+POST, `/goals/{goalId}` GET+PUT, `/goals/{goalId}/status` POST, all under `auth:sanctum`, owner-scoped (404 on cross-user access, SRS §15.1)
  - [x] OpenAPI Goals paths + Goal schemas synchronized
- Verification:
  - [x] Unit/Feature: `vendor/bin/phpunit` → OK (36 tests, 102 assertions)
  - [x] `composer analyse` → PHPStan no errors
  - [x] `composer lint` → Pint PASS
  - [x] migration applies to PostgreSQL (`migrate:status` Ran; goals table present)
  - [x] `check-secrets.sh`, `validate-repo.sh`, `check-doc-links.sh`, `check-openapi.sh` all pass
- Evidence: server/app/Domain/Goals, server/app/Application/Goals, server/app/Infrastructure/Goals, server/app/Models/Goal.php, server/app/Http/Controllers/Api/GoalController.php, server/routes/api.php, database/migrations/2026_08_17_150000_create_goals_table.php, server/tests/Feature/Api/GoalApiTest.php, server/tests/Unit/GoalTest.php, docs/api/openapi.yaml (Goals paths/schemas)
- Notes: single-owner scoping via user_id; progress stays derived (0) until contribution sources land in later tasks.

#### TASK-012 — Milestone aggregate
- Status: DONE
- Priority: P0
- SRS: FR-51; SRS §7.3 (milestones table), domain-model Milestone entity/state.
- Acceptance:
  - [x] `milestones` migration per SRS §7.3: user+goal ownership, title 1–200, description, sequence, target_date, estimated_minutes, status (planned|active|blocked|completed|dropped), progress_mode, progress, completed_at, version, timestamps + (goal_id,sequence) & (user_id,status) indexes
  - [x] Domain: `Milestone` entity + `MilestoneStatus` VO + `MilestoneRepository` contract; explicit status state machine (planned→active/blocked/dropped→completed, terminal completed/dropped); FR-51: milestone belongs to exactly one owned goal, no recursive nesting
  - [x] Completing a milestone stamps completed_at and bumps optimistic `version`; progress bounded 0–100
  - [x] Application use cases: Create/List/Get/Update/SetMilestoneStatus/Reorder
  - [x] HTTP: `/goals/{goalId}/milestones` GET+POST, `/goals/{goalId}/milestones/reorder` POST, `/goals/{goalId}/milestones/{milestoneId}` GET+PUT, `/goals/{goalId}/milestones/{milestoneId}/status` POST, all under `auth:sanctum`, owner-scoped (404 on cross-user access, SRS §15.1)
  - [x] OpenAPI Milestones paths + Milestone schemas synchronized
- Verification:
  - [x] Unit/Feature: `vendor/bin/phpunit` → OK (56 tests, 169 assertions)
  - [x] `composer analyse` → PHPStan no errors
  - [x] `composer lint` → Pint PASS (67 files)
  - [x] migration applies to PostgreSQL (`migrate:status` Ran; milestones table present)
  - [x] `check-secrets.sh`, `validate-repo.sh`, `check-doc-links.sh`, `check-openapi.sh` all pass
- Evidence: server/app/Domain/Milestones, server/app/Application/Milestones, server/app/Infrastructure/Milestones, server/app/Models/Milestone.php, server/app/Http/Controllers/Api/MilestoneController.php, server/routes/api.php, database/migrations/2026_08_17_160000_create_milestones_table.php, server/tests/Feature/Api/MilestoneApiTest.php, server/tests/Unit/MilestoneTest.php, docs/api/openapi.yaml (Milestones paths/schemas)
- Notes: reorder only touches milestones that belong to the goal (422 on foreign ids); progress stays derived (0) until contribution sources land in later tasks.

#### TASK-013 — Program domain
- Status: DONE
- Priority: P0
- SRS: FR-22, FR-26; domain-model Program/state machine.
- Acceptance:
  - [x] `programs` migration: user ownership, name 1–200, description, category, workload_type, weekly_target_minutes, min/max_weekly_minutes, status (active|paused|completed|dropped), priority_tier 1–3, version + (user_id,status) index
  - [x] Domain: `Program` entity + `ProgramStatus` + `ProgramWorkloadType` VOs + `ProgramRepository` contract; explicit FR-22 lifecycle state machine (Active↔Paused, Active/Paused→Completed/Dropped, terminals)
  - [x] FR-26 intake: Structured requires weekly target; Range requires min+max with min≤max; Flexible forbids weekly target; `affectsWeeklyCapacity()` false for Flexible
  - [x] Optimistic `version` bumped on lifecycle transitions
  - [x] Application use cases: Create/List/Get/Update/SetProgramStatus
  - [x] HTTP: `/programs` GET+POST, `/programs/{programId}` GET+PUT, `/programs/{programId}/status` POST, all under `auth:sanctum`, owner-scoped (404 on cross-user access, SRS §15.1)
  - [x] OpenAPI Programs paths + Program schemas synchronized
- Verification:
  - [x] Unit/Feature: `vendor/bin/phpunit` → OK (79 tests, 243 assertions)
  - [x] `composer analyse` → PHPStan no errors
  - [x] `composer lint` → Pint PASS (81 files)
  - [x] migration applies to PostgreSQL (`migrate:status` Ran; programs table present)
  - [x] `check-secrets.sh`, `validate-repo.sh`, `check-doc-links.sh`, `check-openapi.sh` all pass
- Evidence: server/app/Domain/Programs, server/app/Application/Programs, server/app/Infrastructure/Programs, server/app/Models/Program.php, server/app/Http/Controllers/Api/ProgramController.php, server/routes/api.php, database/migrations/2026_08_17_170000_create_programs_table.php, server/tests/Feature/Api/ProgramApiTest.php, server/tests/Unit/ProgramTest.php, docs/api/openapi.yaml (Programs paths/schemas)
- Notes: FR-22 Completed 30s Undo and Dropped contribution retention are scheduled-engine concerns (TASK-020+); lifecycle + capacity-effect rules are now domain-owned.

#### TASK-014 — Task/subtask lifecycle
- Status: DONE
- Priority: P0
- SRS: FR-09, FR-45; SRS §6.5, §8.2 (partial-complete, promote); domain-model Task/Subtask state machine.
- Acceptance:
  - [x] `tasks` + `subtasks` migrations: tasks (user ownership, optional program/goal/milestone context FKs, title 1–200, description, status backlog default, priority_tier, estimated_minutes, due_at, progress_mode, progress, version + (user_id,status,due_at) & (user_id,program_id,status) indexes); subtasks (user ownership, task_id FK cascade, title 1–200, notes, sequence, completed bool, version + (task_id,sequence) index)
  - [x] Domain: `Task` + `Subtask` entities + `TaskStatus` VO (9 states, explicit state machine incl. backlog→scheduled/in_progress/completed/skipped, in_progress→completed/partial/conflict/skipped, partial→continued, missed→backlog, conflict→scheduled) + `TaskProgressCalculator` (progress = completed/total × 100) + `TaskRepository` + `SubtaskRepository` contracts
  - [x] FR-09: partial completion clones remaining subtasks+notes into a continuation Task and marks original `continued`; no remaining subtasks → normal Complete (progress 100); promote deletes child subtask and creates a standalone Task (default 90 min for heavy task with notes, AC-07)
  - [x] FR-45: subtasks are checklist children of exactly one Task; no deeper nesting
  - [x] Application use cases: Create/List/Get/Update/SetTaskStatus/AddSubtask/ToggleSubtask (recalcs progress)/UpdateSubtask (recalcs)/PromoteSubtask/PartialComplete
  - [x] HTTP: `/tasks` GET+POST, `/tasks/{taskId}` GET+PUT, `/tasks/{taskId}/status` POST, `/tasks/{taskId}/partial-complete` POST, `/tasks/{taskId}/subtasks` GET+POST, `/tasks/{taskId}/subtasks/{subtaskId}` PUT, `/tasks/{taskId}/subtasks/{subtaskId}/toggle` POST, `/subtasks/{subtaskId}/promote` POST, all under `auth:sanctum`, owner-scoped (404 on cross-user access, SRS §15.1)
  - [x] OpenAPI Tasks paths + Task/Subtask schemas synchronized
- Verification:
  - [x] Unit/Feature: `vendor/bin/phpunit` → OK (102 tests, 334 assertions)
  - [x] `composer analyse` → PHPStan no errors
  - [x] `composer lint` → Pint PASS (104 files)
  - [x] migration applies to PostgreSQL (`migrate:status` Ran; tasks+subtasks tables present)
  - [x] `check-secrets.sh`, `validate-repo.sh`, `check-doc-links.sh`, `check-openapi.sh` all pass
- Evidence: server/app/Domain/Tasks, server/app/Application/Tasks, server/app/Infrastructure/Tasks, server/app/Models/Task.php, server/app/Models/Subtask.php, server/app/Http/Controllers/Api/TaskController.php, server/routes/api.php, database/migrations/2026_08_17_180000_create_tasks_and_subtasks_table.php, server/tests/Feature/Api/TaskApiTest.php, server/tests/Unit/TaskTest.php, docs/api/openapi.yaml (Tasks/Subtask paths/schemas)
- Notes: partial-complete only valid on in_progress tasks (backlog→partial rejected by state machine); multi-route controllers must declare all route params (`{taskId}` + `{subtaskId}`) to avoid positional binding; test token switches require `auth()->forgetGuards()` since Sanctum caches the guard across requests.

#### TASK-015 — Activity log
- Status: DONE
- Priority: P0
- SRS: FR-34; SRS §7.1 (activity_logs table), §7.8 (activity_logs(user_id, event_at) index), §8.2 (`GET /logs`, `POST /export`); §9.3 idempotency via operation_id.
- Acceptance:
  - [x] `activity_logs` migration: user ownership, event_type, entity_type, entity_id, title, event_at, operation_id (unique per user), payload JSON + (user_id, event_at) index
  - [x] Domain: `ActivityLog` immutable entity + `ActivityEventType` VO (task_completed|task_continued|subtask_completed) + `ActivityLogRepository` contract; append-only — correction is by compensating event (FR-34 Business Rules)
  - [x] FR-34: completing a task appends exactly one `task_completed` event; partial completion appends `task_continued`; checking a subtask appends `subtask_completed`
  - [x] Idempotency: duplicate operation_id ignored (unique (user_id, operation_id)); retry does not double-log
  - [x] Application use cases: RecordActivity (idempotent append)/ListActivityLogs (from/to/event_type/limit filters)/ExportActivityLogs (JSON or CSV; references task/subtask ids only, notes excluded per privacy policy)
  - [x] HTTP: `GET /logs` (inspection) + `POST /export` (JSON/CSV), all under `auth:sanctum`, owner-scoped (SRS §15.1)
  - [x] OpenAPI Activity paths + ActivityLog/ActivityExport schemas synchronized
- Verification:
  - [x] Unit/Feature: `vendor/bin/phpunit` → OK (118 tests, 400 assertions)
  - [x] `composer analyse` → PHPStan no errors
  - [x] `composer lint` → Pint PASS (115 files)
  - [x] migration applies to PostgreSQL (`migrate:status` Ran; activity_logs table present)
  - [x] `check-secrets.sh`, `validate-repo.sh`, `check-doc-links.sh`, `check-openapi.sh` all pass
- Evidence: server/app/Domain/ActivityLogs, server/app/Application/ActivityLogs, server/app/Infrastructure/ActivityLogs, server/app/Models/ActivityLog.php, server/app/Http/Controllers/Api/ActivityLogController.php, server/routes/api.php, database/migrations/2026_08_17_190000_create_activity_logs_table.php, server/tests/Feature/Api/ActivityLogApiTest.php, server/tests/Unit/ActivityLogTest.php, docs/api/openapi.yaml (Activity paths/schemas)
- Notes: activity recording wired into SetTaskStatusUseCase/ToggleSubtaskUseCase/PartialCompleteTaskUseCase; offline queued events and JSON/CSV export download headers land with the offline shell (TASK-050+) and CLI/export UI respectively.

### Phase 2 — Scheduling
#### TASK-020 — TimeRange/slot primitives
- Status: DONE
- Priority: P0
- SRS: FR-01, FR-02; SRS §3.1 (Dynamic Empty Slot ≥15 menit, slot <15 menit menjadi buffer), §7.6 (TimeRange/DurationMinutes VOs, SlotCalculator service); scheduling-engine Dynamic Empty Slot contract + simulation test matrix.
- Acceptance:
  - [x] Domain `DurationMinutes` VO (strictly positive minutes; add/equals)
  - [x] Domain `TimeRange` VO — half-open `[start,end)` boundary (FR-02), duration = end−start, overlaps (boundary-touching does NOT overlap), overlapsOrAdjacent, merge (rejects disjoint), contains/containsInstant, ISO toArray
  - [x] Domain `SlotCalculator` service (FR-02): sort + merge overlapping/adjacent occupied intervals, compute gaps, exclude gaps < minimum slot (default 15 menit), return `[start,end)` slots; overlapping occupied events never treated as available (FR-02 Exception)
  - [x] Deterministic for identical inputs (unsorted occupied input → same output)
  - [x] Simulation test matrix subset: empty day → full-day slot; gap 25 min → fillable slot (AC FR-02); gap 14 min → no fillable slot (AC FR-02); gap exactly 15 min → fillable; adjacent blocks → no zero-length gap; overlapping events → never available; custom minimum honored
- Verification:
  - [x] Unit: `vendor/bin/phpunit` → OK (136 tests, 435 assertions)
  - [x] `composer analyse` → PHPStan no errors
  - [x] `composer lint` → Pint PASS (121 files)
  - [x] `check-secrets.sh`, `validate-repo.sh`, `check-doc-links.sh`, `check-openapi.sh` all pass (no API change)
- Evidence: server/app/Domain/Scheduling/ValueObjects/TimeRange.php, server/app/Domain/Scheduling/ValueObjects/DurationMinutes.php, server/app/Domain/Scheduling/SlotCalculator.php, server/tests/Unit/Scheduling/TimeRangeTest.php, server/tests/Unit/Scheduling/DurationMinutesTest.php, server/tests/Unit/Scheduling/SlotCalculatorTest.php
- Notes: primitives only, no persistence/API yet — consumed by TASK-021+; `diffInMinutes` returns float → cast to int; `toISOString()` includes microseconds; PHPUnit 11 requires test class name = file name.

#### TASK-021 — Hard constraint engine
- Status: DONE
- Priority: P0
- SRS: FR-27, FR-28, FR-64; SRS §0.3 requirement precedence; scheduling-engine hard constraint ordering; FR-04 Sacred Anchor rules.
- Acceptance:
  - [x] FR-64 separation: `HardConstraintEngine` validates feasibility BEFORE any soft scoring; soft changes can never make an invalid candidate executable
  - [x] `CandidatePlacement` value object (taskId, title, duration, slot, deadline, isLocked, isSacredAnchor, existingSlot, priorityTier)
  - [x] `ScheduleContext` (horizon, hardLandscape, existingAssignments, candidate set, reservePercent default 30)
  - [x] `ConstraintViolation` (ruleCode, taskId, message) + `HardConstraintRule` contract
  - [x] Rules in precedence order (scheduling-engine §Hard constraint ordering):
    - [x] #1 `HardLandscapeCollisionRule` — no automation overlap with Hard Landscape (FR-04/FR-27)
    - [x] #2 `LockedTaskMoveRule` — automation must not move locked tasks (same-slot re-place is not a move)
    - [x] #3 `SacredAnchorRule` — exactly 25 min, at/after 06:00, locked against automation (FR-04)
    - [x] #4 `TemporalValidityRule` — slot inside horizon
    - [x] #5 `DeadlineFeasibilityRule` — slot end ≤ deadline
    - [x] #6 `DurationFitRule` — task duration fits slot
    - [x] #7 `IllegalOverlapRule` — no overlap with existing assignments or other candidates
    - [x] #8 `SafetyReserveRule` — occupied (Hard Landscape + assignments + candidates, merged) ≤ (100−reserve)% of horizon (30% recharge/buffer reserve, FR-27)
  - [x] `ValueObjects\PriorityTier` (1..3) + `ValueObjects\Deadline`
- Verification:
  - [x] Unit: `vendor/bin/phpunit` → OK (152 tests, 454 assertions)
  - [x] `composer analyse` → PHPStan no errors
  - [x] `composer lint` → Pint PASS (137 files)
  - [x] `check-secrets.sh`, `validate-repo.sh`, `check-doc-links.sh`, `check-openapi.sh` all pass (no API change)
- Evidence: server/app/Domain/Scheduling/HardConstraintEngine.php, CandidatePlacement.php, ScheduleContext.php, ConstraintViolation.php, Contracts/HardConstraintRule.php, Rules/*.php, ValueObjects/{PriorityTier,Deadline}.php, server/tests/Unit/Scheduling/HardConstraintEngineTest.php
- Notes: engine injects the candidate set into the validation context (overlap/reserve need the full set); reserve rule counts the candidate under evaluation; `max()` on TimeRange is invalid — use an explicit furthest-end merge.

#### TASK-022 — Task ranking engine
- Status: DONE
- Priority: P0
- SRS: FR-23, FR-64; SRS §0.3 precedence (#7 tier, #9 soft signals); scheduling-engine §Soft ranking lexicographic ordering + §Soft scoring examples; FR-48 recovery nearest-deadline.
- Acceptance:
  - [x] FR-64: ranking applies ONLY to hard-feasible candidates (engine consumes post-HardConstraintEngine input); soft ordering can never override hard violations
  - [x] `RankingCandidate` carries soft signals: priorityTier, goal/milestone/task deadlines, progress, contextFit, fragmentationPenalty, slot, continuityPreference, estimatedMinutes
  - [x] `ScoreComponent` contract — independently testable, higher-is-better float score
  - [x] 9 lexicographic components (scheduling-engine §Soft ranking):
    - [x] `PriorityTierComponent` (priority_score): tier 1 > 2 > 3 (FR-23)
    - [x] `GoalDeadlineComponent` (goal_deadline_score): nearest Yearly Goal deadline first — FR-23 equal-tier tie-break
    - [x] `MilestoneUrgencyComponent` (milestone_score)
    - [x] `TaskDeadlineComponent` (task_deadline_score) — FR-48 nearest-deadline recovery
    - [x] `ProgressLeverageComponent` (progress_value_score)
    - [x] `ContextFitComponent` (context_fit_score, null → neutral 0.5)
    - [x] `FragmentationPenaltyComponent` (fragmentation_penalty)
    - [x] `DurationFitComponent` (duration_fit_score) — exact fit preferred (SLOT_FIT_EXACT)
    - [x] `ContinuityPreferenceComponent` (continuity_preference)
  - [x] `TaskRankingEngine::rank()` — best-first lexicographic sort; stable for identical candidates
  - [x] `RankedCandidate` exposes per-component scores for explainability (scheduling-engine §Explainability)
- Verification:
  - [x] Unit: `vendor/bin/phpunit` → OK (165 tests, 470 assertions)
  - [x] `composer analyse` → PHPStan no errors
  - [x] `composer lint` → Pint PASS (151 files)
  - [x] `check-secrets.sh`, `validate-repo.sh`, `check-doc-links.sh`, `check-openapi.sh` all pass (no API change)
- Evidence: server/app/Domain/Scheduling/TaskRankingEngine.php, RankingCandidate.php, RankedCandidate.php, Contracts/ScoreComponent.php, Components/*.php, server/tests/Unit/Scheduling/TaskRankingEngineTest.php
- Notes: no-deadline sentinel must be `-INF` (not `PHP_FLOAT_MIN`, which is the smallest positive float); `usort` comparator compares component scores in declared lexicographic order — component order in the constructor IS the precedence order.

#### TASK-023 — Auto-schedule draft engine
- Status: DONE
- Priority: P0
- SRS: FR-27 weekly draft; SRS §3.1 Sacred Anchor, Dynamic Empty Slot; §6.5; scheduling-engine core algorithm (steps 1–13) + simulation test matrix.
- Acceptance:
  - [x] Deterministic draft: identical inputs → identical draft (verified by repeated generation)
  - [x] `ScheduleTask` input VO (id, title, duration, priorityTier, goal/milestone/task deadlines, progress, contextFit, fragmentationPenalty, continuityPreference, isLocked, isSacredAnchor, existingSlot)
  - [x] `DraftInput` (horizon, hardLandscape, existingAssignments, tasks, sacredAnchor, reservePercent)
  - [x] `ScheduleDraft` result (assignments + unassigned with reason) + `DraftAssignment` + `UnassignedTask`
  - [x] Core algorithm implemented (scheduling-engine steps): split horizon into days → occupied intervals → Dynamic Empty Slots (SlotCalculator) → Sacred Anchor first (first qualifying ≥25-min slot at/after 06:00, locked) → candidate set → hard constraints (HardConstraintEngine) → ranking (TaskRankingEngine) → greedy assignment, deadline/reserve respected
  - [x] Locked tasks with existingSlot kept in place (never moved by automation, FR-04/FR-27)
  - [x] No overlap between assignments; Hard Landscape never overlapped (adjacent blocks safe)
  - [x] Unassigned tasks reported with deterministic reason (NO_AVAILABLE_SLOT / NO_AVAILABLE_ANCHOR_SLOT)
- Verification:
  - [x] Unit: `vendor/bin/phpunit` → OK (177 tests, 501 assertions)
  - [x] `composer analyse` → PHPStan no errors
  - [x] `composer lint` → Pint PASS (158 files)
  - [x] `check-secrets.sh`, `validate-repo.sh`, `check-doc-links.sh`, `check-openapi.sh` all pass (no API change)
- Evidence: server/app/Domain/Scheduling/ScheduleDraftGenerator.php, ScheduleTask.php, DraftInput.php, ScheduleDraft.php, DraftAssignment.php, UnassignedTask.php, server/tests/Unit/Scheduling/ScheduleDraftGeneratorTest.php
- Notes: locked tasks are placed before slot iteration; occupied intervals exclude double-counting (existingAssignments passed once); reserve check uses per-candidate context so a large single task over 7 days still fits (10080 min horizon, 30% reserve → 7056 limit).

#### TASK-024 — Dynamic rescheduler preview/apply
- Status: DONE
- Priority: P0
- SRS: FR-28; SRS §0.3; scheduling-engine §RESCHEDULE_PROPOSAL mode, §Schedule versioning (stale apply → 409 SCHEDULE_VERSION_CONFLICT), §Draft vs applied schedule.
- Acceptance:
  - [x] `ScheduleVersion` VO (monotonic positive int; next(); equals) — domain-model recommended VO
  - [x] `ScheduleState` immutable snapshot (version + taskId→slot assignments; withAssignments bumps version; isConsistent overlap check)
  - [x] `TaskMove` diff entry (taskId, title, fromSlot, toSlot)
  - [x] `RescheduleProposal` (baseVersion, newVersion = base+1, moves, conflictTaskIds; resultingAssignments; hasChanges)
  - [x] `DynamicRescheduler::propose()` — generates candidate plan via deterministic draft engine + computes diff; impact-driven: only tasks whose CURRENT slot became infeasible under new constraints are moved; locked tasks never moved (FR-28 Business Rule); no schedule mutation on preview
  - [x] `DynamicRescheduler::apply()` — atomic commit; stale proposal → `ScheduleVersionConflict` (maps to HTTP 409); result is consistent
  - [x] Cancel semantics: propose() alone never mutates the schedule
  - [x] Unplaceable tasks flagged as conflict (Alternative Flow: red flag)
- Verification:
  - [x] Unit: `vendor/bin/phpunit` → OK (185 tests, 520 assertions)
  - [x] `composer analyse` → PHPStan no errors
  - [x] `composer lint` → Pint PASS (165 files)
  - [x] `check-secrets.sh`, `validate-repo.sh`, `check-doc-links.sh`, `check-openapi.sh` all pass (no API change)
- Evidence: server/app/Domain/Scheduling/DynamicRescheduler.php, ScheduleState.php, ScheduleVersionConflict.php, TaskMove.php, RescheduleProposal.php, ValueObjects/ScheduleVersion.php, server/tests/Unit/Scheduling/DynamicReschedulerTest.php
- Notes: feasibility check of an existing slot filters out the task's own assignment from the overlap set (else a task would conflict with itself); the rescheduler re-validates existing slots against the NEW hard landscape so only genuinely impacted tasks move.

#### TASK-025 — Capacity feedback
- Status: DONE
- Priority: P1
- SRS: FR-49, AC-09; FR-27 Business Rules (Effective Capacity <80% reduces load proportionally; >90% no burnout → Boost/backlog fill); domain-model `CapacityMinutes` VO + `CapacityCalculator` service.
- Acceptance:
  - [x] `CapacityMinutes` VO (non-negative minutes)
  - [x] `WeekCapacitySample` (planned/completed `DurationMinutes`, tag normal|emergency|break; realizationRatio clamped 0..1; isEligible)
  - [x] `CapacityCalculator::estimate()` — Effective Capacity from recent weeks with confidence (LOW <2, MEDIUM 2–3, HIGH ≥4)
  - [x] AC-09: 60% realization → REDUCE_LOAD at ~60% of target (1800/3000 = 60%)
  - [x] FR-49 Business Rules: <80% → REDUCE_LOAD proportional; >90% & no burnout → BOOST_AVAILABLE; burnout signal suppresses Boost → MAINTAIN
  - [x] Emergency/Break weeks excluded (Exception Flow); zero eligible → baseline MAINTAIN at LOW confidence (Alternative Flow)
  - [x] Single-week history computes at LOW confidence (available minimum, no aggressive baseline)
  - [x] `EffectiveCapacity` result (capacityMinutes, realizationRatio, confidence, recommendation, reason) — reason always present
- Verification:
  - [x] Unit: `vendor/bin/phpunit` → OK (195 tests, 541 assertions)
  - [x] `composer analyse` → PHPStan no errors
  - [x] `composer lint` → Pint PASS (170 files)
  - [x] `check-secrets.sh`, `validate-repo.sh`, `check-doc-links.sh`, `check-openapi.sh` all pass (no API change)
- Evidence: server/app/Domain/Scheduling/CapacityCalculator.php, WeekCapacitySample.php, EffectiveCapacity.php, ValueObjects/CapacityMinutes.php, server/tests/Unit/Scheduling/CapacityCalculatorTest.php
- Notes: `realizationRatio` clamps to 1.0 (completed > planned cannot inflate ratio); AC-09 band 60–70% satisfied by exact proportional reduction (60%); burnout signal is an explicit input — detection is upstream (TASK-060 adaptive context).

#### TASK-026 — Scheduler explainability
- Status: DONE
- Priority: P0
- SRS: FR-63; scheduling-engine §Explainability contract (candidate reason, accepted constraints, rejected alternatives, primary priority, deadline pressure, capacity context, soft context signal).
- Acceptance:
  - [x] `ExplanationReason` — finite, domain-owned reason code set with stable labels (FR-63 example list: HARD_CONSTRAINT_FILTERED, LOCK_PROTECTED, DEADLINE_PRIORITY, CAPACITY_FIT, ENERGY_FIT, CONTEXT_SWITCH_PENALTY, PROGRESS_VALUE + SACRED_ANCHOR, CONTINUITY_PREFERENCE); rejects unknown codes
  - [x] `PlacementExplanation` VO: taskId, title, slot, reasons[], summary, acceptedConstraints[], rejectedAlternatives[], primaryPriority, deadlinePressure, capacityContext, softContextSignal
  - [x] `ReasonMapper` — derives reasons from task + ranking signals (locked, sacred anchor, near deadline, high context fit, high progress, fragmentation penalty, continuity); deterministic
  - [x] `SchedulerExplainer::explain()` — builds human-readable summary + structured context (deadline pressure overdue|high|medium|low; capacity slot vs task; soft context signal)
  - [x] Rejected alternatives reflect constraint violation summaries
  - [x] No-reasons case produces a simple summary
- Verification:
  - [x] Unit: `vendor/bin/phpunit` → OK (206 tests, 559 assertions)
  - [x] `composer analyse` → PHPStan no errors
  - [x] `composer lint` → Pint PASS (175 files)
  - [x] `check-secrets.sh`, `validate-repo.sh`, `check-doc-links.sh`, `check-openapi.sh` all pass (no API change)
- Evidence: server/app/Domain/Scheduling/SchedulerExplainer.php, ReasonMapper.php, ExplanationReason.php, PlacementExplanation.php, server/tests/Unit/Scheduling/SchedulerExplainerTest.php
- Notes: reason codes are a closed set (domain-owned, machine-readable); deadline pressure threshold follows FR-13 (≤1 high, ≤3 medium, >3 low, ≤0 overdue); `nullsafe` on non-nullable `slot` is unnecessary per PHPStan.

### Phase 3 — Knowledge
#### TASK-030 — Note aggregate
- Status: DONE
- Priority: P0
- SRS: FR-53; SRS §7.4 notes table (id, user_id, title, document_json JSONB, markdown_cache, plain_text_cache, version, timestamps); §8.4 Knowledge Endpoints (GET/POST /notes, GET/PATCH /notes/{id}); domain-model Note entity.
- Acceptance:
  - [x] `notes` migration: user ownership, title, document_json JSONB, markdown_cache text nullable, plain_text_cache text nullable, version integer default 1, timestamps, user_id index
  - [x] Domain: `Note` immutable entity (title, documentJson, markdownCache, plainTextCache, version; create/withId/withTitle/withContent — version increments on mutation; toArray) + `NoteRepository` contract (findForUser, listForUser, create, update with baseVersion) + `NoteVersionConflict` exception
  - [x] Infrastructure: `EloquentNoteRepository` — optimistic version check on update (where version = baseVersion, else throw NoteVersionConflict); `Note` model with `#[Fillable]`, `document_json` cast to array, `HasFactory`
  - [x] Application use cases: CreateNote, ListNotes, GetNote, UpdateNote (baseVersion required for optimistic lock)
  - [x] HTTP: `GET /notes` (list), `POST /notes` (create), `GET /notes/{noteId}` (show), `PATCH /notes/{noteId}` (update with base_version → 409 on stale), all under `auth:sanctum`, owner-scoped (404 on cross-user)
  - [x] OpenAPI: Note paths expanded with full request/response schemas (NoteCreateRequest, NoteUpdateRequest, NoteResponse, NoteListResponse, Note)
- Verification:
  - [x] Unit/Feature: `vendor/bin/phpunit` → OK (223 tests, 603 assertions)
  - [x] `composer analyse` → PHPStan no errors
  - [x] `composer lint` → Pint PASS (188 files)
  - [x] migration applies to PostgreSQL (`migrate:status` Ran; notes table present)
  - [x] `check-secrets.sh`, `validate-repo.sh`, `check-doc-links.sh`, `check-openapi.sh` all pass
- Evidence: server/app/Domain/Knowledge/Note.php, NoteVersionConflict.php, Contracts/NoteRepository.php, server/app/Infrastructure/Knowledge/EloquentNoteRepository.php, server/app/Models/Note.php, server/app/Application/Knowledge/*UseCase.php, server/app/Http/Controllers/Api/NoteController.php, server/routes/api.php, database/migrations/2026_08_18_000000_create_notes_table.php, server/tests/Unit/NoteTest.php, server/tests/Feature/Api/NoteApiTest.php, docs/api/openapi.yaml (Note paths/schemas)
- Notes: PATCH (not PUT) per SRS §8.4; optimistic version check is repo-level (WHERE version = base_version); NoteFactory created for feature tests; `@property document_json` must be `array|null` to match Eloquent cast.

#### TASK-031 — Tiptap editor adapter
- Status: DONE
- Priority: P0
- SRS: §10.1–10.3 Knowledge Layer (Tiptap or replaceable adapter behind LIFESYNC boundary; canonical structured JSON; domain-aware references resolve through LIFESYNC APIs); §5.3 layering (Domain must not import Tiptap); architecture.md "Knowledge boundary"; ADR-002 (Vue 3 + TS + Vite); ADR-004 (headless editor); ADR-009.
- Acceptance:
  - [x] Frontend scaffold per ADR-002: Vue 3 + TypeScript + Vite + Pinia; `vue-tsc` typecheck, Vitest (happy-dom), Vite build all wired into `package.json` scripts
  - [x] Editor adapter boundary: framework-agnostic `EditorAdapter` contract (types.ts) with `load(document)`, `getDocument()`, `getDerived()`, `save(baseVersion)`, `setReadOnly(enabled)`, `setTheme(theme)`, `subscribe(listener)`, `flush()`, `destroy()`
  - [x] `TiptapEditorAdapter` implements the contract behind the boundary; canonical ProseMirror/Tiptap JSON is authoritative (SRS §10.2); bounded extension set (StarterKit headings 1–6 + Link + TaskList/TaskItem) per design.md
  - [x] Derived formats: deterministic markdown + plain text serializers (SRS §10.2) as pure functions, unit-tested in isolation
  - [x] Save exposes baseVersion for optimistic versioning (SRS §11.2 contract parity); Cmd/Ctrl+S shortcut hook provided
  - [x] Vue bootstrap (`app.js`) mounts only when `#app` host exists (welcome page unaffected); `@` alias configured
  - [x] Tooling: Node added to dev Docker image (nodejs/npm); Makefile `frontend-typecheck`/`frontend-test`/`frontend-build` targets; CI `frontend` job (typecheck + vitest + build); `composer ci` remains PHP-only (frontend runs via `make ci`)
  - [x] License ledger + attributions updated for Tiptap/ProseMirror/TypeScript and Node dev deps
- Verification:
  - [x] Frontend: `npm run typecheck` → no errors; `npm run test` → OK (24 tests, 2 files); `npm run build` → built in ~311–574ms
  - [x] Backend regression: PHPUnit → OK (223 tests, 603 assertions); Pint → PASS (188 files); PHPStan → no errors
  - [x] Gates inside container: `make frontend-*` targets all pass with Node 24 in dev image
  - [x] `check-secrets.sh`, `check-doc-links.sh`, `validate-repo.sh` all pass
- Evidence: server/resources/js/editor/{types.ts,serializers.ts,TiptapEditorAdapter.ts}, server/resources/js/editor/__tests__/, server/resources/js/app.js, server/package.json, server/tsconfig.json, server/vite.config.ts, server/vitest.config.ts, infrastructure/docker/Dockerfile, Makefile, .github/workflows/ci.yml, docs/third-party/licenses.md + attributions.md
- Notes: Tiptap canonical empty document is a single empty paragraph (not zero content) — tests assert that. Editor adapter intentionally framework-agnostic (no Vue import) so the engine is replaceable behind the boundary; Vue binding is future work in the Notes UI task. `Level[]` must be typed via `@tiptap/extension-heading` type, not `number[]`.

#### TASK-032 — Knowledge linking
- Status: DONE
- Priority: P0
- SRS: FR-54.

#### TASK-033 — Knowledge search
- Status: TODO
- Priority: P1.

### Phase 4 — Canvas
#### TASK-040 — Architecture Spike verification
- Status: TODO
- Priority: P0
- Acceptance: Vue → adapter → React → Excalidraw → Laravel → PostgreSQL → IndexedDB path works.

#### TASK-041 — Canvas domain schema
- Status: TODO
- Priority: P0
- SRS: FR-55, FR-56.

#### TASK-042 — Excalidraw adapter
- Status: TODO
- Priority: P0

#### TASK-043 — Canvas autosave/versioning
- Status: TODO
- Priority: P0

#### TASK-044 — Canvas offline mutation queue
- Status: TODO
- Priority: P0
- SRS: FR-57.

### Phase 5 — Offline/Recovery
#### TASK-050 — Service Worker shell caching
- Status: TODO
- Priority: P0

#### TASK-051 — Today cache
- Status: TODO
- Priority: P0
- SRS: FR-44.

#### TASK-052 — Mutation queue
- Status: TODO
- Priority: P0.

#### TASK-053 — Last-write-wins policy
- Status: TODO
- Priority: P0.

#### TASK-054 — EOD reconciliation
- Status: TODO
- Priority: P0
- SRS: FR-47.

#### TASK-055 — Morning Recovery
- Status: TODO
- Priority: P0
- SRS: FR-48.

### Phase 6 — Adaptive Productivity
#### TASK-060 — Context check-in model
- Status: TODO
- Priority: P1
- SRS: FR-58.

#### TASK-061 — Soft signal scoring
- Status: TODO
- Priority: P1
- SRS: FR-59.

#### TASK-062 — Adaptive focus block recommendation
- Status: TODO
- Priority: P1.

#### TASK-063 — Progress event model
- Status: TODO
- Priority: P1.

### Phase 7 — AI
#### TASK-070 — AI provider interface
- Status: TODO
- Priority: P1
- SRS: FR-60.

#### TASK-071 — Ollama development adapter
- Status: TODO
- Priority: P1.

#### TASK-072 — Structured output validation
- Status: TODO
- Priority: P1
- SRS: FR-61.

#### TASK-073 — Goal decomposition proposal
- Status: TODO
- Priority: P1
- SRS: FR-52.

#### TASK-074 — Note summarization/extraction
- Status: TODO
- Priority: P1.

#### TASK-075 — Canvas generation proposal
- Status: TODO
- Priority: P2.

### Phase 8 — Operations
#### TASK-080 — Production Docker profile
- Status: TODO
- Priority: P0.

#### TASK-081 — Reverse proxy/TLS
- Status: TODO
- Priority: P0.

#### TASK-082 — Backup/restore automation
- Status: TODO
- Priority: P0.

#### TASK-083 — Observability
- Status: TODO
- Priority: P1.

### Execution rules
- A task may move to `DONE` only when acceptance and verification boxes are satisfied.
- A task that exposes a requirement gap MUST create an issue/ADR before “working around” it.
- Completed work MUST include evidence: commit, test output, screenshot, trace, or deployment proof.
- Dependencies MUST be respected; do not parallelize tasks that would create incompatible migrations or contracts.

---

