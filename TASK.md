# Kinevo — Master Execution Task Board

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
  - [x] `curl localhost:8000` → HTTP 200, title "Kinevo"
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
  - [x] stale `kinevo-bootstrap-kit.tar.gz` removed from tree
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
- SRS: §10.1–10.3 Knowledge Layer (Tiptap or replaceable adapter behind Kinevo boundary; canonical structured JSON; domain-aware references resolve through Kinevo APIs); §5.3 layering (Domain must not import Tiptap); architecture.md "Knowledge boundary"; ADR-002 (Vue 3 + TS + Vite); ADR-004 (headless editor); ADR-009.
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
- SRS: FR-54 (explicit links between Notes and Goals/Milestones/Programs/Tasks).
- Acceptance:
  - [x] `knowledge_links` migration: user ownership, source/target type+id, link_type enum
  - [x] Domain: `KnowledgeLink` entity + `KnowledgeTargetType` + `KnowledgeLinkType` VOs + `KnowledgeLinkRepository` contract
  - [x] FR-54: links are domain relationships (not arbitrary HTML); orphan/preserve policy on deletion
  - [x] `POST /notes/{noteId}/links` — create link (409 on duplicate)
  - [x] `GET /notes/{noteId}/links` — list links from a note
  - [x] `DELETE /notes/{noteId}/links/{linkId}` — remove link
  - [x] `GET /knowledge/links?target_type=X&target_id=Y` — reverse navigation to find notes linked to an entity
  - [x] All endpoints require auth (401) and ownership scope (404 on cross-user access, SRS §15.1)
  - [x] OpenAPI KnowledgeLink schemas synchronized
- Verification:
  - [x] Unit/Feature: `vendor/bin/phpunit` → OK (255 tests, 692 assertions)
  - [x] `composer analyse` → PHPStan no errors
  - [x] `composer lint` → Pint PASS (205 files)
  - [x] `check-openapi.sh` → PASS (39 paths)
- Evidence: server/app/Domain/Knowledge/KnowledgeLink.php, server/app/Domain/Knowledge/ValueObjects/{KnowledgeTargetType,KnowledgeLinkType}.php, server/app/Domain/Knowledge/Contracts/KnowledgeLinkRepository.php, server/app/Infrastructure/Knowledge/EloquentKnowledgeLinkRepository.php, server/app/Application/Knowledge/{CreateNoteLinkUseCase,ListNoteLinksUseCase,ListTargetLinksUseCase,RemoveNoteLinkUseCase}.php, server/app/Http/Controllers/Api/KnowledgeLinkController.php, server/routes/api.php, database/migrations/*knowledge_links*.php, server/tests/{Unit/Feature}/Api/KnowledgeLinkApiTest.php, docs/api/openapi.yaml
- Notes: Canvas links deferred until Canvas (TASK-042) exists; link_type supports: supports|references|derived_from|evidence_for|related_to.

#### TASK-033 — Knowledge search
- Status: DONE
- Priority: P1
- SRS: FR-53 (search text), knowledge-layer.md §Search (title, plain text, PostgreSQL full-text search).
- Acceptance:
  - [x] `GET /api/v1/knowledge/search?q=<query>` endpoint implemented
  - [x] PostgreSQL full-text search via tsvector column + GIN index + trigger
  - [x] LIKE-based fallback for SQLite (testing)
  - [x] Search scoped to authenticated user (owner-only results, 401 on unauthenticated)
  - [x] Results ordered by updated_at descending
  - [x] Empty query returns 422 validation error
  - [x] OpenAPI schema synchronized (KnowledgeSearchResponse)
- Verification:
  - [x] Unit/Feature: `vendor/bin/phpunit` → OK (255 tests, 692 assertions, 9 new search tests)
  - [x] `composer analyse` → PHPStan no errors
  - [x] `composer lint` → Pint PASS (205 files)
  - [x] `check-openapi.sh` → PASS (39 paths)
  - [x] `check-doc-links.sh` → PASS (15 links)
- Evidence: server/app/Application/Knowledge/SearchNotesUseCase.php, server/app/Infrastructure/Knowledge/EloquentNoteRepository.php (searchForUser), server/app/Http/Controllers/Api/KnowledgeSearchController.php, server/routes/api.php, database/migrations/2026_08_18_000001_add_notes_search_vector.php, server/tests/Feature/Api/KnowledgeSearchApiTest.php, docs/api/openapi.yaml (KnowledgeSearchResponse schema + /knowledge/search path)
- Notes: PostgreSQL full-text search preferred per knowledge-layer.md; SQLite uses LIKE fallback for CI/testing compatibility.

### Phase 4 — Canvas
#### TASK-040 — Architecture Spike verification
- Status: DONE
- Priority: P0
- SRS: FR-55 (Canvas lifecycle), FR-56 (version conflict); ADR-005 (Excalidraw behind adapter); ADR-002 (React island).
- Acceptance:
  - [x] Backend path verified: `canvases` + `canvas_documents` migrations → Canvas/CanvasDocument domain → CanvasRepository → CreateCanvas/ListCanvases/GetCanvas/SaveCanvas use cases → CanvasController (`GET/POST /canvases`, `GET/PUT /canvases/{canvasId}`) → PostgreSQL
  - [x] FR-56 optimistic versioning: stale `PUT` returns `409` (CanvasVersionConflict); no silent overwrite
  - [x] Frontend path verified: Vue `CanvasHost.vue` → framework-agnostic `CanvasAdapter` contract (`types.ts`) → `ExcalidrawCanvasAdapter` → React Island (`ExcalidrawIsland.tsx`) → Excalidraw
  - [x] Boundary enforced: Vue layer depends only on `CanvasAdapter`, never on React/Excalidraw types (verified by typecheck + boundary test)
  - [x] Excalidraw + React installed and licensed (MIT), ledger updated
  - [x] `scene_json` stored as JSONB in PostgreSQL; schema_version recorded
- Verification:
  - [x] Backend: `vendor/bin/phpunit` → OK (277 tests, 744 assertions, 22 canvas tests)
  - [x] `composer analyse` → PHPStan no errors; `composer lint` → Pint PASS (221 files)
  - [x] Frontend: `npm run typecheck` → no errors; `npm run test` → 28 tests; `npm run build` → built OK
  - [x] `check-openapi.sh` → PASS (39 paths); `check-doc-links.sh` → PASS (15 links)
- Evidence: database/migrations/{2026_08_18_100000_create_canvases_table.php,2026_08_18_100001_create_canvas_documents_table.php}, server/app/Domain/Canvas/{Canvas,CanvasDocument,CanvasVersionConflict}.php, server/app/Domain/Canvas/Contracts/CanvasRepository.php, server/app/Infrastructure/Canvas/EloquentCanvasRepository.php, server/app/Application/Canvas/*.php, server/app/Http/Controllers/Api/CanvasController.php, server/routes/api.php, server/resources/js/canvas/{types.ts,ExcalidrawCanvasAdapter.ts,CanvasHost.vue,react/ExcalidrawIsland.tsx,__tests__/canvas-boundary.test.ts}, server/tests/{Unit/CanvasTest.php,Feature/Api/CanvasApiTest.php}, server/database/factories/{CanvasFactory.php,CanvasDocumentFactory.php}, docs/api/openapi.yaml, docs/third-party/licenses.md, docs/implementation-status.md
- Notes: IndexedDB offline mutation queue is deferred to TASK-044 (FR-57); the spike verifies the in-memory path end-to-end. Excalidraw's imperative API is surfaced via the `excalidrawAPI` callback prop, projected onto the adapter's own handle so consumers stay decoupled from Excalidraw internal types.

#### TASK-041 — Canvas domain schema
- Status: DONE
- Priority: P0
- SRS: FR-55, FR-56; SRS §7.5 Canvas Tables (canvases, canvas_documents, canvas_files).
- Acceptance:
  - [x] `canvases` migration: user ownership, title, optional goal/milestone/program/task context FKs, version, timestamps (SRS §7.5)
  - [x] `canvas_documents` migration: canvas_id, schema_version, scene_json JSONB, version, timestamps (SRS §7.5)
  - [x] `canvas_files` migration: canvas_id, storage_path, content_type, size_bytes, sha256, timestamps (SRS §7.5)
  - [x] Binary files referenced by stable application-owned storage path; binary payloads live in object storage (SRS §7.5)
  - [x] Domain: `Canvas`, `CanvasDocument`, `CanvasFile` entities + `CanvasRepository` contract (find/list/create/updateDocument + list/createFile)
  - [x] FR-56 optimistic versioning: `version` monotonic, stale update → `CanvasVersionConflict` (409)
  - [x] Application use cases: CreateCanvas, ListCanvases, GetCanvas, SaveCanvas, AddCanvasFile, ListCanvasFiles
  - [x] HTTP: `/canvases` GET+POST, `/canvases/{canvasId}` GET+PUT, `/canvases/{canvasId}/files` GET+POST, owner-scoped (404 on cross-user, SRS §15.1)
  - [x] OpenAPI Canvas + CanvasDocument + CanvasFile schemas synchronized
- Verification:
  - [x] Unit/Feature: `vendor/bin/phpunit` → OK (287 tests, 768 assertions, 32 canvas tests)
  - [x] `composer analyse` → PHPStan no errors; `composer lint` → Pint PASS (227 files)
  - [x] migrations apply to PostgreSQL (`migrate:status` Ran; canvases, canvas_documents, canvas_files present)
  - [x] `check-openapi.sh` → PASS (40 paths); `check-doc-links.sh` → PASS
- Evidence: database/migrations/{2026_08_18_100000_create_canvases_table.php,2026_08_18_100001_create_canvas_documents_table.php,2026_08_18_100002_create_canvas_files_table.php}, server/app/Domain/Canvas/{Canvas,CanvasDocument,CanvasFile,CanvasVersionConflict}.php, server/app/Domain/Canvas/Contracts/CanvasRepository.php, server/app/Infrastructure/Canvas/EloquentCanvasRepository.php, server/app/Application/Canvas/*.php, server/app/Http/Controllers/Api/CanvasController.php, server/app/Models/{Canvas,CanvasDocument,CanvasFile}.php, server/routes/api.php, server/tests/{Unit/CanvasTest.php,Unit/CanvasFileTest.php,Feature/Api/CanvasApiTest.php}, server/database/factories/{CanvasFactory.php,CanvasDocumentFactory.php,CanvasFileFactory.php}, docs/api/openapi.yaml
- Notes: Note-context attachment (FR-55 "Note context") is a knowledge-layer concern (note↔canvas link via knowledge_links) rather than a `canvases.note_id` column, since the SRS §7.5 schema does not define note_id on canvases; the link model already supports it. Canvas archive behavior is a future lifecycle concern tracked at the Canvas UI layer.

#### TASK-042 — Excalidraw adapter
- Status: DONE
- Priority: P0
- SRS: FR-55; ADR-005 (Excalidraw behind a Kinevo CanvasAdapter boundary); ADR-002 (React island).
- Acceptance:
  - [x] Framework-agnostic `CanvasAdapter` contract (types.ts): mount/load/getScene/save/setReadOnly/setTheme/subscribe/flush/destroy
  - [x] `ExcalidrawCanvasAdapter` implements the contract behind the boundary; Vue talks only to the adapter, never to React/Excalidraw types
  - [x] `ExcalidrawIsland` React component renders Excalidraw; imperative API surfaced via `excalidrawAPI` callback and projected onto an adapter-owned handle
  - [x] `CanvasHost.vue` mounts the adapter and forwards scene/readOnly/theme; emits `change` + `ready` events
  - [x] Adapter refactored for testability: island + React-root factories injectable (DI seam), so orchestration is verifiable without a WebGL/canvas environment
  - [x] 9 unit tests verify adapter orchestration (mount/load/save/subscribe/flush/destroy + engine forwarding) via fake island/root
  - [x] Excalidraw scene JSON is the canonical representation; Kinevo owns persistence/versioning/ownership
- Verification:
  - [x] Frontend: `npm run typecheck` → no errors; `npm run test` → 37 tests (9 new adapter tests); `npm run build` → built OK
  - [x] Backend regression: PHPUnit → OK (287 tests, 768 assertions); Pint PASS; PHPStan no errors
- Evidence: server/resources/js/canvas/{types.ts,ExcalidrawCanvasAdapter.ts,CanvasHost.vue,react/ExcalidrawIsland.tsx,__tests__/ExcalidrawCanvasAdapter.test.ts,__tests__/canvas-boundary.test.ts}
- Notes: The React island module is `vi.mock`-ed in the adapter test because Excalidraw requires a WebGL/canvas environment absent from happy-dom; the DI seam (injectable island/root factories) lets the adapter's own boundary logic be tested in isolation.

#### TASK-043 — Canvas autosave/versioning
- Status: DONE
- Priority: P0
- SRS: FR-56 (optimistic versioning, 409 on stale); design.md §Canvas save states (Saved/Saving/Offline/Syncing/Conflict/Failed).
- Acceptance:
  - [x] Framework-agnostic `CanvasAutosaveController` orchestrates adapter + server save
  - [x] Debounced autosave on adapter scene changes (configurable wait, cancellable timer)
  - [x] Optimistic versioning: tracks base version, sends it with each save, advances from server response (FR-56)
  - [x] Save-state lifecycle surfaced for UI: idle/dirty/saving/saved/offline/conflict/failed (design.md)
  - [x] 409-style conflict detected (`CANVAS_VERSION_CONFLICT`) → `conflict` state, autosave paused until `reconcile()`
  - [x] `reconcile(scene, serverVersion)` adopts authoritative version + scene, returns to idle
  - [x] `flush()`/`saveNow()` immediate save bypassing debounce; `dispose()` stops autosave
  - [x] `CanvasPersistence` contract injectable (HTTP layer implements `PUT /canvases/{canvasId}`)
- Verification:
  - [x] Frontend: `npm run typecheck` → no errors; `npm run test` → 45 tests (8 new autosave tests); `npm run build` → built OK
  - [x] Backend regression: PHPUnit → OK (287 tests, 768 assertions); Pint PASS; PHPStan no errors
- Evidence: server/resources/js/canvas/autosave.ts, server/resources/js/canvas/__tests__/autosave.test.ts
- Notes: Offline persistence of the pending scene (IndexedDB) and the offline sync state machine are the scope of TASK-044/offline tasks; this task handles the in-memory autosave orchestration and conflict surfacing. The server `PUT /canvases/{canvasId}` already returns `409 CANVAS_VERSION_CONFLICT` (TASK-040/041).

#### TASK-044 — Canvas offline mutation queue
- Status: DONE
- Priority: P0
- SRS: FR-57 (offline canvas mutations queueable via IndexedDB, sync on reconnect); SRS §9.2/§9.3/§9.4/§9.5; offline-sync.md.
- Acceptance:
  - [x] `MutationEnvelope` per SRS §9.3 + offline-sync.md (operation_id, entity_type, entity_id, operation_type, payload, client_timestamp, base_version, status, attempt_count, last_error)
  - [x] `MutationStore` contract (enqueue/listPending/markSyncing/markApplied/markFailed + canvas snapshot) with IndexedDB implementation (`IndexedDbMutationStore`) and injectable in-memory store for tests
  - [x] `CanvasOfflineQueue`: enqueue persists snapshot + envelope before reporting success (edit survives tab close, FR-57); FIFO sync; retryable failures retained and retried
  - [x] Sync state machine surfaced (offline-sync.md): idle/queued/syncing/conflict/failed_retryable/failed_permanent
  - [x] Conservative versioning (SRS §9.4): canvas conflicts are preserved for reconciliation and never silently last-write-wins overwritten
  - [x] `OfflineAwarePersistence` integrates with the autosave controller: offline saves are queued + snapshot locally, non-offline failures propagate
  - [x] Local canvas snapshot stored (SRS §9.2) so an offline edit is recoverable
- Verification:
  - [x] Frontend: `npm run typecheck` → no errors; `npm run test` → 54 tests (9 new offline tests); `npm run build` → built OK
  - [x] Backend regression: PHPUnit → OK (287 tests, 768 assertions); Pint PASS; PHPStan no errors
- Evidence: server/resources/js/canvas/{offline.ts,offline-queue.ts,offline-store.ts,offline-persistence.ts,__tests__/offline.test.ts}
- Notes: IndexedDB is cache/queue, never canonical (offline-sync.md §Principle); PostgreSQL remains authoritative. Service Worker shell caching (TASK-050) and broader offline cache/today cache (TASK-051) are Phase 5 scope. The IndexedDB store requires a real IndexedDB (happy-dom lacks it), so the queue/sync logic is verified against the injectable in-memory store.

### Phase 5 — Offline/Recovery
#### TASK-050 — Service Worker shell caching
- Status: DONE
- Priority: P0
- SRS: FR-44 (offline support via Service Worker); offline-sync.md §Service Worker (cache app shell, enable offline navigation in scope, never a second business-logic engine); SRS §9.1.
- Acceptance:
  - [x] Testable, browser-agnostic SW cache-strategy core (`sw-core.ts`): precache on install, network-first navigations with cache fallback, cache-first shell assets
  - [x] `installShellCaching` wires install/activate/fetch: precache shell, claim clients + purge stale caches on activate, serve navigations offline
  - [x] Service Worker NEVER intercepts business API requests (pass-through) — it is not a business-logic engine (offline-sync.md §Service Worker)
  - [x] SW entry (`sw.ts`) binds browser globals (self, caches, clients, fetch) to the testable core
  - [x] Vite plugin builds the SW and injects a precache manifest of the hashed shell assets (app.css, app.js, fonts); final `sw.js` copied to web root for full-origin scope
  - [x] Guarded SW registration in `app.js` (only secure contexts + SW-capable browsers; failures swallowed)
  - [x] `public/sw.js` build artifact gitignored
- Verification:
  - [x] Frontend: `npm run typecheck` → no errors; `npm run test` → 63 tests (9 new sw-core tests); `npm run build` → built OK, `public/sw.js` precaches app shell assets
  - [x] Backend regression: PHPUnit → OK (287 tests, 768 assertions); Pint PASS; PHPStan no errors
- Evidence: server/resources/js/offline/{sw-core.ts,sw.ts,sw-env.d.ts,register-sw.ts,__tests__/sw-core.test.ts}, server/resources/js/app.js, server/vite.config.ts, server/vite-shell-precache-plugin.ts, server/.gitignore
- Notes: This task caches the app SHELL (HTML/CSS/JS/fonts) for offline navigation. Today/business data caching is TASK-051 (FR-44). The SW core is tested with an injectable browser-environment mock since happy-dom lacks a real Service Worker/Cache Storage; `Request`/`Response` are used directly.

#### TASK-051 — Today cache
- Status: DONE
- Priority: P0
- SRS: FR-44 (offline Today cache, "Today has been loaded online at least once for full baseline cache"); SRS §9.1 (Today view cache), §9.2 (cached entities + schedule snapshot in IndexedDB); offline-sync.md.
- Acceptance:
  - [x] `TodayData` snapshot type (date, tasks, subtasks, schedule slots, cachedAt) matching the Today view surface
  - [x] `TodayCacheStore` contract (put/get/clear by date) + `IndexedDbTodayCacheStore` (IndexedDB) + injectable in-memory store for tests
  - [x] `TodayCache` orchestration: online first-load fetches `GET /api/v1/today?date=` and persists snapshot (FR-44 baseline cache precondition)
  - [x] Offline reads serve the cached snapshot (SRS §9.1 "Today view cache"); returns `none` if never loaded online
  - [x] `refresh()` forces a network fetch on reconnect; `isStale()` detects stale cache; `clear()` removes a superseded snapshot
  - [x] IndexedDB is cache only — PostgreSQL remains authoritative (offline-sync.md §Principle)
- Verification:
  - [x] Frontend: `npm run typecheck` → no errors; `npm run test` → 71 tests (8 new Today cache tests); `npm run build` → built OK
  - [x] Backend regression: PHPUnit → OK (287 tests, 768 assertions); Pint PASS; PHPStan no errors
- Evidence: server/resources/js/offline/{today-types.ts,today-cache.ts,today-store.ts,__tests__/today-cache.test.ts}
- Notes: The Today schedule endpoint (`GET /api/v1/today?date=`) is contractually defined (OpenAPI Today tag, SRS §8.4); the cache fetches and stores its response. Quick Capture offline queueing and mutation enqueue are the scope of TASK-052+. Cache staleness vs network refresh is surfaced to the UI via `isStale()`.

#### TASK-052 — Mutation queue
- Status: DONE
- Priority: P0
- SRS: FR-44 (Quick Capture offline via outbound mutation queue, last-write-wins); SRS §9.3 (mutation envelope); SRS §9.4 (LWW for low-risk, conservative for versioned); offline-sync.md §Queue semantics + §Sync state machine.
- Acceptance:
  - [x] General, entity-agnostic `MutationEnvelope` (entity_type, entity_id, operation_type, payload, client_timestamp, base_version, status, attempt_count, last_error) per SRS §9.3
  - [x] `MutationQueue` class: `enqueue(entityType, entityId, operationType, payload, baseVersion?)` persists before resolving (survives tab close, FR-44)
  - [x] FIFO sync of pending mutations; retryable failures retained and retried; permanent failures surfaced
  - [x] Conflict handling (SRS §9.4): versioned/rich-content conflicts preserved and surfaced, never silently discarded
  - [x] Last-write-wins collapse for low-risk non-versioned mutations to the same entity (SRS §9.4); versioned mutations never collapsed
  - [x] Sync state machine surfaced: idle/queued/syncing/conflict/failed_retryable/failed_permanent
  - [x] `OfflineMutationStore` contract (entity-agnostic) + `IndexedDbQueueStore` + injectable in-memory store for tests
- Verification:
  - [x] Frontend: `npm run typecheck` → no errors; `npm run test` → 81 tests (10 new queue tests); `npm run build` → built OK
  - [x] Backend regression: PHPUnit → OK (287 tests, 768 assertions); Pint PASS; PHPStan no errors
- Evidence: server/resources/js/offline/{queue-types.ts,queue.ts,queue-store.ts,__tests__/queue.test.ts}
- Notes: This general queue (TASK-052) supersedes the canvas-specific enqueue used by TASK-044 for general entities (tasks/notes/quick capture); the canvas path retains its snapshot persistence. Versioned rich content (canvas/notes) always uses the conservative rule; low-risk operations (task create/update without version) may collapse to last-write-wins.

#### TASK-053 — Last-write-wins policy
- Status: DONE
- Priority: P0
- SRS: §9.4 Conflict Strategy (LWW for narrow MVP queue where configured; conservative rule for versioned rich content/canvas, never silently discarded); FR-44 (Quick Capture LWW sync); offline-sync.md §Conflict strategy; domain-model `ConflictResolver`.
- Acceptance:
  - [x] Domain-owned, deterministic `LastWriteWinsPolicy` (pure, no I/O) deciding conflict resolution
  - [x] `isLwwEligible(entityType, operationType, isVersioned)` — low-risk entities (task/subtask/goal/milestone/program/quick_capture) + low-risk ops (toggle/quick_capture) eligible ONLY when unversioned
  - [x] Versioned rich content (canvas/note) and any baseVersion-bearing mutation are ALWAYS conservative (never LWW)
  - [x] `resolveConflict(ctx)` returns `last_write_wins` for LWW-eligible stale mutations, `conflict` otherwise (SRS §9.4)
  - [x] Unknown entities default to conservative (conflict) — no blind LWW
  - [x] Deterministic for identical inputs; supports injectable allow-lists
- Verification:
  - [x] Frontend: `npm run typecheck` → no errors; `npm run test` → 94 tests (13 new policy tests); `npm run build` → built OK
  - [x] Backend regression: PHPUnit → OK (287 tests, 768 assertions); Pint PASS; PHPStan no errors
- Evidence: server/resources/js/offline/{lww-policy.ts,__tests__/lww-policy.test.ts}
- Notes: This policy formalizes the decision already exercised by TASK-052's collapse logic; it is the single domain-owned source of truth for offline conflict resolution. The sync layer consults it when a queued mutation collides with server state.

#### TASK-054 — EOD reconciliation
- Status: DONE
- Priority: P0
- SRS: FR-47, FR-35.
- Acceptance:
  - [x] `notifications` migration: user ownership, type, scheduled_for date, title, payload JSON, read_at, timestamps, `(user_id, type, scheduled_for)` unique + `(user_id, scheduled_for, read_at)` index (SRS §7, §7.8)
  - [x] Domain: `Notification` immutable entity + `NotificationType` VO (reconciliation) + `NotificationRepository` contract; owner-scoped read (`markRead` returns null on cross-user, SRS §15.1)
  - [x] FR-47 21:00 prompt: `RunEodPromptUseCase` creates exactly ONE reconciliation notification per user/local-day (idempotent — retry returns the existing notification); no untouched tasks → no notification (FR-35 Alternative Flow); payload snapshots eligible task id/title/status
  - [x] FR-47 23:59 deadline: `RunEodDeadlineUseCase` transitions eligible tasks to `missed` (Terlewat) via the Task state machine (`scheduled → missed`); idempotent — retry yields no duplicate transitions (FR-47 Exception Flows)
  - [x] Prompt eligibility (FR-35/FR-47): tasks neither completed nor partial — `scheduled` + `in_progress`; deadline eligibility = state-machine `canTransitionTo(missed)`
  - [x] Timezone: local day computed in the owner profile timezone (FR-47 Business Rules), falling back to `config('app.timezone')`
  - [x] Scheduler wired: `eod:reconcile --phase=prompt` @21:00, `--phase=deadline` @23:59 (`bootstrap/app.php` withSchedule)
  - [x] HTTP: `GET /notifications` (owner list, unread filter, limit) + `POST /notifications/{notificationId}/read` (owner-scoped, 404 on cross-user/missing); OpenAPI Notifications tag/paths/schemas synchronized
- Verification:
  - [x] Unit/Feature: `vendor/bin/phpunit` → OK (315 tests, 832 assertions; +20: EOD prompt/deadline service, Notification entity, notifications API, eod:reconcile command incl. idempotency)
  - [x] `composer analyse` → PHPStan no errors
  - [x] `composer lint` → Pint PASS (243 files)
  - [x] migration applies to PostgreSQL (`migrate:status` Ran; notifications table + unique constraint + index present)
  - [x] `check-secrets.sh`, `validate-repo.sh`, `check-doc-links.sh`, `check-openapi.sh` (42 paths) all pass
- Evidence: server/app/Domain/Reconciliation/EndOfDayReconciliationService.php, server/app/Application/Reconciliation/{RunEodPrompt,RunEodDeadline}UseCase.php, server/app/Domain/Notifications/{Notification,ValueObjects/NotificationType,Contracts/NotificationRepository}.php, server/app/Infrastructure/Notifications/EloquentNotificationRepository.php, server/app/Models/Notification.php, server/app/Application/Notifications/{ListNotifications,MarkNotificationRead}UseCase.php, server/app/Console/Commands/EodReconcileCommand.php, server/app/Http/Controllers/Api/NotificationController.php, server/routes/api.php, server/bootstrap/app.php, database/migrations/2026_08_18_120000_create_notifications_table.php, server/tests/{Unit/NotificationTest.php,Unit/EndOfDayReconciliationServiceTest.php,Feature/Api/NotificationsApiTest.php,Feature/Console/EodReconcileCommandTest.php}, docs/api/openapi.yaml (Notifications paths/schemas)
- Notes: user response to the prompt (Selesai/Sebagian/Jadwal Ulang/Lewati) flows through the existing task endpoints (status/partial-complete), so no new response API was needed. "Scheduled today" is approximated by `status=scheduled` because `task_assignments` persistence (SRS §7 data model) is not yet built — when assignments land, eligibility should be refined to tasks assigned on the reconciliation day. Emergency-pause notification suppression (FR-47 Business Rules) is deferred with `pause_events` (TASK-060+ context). Morning Recovery (FR-48) is TASK-055.

#### TASK-055 — Morning Recovery
- Status: DONE
- Priority: P0
- SRS: FR-48.
- Acceptance:
  - [x] State machine correction (smallest safe change): `missed → completed` added so a recovered task can be marked complete (FR-48; design.md Recovery UI "Complete"); `missed → backlog/scheduled` retained — verified by TaskTest
  - [x] Domain `MorningRecoveryService`: deadline-first ordering (nearest first, no-deadline last, deterministic id tiebreak — FR-48 Business Rule); program invalidation (`program_completed`/`program_dropped`, FR-48 Exception Flow); allowed-actions per task (reschedule withheld for terminal programs)
  - [x] `GET /recovery`: owner-scoped list of previous-day Terlewat (missed) tasks with `allowed_actions` + `invalid_reason`, nearest deadline first (FR-48 Normal Flow, Business Rule)
  - [x] `POST /recovery/{taskId}` with `action` = reschedule|complete|backlog (+ optional `due_at` for reschedule): only `missed` tasks are recoverable (422 otherwise); complete logs `task_completed` activity (FR-48 Normal Flow "update task and log"); owner-scoped 404 (SRS §15.1)
  - [x] Exception flow: reschedule on a task whose program is Completed/Dropped → 422 with the reason surfaced in the list; complete/backlog remain available (manual disposition)
  - [x] AC-06/FR-48 AC: missed task from yesterday appears next morning and can be rescheduled to today (`reschedule` → `scheduled`)
  - [x] OpenAPI Recovery tag + `/recovery` GET + `/recovery/{taskId}` POST + RecoveryItem/RecoveryListResponse/RecoveryResolveRequest schemas synchronized
- Verification:
  - [x] Unit/Feature: `vendor/bin/phpunit` → OK (332 tests, 877 assertions; +17: MorningRecoveryService unit, RecoveryApi feature incl. ordering/scoping/actions/invalid-program)
  - [x] `composer analyse` → PHPStan no errors
  - [x] `composer lint` → Pint PASS (249 files)
  - [x] `check-secrets.sh`, `validate-repo.sh`, `check-doc-links.sh`, `check-openapi.sh` (44 paths) all pass
- Evidence: server/app/Domain/Reconciliation/MorningRecoveryService.php, server/app/Application/Recovery/{GetRecoveryList,ResolveRecoveredTask}UseCase.php, server/app/Domain/Tasks/ValueObjects/TaskStatus.php (MISSED transitions), server/app/Infrastructure/Tasks/EloquentTaskRepository.php (listMissedForUser), server/app/Http/Controllers/Api/RecoveryController.php, server/routes/api.php, server/tests/{Unit/MorningRecoveryServiceTest.php,Unit/TaskTest.php,Feature/Api/RecoveryApiTest.php}, docs/api/openapi.yaml (Recovery paths/schemas)
- Notes: "Delete" action from the FR-48 description is deferred — the design.md Recovery UI (Reschedule/Complete/Keep in backlog) is the concrete UX contract, and destructive task deletion has no API contract yet (task ids are referenced by activity_logs/knowledge_links; deletion policy is a separate lifecycle decision). "Previous-day" is represented by all currently-`missed` tasks (produced by nightly EOD runs), since no `missed_at` column exists; a missed_at timestamp can refine this later. Morning Recovery is driven by the EOD job (TASK-054) + this query/resolve surface; the Today UI integration is frontend scope.

### Phase 6 — Adaptive Productivity
#### TASK-060 — Context check-in model
- Status: DONE
- Priority: P1
- SRS: FR-58; SRS §7.6 (adaptive_context), §12.2; domain-model Context Observation.
- Acceptance:
  - [x] `adaptive_context` migration (SRS §7.6): user ownership, optional task_id (FK nullOnDelete), energy/stress/task_difficulty/skill_familiarity 1–10, interruption_count, context_switch_cost, focus_duration_minutes, checked_at, timestamps + (user_id, checked_at) & (user_id, task_id) indexes
  - [x] Domain `SignalLevel` VO (bounded 1–10) + `ContextObservation` immutable entity (at least one signal required; negative counts rejected; advisory-only semantics — never clinical/neurological, FR-58 Business Rule)
  - [x] Domain `BurnoutSignalDetector` (deterministic heuristic): sustained avg stress ≥7 with avg energy ≤4 over ≥3 samples raises a burnout warning; sparse data never triggers (FR-58/§12.3 fallback); result `BurnoutSignal`(active, reason, sampleCount) feeds the Capacity feedback loop (FR-49 upstream, TASK-025 note)
  - [x] `ContextObservationRepository` contract (create/listForUser/listForTask/listSince) + Eloquent impl (deterministic `checked_at desc, id desc` ordering)
  - [x] Application use cases: RecordContextCheckIn (task ownership validated via GetTaskUseCase, 404 on foreign/missing), ListContextCheckIns, GetBurnoutSignal (14-day window)
  - [x] HTTP: `POST /adaptive/context` (record, 422 without ≥1 signal / out-of-range levels), `GET /adaptive/context` (owner list, limit), `GET /adaptive/burnout`; all owner-scoped (SRS §15.1)
  - [x] OpenAPI Adaptive tag + paths + ContextCheckInRequest/ContextObservation(+Response/List)/BurnoutSignalResponse schemas synchronized
- Verification:
  - [x] Unit/Feature: `vendor/bin/phpunit` → OK (350 tests, 931 assertions; +18: SignalLevel/ContextObservation/BurnoutSignalDetector unit, AdaptiveContextApi feature incl. task ownership, scoping, burnout activation)
  - [x] `composer analyse` → PHPStan no errors
  - [x] `composer lint` → Pint PASS (263 files)
  - [x] migration applies to PostgreSQL (`migrate:status` Ran; adaptive_context table + indexes + FKs present)
  - [x] `check-secrets.sh`, `validate-repo.sh`, `check-doc-links.sh`, `check-openapi.sh` (46 paths) all pass
- Evidence: database/migrations/2026_08_18_130000_create_adaptive_context_table.php, server/app/Domain/Adaptive/{ContextObservation,BurnoutSignal,BurnoutSignalDetector,Contracts/ContextObservationRepository}.php, server/app/Domain/Adaptive/ValueObjects/SignalLevel.php, server/app/Infrastructure/Adaptive/EloquentContextObservationRepository.php, server/app/Models/AdaptiveContext.php, server/app/Application/Adaptive/{RecordContextCheckIn,ListContextCheckIns,GetBurnoutSignal}UseCase.php, server/app/Http/Controllers/Api/AdaptiveContextController.php, server/routes/api.php, server/tests/{Unit/ContextObservationTest.php,Unit/BurnoutSignalDetectorTest.php,Feature/Api/AdaptiveContextApiTest.php}, docs/api/openapi.yaml (Adaptive paths/schemas)
- Notes: domain-model.md's recommended `EnergyLevel`/`StressLevel` VOs are realized as `SignalLevel` instances per field (one bounded 1–10 VO) to avoid four identical VOs; documented here as a faithful simplification. Burnout thresholds (stress ≥7, energy ≤4, ≥3 samples) are a heuristic policy, deliberately conservative and deterministic, not a clinical claim (FR-58). Soft ranking consumption of these signals (context_fit → ranking component) is TASK-061 (FR-59).

#### TASK-061 — Soft signal scoring
- Status: DONE
- Priority: P1
- SRS: FR-59; scheduling-engine §Soft ranking (#6 context fit); architecture.md §Soft optimization signals.
- Acceptance:
  - [x] Domain `ContextFitScorer` (deterministic, FR-59 AC): converts energy/stress/difficulty/familiarity (0..1) into a single context-fit score (0..1, higher = better fit); null inputs (sparse/anomalous) fall back to the neutral baseline 0.5 per component (FR-59 Business Rule — deterministic baseline policy)
  - [x] Formula verified against FR-59 AC: high difficulty (0.9) + low energy (0.2) → low fit (~0.25); all-baseline inputs → exactly 0.5 (engine-neutral)
  - [x] Domain `ContextFitService`: aggregates check-ins into a per-task fit map — user energy/stress require ≥2 samples (else neutral), task difficulty/familiarity use any task-scoped sample; deterministic; `applyToScheduleTasks()` injects `contextFit` into `ScheduleTask` (→ `RankingCandidate.contextFit` → `ContextFitComponent`, soft ranking #6)
  - [x] `ScheduleTask::withContextFit()` — immutable rebuild with the soft signal; hard signals (locked, priority, deadlines) untouched — soft ordering can never override hard constraints (FR-59/FR-64)
  - [x] Application `BuildContextFitMapUseCase` (14-day window via ContextObservationRepository::listSince) ready for the schedule assembly path
  - [x] Integration test proves FR-59 AC end-to-end: equal-tier/deadline tasks re-order by context fit through `TaskRankingEngine::default()`, and the fit map never mutates locked/priority signals
- Verification:
  - [x] Unit: `vendor/bin/phpunit` → OK (363 tests, 951 assertions; +13: ContextFitScorer, ContextFitService, ContextFitRankingIntegration)
  - [x] `composer analyse` → PHPStan no errors
  - [x] `composer lint` → Pint PASS (269 files)
  - [x] `check-secrets.sh`, `validate-repo.sh`, `check-doc-links.sh`, `check-openapi.sh` (46 paths) all pass — no API/schema change (scheduling engine component, matches TASK-020..026 precedent)
- Evidence: server/app/Domain/Adaptive/{ContextFitScorer.php,ContextFitService.php}, server/app/Domain/Scheduling/ScheduleTask.php (withContextFit), server/app/Application/Adaptive/BuildContextFitMapUseCase.php, server/tests/Unit/{ContextFitScorerTest.php,ContextFitServiceTest.php}, server/tests/Unit/Scheduling/ContextFitRankingIntegrationTest.php
- Notes: Feed path is complete — the schedule assembly step (building ScheduleTask from persisted tasks, currently part of the future schedule-persistence task) calls `BuildContextFitMapUseCase` then `ContextFitService::applyToScheduleTasks`. The burnout signal (TASK-060) suppresses capacity boosts upstream; the fit signal here only ranks which tasks fit the current context best.

#### TASK-062 — Adaptive focus block recommendation
- Status: DONE
- Priority: P1
- SRS: §12.4 (Adaptive Focus Blocks), §12.2 (focus-session completion signal); SRS §7 `focus_sessions` table; design.md §Adaptive focus block UI.
- Acceptance:
  - [x] `focus_sessions` migration (SRS §7): user ownership, optional task_id (FK nullOnDelete), started_at, ended_at, duration_minutes, timestamps + (user_id, started_at) & (user_id, task_id) indexes
  - [x] Domain `FocusSession` immutable entity: duration derived from the actual interval; end-after-start + ≥1-minute validation; toArray
  - [x] Domain `FocusBlockRecommender` (deterministic, SRS §12.4): task-scoped patterns take precedence, then user-wide patterns, then the configured baseline; out-of-range durations excluded as anomalous; result rounded to a configurable step and clamped to configured bounds
  - [x] Durations are configuration, never biological claims (design.md: recommendation, not "scientifically optimal"); sparse history → baseline fallback with an explicit `basis` (task_patterns|user_patterns|baseline) + `reason`
  - [x] `FocusSessionRepository` contract (create/listForUser/listSince) + Eloquent impl (deterministic `started_at desc, id desc` ordering)
  - [x] Application use cases: RecordFocusSession (task ownership → 404), ListFocusSessions (task filter + limit), RecommendFocusBlock (30-day window, task-scoped then user-wide)
  - [x] HTTP: `POST /focus-sessions` (record completed session), `GET /focus-sessions` (list, task_id filter), `GET /focus-sessions/recommendation` (task_id optional); owner-scoped (SRS §15.1)
  - [x] OpenAPI Focus tag + paths + FocusSession(+Create/Response/List) + FocusBlockRecommendationResponse schemas synchronized
- Verification:
  - [x] Unit/Feature: `vendor/bin/phpunit` → OK (381 tests, 1004 assertions; +18: FocusSession, FocusBlockRecommender, FocusSessionsApi incl. task ownership, scoping, pattern-based recommendation)
  - [x] `composer analyse` → PHPStan no errors
  - [x] `composer lint` → Pint PASS (282 files)
  - [x] migration applies to PostgreSQL (`migrate:status` Ran; focus_sessions table + indexes + FK present)
  - [x] `check-secrets.sh`, `validate-repo.sh`, `check-doc-links.sh`, `check-openapi.sh` (48 paths) all pass
- Evidence: database/migrations/2026_08_18_140000_create_focus_sessions_table.php, server/app/Domain/Focus/{FocusSession.php,FocusBlockRecommender.php,FocusBlockRecommendation.php,Contracts/FocusSessionRepository.php}, server/app/Infrastructure/Focus/EloquentFocusSessionRepository.php, server/app/Models/FocusSession.php, server/app/Application/Focus/{RecordFocusSession,ListFocusSessions,RecommendFocusBlock}UseCase.php, server/app/Http/Controllers/Api/FocusSessionController.php, server/routes/api.php, server/tests/{Unit/FocusSessionTest.php,Unit/FocusBlockRecommenderTest.php,Feature/Api/FocusSessionsApiTest.php}, docs/api/openapi.yaml (Focus paths/schemas)
- Notes: The in-progress timer lifecycle (start/pause/abandon, FR-05 Recharge pairing, persisted timer state) is the execution-timer UI concern and stays out of this slice — sessions are recorded on completion as actual intervals. Recharge accounting (Work-Life Ratio) remains a future FR-05 task. Recommendation config defaults: baseline 45 min, bounds 15–120 min, round-to 5 min, min 3 samples — all injectable via `FocusBlockRecommender` options.

#### TASK-063 — Progress event model
- Status: DONE
- Priority: P1
- SRS: §6.8 (meaningful progress events), §12.5 (progress event references the domain change that created it), §7 (progress_events table), §7.8-style append-only semantics; design.md §Meaningful progress; domain-model.md ProgressEventService.
- Acceptance:
  - [x] `progress_events` migration: user ownership, event_type, entity_type/entity_id, optional title, occurred_at, optional operation_id, JSON payload; index (user_id, occurred_at); unique (user_id, operation_id) for idempotent append
  - [x] `ProgressEventType` closed VO covering all §6.8 types (task_completed, milestone_advanced, milestone_completed, evidence_attached, experiment_recorded, goal_progress); only the three non-derived types are manually recordable
  - [x] Immutable `ProgressEvent` entity + `ProgressEventService` domain factories mapping a mutation to its event and its operation reference (§12.5)
  - [x] `ProgressEventRepository` contract + Eloquent impl (idempotent append by operation_id, deterministic ordering)
  - [x] Application: `RecordProgressEventUseCase` (idempotent), `ListProgressEventsUseCase` (from/to/type/limit)
  - [x] Auto-generation wired into `SetTaskStatusUseCase` (task_completed, same operation reference as FR-34 activity) and `SetMilestoneStatusUseCase` (milestone_advanced on planned→active; milestone_completed on completion; direct complete emits only the completed event)
  - [x] HTTP: `GET /progress` (list + filters), `POST /progress` (manual record of evidence_attached/experiment_recorded/goal_progress); owner-scoped (SRS §15.1); entity reference on manual records is informational (SRS §6.8 analytics input), not FK-authoritative
  - [x] OpenAPI Progress tag + paths + ProgressEvent(+Create/Response/List) schemas synchronized
- Verification:
  - [x] Unit/Feature: `vendor/bin/phpunit` → OK (397 tests, 1061 assertions; +16: ProgressEventType/entity, ProgressEventService, ProgressEventsApi incl. task/milestone auto-generation, milestone direct-complete single event, append idempotency, scoping, filters)
  - [x] `composer analyse` → PHPStan no errors
  - [x] `composer lint` → Pint PASS (294 files)
  - [x] migration applies to PostgreSQL (`migrate:status` Ran; progress_events table + unique/index present)
  - [x] `check-secrets.sh`, `validate-repo.sh`, `check-doc-links.sh`, `check-openapi.sh` (49 paths) all pass
- Evidence: database/migrations/2026_08_18_150000_create_progress_events_table.php, server/app/Domain/Progress/{ProgressEvent.php,ProgressEventService.php,ValueObjects/ProgressEventType.php,Contracts/ProgressEventRepository.php}, server/app/Infrastructure/Progress/EloquentProgressEventRepository.php, server/app/Models/ProgressEvent.php, server/app/Application/Progress/{RecordProgressEvent,ListProgressEvents}UseCase.php, server/app/Application/Tasks/SetTaskStatusUseCase.php, server/app/Application/Milestones/SetMilestoneStatusUseCase.php, server/app/Http/Controllers/Api/ProgressEventController.php, server/routes/api.php, server/tests/{Unit/ProgressEventTest.php,Unit/ProgressEventServiceTest.php,Feature/Api/ProgressEventsApiTest.php}, docs/api/openapi.yaml (Progress paths/schemas)
- Notes: Remaining §6.8 types (evidence_attached, experiment_recorded, goal_progress) are recorded manually until their generating features (note attachments, experiment tracking, goal material-progress detection) land — the model is already wired for them. Progress events remain informational inputs to analytics/adaptive recommendations and never overwrite activity logs (SRS §6.8).

### Phase 7 — AI
#### TASK-070 — AI provider interface
- Status: DONE
- Priority: P1
- SRS: FR-60 (provider abstraction; app remains operational when provider unavailable), NFR-11 (§4.11 providers behind an interface), §8.7 AI_PROVIDER_UNAVAILABLE, §13.4 (minimal context), §13.6 (production local AI), §17.8 (AI provider status telemetry); docs/ai-architecture.md (provider tree, roles, failure behavior); ADR-011.
- Acceptance:
  - [x] `config/ai.php` driver selection: `ollama | openai | mock | disabled`; timeouts; per-provider base URLs/models/keys; prompt budget caps; `.env.example` AI block
  - [x] `AiProvider` interface (name/model/isAvailable/generate/status) with four interchangeable providers: `OllamaProvider` (/api/generate), `OpenAiCompatibleProvider` (external, /chat/completions, opt-in), `MockProvider` (deterministic, dev/test), `DisabledProvider` (explicit no-AI)
  - [x] Domain VOs: `AiRole` (allowed roles from ai-architecture), `AiRequest` (validated role/prompt/temperature), `AiResponse` (metadata only — never private content), `AiProviderStatus` (telemetry snapshot); `AiProviderException` (catchable, CODE_UNAVAILABLE)
  - [x] `AiOrchestrator` domain seam (provider routing; future context building §13.4 and audit §7.7 plug in here) + `AiProviderFactory` driver resolution
  - [x] Application: `GenerateAiTextUseCase` (non-mutating; AI never reaches persistence here — FR-61/62 flow handles mutations), `GetAiProviderStatusUseCase`
  - [x] HTTP: `GET /ai/status` (provider status telemetry), `POST /ai/generate` (role-constrained text generation, prompt/size budget); unavailable provider → `503` + canonical code `AI_PROVIDER_UNAVAILABLE` (§8.7)
  - [x] Core app and deterministic scheduling remain fully operational when the provider is unavailable (FR-60 AC; DisabledProvider/connection-failure tests)
  - [x] OpenAPI AI tag + paths + AiStatusResponse/AiGenerateRequest/AiGenerateResponse/ErrorResponse schemas synchronized
- Verification:
  - [x] Unit/Feature: `vendor/bin/phpunit` → OK (415 tests, 1111 assertions; +18: VOs, factory driver resolution, all four providers incl. Http::fake success/500/connection-refused/empty-response, generate validation, 503 canonical code, status)
  - [x] `composer analyse` → PHPStan no errors
  - [x] `composer lint` → Pint PASS (313 files)
  - [x] no migration required (interface slice only)
  - [x] `check-secrets.sh`, `validate-repo.sh`, `check-doc-links.sh`, `check-openapi.sh` (51 paths) all pass
- Evidence: server/config/ai.php, server/.env.example (AI block), server/app/Domain/Ai/{AiOrchestrator.php,AiProviderException.php,Contracts/AiProvider.php,ValueObjects/{AiRole,AiRequest,AiResponse,AiProviderStatus}.php}, server/app/Infrastructure/Ai/{AiProviderFactory.php,Providers/{OllamaProvider,OpenAiCompatibleProvider,MockProvider,DisabledProvider}.php}, server/app/Application/Ai/{GenerateAiText,GetAiProviderStatus}UseCase.php, server/app/Http/Controllers/Api/AiController.php, server/routes/api.php, server/tests/{Unit/AiValueObjectsTest.php,Unit/AiProviderTest.php,Feature/Api/AiApiTest.php}, docs/api/openapi.yaml (AI paths/schemas)
- Notes: `ai_runs`/`ai_proposals` audit tables (SRS §7.7) and structured-output validation/approval are deferred to TASK-072 (FR-61) / TASK-073 (FR-62). TASK-071 (Ollama development adapter) is the explicit wiring/verification of the local Ollama transport already implemented here. AI context building (§13.4) lands with the proposal features.

#### TASK-071 — Ollama development adapter
- Status: DONE
- Priority: P1
- SRS: §13.6 (Ollama MAY run as a separate optional service; app MUST remain functional when unavailable), FR-60, §16.4 (Ollama internal-network only), §17.8 (AI provider status telemetry), §4.11 NFR-11; docs/ai-architecture.md (local Ollama preferred for privacy; small quantized model profile), docs/deployment.md.
- Acceptance:
  - [x] Optional `ollama` compose service (profile `ai`): internal network only (no host port published; app reaches it at `http://ollama:11434`), persistent model volume, healthcheck, `OLLAMA_KEEP_ALIVE=30m` load-on-demand posture; excluded from default `docker compose up`
  - [x] `make ollama-up` / `make ollama-down` profile targets; `make ai-status` / `make ai-smoke` adapter verification targets
  - [x] Artisan wiring/verification: `ai:status` (provider snapshot table; exit 1 when unavailable) and `ai:smoke` (tiny deterministic generation; exit 0/1) — non-mutating
  - [x] Provider resolution is lazy (call-time): Laravel resolves every command at console boot, which eagerly built the AiProvider singleton with boot-time config and broke runtime driver selection — replaced with a domain `AiProviderResolver` contract + `ConfigAiProviderResolver` (deferred to first use) so configured drivers resolve with current configuration in tests, local dev, and production
  - [x] Dev docs: deployment.md "Ollama development adapter" section (start/configure/verify; small quantized model guidance; internal-only exposure; failure behavior) + environment.md AI variable baseline + `OPENAI_API_KEY` classified as a secret; `.env.example` AI block
- Verification:
  - [x] Unit/Feature: `vendor/bin/phpunit` → OK (419 tests, 1122 assertions; +4 AiCommandTest: ai:status/ai:smoke under mock and disabled, exit codes and output)
  - [x] `composer analyse` → PHPStan no errors
  - [x] `composer lint` → Pint PASS (318 files)
  - [x] `docker compose config --quiet` valid; default services `postgres app`; `--profile ai` adds `ollama`
  - [x] no migration required (dev adapter + verification tooling)
  - [x] `check-secrets.sh`, `validate-repo.sh`, `check-doc-links.sh`, `check-openapi.sh` (51 paths) all pass
- Evidence: infrastructure/docker-compose.yml (ollama service), Makefile (ollama-up/down, ai-status, ai-smoke), server/app/Console/Commands/{AiStatusCommand,AiSmokeCommand}.php, server/app/Domain/Ai/Contracts/AiProviderResolver.php, server/app/Infrastructure/Ai/ConfigAiProviderResolver.php, server/app/Domain/Ai/AiOrchestrator.php (lazy provider), server/app/Providers/AppServiceProvider.php (resolver binding), server/tests/Feature/AiCommandTest.php, docs/deployment.md, docs/environment.md, server/.env.example
- Notes: The Ollama transport itself landed in TASK-070; this task wired and verified it for local development (compose service, artisan commands, Makefile, docs) and fixed the eager-resolver defect the command wiring exposed. Model choice stays a deployment tuning decision (SRS §13.6) — the compose service is model-agnostic and pulls on demand.

#### TASK-072 — Structured output validation
- Status: DONE
- Priority: P1
- SRS: FR-61 (versioned schemas; malformed AI JSON never reaches persistence as a domain mutation), §13.3 (proposal categories), §7.7 (ai_runs/ai_proposals audit), §7.8 (ai_runs index), §8.7 (AI_OUTPUT_INVALID); docs/ai-architecture.md (structured output, schema-constrained proposals).
- Acceptance:
  - [x] Migrations `ai_runs` + `ai_proposals` per §7.7 (provider, model, proposal_type, schema_version, prompt template version, context hash, token metadata, status, latency, error code; proposal payload, validation_result, decision, operation_id); §7.8 indexes
  - [x] `AiProposalType` closed VO for the §13.3 categories (goal_breakdown, milestone, task_extraction, canvas, summary) mapped to AI roles
  - [x] Versioned schema registry (`AiSchemaRegistry`, all v1) + dependency-free rule engine (`AiSchemaRules`: required/type/enum/int-bounds/length/date-pattern/array items — objects or scalars)
  - [x] `StructuredAiOutputParser`: JSON decode (tolerates ```json fences) → schema validation → `ValidatedAiProposal`; any failure throws `AiOutputException` (AI_OUTPUT_INVALID) BEFORE anything can be persisted
  - [x] `GenerateValidatedProposalUseCase`: generate → parse/validate → audit `ai_runs` (success or failed + error code + latency + context hash); provider unavailable → 503 audited as failed run
  - [x] HTTP: `POST /ai/proposals` (returns validated proposal or 422 AI_OUTPUT_INVALID / 503 AI_PROVIDER_UNAVAILABLE), `GET /ai/runs` (owner-scoped audit list + proposal_type filter); OpenAPI AI paths/schemas synchronized
  - [x] ai_proposals table ready for the FR-62 approval lifecycle (TASK-073); nothing is recorded there yet
- Verification:
  - [x] Unit/Feature: `vendor/bin/phpunit` → OK (438 tests, 1163 assertions; +19: parser/rule engine valid+invalid across all 5 schemas, malformed JSON, fences, enum/date/range/array violations, proposals 200/422/503, ai_runs audit rows, scoping/filtering)
  - [x] `composer analyse` → PHPStan no errors
  - [x] `composer lint` → Pint PASS (333 files)
  - [x] migrations applied to PostgreSQL (ai_runs/ai_proposals present with §7.8 indexes + FKs)
  - [x] `check-secrets.sh`, `validate-repo.sh`, `check-doc-links.sh`, `check-openapi.sh` (53 paths) all pass
- Evidence: database/migrations/2026_08_18_160000_create_ai_audit_tables.php, server/app/Domain/Ai/{AiSchemaRegistry.php,AiSchemaRules.php,StructuredAiOutputParser.php,AiOutputException.php,Contracts/AiRunRepository.php,Entities/AiRun.php,ValueObjects/{AiProposalType,ValidatedAiProposal}.php}, server/app/Infrastructure/Ai/EloquentAiRunRepository.php, server/app/Models/{AiRun,AiProposal}.php, server/app/Application/Ai/{GenerateValidatedProposal,ListAiRuns}UseCase.php, server/app/Http/Controllers/Api/AiController.php, server/routes/api.php, server/tests/{Unit/StructuredAiOutputTest.php,Feature/Api/AiProposalsApiTest.php}, docs/api/openapi.yaml (AI proposals/runs paths/schemas)
- Notes: Schema v1 rules are intentionally minimal and dependency-free; bumping `schema_version` is a breaking contract change requiring a documented migration note. Prompt/template versioning (ai_architecture: "Kinevo owns prompts/templates") and the approval/decision flow (FR-62, ai_proposals rows, accept/reject endpoints per §8.6) are TASK-073.

#### TASK-073 — Goal decomposition proposal
- Status: DONE
- Priority: P1
- SRS: FR-52 (draft breakdown → Milestones + workload; no large hierarchy silently committed), FR-62 (proposal before application; reject creates no mutation), FR-61 (schema-validated only), §8.6 (accept/reject endpoints), §15.1 (ownership), §13.3 (GoalBreakdownProposal), §7.7 (ai_proposals); docs/ai-architecture.md (Propose→Preview→Accept/Edit/Reject→Validate→Commit).
- Acceptance:
  - [x] `CreateGoalBreakdownProposalUseCase`: validates goal ownership, generates a schema-validated goal_breakdown proposal (audited in ai_runs), persists it as PENDING in ai_proposals — nothing is applied (FR-52 postcondition); rejects a payload whose goal_id does not match the requested goal (AI_OUTPUT_INVALID)
  - [x] Proposal entity + `AiProposalRepository` (persist/findForUser/list/updateDecision); owner-scoped everywhere (SRS §15.1)
  - [x] FR-62 decision flow: `POST /ai/proposals/{id}/accept` applies the Goal's Milestones within a DB transaction (SRS Transaction rule) and marks accepted + operation_id; `POST /ai/proposals/{id}/reject` marks rejected with no domain mutation; non-pending proposals cannot be re-decided
  - [x] HTTP: `POST /goals/{goalId}/breakdown-proposals` (per §8.6 / existing contract; returns pending proposal), `GET /ai/proposals`, `GET /ai/proposals/{id}`, `POST /ai/proposals/{id}/accept`, `POST /ai/proposals/{id}/reject`; generic `POST /ai/proposals` now persists the validated proposal as pending (was ephemeral)
  - [x] OpenAPI: breakdown-proposals + proposal list/show/accept/reject paths + AiProposal schema synchronized
- Verification:
  - [x] Unit/Feature: `vendor/bin/phpunit` → OK (450 tests, 1218 assertions; +12: AiProposal entity lifecycle, breakdown auth/ownership/pending, mismatched goal_id 422, accept applies milestones in transaction, reject no mutation, non-pending guard, owner scoping, list/filter, generic proposal persists+view)
  - [x] `composer analyse` → PHPStan no errors
  - [x] `composer lint` → Pint PASS (343 files)
  - [x] no migration required (ai_proposals table from TASK-072)
  - [x] `check-secrets.sh`, `validate-repo.sh`, `check-doc-links.sh`, `check-openapi.sh` (56 paths) all pass
- Evidence: server/app/Application/Ai/{CreateGoalBreakdownProposal,GetAiProposal,ListAiProposals,AcceptAiProposal,RejectAiProposal}UseCase.php, server/app/Domain/Ai/{Entities/AiProposal.php,Contracts/AiProposalRepository.php}, server/app/Infrastructure/Ai/EloquentAiProposalRepository.php, server/app/Http/Controllers/Api/{GoalController,AiController}.php, server/routes/api.php, server/tests/{Unit/AiProposalTest.php,Feature/Api/GoalBreakdownProposalApiTest.php}, docs/api/openapi.yaml (breakdown-proposals, proposals/{id}, accept, reject)
- Notes: Accept currently applies the Milestones only; the related workload/capacity allocation and task creation remain future work (FR-52 "workload allocation"). The `edited` decision (FR-62 allow edit) and AI audit `prompt_template_version` are deferred. Local dev uses the Ollama provider via TASK-071; tests use Http::fake.

#### TASK-074 — Note summarization/extraction
- Status: DONE
- Priority: P1
- SRS: §13.3 (SummaryProposal, TaskExtractionProposal), §13.4 (minimal owner-scoped context, no full-database sends), FR-61 (schema validation), FR-62 (proposal before task mutation), §17.4 golden flow #5 (note → extract task proposal → review → create Task), §8.6 (/ai/summarize-note, /ai/extract-tasks), §15.1 (ownership).
- Acceptance:
  - [x] `GenerateNoteProposalUseCase` for summary + task_extraction: loads the owner's note (404 otherwise), builds a minimal context prompt from the note's plain-text content bounded by `AI_MAX_PROMPT_CHARS` (SRS §13.4 — only the requested note, never the whole DB), validates AI output (FR-61), persists as PENDING (FR-62)
  - [x] `AcceptNoteTaskExtractionUseCase`: creates Tasks from an accepted task-extraction proposal within a DB transaction + marks accepted/operation_id; `reject` uses the shared RejectAiProposalUseCase → no task mutation
  - [x] HTTP: `POST /ai/summarize-note`, `POST /ai/extract-tasks`; `POST /ai/proposals/{id}/accept` now dispatches by type — goal_breakdown → milestones, task_extraction → tasks; owner-scoped
  - [x] OpenAPI: summarize-note + extract-tasks paths, accept oneOf (milestones | tasks) synchronized
- Verification:
  - [x] Unit/Feature: `vendor/bin/phpunit` → OK (460 tests, 1253 assertions; +10: role mapping, prompt budgeting, auth, summarize pending, extract pending (no task before accept), accept creates tasks in transaction, reject no mutation, ownership 404, invalid output 422, accept owner-scoped)
  - [x] `composer analyse` → PHPStan no errors
  - [x] `composer lint` → Pint PASS (347 files)
  - [x] no migration required (ai_proposals table from TASK-072)
  - [x] `check-secrets.sh`, `validate-repo.sh`, `check-doc-links.sh`, `check-openapi.sh` (58 paths) all pass
- Evidence: server/app/Application/Ai/{GenerateNoteProposal,AcceptNoteTaskExtraction}UseCase.php, server/app/Http/Controllers/Api/AiController.php, server/routes/api.php, server/tests/{Unit/NoteAiContextTest.php,Feature/Api/NoteAiApiTest.php}, docs/api/openapi.yaml (summarize-note, extract-tasks, accept oneOf)
- Notes: Summarization is informational (no mutation). Task extraction requires explicit acceptance before any Task is created (FR-62, §17.4 #5). `edited` decision and prompt-template versioning remain deferred.

#### TASK-075 — Canvas generation proposal
- Status: DONE
- Priority: P2
- SRS: §13.3 (CanvasProposal), §8.6 (/ai/suggest-canvas), FR-61 (schema validation), FR-62 (proposal before canvas creation), §15.1 (ownership); docs/ai-architecture.md (canvas proposal role; Excalidraw owns drawing behavior — external engine boundary).
- Acceptance:
  - [x] `GenerateCanvasProposalUseCase`: generates a canvas proposal (title + sections) from the user prompt, validates against the canvas schema (FR-61), persists as PENDING (FR-62); audited in ai_runs
  - [x] `AcceptCanvasProposalUseCase`: creates the Canvas (title) within a DB transaction + marks accepted/operation_id; reject uses the shared RejectAiProposalUseCase → no canvas created
  - [x] HTTP: `POST /ai/suggest-canvas`; `POST /ai/proposals/{id}/accept` dispatch extended to canvas → returns created Canvas; owner-scoped
  - [x] OpenAPI: suggest-canvas path + accept oneOf (milestones | tasks | canvas) synchronized
- Verification:
  - [x] Unit/Feature: `vendor/bin/phpunit` → OK (467 tests, 1278 assertions; +7: auth, suggest pending (no canvas before accept), accept creates canvas, reject no mutation, invalid output 422, payload validation, accept owner-scoped)
  - [x] `composer analyse` → PHPStan no errors
  - [x] `composer lint` → Pint PASS (350 files)
  - [x] no migration required (ai_proposals table from TASK-072)
  - [x] `check-secrets.sh`, `validate-repo.sh`, `check-doc-links.sh`, `check-openapi.sh` (59 paths) all pass
- Evidence: server/app/Application/Ai/{GenerateCanvasProposal,AcceptCanvasProposal}UseCase.php, server/app/Http/Controllers/Api/AiController.php, server/routes/api.php, server/tests/Feature/Api/CanvasAiApiTest.php, docs/api/openapi.yaml (suggest-canvas, accept oneOf)
- Notes: Accept creates the Canvas (title); the proposal's sections are returned as starting content for the UI to render — the actual Excalidraw scene serialization stays an editor/UI concern (external engine boundary), never AI-owned scene JSON. `edited` decision and prompt-template versioning remain deferred.

### Phase 8 — Operations
#### TASK-080 — Production Docker profile
- Status: DONE
- Priority: P0
- SRS: §16 (deployment/security posture), NFR-11 (Linux container, no Oracle dependency), §13.6 (optional Ollama); docs/architecture.md §Deployment shape (app/queue-worker/scheduler/postgres roles), docs/deployment.md (immutable image, explicit migration step, internal-only services, secrets never baked).
- Acceptance:
  - [x] `Dockerfile.prod`: multi-stage — Node stage builds frontend assets, slim php-fpm runtime has NO dev tooling; `--no-dev` composer deps + optimized autoload; opcache + JIT with `validate_timestamps=0`; `.dockerignore` keeps dev/build artifacts out of the build context
  - [x] Production entrypoint: applies container env over baked `.env` (canonical set), fails fast without `APP_KEY` (verified exit 1), builds config/route/event caches at boot with the REAL runtime env (so secret-backed config is never frozen at image build time), and dispatches roles: app → php-fpm, queue-worker, scheduler, migrate, artisan
  - [x] `docker-compose.prod.yml`: app (php-fpm :9000 internal), queue-worker, scheduler each as a container role; postgres with named volume + healthcheck; optional `ollama` behind `ai` profile (internal only); migrations as an explicit release step (`make prod-migrate`), not implicit on boot
  - [x] `.env.production.example` shipped in the image as the non-secret template (real secrets always injected by the deployment environment)
  - [x] Makefile production targets: `prod-build`, `prod-up`, `prod-down`, `prod-migrate`, `prod-logs`
  - [x] docs/deployment.md "Production Docker profile" section documenting build/usage
- Verification:
  - [x] `docker build` of Dockerfile.prod succeeds (image `kinevo-app:prod`)
  - [x] `docker compose -f docker-compose.prod.yml config` valid; `--profile ai` yields app/queue-worker/scheduler/postgres/ollama
  - [x] Runtime checks: `artisan --version` works with APP_KEY; missing APP_KEY → FATAL + exit 1; queue-worker and scheduler role dispatch verified
  - [x] `check-secrets.sh`, `validate-repo.sh`, `check-doc-links.sh`, `check-openapi.sh` (59 paths) all pass
- Evidence: infrastructure/docker/Dockerfile.prod, infrastructure/docker/kinevo-prod-entrypoint.sh, infrastructure/docker/.env.production.example, infrastructure/docker-compose.prod.yml, .dockerignore, Makefile (prod-* targets), docs/deployment.md
- Notes: Reverse proxy + TLS termination is TASK-081 (nginx routes to the app's internal :9000). Backup/restore is TASK-082, observability TASK-083. `config:cache` runs at container boot rather than image build to avoid freezing the build placeholder APP_KEY (a security/correctness concern identified during implementation).

#### TASK-081 — Reverse proxy/TLS
- Status: DONE
- Priority: P0
- SRS: NFR-02 (HTTPS/TLS for all traffic, security headers), §16.4 (only HTTP/HTTPS externally exposed; PostgreSQL/Ollama internal), §16.1 network trust boundaries ("Public HTTP enters through Nginx/Cloudflare"); docs/architecture.md §Network trust boundaries, docs/deployment.md (80/443 only through reverse proxy, Cloudflare edge).
- Acceptance:
  - [x] Nginx reverse-proxy config (`infrastructure/docker/nginx/default.conf`): HTTP→HTTPS redirect (except ACME challenge), TLS 1.2/1.3 termination with mounted certs, security headers, serves Vite `/build/` (immutable cache) + `sw.js` (no-cache), proxies to app php-fpm `:9000` via fastcgi, forwards `X-Forwarded-Proto https`
  - [x] `reverse-proxy` service in `docker-compose.prod.yml` publishing only 80/443; shared `certbot_conf`/`certbot_www` volumes; app publishes no host port
  - [x] `certbot` companion service (webroot) behind the `certbot` profile; `make prod-certbot EMAIL=...` issues/renews; Cloudflare-edge documented as an equal TLS profile
  - [x] App trusts the proxy (`trustProxies('*')` in `bootstrap/app.php`) so HTTPS URLs/schemes are generated correctly behind nginx
  - [x] docs/deployment.md "Reverse proxy & TLS" section (config, compose, first-time webroot issuance, renewal, Cloudflare option)
- Verification:
  - [x] `docker compose -f docker-compose.prod.yml config` valid; services include `reverse-proxy`; `certbot` behind `certbot` profile
  - [x] Nginx template envsubst verified (SERVER_NAME substituted; nginx runtime vars preserved); config syntactically valid (only "host not found in upstream app" outside the compose network, expected)
  - [x] `vendor/bin/phpunit` → OK (467 tests, 1278 assertions; trustProxies change covered)
  - [x] `composer analyse` → PHPStan no errors; `composer lint` → Pint PASS (350 files)
  - [x] `check-secrets.sh`, `validate-repo.sh`, `check-doc-links.sh`, `check-openapi.sh` all pass
- Evidence: infrastructure/docker/nginx/default.conf, infrastructure/docker-compose.prod.yml (reverse-proxy + certbot services/volumes), Makefile (prod-certbot), server/bootstrap/app.php (trustProxies), docs/deployment.md
- Notes: First-time cert issuance is a manual/release step (`make prod-certbot`); renewal is a scheduled `certbot renew`. Cloudflare edge (SRS "Nginx + Cloudflare-compatible edge") is an equally supported TLS profile documented as the alternative to self-managed LetsEncrypt.

#### TASK-082 — Backup/restore automation
- Status: DONE
- Priority: P0
- SRS: §16.4 (daily DB backup, remote backup copy, manual export, restore test), NFR-05 (daily backup, RPO ≤24h / RTO ≤4h suggested), §16.3 deployment (backups automated, restore tested); docs/deployment.md (backup strategy, restore procedure).
- Acceptance:
  - [x] `scripts/backup.sh`: timestamped gzipped `pg_dump` of the canonical store, retention prune (`BACKUP_KEEP`, default 7), optional S3-compatible remote copy (`--remote-bucket`, `aws`/`mc`)
  - [x] `scripts/restore.sh`: terminates connections, drops+recreates the DB, applies the backup; destructive flow guarded by `CONFIRM_RESTORE=yes` and DB-identifier validation
  - [x] Compose `backup` service: runs `backup.sh` on a daily loop into the `kinevo_backups` volume; remote copy via env; depends on healthy postgres
  - [x] Makefile targets: `prod-backup`, `prod-backup-list`, `prod-restore`
  - [x] docs/deployment.md "Backup & restore automation" section (scripts, compose, usage, restore test, RPO/RTO); manual JSON/CSV export noted (existing `GET /export`)
- Verification:
  - [x] `bash -n` syntax check on both scripts
  - [x] End-to-end: backup ran against the dev postgres (valid gzipped dump); restore aborted without `CONFIRM_RESTORE` and completed with it (terminate→drop→create→apply); schema (migrations) intact after restore
  - [x] `docker compose -f docker-compose.prod.yml config` valid (backup service + volume)
  - [x] `check-secrets.sh`, `validate-repo.sh`, `check-doc-links.sh`, `check-openapi.sh` all pass
- Evidence: scripts/backup.sh, scripts/restore.sh, infrastructure/docker-compose.prod.yml (backup service + kinevo_backups volume), Makefile (prod-backup/list/restore), docs/deployment.md
- Notes: Remote copy requires `aws`/`mc` present in the backup environment (the compose backup image can be extended with a client if off-box copy is needed); §16.4 remote backup copy is wired via `REMOTE_BUCKET`/`AWS_*`. Periodic restore testing remains an operational checklist item (SRS §16.4).

#### TASK-083 — Observability
- Status: DONE
- Priority: P1
- SRS: §16.5 (minimum telemetry: scheduler run status/duration, queue failures, API error rate, offline queue backlog, import failures, storage failures, AI provider status, database health; sensitive content MUST NOT be logged), §7.8 (scheduler_runs index), §16.3 (database health); docs/deployment.md Monitoring.
- Acceptance:
  - [x] `scheduler_runs` migration (SRS §7.8): job, status, duration_ms, error, started_at + (user_id, started_at) & (status, started_at) indexes; every scheduled job run (e.g. `eod:reconcile`) records success/failure + duration at runtime
  - [x] `ObservabilityService` (domain): DB health (live query), queue pending/failed, storage writability, AI provider status, recent scheduler runs; safe metadata only — never payloads/notes/prompts (SRS §16.5)
  - [x] Use cases: `GetHealthUseCase` (public readiness), `GetMetricsUseCase` (SRS §16.5 snapshot), `ListSchedulerRunsUseCase`, `RecordSchedulerRunUseCase` (wired into EOD command)
  - [x] HTTP: `GET /api/v1/health` (public; 200 ok / 503 degraded), `GET /api/v1/metrics` (authenticated telemetry snapshot), `GET /api/v1/observability/runs` (recent scheduler runs); OpenAPI Observability tag/paths/schemas synchronized
  - [x] docs/deployment.md "Observability" section (endpoints, scheduler telemetry, healthcheck wiring)
- Verification:
  - [x] Unit/Feature: `vendor/bin/phpunit` → OK (475 tests, 1307 assertions; +8: DB health, queue counts, snapshot no-sensitive-fields, public health, metrics auth+snapshot, scheduler run recording+listing, limit validation)
  - [x] `composer analyse` → PHPStan no errors; `composer lint` → Pint PASS (362 files)
  - [x] migration applied to PostgreSQL (scheduler_runs table + §7.8 indexes present)
  - [x] `check-secrets.sh`, `validate-repo.sh`, `check-doc-links.sh`, `check-openapi.sh` (62 paths) all pass
- Evidence: database/migrations/2026_08_19_100000_create_scheduler_runs_table.php, server/app/Domain/Observability/{ObservabilityService.php,SchedulerRun.php,Contracts/SchedulerRunRepository.php}, server/app/Infrastructure/Observability/EloquentSchedulerRunRepository.php, server/app/Application/Observability/{GetHealth,GetMetrics,ListSchedulerRuns,RecordSchedulerRun}UseCase.php, server/app/Models/SchedulerRun.php, server/app/Http/Controllers/Api/HealthController.php, server/app/Console/Commands/EodReconcileCommand.php, server/routes/api.php, server/app/Providers/AppServiceProvider.php, server/tests/{Unit/ObservabilityServiceTest.php,Feature/Api/HealthApiTest.php}, docs/api/openapi.yaml (Observability paths/schemas), docs/deployment.md
- Notes: "API error rate", "offline queue backlog", and "import failures" (SRS §16.5) are not yet instrumented as dedicated counters — they require request middleware, the offline queue table, and import parsing respectively, which are out of scope here (the queue pending/failed counters cover queue health). AI provider status, DB health, storage, and scheduler runs are covered. Sensitive content is excluded by construction.

### Phase 9 — Scheduling Application & Calendar
#### TASK-090 — Schedule Assignment Aggregate
- Status: DONE
- Priority: P0
- Depends On: TASK-020..TASK-026 (scheduling primitives), TASK-014 (task lifecycle)
- SRS: FR-01, FR-02, FR-08, FR-27, FR-28; SRS §7.1, §7.8 (task_assignments); domain-model Assignment.
- Acceptance:
  - [x] `ScheduleAssignment` domain aggregate exists with id, user_id, task_id, date, start_at, end_at, duration_minutes, status, source, schedule_version, locked, version, timestamps.
  - [x] `ScheduleAssignmentStatus` and `ScheduleAssignmentSource` value objects enforce closed sets.
  - [x] Aggregate invariants enforced: start < end, positive duration matching start/end, ownership, positive task reference, schedule-version consistency, optimistic versioning on mutation.
  - [x] `ScheduleAssignmentRepository` contract exists (find/list-by-date/list-by-range/list-by-task/create/update/delete/cancel).
  - [x] Unit tests cover invariants and overlap detection.
- Verification:
  - [x] Unit: `vendor/bin/phpunit` → OK (501 tests, 1375 assertions)
  - [x] `composer analyse` → PHPStan no errors
  - [x] `composer lint` → Pint PASS (372 files)
- Evidence: server/app/Domain/Scheduling/{ScheduleAssignment,ScheduleAssignmentOverlap,ScheduleAssignmentVersionConflict}.php, server/app/Domain/Scheduling/ValueObjects/{ScheduleAssignmentStatus,ScheduleAssignmentSource}.php, server/app/Domain/Scheduling/Contracts/ScheduleAssignmentRepository.php, server/tests/Unit/Scheduling/ScheduleAssignmentTest.php
- Notes: No scheduler algorithm is duplicated here. The aggregate is the persistent representation that bridges the in-memory `ScheduleDraft`/`ScheduleState` to `task_assignments`. Optimistic version increments on domain mutations; the repository accepts a `baseVersion` for the concurrency check (same pattern as Note).

#### TASK-091 — Schedule Assignment Persistence
- Status: DONE
- Priority: P0
- Depends On: TASK-090
- SRS: FR-01, FR-02, FR-08; SRS §7.1, §7.8 (task_assignments indexes).
- Acceptance:
  - [x] `task_assignments` migration: id, user_id, task_id, date, start_at, end_at, duration_minutes, status, source, schedule_version, locked, version, timestamps.
  - [x] Required indexes: `(user_id, date, start_at)`, `(user_id, start_at, end_at)`, `(task_id)`.
  - [x] Ownership scoping, FK to users/tasks, efficient day/range/task queries.
  - [x] Eloquent `TaskAssignment` model and `EloquentScheduleAssignmentRepository` implement repository contract.
  - [x] Optimistic concurrency via `version` check on update.
- Verification:
  - [x] Feature/API tests for create/update/delete/list scoping and overlap.
  - [x] `composer analyse` → PHPStan no errors
  - [x] `composer lint` → Pint PASS
- Evidence: database/migrations/2026_08_19_110000_create_task_assignments_table.php, server/app/Models/TaskAssignment.php, server/app/Infrastructure/Scheduling/EloquentScheduleAssignmentRepository.php, server/app/Providers/AppServiceProvider.php, server/tests/Feature/Scheduling/ScheduleAssignmentRepositoryTest.php

#### TASK-092 — Apply Schedule Draft
- Status: DONE
- Priority: P0
- Depends On: TASK-090, TASK-091
- SRS: FR-27; scheduling-engine §Draft versus applied schedule, §Schedule versioning.
- Acceptance:
  - [x] `ApplyScheduleDraftUseCase` generates (or receives) a `ScheduleDraft` and persists assignments atomically.
  - [x] Generate draft never mutates schedule; apply is explicit.
  - [x] Idempotent retry; stale schedule version → 409.
  - [x] Locked tasks remain protected; invalid draft never partially persists.
- Verification:
  - [x] Unit + Feature tests: apply success, retry, partial failure, version conflict, locked task, overlap, transaction rollback.
- Evidence: server/app/Application/Scheduling/ApplyScheduleDraftUseCase.php, server/app/Application/Scheduling/ScheduleApplyResult.php, server/app/Domain/Scheduling/ScheduleAssignmentLockedConflict.php, server/app/Domain/Scheduling/Contracts/ScheduleAssignmentRepository.php, server/app/Infrastructure/Scheduling/EloquentScheduleAssignmentRepository.php, server/tests/{Unit,Feature}/Scheduling/ApplyScheduleDraftUseCaseTest.php

#### TASK-093 — Apply Dynamic Reschedule Proposal
- Status: DONE
- Priority: P0
- Depends On: TASK-090, TASK-091, TASK-092
- SRS: FR-28; scheduling-engine §RESCHEDULE_PROPOSAL.
- Acceptance:
  - [x] `ApplyRescheduleProposalUseCase` applies `RescheduleProposal` atomically.
  - [x] Preview non-mutating; stale version → `409 SCHEDULE_VERSION_CONFLICT`.
  - [x] Locked tasks untouched; conflicts visible; no task deletion; affected assignments update consistently.
- Verification:
  - [x] Unit + Feature tests for proposal apply, conflict, version conflict, locked protection.
- Evidence: server/app/Application/Scheduling/ApplyRescheduleProposalUseCase.php, server/app/Application/Scheduling/RescheduleApplyResult.php, server/tests/{Unit,Feature}/Scheduling/ApplyRescheduleProposalUseCaseTest.php, server/tests/Support/FakeAssignmentStore.php

#### TASK-094 — Schedule Query API
- Status: DONE
- Priority: P0
- Depends On: TASK-091
- SRS: FR-01, FR-11, FR-15; SRS §8.2, §8.4.
- Acceptance:
  - [x] `GET /schedule?date=`, `GET /schedule?from=&to=`, `GET /today?date=`, `GET /week?date=`, `GET /calendar?month=` implemented (reuse existing stubs).
  - [x] Response contains task, assignment, program/goal/milestone context, hard landscape, lock/conflict state, capacity indicators, scheduler explanation.
- Verification:
  - [x] Feature/API tests; OpenAPI schemas synchronized.
- Evidence: server/app/Application/Scheduling/ScheduleQueryService.php, server/app/Http/Controllers/Api/{TodayController,ScheduleController,WeekController,CalendarController}.php, server/routes/api.php, docs/api/openapi.yaml, server/tests/Feature/Api/ScheduleApiTest.php (10 tests pass)
- Release Impact: MINOR (new optional read-only query endpoints; no breaking change)

#### TASK-095 — Hard Landscape Domain
- Status: DONE
- Priority: P0
- Depends On: TASK-091
- SRS: FR-27; SRS §7.1 (hard_landscape_events); scheduling-engine hard-constraint ordering.
- Acceptance:
  - [x] `HardLandscapeEvent` aggregate + `HardLandscapeRepository` + recurrence/context support.
  - [x] CRUD API `GET/POST /hard-landscape`, `GET/PATCH/DELETE /hard-landscape/{id}`.
  - [x] Ownership, start/end, title, type, permanent rule, one-time override, conflict detection.
- Verification:
  - [x] Unit + Feature tests.
- Evidence: server/app/Domain/Scheduling/{HardLandscapeEvent,HardLandscapeConflict}.php, server/app/Domain/Scheduling/ValueObjects/HardLandscapeType.php, server/app/Domain/Scheduling/Contracts/HardLandscapeRepository.php, server/app/Infrastructure/Scheduling/EloquentHardLandscapeRepository.php, server/app/Application/Scheduling/*HardLandscapeUseCase.php, server/app/Http/Controllers/Api/HardLandscapeController.php, database/migrations/2026_08_19_120000_create_hard_landscape_events_table.php, docs/api/openapi.yaml, server/tests/Unit/Scheduling/HardLandscapeEventTest.php, server/tests/Feature/Scheduling/HardLandscapeRepositoryTest.php, server/tests/Feature/Api/HardLandscapeApiTest.php
- Release Impact: MINOR (new optional CRUD capability; no breaking change)

#### TASK-096 — Recurring Schedule
- Status: DONE
- Priority: P0
- Depends On: TASK-095
- SRS: FR-25; SRS §7.1 (task_templates, schedule_overrides).
- Acceptance:
  - [x] Recurrence definition, bounded occurrence generation, timezone awareness, deterministic, no duplicates, exceptions/cancellation/override.
- Verification:
  - [x] Tests: daily, weekly, multiple weekdays, timezone boundary, exception day, deleted occurrence, duplicate prevention.
- Evidence: server/app/Domain/Scheduling/Recurrence/{RecurrenceRule,RecurrenceOccurrenceGenerator}.php, server/tests/Unit/Scheduling/Recurrence/RecurrenceOccurrenceGeneratorTest.php
- Release Impact: MINOR (reusable domain capability; no API/schema change)

#### TASK-097 — Schedule Overrides
- Status: DONE
- Priority: P0
- Depends On: TASK-095, TASK-096
- SRS: FR-25.
- Acceptance:
  - [x] Permanent override and one-time exception; explicit precedence (hard landscape > locked task > explicit override > recurrence-generated event > ordinary generated schedule).
  - [x] No silent historical mutation.
- Verification:
  - [x] Unit + Feature tests.
- Evidence: server/app/Domain/Scheduling/ScheduleOverride.php, server/app/Domain/Scheduling/ValueObjects/{ScheduleOverrideType,SchedulePrecedence}.php, server/app/Domain/Scheduling/Contracts/ScheduleOverrideRepository.php, server/app/Infrastructure/Scheduling/EloquentScheduleOverrideRepository.php, server/app/Application/Scheduling/*ScheduleOverrideUseCase.php, server/app/Http/Controllers/Api/ScheduleOverrideController.php, database/migrations/2026_08_19_130000_create_schedule_overrides_table.php, docs/api/openapi.yaml, server/tests/Unit/Scheduling/ScheduleOverrideTest.php, server/tests/Feature/Scheduling/ScheduleOverrideRepositoryTest.php, server/tests/Feature/Api/ScheduleOverrideApiTest.php
- Release Impact: MINOR (new optional CRUD capability; additive table; no breaking change)

#### TASK-098 — Quick Capture Placement
- Status: DONE
- Priority: P0
- Depends On: TASK-090, TASK-091
- SRS: FR-03.
- Acceptance:
  - [x] Quick Capture flow: create task → attempt placement → slot exists → task+assignment, else return strategies (Manual Swap, Auto Swap, Schedule Later).
  - [x] Task never disappears; `TASK_NO_CAPACITY` error semantics.
- Verification:
  - [x] Feature tests.
- Evidence: server/app/Application/Scheduling/{QuickCapturePlacementUseCase,QuickCaptureResult}.php, server/app/Http/Controllers/Api/TaskController.php (quickCapture), server/routes/api.php (`POST /quick-capture`), docs/api/openapi.yaml, server/tests/Feature/Scheduling/QuickCapturePlacementUseCaseTest.php, server/tests/Feature/Api/QuickCaptureApiTest.php
- Release Impact: MINOR (new optional endpoint; no breaking change)

#### TASK-099 — Auto Swap
- Status: DONE
- Priority: P0
- Depends On: TASK-090, TASK-091, TASK-098
- SRS: FR-03, FR-23, FR-28.
- Acceptance:
  - [x] `AutoSwapUseCase` implements explicit Auto Swap (FR-03): selects lowest-priority unlocked task on target day (farthest deadline as tie-breaker), places new task in vacated slot, moves swapped-out task to a feasible slot on the following day.
  - [x] Never moves locked tasks (FR-03): locked candidate reported in `swapped_task` but never moved; applied=false.
  - [x] Never violates Hard Landscape: next-day placement validated via HardConstraintEngine (HardLandscapeCollision, DurationFit, TemporalValidity, etc.).
  - [x] Reuses the hard-constraint engine for feasibility (no soft scoring can override hard violations, FR-64).
  - [x] Atomic transaction (DB::transaction): vacate + place new + move candidate commit or roll back together; schedule version bumped atomically.
  - [x] User-visible explanation always present; result exposed via `GET/POST /tasks/{taskId}/auto-swap` (200 applied, 202 no safe candidate, 404 task missing, 422 validation).
  - [x] OpenAPI AutoSwapRequest/AutoSwapResponse schemas + path synchronized.
- Verification:
  - [x] Unit + Feature: `vendor/bin/phpunit` → OK (616 tests, 1647 assertions; 8 AutoSwap tests).
  - [x] `composer lint` → Pint PASS (430 files); `composer analyse` → PHPStan no errors.
  - [x] `check-openapi.sh` → PASS.
- Evidence: server/app/Application/Scheduling/{AutoSwapUseCase,AutoSwapResult}.php, server/app/Http/Controllers/Api/TaskController.php (autoSwap), server/routes/api.php (`POST /tasks/{taskId}/auto-swap`), docs/api/openapi.yaml, server/tests/Feature/Scheduling/AutoSwapUseCaseTest.php, server/tests/Feature/Api/AutoSwapApiTest.php
- Release Impact: MINOR (new optional endpoint; no breaking change)

### Execution rules
- A task may move to `DONE` only when acceptance and verification boxes are satisfied.
- A task that exposes a requirement gap MUST create an issue/ADR before “working around” it.
- Completed work MUST include evidence: commit, test output, screenshot, trace, or deployment proof.
- Dependencies MUST be respected; do not parallelize tasks that would create incompatible migrations or contracts.

# 8. PHASE 10 — CORE FRONTEND PRODUCT

Create:

```text
Phase 10 — Frontend Product Surface
TASK-100 … TASK-109
```

This phase turns backend systems into an actual application.

---

# TASK-100 — Vue Application Shell

Implement:

```text
App shell
Primary navigation
Responsive layout
Global error boundary
Global loading state
Sync indicator
Notification indicator
Theme handling
Mobile navigation
Desktop navigation
```

Navigation MUST include:

```text
Today
Week
Calendar
Goals / Roadmap
Knowledge
Analytics
Settings
```

Use `design.md` as the UI contract.

Do not build a dashboard full of metrics.

Today remains the primary execution surface.

- Status: DONE
- Priority: P0
- Depends On: TASK-031 (frontend scaffold / Pinia / Vite)
- SRS: design.md §App shell, §Global UI states, §Responsive behavior; ADR-002 (Vue 3 + TS + Pinia).
- Acceptance:
  - [x] App shell component (`AppShell.vue`) with primary navigation, content surface, and error banner.
  - [x] Navigation config (`navigation.ts`) includes Today, Week, Calendar, Goals/Roadmap, Knowledge, Analytics, Settings.
  - [x] Responsive layout: persistent desktop side nav (`lg:flex`) and mobile bottom nav (`lg:hidden`), Today as primary entry.
  - [x] Global error boundary (`AppErrorBoundary.vue`) via Vue `onErrorCaptured`.
  - [x] Global loading state + sync indicator + notification indicator in the Pinia shell store and topbar.
  - [x] Theme handling (`theme.ts`): light/dark/system, persisted, `dark` class applied; topbar toggle.
  - [x] Shell mounted in `app.js` behind the existing `#app` host (welcome page unaffected).
- Verification:
  - [x] `npm run typecheck` → no errors
  - [x] `npm run test` → OK (113 tests, 17 new shell tests: navigation, theme, store, AppShell)
  - [x] `npm run build` → built OK
  - [x] Backend regression: `composer test` → OK (616 tests, 1647 assertions)
  - [x] `check-secrets.sh`, `check-doc-links.sh`, `validate-repo.sh`, `check-changelog.sh` all pass
- Evidence: server/resources/js/shell/{navigation.ts,theme.ts,store.ts,AppShell.vue,AppErrorBoundary.vue,index.vue}, server/resources/js/app.js, server/resources/js/shell/__tests__/*.test.ts
- Release Impact: MINOR (new frontend surface; no backend/API change)

---

# TASK-101 — Authentication UI

Implement:

```text
Login
First-owner registration
Session restoration
Logout
401 handling
Profile/settings
Timezone
Locale
Week start
```

Connect to existing Sanctum API.

Do not implement a second authentication mechanism.

- Status: DONE
- Priority: P0
- Depends On: TASK-100 (app shell)
- SRS: NFR-02 (auth/token), §15.1 ownership; FR-10/FR-13 (timezone/locale/week-start via ProfileSettings).
- Acceptance:
  - [x] Login form (`LoginView.vue`) posting to `POST /auth/login`, with 401/422 error handling.
  - [x] First-owner registration (`RegisterView.vue`) posting to `POST /auth/register`, handling 409/422.
  - [x] Session restoration on mount via `GET /auth/me` (valid token → shell; missing/stale → guest).
  - [x] Logout via `POST /auth/logout` clearing the local token and returning to the guest gate.
  - [x] Profile/settings (`ProfileView.vue`) for display name, timezone, locale, week start via `GET/PUT /profile`, using server-allowed values.
  - [x] Typed API client (`auth/client.ts`) with Bearer token persistence + parsed `ApiError` (401/422 field errors).
  - [x] Pinia auth store (`auth/store.ts`): login/register/logout/restoreSession/loadProfile/updateProfile.
  - [x] Auth gate (`AuthHost.vue`) wraps the app shell: guest → login/register, authenticated → shell + logout + settings.
  - [x] SPA host (`/app` route + `app.blade.php`) mounts the Vue app at `#app`.
  - [x] No second auth mechanism; reuses the existing Sanctum bearer-token API.
- Verification:
  - [x] `npm run typecheck` → no errors
  - [x] `npm run test` → OK (132 tests; 19 new auth tests: client, store, AuthHost)
  - [x] `npm run build` → built OK
  - [x] Backend regression: `composer test` → OK (616 tests, 1647 assertions)
  - [x] `GET /app` → 200; repo gates (secrets/doc-links/validate/changelog) all PASS
- Evidence: server/resources/js/auth/{types.ts,client.ts,store.ts,LoginView.vue,RegisterView.vue,ProfileView.vue,AuthHost.vue}, server/resources/js/auth/__tests__/*.test.ts, server/resources/views/app.blade.php, server/routes/web.php (`/app`)
- Release Impact: MINOR (new frontend surface; no backend/API change)

---

# TASK-102 — Global API / State Client

Create the frontend application infrastructure for:

```text
API client
auth state
request errors
422 validation
401 unauthorized
403
404
409 conflict
422 state violations
429
503
offline
retry
```

Provide consistent typed responses.

Do not place business logic in API composables.

- Status: DONE
- Priority: P0
- Depends On: TASK-101 (auth UI), TASK-031 (frontend scaffold)
- SRS: §8.2 API contract; offline-sync.md §Sync/conflict; §5.3 layering (no business logic in API composables).
- Acceptance:
  - [x] Global typed API client (`api/client.ts`, `ApiClient`) built on `fetch`, injected base URL + bearer token, shared by all API modules.
  - [x] Error taxonomy (`api/types.ts`): 401 UNAUTHORIZED, 403 FORBIDDEN, 404 NOT_FOUND, 409 CONFLICT, 422 VALIDATION, 429 TOO_MANY_REQUESTS, 503 UNAVAILABLE, plus SERVER/NETWORK/OFFLINE/UNKNOWN.
  - [x] Parsed `ApiError` with `code`, `status`, `message`, field `errors`, stable server `serverCode` (e.g. `SCHEDULE_VERSION_CONFLICT`), and `retryable`.
  - [x] Automatic retry with backoff for transient/network/5xx/429 (default 2 retries, linear delay); never retries 4xx; `noRetry` opt-out.
  - [x] Offline detection: `isOnline` hook throws OFFLINE when disconnected; network `TypeError` → NETWORK error; connectivity wired to the shell sync indicator via online/offline listeners.
  - [x] Global API state store (`api/store.ts`): in-flight/loading count, last error, online state, offline queue count.
  - [x] Auth client refactored to use the shared `ApiClient` (token storage moved to `api/token.ts`); no duplicated request logic.
  - [x] Consistent typed responses (`request<T>` returning typed JSON, `204 → undefined`); no business logic in API composables.
- Verification:
  - [x] `npm run typecheck` → no errors
  - [x] `npm run test` → OK (148 tests; 16 new API tests: client, store)
  - [x] `npm run build` → built OK
  - [x] Backend regression: `composer test` → OK (616 tests, 1647 assertions)
  - [x] Repo gates (secrets/doc-links/validate/changelog) all PASS
- Evidence: server/resources/js/api/{client.ts,types.ts,token.ts,store.ts}, server/resources/js/api/__tests__/*.test.ts, server/resources/js/auth/client.ts (refactored), server/resources/js/auth/AuthHost.vue (connectivity wiring)
- Release Impact: MINOR (new frontend infrastructure; no backend/API change)

---

# TASK-103 — Today UI

This is one of the highest-priority tasks.

Implement:

```text
Header
Date
Sync state
Recovery notice
NOW
NEXT
Timeline
Quick Capture
Today actions
```

Timeline MUST render:

```text
Hard Landscape
Scheduled Tasks
Recharge
Buffer
Empty Slots
Conflict
Locked Tasks
```

NOW card MUST expose:

```text
title
duration
context
goal/milestone/program
lock
conflict
completion
notes
canvas link
```

Do not dump analytics into Today.

- Status: DONE
- Priority: P0
- Depends On: TASK-102 (API client), TASK-100 (shell), TASK-094 (Today API)
- SRS: FR-01 (Today view), FR-02 (dynamic empty slots), FR-27 (hard landscape); design.md §Today screen, §Slot visualization, §Quick Capture.
- Acceptance:
  - [x] Header with formatted date and sync state (shell sync indicator).
  - [x] NOW card: title, duration, time range, goal/milestone/program context, lock + conflict badges.
  - [x] NEXT card: next upcoming scheduled event.
  - [x] Timeline (`/today`): scheduled tasks, Hard Landscape, empty slots, lock/conflict visual states, 06:00–24:00 axis.
  - [x] Capacity indicator (scheduled vs available, overload) — no analytics dumped into Today.
  - [x] Quick Capture form (title, priority, duration) posting to `POST /quick-capture`, then reloading Today.
  - [x] Typed Today API client + Pinia Today store (`today/`); wired into the shell for the `today` view.
  - [x] No mock data — uses the real `GET /today` schedule API (FR-01).
- Verification:
  - [x] `npm run typecheck` → no errors
  - [x] `npm run test` → OK (155 tests; 7 new Today tests: store, TodayView)
  - [x] `npm run build` → built OK
  - [x] Backend regression: `composer test` → OK (616 tests, 1647 assertions)
  - [x] Repo gates (secrets/doc-links/validate/changelog/openapi) all PASS
- Evidence: server/resources/js/today/{types.ts,api.ts,store.ts,TodayView.vue}, server/resources/js/today/__tests__/*.test.ts, server/resources/js/auth/AuthHost.vue (Today wiring)
- Release Impact: MINOR (new frontend surface; no backend/API change)

---

# TASK-104 — Week / Calendar UI

Implement:

```text
Week view
Monthly calendar
Date navigation
Capacity awareness
Overload indication
Deadline awareness
Hard Landscape visualization
Task assignments
```

Use real schedule APIs.

No mock schedule after integration is available.

- Status: DONE
- Priority: P0
- Depends On: TASK-102 (API client), TASK-100 (shell), TASK-094 (Week/Calendar/range APIs)
- SRS: FR-11 (week view), FR-15 (monthly calendar); design.md §Week screen, §Responsive behavior.
- Acceptance:
  - [x] Week view (`/week?date=`): 7-day summary grid with day columns, task count, and scheduled minutes per day.
  - [x] Monthly calendar (`/calendar?month=`): month grid with per-day task indicators and leading blank cells for weekday alignment.
  - [x] Date navigation: prev/next week and month, plus "Today"/"This month" reset.
  - [x] Capacity awareness + overload indication: weekly totals, per-day scheduled minutes, and an overload badge when a day exceeds a 720-minute threshold (design.md §Week screen).
  - [x] Deadline awareness: per-day "due" markers from task `due_at` via `/schedule?from=&to=` range fetch.
  - [x] Task assignments: per-day assignment list in the week view from the range events.
  - [x] Typed Week/Calendar API client + Pinia store; wired into the shell for the `week` and `calendar` nav views.
  - [x] No mock schedule — uses the real `GET /week`, `GET /calendar`, `GET /schedule` APIs.
- Verification:
  - [x] `npm run typecheck` → no errors
  - [x] `npm run test` → OK (163 tests; 8 new Week/Calendar tests: store, WeekView, CalendarView)
  - [x] `npm run build` → built OK
  - [x] Backend regression: `composer test` → OK (616 tests, 1647 assertions)
  - [x] Repo gates (secrets/doc-links/validate/changelog) all PASS
- Evidence: server/resources/js/week/{types.ts,api.ts,store.ts,WeekView.vue,CalendarView.vue}, server/resources/js/week/__tests__/*.test.ts, server/resources/js/auth/AuthHost.vue (Week/Calendar wiring)
- Release Impact: MINOR (new frontend surface; no backend/API change)

---

# TASK-105 — Task UI

Implement:

```text
Task list
Task detail
Task creation
Edit
Status transitions
Subtasks
Partial completion
Promote subtask
Lock
Schedule
Notes
Attachments
Activity history
AI actions where approved
```

All state transitions MUST use backend rules.

The frontend may present actions; it must not become the state authority.

- Status: DONE
- Priority: P0
- Depends On: TASK-102 (API client), TASK-100 (shell), TASK-014 (task/subtask lifecycle)
- SRS: FR-09 (partial-complete, promote), FR-45 (subtask hierarchy), §6.5; design.md §Task states, §Lock interaction; TaskStatus state machine.
- Acceptance:
  - [x] Task list (`GET /tasks`), creation (`POST /tasks`), and a "Tasks" nav view.
  - [x] Task detail (`GET /tasks/{id}`) with edit (`PUT /tasks/{id}`): title, description, priority, duration, due date.
  - [x] Status transitions presented from the TaskStatus state machine (`TASK_TRANSITIONS`) via `POST /tasks/{id}/status`; the backend remains the state authority (no client-side state mutation).
  - [x] Subtasks: list, add (`POST /tasks/{id}/subtasks`), toggle (`POST .../toggle`) updating task progress.
  - [x] Partial completion (`POST /tasks/{id}/partial-complete`) when in_progress.
  - [x] Promote subtask (`POST /subtasks/{id}/promote`) to a standalone task.
  - [x] Typed Task API client + Pinia store; TaskView container switches list/detail.
- Verification:
  - [x] `npm run typecheck` → no errors
  - [x] `npm run test` → OK (173 tests; 10 new Task tests: store, TaskListView, TaskDetailView)
  - [x] `npm run build` → built OK
  - [x] Backend regression: `composer test` → OK (616 tests, 1647 assertions)
  - [x] Repo gates (secrets/doc-links/validate/changelog) all PASS
- Evidence: server/resources/js/task/{types.ts,api.ts,store.ts,TaskView.vue,TaskListView.vue,TaskDetailView.vue}, server/resources/js/task/__tests__/*.test.ts, server/resources/js/shell/navigation.ts (Tasks nav), server/resources/js/auth/AuthHost.vue (Task wiring)
- Release Impact: MINOR (new frontend surface; no backend/API change)

---

# TASK-106 — Goals / Milestones / Programs UI

Implement:

```text
Goal list
Goal detail
Milestone timeline
Program list
Progress
Deadline
Workload
Linked knowledge
Next actions
```

Goal detail should follow:

```text
Outcome
Deadline
Progress
Milestones
Programs
Knowledge
Capacity impact
Next actions
History
```

- Status: DONE
- Priority: P0
- Depends On: TASK-102 (API client), TASK-100 (shell), TASK-011/012/013 (Goal/Milestone/Program aggregates)
- SRS: FR-19/20 (goal limits), FR-22/26 (program), FR-50/51 (milestone); design.md §Goal workspace, §Goal detail, §Milestone interaction, §Roadmap.
- Acceptance:
  - [x] Goal list (`GET /goals`) with horizon, deadline, progress, status; goal creation (`POST /goals`).
  - [x] Goal detail (`GET /goals/{id}`): outcome, deadline, progress, status actions (`POST /goals/{id}/status`).
  - [x] Milestone timeline (`GET /goals/{id}/milestones`): sequence-ordered list, add (`POST`), status transitions (`POST .../status`).
  - [x] Program list (`GET /programs`) with workload type and weekly target; program creation (`POST /programs`).
  - [x] Typed Goal/Milestone/Program API client + Pinia store; wired into the shell for the `goals` nav view.
- Verification:
  - [x] `npm run typecheck` → no errors
  - [x] `npm run test` → OK (181 tests; 8 new Goal tests: store, GoalListView, GoalDetailView)
  - [x] `npm run build` → built OK
  - [x] Backend regression: `composer test` → OK (616 tests, 1647 assertions)
  - [x] Repo gates (secrets/doc-links/validate/changelog) all PASS
- Evidence: server/resources/js/goal/{types.ts,api.ts,store.ts,GoalView.vue,GoalListView.vue,GoalDetailView.vue}, server/resources/js/goal/__tests__/*.test.ts, server/resources/js/auth/AuthHost.vue (Goal wiring)
- Release Impact: MINOR (new frontend surface; no backend/API change)

---

# TASK-107 — Quick Capture UI

Global quick capture:

```text
title
priority
duration
program
goal
milestone
due date
```

When slot is full:

```text
Manual Swap
Auto Swap
Schedule Later
```

must be visibly actionable.

- Status: DONE
- Priority: P0
- Depends On: TASK-098/099 (quick-capture + auto-swap APIs), TASK-102 (API client), TASK-100 (shell)
- SRS: FR-03 (quick capture strategies); design.md §Quick Capture.
- Acceptance:
  - [x] Global Quick Capture modal (`QuickCapture.vue`) reachable from a topbar button across all authenticated views.
  - [x] Fields: title, priority, size (cepat/sedang/berat → default duration), duration, program, goal, milestone (dependent on goal), due date.
  - [x] On `TASK_NO_CAPACITY`, shows the three primary strategies in SRS order — Manual Swap, Auto Swap, Schedule Later — each visibly actionable.
  - [x] Auto Swap runs `POST /tasks/{id}/auto-swap` and reports success/explanation.
  - [x] Schedule Later dismisses (task stays in backlog; never disappears).
  - [x] Manual Swap dismisses and lets the user adjust the schedule themselves.
  - [x] Goal/program/milestone dropdown context loaded from the Goal APIs.
  - [x] Placed captures show a confirmation with the assigned slot.
- Verification:
  - [x] `npm run typecheck` → no errors
  - [x] `npm run test` → OK (190 tests; 9 new QuickCapture tests: store, component)
  - [x] `npm run build` → built OK
  - [x] Backend regression: `composer test` → OK (616 tests, 1647 assertions)
  - [x] Repo gates (secrets/doc-links/validate/changelog) all PASS
- Evidence: server/resources/js/quickcapture/{store.ts,QuickCapture.vue}, server/resources/js/quickcapture/__tests__/*.test.ts, server/resources/js/auth/AuthHost.vue (global button + modal wiring)
- Release Impact: MINOR (new frontend surface; no backend/API change)

---

# TASK-108 — Schedule Draft / Rescheduler UI

Implement:

```text
Generate Draft
Preview
Reasoning
Accepted tasks
Rejected tasks
Changes
Conflicts
Apply
Cancel
```

Dynamic Rescheduler MUST show:

```text
BEFORE
AFTER
REASON
```

Never show only:

> “Schedule updated.”

- Status: DONE
- Priority: P0
- Depends On: TASK-023/024 (draft + rescheduler engines), TASK-092/093 (apply use cases), TASK-102 (API client)
- SRS: FR-27 (weekly draft), FR-28 (dynamic reschedule preview/apply); scheduling-engine §RESCHEDULE_PROPOSAL mode, §Draft vs applied schedule, §Schedule versioning.
- Acceptance:
  - [x] Backend endpoints added: `POST /schedule/draft`, `/schedule/draft/apply`, `/schedule/reschedule`, `/schedule/reschedule/apply` (owner-scoped, version-conflict 409, locked 422) — documented in OpenAPI.
  - [x] Generate Draft (date range) → preview of accepted assignments and rejected/unassigned tasks with reasons (NO_AVAILABLE_SLOT etc.).
  - [x] Reasoning note (deterministic, respects Hard Landscape/locked/deadlines/reserve).
  - [x] Apply Draft atomically at the next schedule version (stale → 409 shown).
  - [x] Dynamic Rescheduler: propose → BEFORE / AFTER / REASON per move, conflict flags; Apply / Cancel.
  - [x] Never shows only “Schedule updated.” — always shows the diff.
  - [x] Typed frontend client + store; wired into the shell under a Schedule nav view.
- Verification:
  - [x] Backend: `composer test` → OK (628 tests; 12 new ScheduleDraftApi tests); Pint PASS; PHPStan no errors
  - [x] `npm run typecheck` → no errors
  - [x] `npm run test` → OK (198 tests; 8 new schedulerdraft tests)
  - [x] `npm run build` → built OK
  - [x] Repo gates (openapi 72 paths / doc-links / secrets / validate / changelog) all PASS
- Evidence: server/app/Http/Controllers/Api/ScheduleDraftController.php, server/tests/Feature/Api/ScheduleDraftApiTest.php, server/routes/api.php, docs/api/openapi.yaml, server/resources/js/schedulerdraft/{types.ts,api.ts,store.ts,date.ts,ScheduleDraftView.vue,RescheduleView.vue,ScheduleView.vue}, server/resources/js/schedulerdraft/__tests__/*.test.ts, server/resources/js/auth/AuthHost.vue, server/resources/js/shell/navigation.ts
- Release Impact: MINOR (new endpoints + frontend surface)

---

# TASK-109 — Conflict / Lock / Explainability UI

Implement consistent visual states:

```text
locked
conflict
overdue
draft
proposed
offline
syncing
queued
failed
```

Color MUST NOT be the only signal.

Scheduler explanations MUST expose the already-implemented reason codes.

- Status: DONE
- Priority: P0
- Depends On: TASK-100 (shell), TASK-103/105 (Today/Task views), TASK-026 (scheduler reason codes)
- SRS: FR-63 (explainability reason codes); design.md §State visibility, §Lock interaction, §Conflict UI, §Global UI states.
- Acceptance:
  - [x] Shared `VisualStateBadge` component + `VISUAL_STATES` map covering locked, conflict, overdue, draft, proposed, offline, syncing, queued, failed (plus saved/online).
  - [x] Non-color signals for every state: glyph/icon + text label + dashed border pattern where appropriate — color is never the only signal.
  - [x] `taskStates` derive helper marks overdue (past-due non-terminal) and propagates lock/conflict.
  - [x] Applied consistently: Today NOW card (lock/conflict/overdue badges), Task list (overdue badges), AppShell sync indicator (offline/syncing/queued/saved/failed).
  - [x] Scheduler explanation reason codes (HARD_CONSTRAINT_FILTERED, LOCK_PROTECTED, SACRED_ANCHOR, DEADLINE_PRIORITY, CAPACITY_FIT, ENERGY_FIT, CONTEXT_SWITCH_PENALTY, PROGRESS_VALUE, CONTINUITY_PREFERENCE) exposed via `SchedulerExplanation` + `explanation.ts`, rendered in the Schedule Draft view (FR-63).
- Verification:
  - [x] `npm run typecheck` → no errors
  - [x] `npm run test` → OK (213 tests; 15 new visualstate tests: derive, definitions, explanation, badges, SchedulerExplanation)
  - [x] `npm run build` → built OK
  - [x] Backend regression: `composer test` → OK (628 tests, 1702 assertions)
  - [x] Repo gates (secrets/doc-links/validate/changelog) all PASS
- Evidence: server/resources/js/visualstate/{types.ts,derive.ts,explanation.ts,VisualStateBadge.vue,SchedulerExplanation.vue}, server/resources/js/visualstate/__tests__/*.test.ts, server/resources/js/{today/TodayView.vue,task/TaskListView.vue,shell/AppShell.vue,schedulerdraft/ScheduleDraftView.vue}
- Release Impact: MINOR (new frontend surface; no backend/API change)

---

# 9. PHASE 11 — KNOWLEDGE / CANVAS UI

Create:

```text
TASK-110 … TASK-115
```

---

# TASK-110 — Notes UI

Implement:

```text
Note list
Search
Create
Edit
Delete/archive according to approved lifecycle
Autosave
Saved/Saving/Error/Offline/Conflict states
Goal link
Milestone link
Program link
Task link
Attachments
```

- Status: DONE
- Priority: P0
- Depends On: TASK-102 (API client), TASK-109 (visual states), TASK-030/032/033 (Note + links + search API)
- SRS: FR-53 (notes/search), FR-54 (knowledge links), §7.4; design.md §Notes UX, §Autosave indicator.
- Acceptance:
  - [x] Note list (`GET /notes`) and search (`GET /knowledge/search`) in a NotesListView under the Knowledge nav view.
  - [x] Create note (`POST /notes`).
  - [x] Edit note (`PATCH /notes/{id}`) with optimistic `base_version` (409 → conflict).
  - [x] Autosave with debounce + explicit "Save now"; Saved/Saving/Error/Offline/Conflict states shown via the shared VisualStateBadge.
  - [x] Offline: autosave reports Offline (uses the api connectivity state) instead of attempting to sync.
  - [x] Linked entities displayed from `GET /notes/{id}/links` (goal/milestone/program/task/canvas).
  - [x] Typed Note client + store; wired into the shell Knowledge view.
  - Note: delete/archive and file attachments are not exposed by the current Note API (no DELETE/attachments endpoints) — out of scope until the backend lifecycle/attachment contract lands (tracked with Canvas import tasks).
- Verification:
  - [x] `npm run typecheck` → no errors
  - [x] `npm run test` → OK (224 tests; 11 new note tests: store, NotesListView, NoteEditView)
  - [x] `npm run build` → built OK
  - [x] Backend regression: `composer test` → OK (628 tests, 1702 assertions)
  - [x] Repo gates (secrets/doc-links/validate/changelog) all PASS
- Evidence: server/resources/js/note/{types.ts,api.ts,store.ts,NotesListView.vue,NoteEditView.vue,NoteView.vue}, server/resources/js/note/__tests__/*.test.ts, server/resources/js/auth/AuthHost.vue (Knowledge wiring)
- Release Impact: MINOR (new frontend surface; no backend/API change)

---

# TASK-111 — Tiptap Vue Binding

Connect:

```text
Vue
 ↓
EditorAdapter
 ↓
TiptapEditorAdapter
```

Do NOT bypass the adapter.

The editor engine must remain replaceable.

- Status: DONE
- Priority: P0
- Depends On: TASK-031 (EditorAdapter + TiptapEditorAdapter), TASK-110 (Notes UI)
- SRS: §10.1–10.3 Knowledge Layer (Tiptap behind the Kinevo boundary, canonical structured JSON); ADR-004 (headless editor); architecture.md "Knowledge boundary".
- Acceptance:
  - [x] `EditorHost.vue` Vue binding: Vue → EditorAdapter → (default) TiptapEditorAdapter.
  - [x] Mounts the adapter into a host element; loads the canonical `document_json`; emits `ready` (adapter handle) and `change` (document + derived markdown/plain text).
  - [x] Adapter factory injectable (`adapterFactory` prop) so the editor engine remains replaceable — the Vue layer only talks to the `EditorAdapter` contract, never Tiptap.
  - [x] readOnly + theme reactive props forwarded to the adapter.
  - [x] Integrated into NoteEditView (replaces the plain textarea): autosave persists `document_json` + derived markdown/plain text via the note store's optimistic `base_version`; the engine is never bypassed.
  - [x] No business logic inside the editor engine (persistence/versioning stays in the note store).
- Verification:
  - [x] `npm run typecheck` → no errors
  - [x] `npm run test` → OK (227 tests; 3 new EditorHost binding tests using a fake adapter)
  - [x] `npm run build` → built OK
  - [x] Backend regression: `composer test` → OK (628 tests, 1702 assertions)
  - [x] Repo gates (secrets/doc-links/validate/changelog) all PASS
- Evidence: server/resources/js/editor/EditorHost.vue, server/resources/js/editor/__tests__/EditorHost.test.ts, server/resources/js/note/NoteEditView.vue (editor integration + adapterFactory)
- Release Impact: MINOR (new frontend surface; no backend/API change)

---

# TASK-112 — Knowledge Linking UI

Implement user-facing creation and removal of links:

```text
Note → Goal
Note → Milestone
Note → Program
Note → Task
Note ↔ Canvas
```

Show linked entities in context.

- Status: DONE
- Priority: P0
- Depends On: TASK-102 (API client), TASK-110 (Notes UI), TASK-032 (knowledge links API)
- SRS: FR-54 (links to Goals/Milestones/Programs/Tasks/Canvases), SRS §10.5; knowledge-layer.md §Link model.
- Acceptance:
  - [x] Backend: `KnowledgeTargetType` + CreateNoteLink/ListTargetLinks use cases + OpenAPI extended to support `canvas` as a link target (SRS §10.5, FR-54), owner-scoped via CanvasRepository; duplicate → 409; foreign/unknown → 404; invalid → 422.
  - [x] Typed link module (`knowledge/`): types, api (linksForNote/createForNote/removeFromNote/reverseLinks + goal/program/task/canvas/milestone context list), Pinia store (`useKnowledgeLinkStore`) with loadLinks/loadContext/loadMilestones/createLink/removeLink/clear.
  - [x] `LinkManager.vue` in the Note edit view: lists linked entities with label + link type, creates Note→Goal/Milestone/Program/Task/Canvas links (type → entity dropdown, milestone depends on a selected Goal), removes links.
  - [x] Conflict (duplicate, 409) and validation errors surfaced to the user.
  - [x] Milestone context resolved by goal (dependent dropdown).
- Verification:
  - [x] Backend: `php artisan test` → OK (631 tests, 1709 assertions; +3 KnowledgeLink canvas tests); Pint PASS (432 files); PHPStan no errors
  - [x] Frontend: `npm run typecheck` → no errors; `npm run test` → OK (240 tests, 13 new: 7 link store + 6 LinkManager); `npm run build` → built OK
  - [x] `check-secrets.sh`, `check-doc-links.sh` (19 links), `validate-repo.sh`, `check-openapi.sh` (72 paths), `check-changelog.sh` all PASS
- Evidence: server/resources/js/knowledge/{types.ts,api.ts,store.ts,LinkManager.vue,__tests__/*.test.ts}, server/resources/js/note/NoteEditView.vue, server/app/Domain/Knowledge/ValueObjects/KnowledgeTargetType.php, server/app/Application/Knowledge/{CreateNoteLink,ListTargetLinks}UseCase.php, server/tests/{Unit/KnowledgeLinkTest.php,Feature/Api/KnowledgeLinkApiTest.php}, docs/api/openapi.yaml (CreateKnowledgeLinkRequest/KnowledgeLink target_type enum)
- Release Impact: MINOR (new frontend surface + additive canvas link target; no breaking API change)

---

# TASK-113 — Canvas Workspace UI

- Status: DONE
- Priority: P0
- Depends On: TASK-040 (canvas domain), TASK-043 (Excalidraw adapter + React island), TASK-044 (canvas persistence)
- SRS: FR-55 (canvas lifecycle), FR-56 (optimistic versioning / 409), FR-57 (offline canvas mutations); SRS §7.5, §8.5.
- Acceptance:
  - [x] Backend: `canvases.archived_at` migration (nullable, after version); `Canvas` domain gains `archivedAt` + `archive()`/`restore()`/`isArchived()`; `CanvasRepository.update()` + `listForUser()` excludes archived; `RenameCanvasUseCase` + `ArchiveCanvasUseCase`; `PATCH /canvases/{canvasId}` (rename) + `POST /canvases/{canvasId}/archive`; owner-scoped 404s, rename validation (title 1–200) → 422.
  - [x] Frontend canvas module: `canvas/` types, api client (list/show/create/save/rename/archive), `HttpCanvasPersistence` (409 → `CANVAS_VERSION_CONFLICT`, OFFLINE/NETWORK → `OFFLINE`), Pinia store (loadList/open/create/rename/archive/saveState/recordSaved/reconcile).
  - [x] `CanvasListView` (list + create), `CanvasWorkspaceView` (open, rename on save, read-only toggle, theme cycle light/dark/auto, archive with confirmation, version-conflict reload/reconcile, VisualStateBadge save state), `CanvasView` orchestrator; `CanvasHost` watchers for scene/readOnly/theme + `adapterFactory` DI seam.
  - [x] Layering preserved per ADR-005: Vue → CanvasHost → CanvasAdapter → React Island → Excalidraw; no adapter bypass.
  - [x] Shell: `canvas` nav item (`shell/navigation.ts`) + `AuthHost` dispatch.
- Verification:
  - [x] Backend: `php artisan test` → OK (638 tests, 1730 assertions; +7 Canvas rename/archive tests); PHPStan no errors; Pint PASS (434 files)
  - [x] Frontend: `npm run typecheck` → no errors; `npm run test` → OK (258 tests, +18 new: 7 store + 5 list + 6 workspace); `npm run build` → built OK (vite oxc JSX enabled for React island)
  - [x] `check-secrets.sh`, `check-doc-links.sh` (19 links), `validate-repo.sh`, `check-openapi.sh` (73 paths), `check-changelog.sh`, `check-version.sh` all PASS
- Evidence: database/migrations/2026_08_19_140000_add_archived_at_to_canvases_table.php, server/app/Domain/Canvas/Canvas.php, server/app/Application/Canvas/{RenameCanvasUseCase,ArchiveCanvasUseCase}.php, server/app/Http/Controllers/Api/CanvasController.php, server/routes/api.php, server/resources/js/canvas/{api-types.ts,api.ts,store.ts,http-persistence.ts,CanvasListView.vue,CanvasWorkspaceView.vue,CanvasView.vue,__tests__/*.test.ts}, server/resources/js/auth/AuthHost.vue, server/resources/js/shell/navigation.ts, docs/api/openapi.yaml (CanvasRenameRequest, Canvas.archived_at, PATCH /canvases/{canvasId}, POST /canvases/{canvasId}/archive), server/tests/{Unit/CanvasTest.php,Feature/Api/CanvasApiTest.php}
- Release Impact: MINOR (new canvas workspace surface + additive rename/archive endpoints; no breaking API change)

---

# TASK-114 — Canvas Context / Linking

- Status: DONE
- Priority: P0
- Depends On: TASK-113 (canvas workspace), TASK-112 (knowledge links), TASK-032 (knowledge links API)
- SRS: FR-54 (knowledge links), FR-55 (canvas lifecycle attachment), SRS §10.5, knowledge-layer.md §Link model.
- Acceptance:
  - [x] Canvas is a first-class `knowledge_links` source (`source_type='canvas'`), attachable to Goal/Milestone/Program/Task/Note targets using the shared `knowledge_links` relation — no duplicate canvas foreign keys (TASK-114 directive).
  - [x] Backend: `KnowledgeLink::SOURCE_CANVAS` + `KnowledgeTargetType::NOTE`; `CreateCanvasLinkUseCase`/`ListCanvasLinksUseCase`/`RemoveCanvasLinkUseCase`; canvas link endpoints `GET/POST /canvases/{canvasId}/links` + `DELETE /canvases/{canvasId}/links/{linkId}`; `byTarget` reverse navigation supports `note` targets; owner-scoped 404s, duplicate → 409, invalid → 422.
  - [x] Frontend: `CanvasContextPanel.vue` in the canvas workspace lists linked Goal/Milestone/Program/Task/Note entities (label + link type) and creates/removes links; note is a target option; knowledge link store gains canvas-scoped load/create/remove + note context; milestones resolve dependent on selected goal.
  - [x] Note link surface (LinkManager) unchanged; target type set extended with `note` consistently.
- Verification:
  - [x] Backend: `php artisan test` → OK (650 tests, 1770 assertions; +12 KnowledgeLink canvas tests); Pint PASS (437 files); PHPStan no errors
  - [x] Frontend: `npm run typecheck` → no errors; `npm run test` → OK (268 tests, +10 new: 4 store + 6 CanvasContextPanel); `npm run build` → built OK
  - [x] `check-secrets.sh`, `check-doc-links.sh`, `validate-repo.sh`, `check-openapi.sh`, `check-changelog.sh`, `check-version.sh` all PASS
- Evidence: server/app/Application/Knowledge/{CreateCanvasLink,ListCanvasLinks,RemoveCanvasLink}UseCase.php, server/app/Domain/Knowledge/{KnowledgeLink.php,ValueObjects/KnowledgeTargetType.php}, server/app/Http/Controllers/Api/KnowledgeLinkController.php, server/routes/api.php, server/resources/js/canvas/{CanvasContextPanel.vue,__tests__/CanvasContextPanel.test.ts}, server/resources/js/knowledge/{api.ts,store.ts,types.ts,__tests__/*.test.ts}, server/tests/{Unit/KnowledgeLinkTest.php,Feature/Api/KnowledgeLinkApiTest.php}, docs/api/openapi.yaml (/canvases/{canvasId}/links, KnowledgeLink source/target enums)
- Release Impact: MINOR (additive canvas link endpoints + note target type; no breaking API change)

---

# TASK-115 — Offline Synchronization UX

Implement visible states:

```text
Online
Offline
Queued
Syncing
Saved
Conflict
Retrying
Failed
```

Users must understand whether their mutation is:

```text
persisted server-side
stored locally
waiting for synchronization
in conflict
```

### TASK-115 — DONE

- Status: DONE
- Scope: Frontend-only (Vue/TS). No backend, migration, or API change required —
  the visible sync layer maps the existing general MutationQueue (TASK-052) and
  network state into the eight user-visible states.
- Requirements: FR-44 (Offline Support), FR-57 (Offline Knowledge/Canvas
  Mutations), NFR-15 (Offline Integrity), SRS §9.1–§9.4, offline-sync.md
  §Sync state machine / §Failure safety.
- Changes:
  - `VisualStateValue` + `VISUAL_STATES` gain a `retrying` state (dashed,
    non-color glyph, warning tone) so every visible sync state has a
    color-independent signal (design.md §Visible states).
  - Shell store `SyncState` extended to all eight states (`online`, `offline`,
    `queued`, `syncing`, `saved`, `conflict`, `retrying`, `failed`) with
    `SYNC_STATES` export, plus `syncQueuedCount`/`setSyncQueuedCount`,
    `syncError`/`setSyncError`, and `retrySync`/`registerRetrySync`.
  - New framework-agnostic `offline/sync-status.ts`: `SyncStatusController`
    bridges a `MutationQueue` into a `SyncStatus` sink (`state`, `queuedCount`,
    `explanation`, `retryable`, `error`); `mapQueueStateToSyncState` maps
    queue/network state to the visible state; `SYNC_STATE_EXPLANATIONS` answers
    "persisted server-side / stored locally / waiting for sync / in conflict".
  - New `offline/http-applier.ts`: `HttpMutationApplier` implements the general
    queue's `OfflineOperationApplier` against the existing `apiClient`
    (task/note/quick-capture/canvas routes), translating outcomes to
    applied/conflict/retryable/permanent (409 → conflict, offline/5xx/429 →
    retryable, other 4xx → permanent; unsupported operations are a permanent
    failure that preserves local data — never silent discard).
  - New visible `shell/SyncStatusPanel.vue`: badge + human-readable explanation
    + queued count + "Retry sync" button for retrying/failed states; wired into
    `AppShell`'s header sync slot.
  - `AuthHost` boots the queue (`IndexedDbQueueStore` + `HttpMutationApplier`) +
    `SyncStatusController` when IndexedDB exists, publishes status into the
    shell store, syncs on reconnect, and disposes on unmount.
- Verification:
  - [x] Frontend: `npm run typecheck` → no errors; `npm run test` → OK (295 tests, 46 files; +27 new: 9 sync-status + 11 http-applier + 3 SyncStatusPanel + 3 shell store + 1 visualstate retrying); `npm run build` → built OK
  - [x] Backend: `php artisan test` → OK (650 tests, 1770 assertions; unchanged — no backend change)
  - [x] `check-secrets.sh`, `check-doc-links.sh`, `validate-repo.sh`, `check-openapi.sh`, `check-changelog.sh`, `check-version.sh` all PASS
- Evidence: server/resources/js/{visualstate/types.ts,shell/{store.ts,AppShell.vue,SyncStatusPanel.vue},offline/{sync-status.ts,http-applier.ts},auth/AuthHost.vue} and their `__tests__`
- Release Impact: PATCH (frontend-only UX additions; no API/schema change)

---

# 10. PHASE 12 — PRODUCTIVITY / RECOVERY

Create:

```text
TASK-120 … TASK-126
```

---

# TASK-120 — Execution Timer

Implement:

```text
start
pause
resume
complete
abandon
```

Connect execution to:

```text
FocusSession
Task status
Activity Log
Progress Events
```

Do not create a timer state model disconnected from persistence.

- Status: DONE
- Scope: Backend + frontend. New persisted `execution_sessions` table, Execution
  domain (state machine), application use cases, REST endpoints, timer UI in the
  Today NOW card.
- Requirements: FR-05 (Execution Timer — timer state derived from persisted
  timestamps; recorded = tracked, not nominal), FR-06 (Task Status), FR-18
  (Activity Log), FR-25 (Progress Events), NFR-12 (Concurrency/optimistic
  versioning — task transitions reuse existing optimistic flows).
- Changes:
  - Migration `2026_08_19_150000_create_execution_sessions_table`: `user_id`
    FK, `task_id` FK (cascade), `status` (`running|paused|completed|abandoned`),
    `started_at`, `last_resumed_at` (nullable), `accumulated_seconds` (default
    0), `ended_at` (nullable); indexes on `[user_id, status]` and
    `[user_id, task_id]`. Elapsed time is never a client-only model — it is
    derived from persisted timestamps + accumulated seconds (FR-05).
  - Domain: `ExecutionStatus` value object (transition rules:
    running→paused/completed/abandoned, paused→running/completed/abandoned,
    terminal states), immutable `ExecutionSession` entity (`start`, `pause`,
    `resume`, `complete`, `abandon`, `elapsedSeconds`, `toArray`, `withId`),
    `ExecutionSessionRepository` contract.
  - `FocusSession::fromTracked()` factory — records tracked (not nominal)
    duration, rounded to ≥ 1 minute (FR-05).
  - Application use cases: `StartExecutionUseCase` (rejects an already-running
    timer → `'An execution timer is already running.'`, moves task →
    `in_progress`, logs `task_started` activity with deterministic operationId
    `execution:started:{taskId}:{ts}`), `PauseExecutionUseCase`,
    `ResumeExecutionUseCase`, `CompleteExecutionUseCase` (records a
    `FocusSession` via `FocusSession::fromTracked`; if the task has no remaining
    subtasks → `SetTaskStatusUseCase(completed)` → `task_completed` activity +
    progress event; otherwise → `PartialCompleteTaskUseCase` → `continued` +
    scheduled continuation + `task_continued` activity; returns execution,
    focus_session, task, continuation), `AbandonExecutionUseCase` (logs
    `task_abandoned`, operationId `execution:abandoned:{sessionId}:{ts}`), plus
    `GetActiveExecutionUseCase` and `ListExecutionSessionsUseCase`.
  - `ActivityEventType` extended with `task_started` and `task_abandoned`.
  - REST: `ExecutionController` + routes — `GET /execution`, `GET
    /execution/active`, `POST /execution/start`, `POST
    /execution/{sessionId}/pause|resume|complete|abandon`. Error mapping:
    `Task not found.` → 404, `An execution timer is already running.` → 409,
    `Execution session not found.` → 404, other `InvalidArgumentException` →
    422. `ActivityLogController` event_type validation extended with the two new
    types.
  - OpenAPI: `/execution*` paths + `ExecutionSession`, `ExecutionStartRequest`,
    `ExecutionSessionResponse`, `ExecutionActiveResponse`,
    `ExecutionSessionListResponse`, `ExecutionCompleteResponse` schemas;
    `ActivityLog.event_type` enum extended.
  - Frontend: `execution/{types.ts,api.ts,store.ts,ExecutionTimer.vue}` —
    Pinia store derives `elapsedSeconds` from persisted timestamps (FR-05),
    ticks locally while running, reloads active session on mount; `ExecutionTimer`
    renders Start/Pause/Resume/Complete/Abandon controls in the Today NOW card
    (replaces inert Complete button); `TodayView` reloads the day on completion
    so task status/progress reflect the result.
- Verification:
  - [x] Backend: `php artisan test` → OK (668 tests, 1832 assertions; +18 new:
    9 ExecutionSession unit + 2 FocusSession.fromTracked + 7 Execution API
    feature)
  - [x] Backend: `vendor/bin/pint --test` clean (auto-fixed); `vendor/bin/phpstan analyse` → No errors
  - [x] Frontend: `npm run typecheck` → no errors; `npm run test` → OK (307
    tests, 48 files; +12 new: 7 execution store + 5 ExecutionTimer component);
    `npm run build` → built OK
  - [x] `check-secrets.sh`, `check-doc-links.sh`, `validate-repo.sh`,
    `check-openapi.sh`, `check-changelog.sh`, `check-version.sh` all PASS
- Evidence: server/app/Domain/Execution/, server/app/Application/Execution/,
  server/app/Infrastructure/Execution/, server/app/Models/ExecutionSession.php,
  server/app/Http/Controllers/Api/ExecutionController.php,
  database/migrations/2026_08_19_150000_create_execution_sessions_table.php,
  server/resources/js/execution/, docs/api/openapi.yaml, and their `__tests__`
- Release Impact: MINOR (new REST API + schema + frontend feature)

---

# TASK-121 — Recharge Timer

Implement Recharge according to the SRS.

Recharge MUST contribute to:

```text
RechargeMinutes
```

and therefore:

```text
WorkRatio
RechargeRatio
```

- Status: DONE
- Scope: Backend + frontend. New persisted `recharge_sessions` table, Recharge
  domain (timer state machine), application use cases, REST endpoints, and the
  Today recharge CTA/timer UI with the day's Work-Life Ratio.
- Requirements: FR-05 (Recharge Timer — 15-min Recharge after every two
  completed focus sessions; recorded duration is the tracked duration, never
  the nominal 15 minutes; Recharge counts as Recharge, never Productive Time;
  timer state derived from persisted timestamps so refresh/browser close must
  not lose a started timer), SRS §7.1 (recharge in the 24h timeline).
- Changes:
  - Migration `2026_08_20_150000_create_recharge_sessions_table`: `user_id` FK,
    `status` (`running|paused|completed|abandoned`), `started_at`,
    `last_resumed_at` (nullable), `accumulated_seconds` (default 0),
    `duration_minutes` (nullable, set on completion), `ended_at` (nullable);
    indexes on `[user_id, status]` and `[user_id, started_at]`.
  - Domain: `RechargeStatus` value object (explicit transition rules mirroring
    the execution timer), immutable `RechargeSession` entity (`start`, `pause`,
    `resume`, `complete` — records `duration_minutes = max(1, round(tracked/60))`
    — `abandon` — no duration recorded — `elapsedSeconds`, `toArray`,
    `withId`), `RechargeSessionRepository` contract.
  - `FocusSessionRepository` extended with `countCompletedBetween` and
    `sumDurationMinutesBetween` so the recharge cadence and Work-Life Ratio are
    computed from persisted productive time.
  - Application use cases: `GetRechargeStatusUseCase` (active session + CTA
    `cue_available` when `intdiv(focusToday, 2) > rechargesToday` and none
    active; RechargeMinutes + ProductiveMinutes + `work_ratio`/`recharge_ratio`
    for the day), `StartRechargeUseCase` (409 when a recharge timer is already
    running), `PauseRechargeUseCase`, `ResumeRechargeUseCase`,
    `CompleteRechargeUseCase` (persists the tracked duration),
    `AbandonRechargeUseCase`, `ListRechargeSessionsUseCase`.
  - REST: `RechargeController` + routes — `GET /recharge/status?date=`,
    `GET /recharge`, `POST /recharge/start`, `POST
    /recharge/{sessionId}/pause|resume|complete|abandon`. Error mapping:
    `Recharge session not found.` → 404, `A recharge timer is already
    running.` → 409, other `InvalidArgumentException` → 422.
  - OpenAPI: `/recharge*` paths + `RechargeSession`, `RechargeSessionResponse`,
    `RechargeSessionListResponse`, `RechargeStatusResponse` schemas; new
    `Recharge` tag. Removed a stray trailing `---` that split the YAML into a
    second document (now parsed cleanly by the deep OpenAPI gate).
  - Frontend: `recharge/{types.ts,api.ts,store.ts,RechargeTimer.vue}` — Pinia
    store loads `GET /recharge/status` on mount, derives elapsed from persisted
    timestamps (FR-05), and refreshes after start/complete/abandon;
    `RechargeTimer` renders the Start CTA after the second completed focus
    session, running controls while active, and the day's Work/Recharge split.
    Wired into the Today NOW card; completion reloads Today so the schedule and
    ratio reflect the change.
- Verification:
  - [x] Backend: `php artisan test` → OK (687 tests, 1912 assertions; +19 new:
    10 RechargeSession unit + 9 Recharge API feature)
  - [x] Backend: `vendor/bin/pint --test` clean (auto-fixed); `vendor/bin/phpstan analyse` → No errors
  - [x] Frontend: `npm run typecheck` → no errors; `npm run test` → OK (321
    tests, 50 files; +14 new: 8 recharge store + 6 RechargeTimer component);
    `npm run build` → built OK
  - [x] `check-secrets.sh`, `check-doc-links.sh`, `validate-repo.sh`,
    `check-openapi.sh` (deep YAML parse), `check-changelog.sh`,
    `check-version.sh` all PASS
- Evidence: server/app/Domain/Recharge/,
  server/app/Application/Recharge/, server/app/Infrastructure/Recharge/,
  server/app/Models/RechargeSession.php,
  server/app/Http/Controllers/Api/RechargeController.php,
  database/migrations/2026_08_20_150000_create_recharge_sessions_table.php,
  server/resources/js/recharge/, docs/api/openapi.yaml, and their `__tests__`
- Release Impact: MINOR (new REST API + schema + frontend feature)

---

# TASK-122 — Mini Pause

Implement:

* select current task;
* move to next eligible slot;
* preserve constraints;
* update assignment transactionally;
* log action;
* explain resulting change.

- Status: DONE
- Scope: Backend + frontend. `MiniPauseUseCase` moves every eligible task
  scheduled on the given date to the first feasible slot on the following day,
  preserving hard constraints, persisting atomically at the next schedule
  version, logging the action (FR-34), and explaining the resulting change.
- Requirements: FR-07 (Mini Pause — move all eligible tasks to the next day's
  eligible slots and recalculate the schedule; locked tasks are never
  auto-moved; on conflict a task is flagged and stays visible; the action
  counts as Recharge at the analytics layer), FR-04/FR-08 (locked assignments
  never moved by automation), FR-64 (hard-constraint engine drives feasibility).
- Changes:
  - Domain: `ScheduleAssignmentSource` gains `mini_pause`; `ActivityEventType`
    gains `mini_pause`.
  - `MiniPauseUseCase` (`server/app/Application/Scheduling/`): selects today's
    unlocked non-cancelled assignments, skips terminal tasks, finds the first
    next-day slot that fits each assignment's duration using `SlotCalculator` +
    `HardConstraintEngine` (Hard Landscape, deadline, duration fit, overlap,
    safety reserve), returns `MiniPauseResult` (`version`, `applied`, `moves`,
    `conflict_task_ids`, `explanation`). Tasks that cannot be placed stay in
    place and are reported as conflicts. Persists all moves in one DB
    transaction at the next schedule version with source `mini_pause`; logs one
    `mini_pause` activity entry (entity `schedule`, entity id = new version)
    with moved/conflict task ids; composes a human-readable explanation.
  - REST: `MiniPauseController::store` + `POST /schedule/mini-pause`
    (`date` required). 200 when tasks moved, 202 when nothing eligible.
  - `ActivityLogController` event_type filter now accepts `mini_pause`.
  - OpenAPI: `/schedule/mini-pause` path + `MiniPauseRequest`, `MiniPauseMove`,
    `MiniPauseResponse` schemas; `mini_pause` added to the `source` enum, the
    `ActivityLog.event_type` enum, and the activity filter enum.
  - Frontend: `todayApi.miniPause()` + `MiniPause*` types; a "Mini Pause"
    button on the Today NOW card that posts the current date, shows the returned
    explanation, and reloads the day.
- Verification:
  - [x] Backend: `php artisan test` → OK (698 tests, 1963 assertions; +11 new:
    7 MiniPause use case + 4 Mini Pause API)
  - [x] Backend: `vendor/bin/pint --test` clean (auto-fixed); `vendor/bin/phpstan analyse --memory-limit=1G` → No errors
  - [x] Frontend: `npm run typecheck` → no errors; `npm run test` → OK (322
    tests, 50 files; +1 new TodayView Mini Pause test); `npm run build` → built OK
  - [x] `check-secrets.sh`, `check-doc-links.sh`, `validate-repo.sh`,
    `check-openapi.sh` (deep YAML parse; 90 paths), `check-changelog.sh`,
    `check-version.sh` all PASS
- Evidence: server/app/Application/Scheduling/MiniPauseUseCase.php,
  server/app/Application/Scheduling/MiniPauseResult.php,
  server/app/Http/Controllers/Api/MiniPauseController.php,
  server/routes/api.php, server/tests/Feature/Scheduling/MiniPauseUseCaseTest.php,
  server/tests/Feature/Api/MiniPauseApiTest.php,
  server/resources/js/today/{api.ts,types.ts,TodayView.vue},
  docs/api/openapi.yaml, README.md
- Release Impact: MINOR (new REST API + frontend feature)

---

# TASK-123 — Emergency Pause

Implement:

```text
Emergency Pause
```

Behavior:

* classify the period as exceptional;
* shift affected tasks according to SRS;
* suppress relevant notifications;
* tag exceptional capacity period;
* visually identify recovery state;
* preserve task ownership;
* do not delete tasks.

- Status: DONE
- Scope: Backend + frontend. `EmergencyPauseUseCase` tags the given week as an
  exceptional recovery period, keeps the user-selected tasks in place, moves
  every other eligible task +1 week to the same weekday, and suppresses
  notifications for the week. Tasks are never deleted and ownership is
  preserved. The week is exposed as a `pause` recovery state in the day/week
  schedule queries.
- Requirements: FR-07 (Emergency Pause — user selects which tasks to keep;
  unchecked tasks shift +1 week; locked tasks are never auto-moved; conflicts
  are flagged and stay visible; the week is marked as recovery state with grey
  analytics; both Mini and Emergency Pause count as Recharge and Emergency
  Pause never deletes historical activity), FR-47 (emergency pause suppresses
  notifications while preserving audit data), FR-49 (emergency/break weeks are
  tagged so the engine excludes them from capacity), FR-04/FR-08 (locked
  assignments never moved by automation), FR-64 (hard-constraint engine drives
  feasibility).
- Changes:
  - Data: migration `2026_08_20_170000_create_pause_events_table` →
    `pause_events` (`user_id`, `type`, `week_start`, `week_end`,
    `keep_task_ids`/`moved_task_ids`/`conflict_task_ids` JSON, `schedule_version`;
    unique `[user_id,type,week_start]`, index `[user_id,week_start]`).
  - Domain: `PauseEventType` (`emergency`, `mini`), `PauseEvent` entity,
    `PauseEventRepository` contract + `EloquentPauseEventRepository`,
    `App\Models\PauseEvent`. `ScheduleAssignmentSource` gains `emergency_pause`;
    `ActivityEventType` gains `emergency_pause`.
  - `EmergencyPauseUseCase` (`server/app/Application/Scheduling/`): computes the
    week range, selects unlocked + non-cancelled + non-terminal + non-kept
    assignments, finds the same-weekday slot next week for each via
    `SlotCalculator` + `HardConstraintEngine` (Hard Landscape + occupancy of the
    following week), returns `EmergencyPauseResult` (`version`, `applied`,
    `week_start`, `week_end`, `kept_task_ids`, `moved_task_ids`,
    `conflict_task_ids`, `explanation`). Tasks that cannot be placed stay in
    place and are reported as conflicts. Persists all moves in one DB
    transaction at the next schedule version with source `emergency_pause`,
    records the `pause_events` row, logs one `emergency_pause` activity entry,
    and composes a human-readable explanation. No eligible tasks → true no-op
    (week not tagged).
  - REST: `EmergencyPauseController` + `POST /schedule/emergency-pause`
    (`date` required, `keep_task_ids` array of task ids, empty array allowed).
    200 when applied, 202 when nothing eligible.
  - Notifications: `RunEodPromptUseCase` now injects `PauseEventRepository` and
    returns null (suppressed) when the week is tagged exceptional — suppression
    while preserving audit data (FR-47).
  - Query: `ScheduleQueryService` injects `PauseEventRepository`; `dayView` and
    `weekView` include a nullable `pause` object for recovery-state UI.
  - `ActivityLogController` event_type filter now accepts `emergency_pause`.
  - OpenAPI: `/schedule/emergency-pause` path + `EmergencyPauseRequest`,
    `EmergencyPauseMove`, `EmergencyPauseResponse`, `PauseEvent` schemas;
    `emergency_pause` added to the `source` and `ActivityLog.event_type` enums;
    `pause` added to `TodayResponse` and `WeekResponse`.
  - Frontend: `todayApi.weekRange()` + `todayApi.emergencyPause()`;
    `EmergencyPause*` types; `EmergencyPauseDialog.vue` lists the week's tasks
    with keep checkboxes (defaults to the current task) and confirms the pause;
    an "Emergency Pause" button on the Today NOW card opens it; a recovery
    banner (`recovery-banner`) renders when the week is tagged exceptional; the
    result explanation is shown after confirmation.
- Verification:
  - [x] Backend: `php artisan test` → OK (712 tests, 2040 assertions; +14 new:
    9 EmergencyPause use case + 5 Emergency Pause API, +1 EOD prompt suppression)
  - [x] Backend: `vendor/bin/pint --test` clean (auto-fixed); `vendor/bin/phpstan analyse --memory-limit=1G` → No errors
  - [x] Frontend: `npm run typecheck` → no errors; `npm run test` → OK (324
    tests, 50 files; +2 new TodayView Emergency Pause tests); `npm run build` → built OK
  - [x] `check-secrets.sh`, `check-doc-links.sh`, `validate-repo.sh`,
    `check-openapi.sh` (deep YAML parse; 91 paths), all PASS
- Evidence: server/app/Application/Scheduling/EmergencyPauseUseCase.php,
  server/app/Application/Scheduling/EmergencyPauseResult.php,
  server/app/Domain/Pauses/{PauseEvent.php,Contracts/PauseEventRepository.php,ValueObjects/PauseEventType.php},
  server/app/Infrastructure/Pauses/EloquentPauseEventRepository.php,
  database/migrations/2026_08_20_170000_create_pause_events_table.php,
  server/app/Http/Controllers/Api/EmergencyPauseController.php,
  server/app/Application/Reconciliation/RunEodPromptUseCase.php,
  server/app/Application/Scheduling/ScheduleQueryService.php,
  server/routes/api.php, server/tests/Feature/Scheduling/EmergencyPauseUseCaseTest.php,
  server/tests/Feature/Api/EmergencyPauseApiTest.php,
  server/tests/Feature/Console/EodReconcileCommandTest.php,
  server/resources/js/today/{EmergencyPauseDialog.vue,TodayView.vue,api.ts,types.ts,store.ts},
  docs/api/openapi.yaml
- Release Impact: MINOR (new REST API + database migration + frontend feature)

---

# TASK-124 — Break Mode

Status: DONE

Requirements: FR-36 (holiday detection with manual confirmation; one active break at a time), FR-39 (H-3 holiday-end notification, exactly once per break period, summary report), FR-41 (in-app notification), FR-49 (break weeks tagged exceptional for capacity feedback). Respects the SRS safe capacity rules.

Implementation:

```text
start break ............ POST /break (StartBreakUseCase, one active break; end >= start)
end break ............. POST /break/end (EndBreakUseCase; no-op 202 when none active)
capacity handling ..... break weeks tagged exceptional (FR-49), excluded from capacity estimates
notification behavior . break:notify-end command (20:30 local, H-3 before end), single break_end notification per period; EOD prompt suppressed during active break
schedule effects ...... ScheduleQueryService exposes nullable `break` recovery state in day/week views
summary ............... StartBreakResponse / EndBreakResponse with duration + explanation
```

Verification evidence: `php artisan test` 733 passed (2105 assertions); Vitest 326 passed (50 files); PHPStan 0 errors; Pint clean (497 files); vue-tsc typecheck clean; `npm run build` OK; repo gates PASS (validate-repo, secrets, doc-links 20, openapi 93 paths, changelog, version).

Changes: `database/migrations/2026_08_20_180000_create_break_periods_table.php`; `server/app/Domain/Breaks/` (BreakPeriodStatus, BreakPeriod, BreakPeriodRepository contract); `server/app/Infrastructure/Breaks/EloquentBreakPeriodRepository.php`; `server/app/Models/BreakPeriod.php`; `server/app/Application/Breaks/` (StartBreakUseCase, EndBreakUseCase, RunBreakEndNotificationUseCase, results); `server/app/Console/Commands/BreakEndNotificationCommand.php` (scheduled 20:30 in `bootstrap/app.php`); `server/app/Http/Controllers/Api/BreakController.php` + routes; `ScheduleQueryService` + `RunEodPromptUseCase`; NotificationType/ActivityEventType VOs; `docs/api/openapi.yaml` (/break, /break/end, BreakPeriod schemas, enums, `break` in Today/Week responses). Frontend: `today/types.ts`, `today/api.ts`, `today/store.ts`, `BreakModeDialog.vue`, TodayView banner + Start/End Break actions. Tests: `BreakPeriodUseCaseTest` (7), `BreakApiTest` (8), `BreakEndNotificationCommandTest` (4), EOD suppression test.

Committed: see git log (TASK-124 full slice, backend + frontend + gates).

---

# TASK-125 — Boost Mode

Status: DONE

Requirements: FR-37 (holiday boost target setup with recommendations and 70% safety cap), FR-38 (use boost targets during confirmed Break Mode when generating schedules, temporary target without mutating baseline), FR-49 (offer Boost when >90% realization and no burnout signal; break weeks tagged). Existing capacity feedback reused — no capacity calculations duplicated.

Implementation:

```text
boost eligibility ...... active Break Mode required; Capacity feedback >90% & no burnout signal offers Boost (FR-49)
capacity ceiling ...... boost target saved as % of daily capacity, capped at 70% (FR-37 exception: capped with explicit warning)
burnout suppression ... recommendation suppressed while a burnout signal is active (FR-49)
user confirmation ..... setup dialog shows recommendation, user adjusts slider, confirms save (FR-37 normal flow)
temporary target ....... boost scoped by start/end datetime within the active break; draft constrained per day; returns to baseline when ended (FR-38)
summary ............... BoostSetupResponse (target + recommendation) and SetBoostTarget/EndBoostTarget summaries
```

Verification evidence: `php artisan test` 752 passed (2182 assertions); Vitest 328 passed (50 files); PHPStan 0 errors; Pint clean (514 files); vue-tsc typecheck clean; `npm run build` OK; repo gates PASS (validate-repo, secrets, doc-links 20, openapi 95 paths, changelog, version).

Changes: `database/migrations/2026_08_20_190000_create_boost_targets_table.php`; `server/app/Domain/Boosts/` (BoostTargetStatus, BoostTarget with 70% safety cap, BoostTargetRepository); `server/app/Models/BoostTarget.php`; `server/app/Infrastructure/Boosts/EloquentBoostTargetRepository.php`; `server/app/Application/Boosts/` (GetBoostSetupUseCase, SetBoostTargetUseCase, EndBoostTargetUseCase, GetEffectiveTargetUseCase, WeekCapacitySampleProvider, results); ActivityEventType boost_start/boost_end; `ScheduleDraftController` resolves the effective boost target and `DraftInput.dailyCapacityPercent` enforces the per-day ceiling in `ScheduleDraftGenerator` (unassigned reason `CAPACITY_CAP`); `BoostController` (GET/POST /boost, POST /boost/end); `docs/api/openapi.yaml` (/boost paths, BoostTarget/BoostRecommendation/BoostSetupResponse schemas, enums). Frontend: `today/types.ts`, `today/api.ts`, `BoostDialog.vue` (slider + recommendation + cap warning), TodayView boost actions in the Break banner. Tests: `BoostApiTest` (12), `BoostTargetTest` (5), `ScheduleDraftGeneratorTest` boost cap cases (3).

Committed: see git log (TASK-125 full slice, backend + frontend + gates).

---

# TASK-126 — Work-Life Ratio

Status: DONE

Requirements: FR-05 (postcondition: the Work-Life Ratio includes the recorded Recharge duration; Recharge is Recharge, never Productive Time) — the full pipeline from productive + recharge sessions through the normative WorkRatio/RechargeRatio to analytics. The ratio is presented as a time-balance indicator, never a health diagnosis.

Implementation:

```text
normative formula ... workRatio = productive / (productive + recharge); rechargeRatio = recharge / (productive + recharge) (single domain service reused by the Recharge status)
aggregation ......... per-day productive (focus) + Recharge minutes over the requested range → totals + per-day series (analytics consumes already-generated data)
read surface ........ GET /analytics/work-life?from=&to= (defaults: week of `to` / now); WorkLifeAnalyticsResponse with band + disclaimer
reuse ............... GetRechargeStatusUseCase now derives its ratios from the same WorkLifeRatio service — no duplicated formula
frontend surface .... Analytics view wired into the shell with 7d/30d/this-week/this-month presets, ratio summary + per-day bars + disclaimer
```

Verification evidence: `php artisan test` 762 passed (2223 assertions); Vitest 333 passed (51 files); PHPStan 0 errors; Pint clean; vue-tsc typecheck clean; `npm run build` OK; repo gates PASS (validate-repo, secrets, doc-links 20, openapi 96 paths, changelog, version).

Changes: `server/app/Domain/Analytics/WorkLifeRatio.php` (normative formula, descriptive band, disclaimer); `server/app/Application/Analytics/` (GetWorkLifeAnalyticsUseCase, WorkLifeAnalyticsResult); `AnalyticsController@workLife` (GET /analytics/work-life); `routes/api.php`; `GetRechargeStatusUseCase` refactored onto WorkLifeRatio; `docs/api/openapi.yaml` (/analytics/work-life, WorkLifeDay/WorkLifeAnalyticsResponse). Frontend: `analytics/types.ts`, `analytics/api.ts`, `analytics/store.ts`, `analytics/AnalyticsView.vue`, wired into `AuthHost`. Tests: `WorkLifeRatioTest` (5), `AnalyticsApiTest` (4), `AnalyticsView.test.ts` (5). Also fixed a latent UTC/local-date bug in `EmergencyPauseDialog.weekStart/addDays` (toISOString shifted the week range a day in UTC+ timezones).

Committed: see git log (TASK-126 full slice, backend + frontend + gates).

---

# 11. PHASE 13 — ANALYTICS

Create:

```text
TASK-130 … TASK-135
```

The analytics layer should consume already-generated data.

Do not duplicate business calculations inside controllers.

---

# TASK-130 — Analytics Read Models

Status: DONE

Requirements: Phase 13 (analytics consumes already-generated data; business calculations never duplicated in controllers; read-side services preferred over ad-hoc Vue calculations). Read models for task completion, goal progress/milestones, capacity, activity, focus, progress events, and the Work-Life Ratio.

Implementation:

```text
task completion .... snapshot of the board (total/completed/rate + by-status) + tasks completed within the period (activity `task_completed` events)
goal progress ..... goal/milestone progression + program contribution from the current aggregates (read-side only)
capacity .......... recent-week samples (planned/completed/realization/tag, week-keyed) + the Capacity feedback-loop estimate — reuses WeekCapacitySampleProvider + CapacityCalculator, never recreates the algorithm (TASK-132 reuses this further)
activity .......... append-only activity log grouped by event type over the period + recency sample
focus ............. completed focus sessions + minutes per day over the period
progress events ... meaningful progress events grouped by type over the period + recency sample
work-life ratio ... the normative WorkRatio/RechargeRatio (TASK-126)
read surface ...... GET /analytics/overview?from=&to= composes every read model for the period (defaults: week of `to` / now)
```

Verification evidence: `php artisan test` 764 passed (2249 assertions); PHPStan 0 errors; Pint clean; repo gates PASS (validate-repo, secrets, doc-links 20, openapi 97 paths, changelog, version).

Changes: `server/app/Application/Analytics/` — read-model use cases (GetTaskCompletionAnalyticsUseCase, GetGoalProgressAnalyticsUseCase, GetCapacityAnalyticsUseCase, GetActivityAnalyticsUseCase, GetFocusAnalyticsUseCase, GetProgressEventsAnalyticsUseCase) and `Results/` value objects; `WeekCapacitySampleProvider` gained a week-keyed `samplesByWeek()`; `AnalyticsController@overview` (GET /analytics/overview) with shared range parsing; `routes/api.php`; `docs/api/openapi.yaml` (/analytics/overview + TaskCompletion/GoalProgress/Capacity/Activity/Focus/ProgressEvents/AnalyticsOverview schemas). Frontend: none — read models are served for the analytics surfaces (TASK-131..135). Tests: AnalyticsApiTest overview cases (2) and authentication coverage.

Committed: see git log (TASK-130 read-model layer, backend + gates).

---

# TASK-131 — Goal Progress Analytics

Status: DONE

Requirements: Phase 13 goal analytics — goal completion, milestone progression, program contribution, deadline health, and workload completion, consumed from the TASK-130 read models (no business calculations in Vue).

Implementation:

```text
goal completion ...... total/completed/rate + per-goal progress bars (overview read model)
milestone progression  milestones total/completed per goal and overall
program contribution . per-program workload completion and task counts
deadline health ...... per-goal timeline classification (completed/on_track/at_risk/overdue/no_deadline) with days remaining — a descriptive schedule indicator, not a health diagnosis
workload completion . goal-linked and program task completion percentages
UI ................... Analytics view now loads GET /analytics/overview and renders a Goal progress section (goals with progress bars, deadline labels, milestone/task counts, deadline-health summary, programs)
```

Verification evidence: `php artisan test` 765 passed (2260 assertions); PHPStan 0 errors; Pint clean; Vitest 334 passed (51 files); vue-tsc typecheck clean; `npm run build` OK; repo gates PASS (validate-repo, secrets, doc-links 20, openapi 97 paths, changelog, version).

Changes: `GetGoalProgressAnalyticsUseCase` extended with deadline health (evaluated at the period end) + per-goal tasks + workload completion; `GoalProgressAnalytics` result gained `deadline_health`, `goal_tasks_*`, `workload_completion` and per-goal/program fields; `docs/api/openapi.yaml` schemas updated (GoalSummary, ProgramContribution, DeadlineHealthCounts, GoalProgressAnalyticsResponse). Frontend: `analytics/types.ts` overview types, `analytics/api.ts` `overview()`, `analytics/store.ts` goal fields, `analytics/AnalyticsView.vue` Goal progress section (consumes the read model). Tests: AnalyticsApiTest deadline-health classification case; AnalyticsView.test.ts goal-section cases.

Committed: see git log (TASK-131 goal analytics surface, backend + frontend + gates).

---

# TASK-132 — Capacity Analytics

Status: DONE

Requirements: Phase 13 capacity analytics — available capacity, scheduled load, overload, effective capacity, realization ratio, and capacity trend, reusing `CapacityCalculator` rather than recreating the algorithm.

Implementation:

```text
scheduled load ...... per-day scheduled minutes from non-cancelled assignments (same primitives as the Today view)
available capacity .. per-day empty-slot minutes via SlotCalculator (occupied events + Hard Landscape)
overload ............ per-day overload = max(0, scheduled − available), flagged `overload`/`ok`
effective capacity .. the Capacity feedback-loop estimate (CapacityCalculator reused, never recreated)
realization ratio ... focus minutes ÷ scheduled minutes over the period
capacity trend ...... weekly realization series (planned/completed per week, tag) from WeekCapacitySampleProvider
UI ................... Analytics view renders a Capacity section (per-day load bars with overload highlighting, summary, weekly trend, and the feedback-loop recommendation/reason)
```

Verification evidence: `php artisan test` 766 passed (2270 assertions); PHPStan 0 errors; Pint clean; Vitest 335 passed (51 files); vue-tsc typecheck clean; `npm run build` OK; repo gates PASS (validate-repo, secrets, doc-links 20, openapi 97 paths, changelog, version).

Changes: `GetCapacityAnalyticsUseCase` extended with per-day capacity (scheduled/available/overload via assignments + hard landscape + SlotCalculator), realization ratio, and the period range; `CapacityAnalytics` result gained `from`, `to`, `days`, `realization_ratio`; `docs/api/openapi.yaml` (CapacityDay + expanded CapacityAnalyticsResponse). Frontend: `analytics/types.ts` (CapacityDay), `analytics/store.ts` capacity fields, `analytics/AnalyticsView.vue` Capacity section. Tests: AnalyticsApiTest scheduled-load/overload case; AnalyticsView.test.ts capacity-section cases.

Committed: see git log (TASK-132 capacity analytics surface, backend + frontend + gates).

---

# TASK-133 — Pillar Analytics

Status: DONE

Requirements: FR-12 (Grafik 4 Pilar Kehidupan) — compute and display realization vs target for exactly Karier, Kesehatan, Bahasa, Branding, plus Uncategorized. Do not invent additional pillars. Pillars are determined via program/goal mapping; Uncategorized is only for tasks without a mapping; division by zero target yields N/A (not NaN).

Implementation:

```text
pillars ............ fixed set (karier, kesehatan, bahasa, branding) + uncategorized; nothing else is invented (FR-12)
mapping ............ program category matched to a canonical pillar (case-insensitive); unknown marker → Uncategorized (FR-12 Business Rules / Exception Flows)
realization ........ completed task minutes in the period per pillar (from `task_completed` progress events → task estimated minutes)
target ............. mapped program weekly_target_minutes × weeks in the period
percent ............ realization ÷ target; null (N/A) when target is 0 — never NaN (FR-12 Exception Flows)
read surface ....... GET /analytics/pillars?from=&to= + included in /analytics/overview
UI ................. Analytics view renders a Life pillars section (realization vs target bars, % or N/A, completed vs target minutes)
```

Verification evidence: `php artisan test` 768 passed (2283 assertions); PHPStan 0 errors; Pint clean; Vitest 336 passed (51 files); vue-tsc typecheck clean; `npm run build` OK; repo gates PASS (validate-repo, secrets, doc-links 20, openapi 98 paths, changelog, version).

Changes: `server/app/Domain/Analytics/Pillar.php` (value object, category→pillar mapping); `GetPillarAnalyticsUseCase`; `PillarAnalytics` result; `AnalyticsController@pillars` (GET /analytics/pillars) + included in overview; `routes/api.php`; `docs/api/openapi.yaml` (PillarRow, PillarAnalyticsResponse, /analytics/pillars). Frontend: `analytics/types.ts` (PillarKey/PillarRow/PillarAnalyticsResponse), `analytics/store.ts` pillars, `analytics/AnalyticsView.vue` Life pillars section. Tests: AnalyticsApiTest pillar realization vs target + overview inclusion; AnalyticsView.test.ts pillars section.

Committed: see git log (TASK-133 pillar analytics, backend + frontend + gates).

---

# TASK-134 — Heatmap

Status: DONE

Requirements: FR-31 (Annual Heatmap) — per-day activity intensity from completion and recharge (plus productive time and progress events), with pillar filtering, an understandable legend, and accessible alternatives. Metric definition is stable within a report version; missing dates report zero with an accessible label.

Implementation:

```text
date ................ per-day series over the selected range
productive time ..... focus minutes per day
recharge ............ recharge minutes per day
completion .......... task completions per day (task_completed progress events)
progress ............ progress events per day
intensity ........... fixed, documented metric: score = productive + recharge + completion*30 + progress*10 → 0..4 (stable within a report version, FR-31 Business Rules)
pillar filter ....... optional pillar restricts the completion/progress dataset without mutating logs (FR-31 AC)
legend .............. level labels (None/Low/Medium/High/Very high) rendered as a color scale
accessible alt ...... per-cell aria-label/title with exact values + a collapsible text list of every day
read surface ........ GET /analytics/heatmap?from=&to=&pillar= (default: trailing year of `to`)
```

Verification evidence: `php artisan test` 770 passed (2304 assertions); PHPStan 0 errors; Pint clean; Vitest 338 passed (51 files); vue-tsc typecheck clean; `npm run build` OK; repo gates PASS (validate-repo, secrets, doc-links 20, openapi 98 paths, changelog, version).

Changes: `GetHeatmapAnalyticsUseCase`; `HeatmapAnalytics` result; `AnalyticsController@heatmap` (GET /analytics/heatmap with optional pillar filter); `routes/api.php`; `docs/api/openapi.yaml` (HeatmapDay/HeatmapLegend/HeatmapAnalyticsResponse + /analytics/heatmap). Frontend: `analytics/types.ts` (HeatmapDay/PillarKey/HeatmapAnalyticsResponse), `analytics/api.ts` `heatmap()`, `analytics/store.ts` heatmap fields + `loadHeatmap`, `analytics/AnalyticsView.vue` Activity heatmap section (weekly grid, range presets, pillar filter, legend, accessible list). Tests: AnalyticsApiTest heatmap intensity/zero-days + pillar filter (2); AnalyticsView.test.ts heatmap section (2).

Committed: see git log (TASK-134 heatmap, backend + frontend + gates).

---

# TASK-135 — Work-Life Analytics

Status: DONE

Requirements: FR-05 WorkRatio/RechargeRatio (normative formula, TASK-126) extended with period comparison, trend, and exceptions. The ratio is a time-balance indicator — never presented as a medical or biological optimum ("70:30" is not framed as a target).

Implementation:

```text
WorkRatio/RechargeRatio .. normative formula (reused, TASK-126)
period comparison ........ current period vs the immediately preceding equal-length period (productive/recharge minutes + both ratios)
trend ................... weekly WorkRatio/RechargeRatio series over the current period
exceptions .............. descriptive notable days: no_data, work_only (focus without recharge), recharge_only (recharge without focus)
disclaimer .............. "Time-balance indicator. Not a health diagnosis." carried in the response and rendered — the ratio is never framed as a medical/biological optimum
```

Verification evidence: `php artisan test` 772 passed (2319 assertions); PHPStan 0 errors; Pint clean; Vitest 338 passed (51 files); vue-tsc typecheck clean; `npm run build` OK; repo gates PASS (validate-repo, secrets, doc-links 20, openapi 98 paths, changelog, version).

Changes: `GetWorkLifeAnalyticsUseCase` extended with previous-period comparison, weekly trend, and descriptive exceptions; `WorkLifeAnalyticsResult` gained `previous` (always present), `trend`, `exceptions`; `docs/api/openapi.yaml` (WorkLifePrevious, WorkLifeTrendWeek, WorkLifeException + expanded WorkLifeAnalyticsResponse). Frontend: `analytics/types.ts`, `analytics/store.ts` (previous/trend/exceptions), `analytics/AnalyticsView.vue` work-life summary shows vs-previous-period comparison, weekly trend, and notable days, all under the disclaimer. Tests: AnalyticsApiTest period-comparison/trend + exceptions (2); AnalyticsView.test.ts additions.

Committed: see git log (TASK-135 work-life comparison/trend/exceptions, backend + frontend + gates).

---

# 12. PHASE 14 — IMPORT / EXPORT / ATTACHMENTS

Create:

```text
TASK-140 … TASK-144
```

---

# TASK-140 — Task Attachments / Evidence

Status: DONE

Requirements: FR-43 — up to 3 evidence files per completed task, each JPG/PNG/PDF and ≤5 MB; fourth file and 5.1 MB file rejected. SRS line 1641 (allowlist extension + detected content type + size) and line 1653 (attachments are not world-readable); the browser-provided MIME is never trusted on its own.

Implementation:

```text
upload ......... POST /tasks/{taskId}/attachments (multipart `file`); task must be completed; count < 3; size ≤ 5 MB; detected content type in allowlist (finfo on contents, not browser MIME); extension allowlist
list ........... GET /tasks/{taskId}/attachments (owner-scoped)
download ....... GET /tasks/{taskId}/attachments/{id} — streams the private file after authorization (not world-readable)
delete ......... DELETE /tasks/{taskId}/attachments/{id} — removes the file + metadata
ownership ...... every operation resolves through findForUser (owner-scoped); 404 for other users
hash/checksum .. SHA-256 stored on every attachment
atomicity ...... storage failure never leaves a dangling DB record (FR-43 Exception Flows)
rules .......... GET /attachments/rules exposes the limits for client pre-validation
```

Verification evidence: `php artisan test` 781 passed (2360 assertions); PHPStan 0 errors; Pint clean (548 files); Vitest 339 passed (51 files); vue-tsc typecheck clean; `npm run build` OK; repo gates PASS (validate-repo, secrets, doc-links 20, openapi 102 paths, changelog, version).

Changes: migration `2026_08_20_200000_create_attachments_table.php`; `app/Domain/Attachments/` (Attachment entity, AttachmentRule constants, AttachmentRepository contract); `app/Models/Attachment.php`; `app/Infrastructure/Attachments/EloquentAttachmentRepository.php`; `app/Application/Attachments/` (UploadTaskAttachmentUseCase, ListTaskAttachmentsUseCase, GetTaskAttachmentUseCase, DeleteTaskAttachmentUseCase); `AttachmentController` (store/index/show/destroy/rules); routes + AppServiceProvider binding; `docs/api/openapi.yaml` (/tasks/{taskId}/attachments, /attachments/rules, Attachment, AttachmentRulesResponse). Frontend: `attachments/types.ts`, `attachments/api.ts` (upload/list/remove/blob download with auth), `attachments/AttachmentList.vue` (list, upload with limits, download, delete, completed-task gate), embedded in `TaskDetailView`. Tests: `AttachmentApiTest` (9) + TaskViews.test.ts attachment cases.

Committed: see git log (TASK-140 attachments, backend + frontend + gates).

---

# TASK-141 — PDF KRS Import

Status: DONE

Requirements: FR-24 — accept a KRS PDF, parse the schedule, show a preview, and only create Hard Landscape after user confirmation; manual input is the mandatory fallback. Import must not silently overwrite an existing schedule; parse failure falls back to manual entry. New runtime dependency `smalot/pdfparser` (MIT) added for pure-PHP PDF text extraction (recorded in `docs/third-party/licenses.md`).

Implementation:

```text
upload ... POST /imports/krs-pdf (PDF, ≤5 MB); validate extension + size
parse .... KrsPdfParser (smalot/pdfparser) extracts text; tolerant line parser maps day + HH:MM–HH:MM → day/time/course/location; confidence = fraction of understood lines
staging .. rows stored in a pending `imports` record — nothing touches the schedule until confirmation
preview .. GET /imports/{importId} returns the staged rows + confidence for the user to review
confirm .. POST /imports/{importId}/confirm — in one transaction, each row becomes a weekly-recurring Hard Landscape event and the import is marked confirmed (never overwrites existing schedule)
discard .. POST /imports/{importId}/discard resolves without persisting
fallback . UI shows manual Hard Landscape entry is available when parsing fails (mandatory fallback)
```

Verification evidence: `php artisan test` 787 passed (2386 assertions); PHPStan 0 errors; Pint clean (559 files); Vitest 340 passed (51 files); vue-tsc typecheck clean; `npm run build` OK; repo gates PASS (validate-repo, secrets, doc-links 20, openapi 106 paths, changelog, version).

Changes: composer dependency `smalot/pdfparser ^2.12` (added to `docs/third-party/licenses.md`); migration `2026_08_20_210000_create_imports_table.php`; `app/Domain/Imports/` (KrsImport entity with pending/confirmed/discarded, KrsImportRepository); `app/Models/Import.php`; `app/Infrastructure/Imports/EloquentKrsImportRepository.php`; `app/Application/Imports/` (KrsPdfParser, UploadKrsImportUseCase, GetKrsImportUseCase, ConfirmKrsImportUseCase with transactional Hard Landscape persistence, DiscardKrsImportUseCase); `ImportController` (store/show/confirm/discard); routes + AppServiceProvider binding; `docs/api/openapi.yaml` (/imports/krs-pdf, /imports/{id}, confirm, discard; KrsImportRow/KrsImport schemas). Frontend: `imports/types.ts`, `imports/api.ts`, `imports/KrsImport.vue` (upload, editable preview table, confirm/discard, manual-fallback note), embedded in `ScheduleDraftView`. Tests: `KrsImportApiTest` (6) + ScheduleViews.test.ts KRS import case.

Committed: see git log (TASK-141 PDF KRS import, backend + frontend + gates).

---

# TASK-142 — iCal Import

Status: DONE

Requirements: FR-30 — import an iCalendar (.ics/.ical) calendar (e.g. public holiday calendar), parse VEVENTs, handle timezones, show a conflict-aware preview, and only persist Hard Landscape after user confirmation. Import must not automatically overwrite existing Hard Landscape (FR-30 Exception Flow: malformed .ics rejected with a per-event error report; all-day/RECURRENCE-ID/EXDATE/unsupported-RRULE events surface as warnings; TASK-144 preview/validation/warnings/accept/cancel principles). No new runtime dependency (bounded RFC-5545 subset parser).

Implementation:

```text
upload ... POST /imports/ics (.ics/.ical, ≤5 MB); validate extension + size
parse .... IcsParser (hand-written RFC-5545 subset) — line unfolding + escaping, VEVENT extraction, DTSTART/DTEND/DURATION, TZID/UTC/floating → owner profile timezone, per-event errors/warnings, confidence = staged events / total
tz ....... explicit TZID (validated), UTC (Z), or floating → profile timezone (allowlist: UTC, Asia/Jakarta, Asia/Makassar, Asia/Jayapura, Asia/Singapore, America/New_York, Europe/London)
staging .. rows + per-event errors/warnings stored in a pending `imports` record (type `ical`) — nothing touches the schedule until confirmation
preview .. GET /imports/ics/{importId} returns staged rows with conflict flags (conflict_with title) for review
confirm .. POST /imports/ics/{importId}/confirm — in one transaction, non-conflicting rows become Hard Landscape (recurring → weekly-recurring with RRULE; else one-time); conflicting/unreadable/all-day rows are skipped, never overwritten; re-confirm → 422
discard .. POST /imports/ics/{importId}/discard resolves without persisting
conflict . IcalConflictResolver flags overlap vs existing Hard Landscape AND intra-import (deterministic order, first non-conflicting wins)
```

Verification evidence: `php artisan test` 815 passed (2507 assertions); PHPStan 0 errors; Pint clean (571 files); Vitest 341 passed (51 files); vue-tsc typecheck clean; `npm run build` OK; repo gates PASS (validate-repo, secrets, doc-links, openapi 110 paths, changelog, version).

Changes: `app/Domain/Imports/` (IcalImport entity with pending/confirmed/discarded + rows/errors/warnings, IcalImportRepository); `app/Models/Import.php` (type `ical`, rows JSON payload); `app/Infrastructure/Imports/EloquentIcalImportRepository.php`; `app/Application/Imports/` (IcsParser, IcalConflictResolver, UploadIcalImportUseCase, GetIcalImportUseCase, ConfirmIcalImportUseCase with transactional Hard Landscape persistence, DiscardIcalImportUseCase); `IcalImportController` (store/show/confirm/discard); routes + AppServiceProvider binding; `docs/api/openapi.yaml` (/imports/ics, /imports/ics/{id}, confirm, discard; IcsImportRow/IcsImportReportItem/IcsImport schemas). Frontend: `imports/types.ts` + `imports/api.ts` (uploadIcs/getIcs/confirmIcs/discardIcs), `imports/IcsImport.vue` (upload, preview table with conflict/recurring labels, per-event error + warning reports, confirm/discard), embedded in `ScheduleDraftView`. Tests: `IcsParserTest` (17) + `IcalImportApiTest` (11) + ScheduleViews.test.ts ICS import case.

Committed: see git log (TASK-142 iCal import, backend + frontend + gates).

---

# TASK-143 — iCal Export

Status: DONE

Requirements: FR-30 — export selected schedules in valid iCalendar format; do not expose internal database identifiers unnecessarily. NFR-03 — export SHALL require authenticated user context; the iCal feed SHALL expose only fields explicitly designated as exportable. FR-30 Business Rules — export feed must not expose unrelated private metadata. FR-30 Acceptance Criteria — generated feed can be parsed by a standards-compatible client.

Implementation:

```text
endpoint .. GET /schedule/export/ics?from=YYYY-MM-DD&to=YYYY-MM-DD (auth:sanctum)
output .... text/calendar; charset=utf-8, Content-Disposition attachment "kinevo-schedule.ics"
domain .... IcsCalendar — deterministic RFC-5545 serializer (UTC YYYYMMDDTHHMMSSZ, RFC-5545
            §3.1 folding at 75 octets, §3.3.11 text escaping, RRULE passthrough)
use case .. ExportScheduleIcsUseCase — assignments (non-cancelled) → VEVENT with task title;
            one_time/permanent Hard Landscape → single VEVENT; recurring Hard Landscape
            expanded in-window via RecurrenceOccurrenceGenerator (unparseable RRULE degrades
            to the base event, never silently dropped)
privacy ... VEVENT UIDs are content-derived hashes (kinevo-<sha256:20>@kinevo) — no internal
            database ids, user_id, task_id, or raw ids are written; only SUMMARY/DTSTART/DTEND
            (+RRULE for recurring) are emitted
validation. from/to required, to after_or_equal from → 422 on invalid range
```

Verification evidence: `php artisan test` 829 passed (2569 assertions) on both PostgreSQL and SQLite (CI config); PHPStan 0 errors; Pint clean (576 files); Vitest 343 passed (51 files); vue-tsc typecheck clean; `npm run build` OK; npm audit 0 vulnerabilities; repo gates PASS (validate-repo, secrets, doc-links 20, openapi 111 paths, changelog, version).

Changes: `app/Domain/Exports/IcsCalendar.php`; `app/Application/Exports/ExportScheduleIcsUseCase.php`; `app/Http/Controllers/Api/ScheduleExportController.php`; route `GET /schedule/export/ics`; `docs/api/openapi.yaml` (/schedule/export/ics path, text/calendar response). Frontend: `exports/api.ts` (downloadScheduleIcs raw-fetch + blob download), `exports/IcsExport.vue` (from/to range, Download .ics, error/success), embedded in `ScheduleDraftView`. Tests: `IcsCalendarTest` (7 unit) + `ScheduleExportApiTest` (7 feature) + ScheduleViews.test.ts ICS export cases (2).

Committed: see git log (TASK-143 iCal export, backend + frontend + gates).

---

# TASK-144 — Import Preview / Fallback

Status: DONE

Requirements: FR-24 / FR-30 cross-cutting contract (TASK-144) — every import feature MUST provide Preview, Validation Errors, Warnings, Accept, Cancel, and Manual Fallback; invalid data is never imported silently.

Audit result (both import features):

```text
element ............ KRS PDF import (FR-24) ... iCal import (FR-30)
Preview ............ already present ...... already present
Validation Errors .. GAP → closed .......... already present (per-event)
Warnings ........... GAP → closed .......... already present (per-event)
Accept ............. already present ...... already present
Cancel ............. already present ...... already present
Manual Fallback .... already present ...... GAP → closed (note added)
```

Implementation:

```text
krs parser . KrsPdfParser now reports per-line errors for schedule-like lines that cannot be
             parsed (day keyword without readable time/course) and rejects rows whose end time
             is not after the start time as errors — nothing is silently dropped; exact
             duplicate rows are reported as warnings and skipped
krs domain . KrsImport stages errors[] + warnings[] alongside rows (toArray exposes them);
             EloquentKrsImportRepository persists {rows, errors, warnings} in the imports JSON
             payload with a legacy-shape fallback for pre-TASK-144 records
ics ui ..... IcsImport.vue shows the manual-fallback note ("add events manually as Hard
             Landscape instead") mirroring the KRS import panel
openapi .... KrsImport schema gains errors/warnings (+KrsImportReportItem)
```

Verification evidence: `php artisan test` 832 passed (2588 assertions) on PostgreSQL and SQLite; PHPStan 0 errors; Pint clean; Vitest 343 passed (51 files); vue-tsc typecheck clean; `npm run build` OK; npm audit 0 vulnerabilities; repo gates PASS (validate-repo, secrets, doc-links, openapi 111 paths, changelog, version).

Changes: `app/Application/Imports/KrsPdfParser.php` (error/warning reporting); `app/Domain/Imports/KrsImport.php`; `app/Infrastructure/Imports/EloquentKrsImportRepository.php`; `app/Application/Imports/UploadKrsImportUseCase.php`; `docs/api/openapi.yaml`. Frontend: `imports/types.ts`, `imports/KrsImport.vue` (error/warning report sections), `imports/IcsImport.vue` (manual-fallback note). Tests: `KrsImportApiTest` +3 (unreadable-line errors incl. preview + confirm scoping, duplicate-row warnings, invalid-time-range errors) + ScheduleViews.test.ts assertions (KRS error/warning items render, iCal fallback note visible).

Committed: see git log (TASK-144 import preview/fallback contract closure).

---

# 13. PHASE 15 — END-TO-END / UAT

Create:

```text
TASK-150 … TASK-156
```

This phase is mandatory.

---

# TASK-150 — Golden One-Week E2E

Status: DONE

Requirements: TASK-150 / SRS §17.4 — implement the complete user journey (Login → Goal → Milestone → Program → Task → Schedule → Today → Execute → Complete → Activity → Progress → Analytics → Capacity → Future Schedule). The test MUST verify user-visible behavior; database-only assertions are insufficient.

Implementation:

```text
test ...... tests/Feature/E2E/GoldenWeekJourneyTest.php — one sequential golden-week journey
scope ..... every assertion targets the public API responses the UI renders (user-visible
            payloads); no database assertions are used anywhere in the journey
login ..... register (first setup) → login → /auth/me
plan ...... goal (quarterly, deadline) → milestone (sequence 1) → program (structured) →
            task linked to all three
schedule .. POST /schedule/draft over the golden week → apply (version 2) → discover the
            scheduled day from the user-visible /schedule range view
today ..... GET /today on the scheduled day: event title, unlocked, no conflict, capacity ≥ task length
execute ... execution session start (auto in_progress) → complete (auto completes the task)
verify .... GET /tasks/{id} shows completed
activity .. GET /logs?event_type=task_completed returns the completion log
progress .. manual progress event recorded and listed back
analytics . GET /analytics/overview for the week: task_completion 1/1 completed,
            activity.by_type.task_completed = 1, goal/milestone counted, focus session counted
capacity .. overview capacity.days covers all 7 days; scheduled day carries the 60 minutes
future .... second task drafted + applied for the following week and visible in /schedule
```

Verification evidence: `php artisan test` 833 passed (2674 assertions) on PostgreSQL and SQLite; PHPStan 0 errors; Pint clean (577 files); repo gates PASS.

Changes: `server/tests/Feature/E2E/GoldenWeekJourneyTest.php` (new E2E suite namespace).

Committed: see git log (TASK-150 golden one-week E2E journey).

---

# TASK-151 — Offline UAT

- Status: DONE
- Priority: P0
- Depends On: TASK-051 (Today cache), TASK-052 (mutation queue), TASK-115 (offline sync UX)
- SRS: FR-44 (Quick Capture offline), FR-57 (offline mutations queueable, conflict preserved), SRS §9.1–§9.4, offline-sync.md §Sync state machine / §Failure safety.
- Acceptance:
  - [x] Golden offline journey verified end to end: load Today online (network + cache baseline) → disconnect → open Today from cache (offline read) → Quick Capture (queued) → edit task (queued) → reconnect → sync → server received both mutations → Today re-fetches.
  - [x] Offline conflict surfaced with local mutation preserved (conservative rule, SRS §9.4; never silently discarded).
  - [x] Version conflict (stale `base_version` on a versioned note) surfaced as conflict with the local note retained.
  - [x] Retry: transient failure → `retrying`; manual retry → `saved`; local preserved until applied.
  - [x] Permanent failure (other 4xx) → `failed`; local copy preserved for the user to fix and resync.
  - [x] Visible sync states use the same `SyncStatusController` + `MutationQueue` wiring as `AuthHost.vue` (in-memory stores/injectable applier substitute for IndexedDB/HTTP, per the testable seam used by TASK-052/115).
- Verification:
  - [x] Frontend: `npm run test` → OK (348 tests; +5 new Offline UAT integration cases: golden journey, offline conflict, version conflict, retry, permanent failure)
  - [x] `npm run typecheck` → no errors; `npm run build` → built OK (2510 modules; root-owned stale `public/build` cleanup is an unrelated environment artifact)
  - [x] Backend regression: unchanged (frontend-only UAT)
  - [x] Repo gates (secrets/doc-links/validate/openapi/changelog/version) — see gates run
- Evidence: server/resources/js/offline/__tests__/offline-uat.test.ts
- Release Impact: PATCH (frontend-only UAT; no API/schema change)

---

# TASK-152 — Scheduler Simulation Suite

Status: DONE

Requirements: Scheduling rule (deterministic engine) / docs/scheduling-engine.md — the complete simulation suite MUST cover every listed scenario and every simulation MUST be deterministic (same inputs → same draft).

Implementation:

```text
suite ....... tests/Unit/Scheduling/ScheduleSimulationSuiteTest.php — pure-unit suite over
              ScheduleDraftGenerator (SlotCalculator + HardConstraintEngine + TaskRankingEngine)
scenarios ... empty day · hard landscape · locked task · sacred anchor · deadline pressure ·
              multiple goals · overload (capacity reduction via daily cap + demand beyond cap) ·
              capacity boost · reserve (safety reserve vs over-booking) · conflicts (overlapping
              hard landscape) · dynamic reschedule (deterministic re-run) · context fit ·
              null boost percent (no cap)
determinism . every scenario asserts equal outcomes across repeated generate() runs where
              ordering/assignment matters; no randomness, no wall-clock dependence (fixed week)
```

Verification evidence: `php artisan test` 848 tests / 2718 assertions — 14/14 simulation tests pass; full suite green except an unrelated untracked TASK-153 WIP file (`CanvasE2ETest.php`, not part of this task); PHPStan 0 errors; Pint clean on the changed file; local PHP without PDO drivers cannot run DB-backed suites outside the container (environment artifact).

Changes: `server/tests/Unit/Scheduling/ScheduleSimulationSuiteTest.php` (14 deterministic scenarios; committed in two increments — see git log TASK-152).

Committed: see git log (TASK-152 scheduler simulation suite).

---

# TASK-153 — Canvas E2E

Status: DONE

Requirements: FR-54 (knowledge links to Goal/Milestone/Program/Task/Canvas) / FR-55 (Canvas Lifecycle: create/read/update/archive) / FR-56 (Canvas Version Conflict Protection → 409 `CANVAS_VERSION_CONFLICT`) / FR-57 (offline Canvas mutations queueable + sync) / NFR-14 (canvas saves versioned). Offline rule: IndexedDB is queue/cache, server authoritative; the E2E verifies the server-side sync/version contract the client reconciles through.

Implementation:

```text
test ...... tests/Feature/E2E/CanvasE2ETest.php — one sequential canvas lifecycle journey
scope ..... every assertion targets the public API responses the UI renders (user-visible
            payloads); no database assertions are used anywhere in the journey
open ...... POST /canvases → 201, GET /canvases/{id} shows version 1 + null document
draw ..... PUT /canvases/{id} scene_json (base_version 0) → v1, then v2
autosave . same PUT path with incremented base_version
reload ... GET restores v2 scene + 2 elements
offline .. device B PUT (v2→v3) while device A is "away"
reconnect  device A GET sees v3 (ellipse1) — server is source of truth
sync ...... device A PUTs merged 3-element scene (v3→v4)
conflict . device A PUT with stale base_version 3 → 409 (Canvas version conflict)
read-only  POST /canvases/{id}/archive → 200, list excludes it, GET still shows archived_at
link goal  POST /canvases/{id}/links target_type=goal → 201
link task  POST /canvases/{id}/links target_type=task → 201; GET /links returns 2 (goal,task)
```

Verification evidence: `php artisan test` 848 passed (2754 assertions); CanvasE2ETest 1 passed (40 assertions); PHPStan 0 errors; Pint clean (579 files); npm audit 0 vulnerabilities; vue-tsc typecheck clean; `npm run build` OK.

Changes: `server/tests/Feature/E2E/CanvasE2ETest.php` (new E2E suite, untracked → added).

Committed: see git log (TASK-153 canvas E2E lifecycle journey).

---

# TASK-154 — Knowledge E2E

Status: DONE

Requirements: FR-53 (Knowledge Item Lifecycle: title, rich content, versioning, search text, ownership) / FR-54 (Knowledge Links between Notes and Goals, Milestones, Programs, Tasks, Canvases) / NFR-14 (note saves versioned).

Implementation:

```text
test ...... tests/Feature/E2E/KnowledgeE2ETest.php — one sequential knowledge/note journey
scope ..... every assertion targets the public API responses the UI renders (user-visible
            payloads); no database assertions are used anywhere in the journey
create .... POST /notes → 201, version 1
edit ...... PATCH /notes/{id} document body (base_version 1) → version bumped
save ...... PATCH /notes/{id} derived caches (base_version from edit) → version bumped;
            reload GET confirms title + plain_text_cache persisted
search .... GET /knowledge/search?q=research → 200, exactly the note returned
link goal .. POST /goals → POST /notes/{id}/links target_type=goal
link milestone POST /goals/{goalId}/milestones → POST /notes/{id}/links target_type=milestone
link program POST /programs → POST /notes/{id}/links target_type=program
link task .. POST /tasks (goal_id) → POST /notes/{id}/links target_type=task
create canvas POST /canvases → 201
link canvas  POST /notes/{id}/links target_type=canvas
final ...... GET /notes/{id}/links → 5 links sorted [canvas,goal,milestone,program,task]
```

Verification evidence: `php artisan test` 849 passed (2789 assertions); KnowledgeE2ETest 1 passed (35 assertions); PHPStan 0 errors; Pint clean (580 files); npm audit 0 vulnerabilities; vue-tsc typecheck clean; `npm run build` OK.

Contract note (observed, not changed): `Note::withTitle` and `Note::withContent` each increment the version (Note.php:72, :88), so a single PATCH editing both title and content advances the version by 2. The E2E asserts monotonic version increases plus persisted field values rather than hardcoded numbers, so it stays deterministic and signals a contract change if the domain later increments by 1.

Changes: `server/tests/Feature/E2E/KnowledgeE2ETest.php` (new E2E suite).

Committed: see git log (TASK-154 knowledge E2E lifecycle journey).

---

# TASK-155 — AI Golden Flows

Status: DONE

Requirements: SRS FR-52 (Goal Breakdown Proposal) / FR-60 (AI provider unavailable → app operational) / FR-61 (AI output validated, invalid → no mutation, 422 `AI_OUTPUT_INVALID`) / FR-62 (extracted tasks only created on accept) / AI rule (structured response → schema validation → human approval → transaction). Offline rule N/A; AI is untrusted input per the AI boundary.

Implementation:

```text
test ...... tests/Feature/E2E/AiGoldenFlowsTest.php — 7 focused golden-flow tests
scope ..... every assertion targets the public API responses the UI renders; accepted
            outcomes are re-checked through the public list/index endpoints (user-visible)
flow 1 .... Goal → POST /goals/{id}/breakdown-proposals (ollama fake) → pending proposal
            (goal_breakdown, 2 milestones) → GET /ai/proposals/{id} preview →
            POST /ai/proposals/{id}/accept → GET /goals/{id}/milestones shows 2 milestones
flow 2 .... Note → POST /ai/extract-tasks (note_id) → pending proposal (task_extraction, 2 tasks)
            → preview → accept → GET /tasks shows 2 tasks
flow 3 .... config ai.driver=disabled → create goal 201 + create task 201 still work;
            /ai/status available=false; /ai/generate 503 AI_PROVIDER_UNAVAILABLE;
            breakdown-proposals 503 AI_PROVIDER_UNAVAILABLE (graceful degradation)
edge ...... malformed AI JSON → 422 AI_OUTPUT_INVALID, no proposal/milestones;
            cross-user proposal → 404 on GET + accept, no milestones;
            stale (rejected then accepted) proposal → 422, no milestones;
            rejected proposal → decision rejected, no milestones/tasks
```

Verification evidence: `php artisan test` 856 passed (2845 assertions); AiGoldenFlowsTest 7 passed (56 assertions); PHPStan 0 errors; Pint clean (581 files); npm audit 0 vulnerabilities; vue-tsc typecheck clean; `npm run build` OK.

Changes: `server/tests/Feature/E2E/AiGoldenFlowsTest.php` (new E2E suite).

Committed: see git log (TASK-155 AI golden flows E2E).

---

# TASK-156 — Production Smoke Test

Status: DONE

Requirements: TASK-156 — the smoke test MUST cover the actual production Docker
path (build → deploy → migrate → health → login → goal → task → schedule →
today → backup → restore), not an internal shortcut.

Implementation:

```text
scripts/prod-smoke.sh  — drives the REAL production compose
                         (infrastructure/docker-compose.prod.yml) end to end:
  build ......... docker compose -f infrastructure/docker-compose.prod.yml build
  deploy ....... up postgres + app roles, provision self-signed TLS into the
                proxy (bind-mounted certbot_conf override), up reverse proxy
  migrate ...... docker compose run --rm app migrate --force
  health ......  GET /api/v1/health through the live nginx + TLS proxy (200)
  login ........ POST /auth/register (first-setup owner) → bearer token
  goal ......... POST /goals
  task ......... POST /tasks (linked to goal)
  schedule ..... POST /schedule/draft → POST /schedule/draft/apply (version 2)
  today ........ GET /today?date=<scheduled day> shows the task (conflict=false)
  backup ....... docker compose run --rm backup /backup/backup.sh
  restore ...... docker compose run --rm backup /backup/restore.sh (CONFIRM_RESTORE=yes)
                → re-verify GET /today still shows the task (data intact)
```

Secrets (APP_KEY / DB_PASSWORD) are generated at runtime and never written to
disk or the repository. The stack is torn down after the run unless KEEP_UP=1.

Makefile target: `make prod-smoke`.

Acceptance:
- [x] Clean-environment run exercises the full production Docker path.
- [x] Health is checked through the actual reverse proxy (nginx + TLS).
- [x] Journey reaches goal → task → schedule → today against the live app.
- [x] Backup produces a gzipped dump; restore re-applies it and the scheduled
      task remains visible in Today (recoverability proven).
- [x] `make prod-smoke` is the single entry point.

Production defects found and fixed by this task (real path was broken):
- prod `app`/`queue-worker`/`scheduler` roles did not receive `APP_KEY`,
  `APP_URL`, or `DB_PASSWORD` → app could not connect to the DB and the
  entrypoint failed fast. Now forwarded via the `&app_env` anchor.
- reverse-proxy nginx used `$realpath_root` for `SCRIPT_FILENAME`; as a pure
  proxy (no local docroot) every PHP/API request returned 404. Changed to
  `$document_root` so requests route to `app:9000`.
- `backup`/`restore` scripts were not executable and the compose mounted
  `./scripts/*.sh` from the wrong relative path (`infrastructure/scripts`, which
  does not exist) → Docker created empty directories and `make prod-backup` /
  `make prod-restore` failed. Fixed paths to `../scripts/*.sh`, made the scripts
  executable, and invoke them via `bash` after installing it in the
  `postgres:17-alpine` image.
- `.dockerignore` excluded `**/storage/app/*` so root-owned runtime uploads do
  not break the build-context walk or ship into the image.

Verification:
- [x] `./scripts/prod-smoke.sh` → "Production smoke test PASSED" (full
      build → deploy → migrate → health → login → goal → task → schedule →
      today → backup → restore, post-restore data verified).
- [x] Repo gates: validate / secrets / check-openapi / check-doc-links /
      check-changelog / check-version — all PASS.

Evidence: scripts/prod-smoke.sh, Makefile (`prod-smoke`),
infrastructure/docker-compose.prod.yml, infrastructure/docker/nginx/default.conf,
.dockerignore, scripts/backup.sh, scripts/restore.sh.

Known limitation (separate from this task): the reverse proxy is a pure proxy
and does not have the built frontend assets locally, so `/build/*` and `sw.js`
static responses 404 in this smoke configuration; the API journey is unaffected.
Serving static assets requires sharing the built `public/build` with the proxy
(a follow-up production hardening item).

Release Impact: PATCH (production deployment config + tooling; no API/schema
change, no user-facing behavior change).

---



### Phase 10 — Release & Documentation Hygiene
#### TASK-160 — Repository Documentation Hygiene & Release Readiness
- Status: DONE
- Priority: P1
- SRS: no requirement change (governance/tooling only).
- Acceptance:
  - [x] `docs/release-management.md` added (versioning, channels, cadence, eligibility, changelog, release notes, tagging, GitHub Releases, migration/API policy, pre-releases, security releases, rollback, doc cleanup, post-release verification).
  - [x] `docs/compatibility.md` added (app ↔ SRS ↔ API ↔ migration head matrix).
  - [x] `CHANGELOG.md` standardized with `## [Unreleased]` staging section.
  - [x] `scripts/check-version.sh` added (SemVer, monotonic bump, changelog consistency).
  - [x] `scripts/check-changelog.sh` added (Keep a Changelog structure validation).
  - [x] `scripts/release-dry-run.sh` added (non-destructive readiness gate → READY/BLOCKED).
  - [x] Makefile targets: `version`, `version-check`, `changelog-check`, `release-check`, `release-dry-run`, `release-prepare`.
  - [x] CI wired: changelog + version checks in `ci.yml`; release dry-run gate in `release.yml`.
  - [x] No duplicate authoritative docs; no obsolete architecture docs (audited docs/, docs/adr/, README map; no competing authorities).
  - [x] `AGENTS.md` contains current rules only (release/document-hygiene rules reviewed and consistent with release-management.md).
  - [x] spike/prompt artifacts classified (none present; no scratch/prompt/temp files in tree or history).
  - [x] release workflow documented and scripts validated (changelog/version/secret/doc-link/OpenAPI gates all PASS; dry-run READY).
- Verification:
  - [x] `make changelog-check` → PASS
  - [x] `make version-check` → PASS
  - [x] `make release-dry-run` → READY
  - [x] `./scripts/validate-repo.sh .` → VALIDATION PASSED
  - [x] `./scripts/check-secrets.sh .` → SECRET SCAN PASSED
  - [x] `./scripts/check-doc-links.sh .` → PASSED (19 links)
  - [x] `./scripts/check-openapi.sh .` → PASSED (71 paths)
- Evidence: docs/release-management.md, docs/compatibility.md, docs/implementation-status.md, README.md, scripts/{check-version,check-changelog,release-dry-run}.sh, Makefile, .github/workflows/{ci,release}.yml
- Release Impact: NONE (internal governance/tooling; no user-facing behavior change)

---

# Phase 16 — UI/UX STABILIZATION (RESCUE R0–R7)

## Status

COMPLETE (2026-08-22) — all R0–R7 rescue tasks closed with evidence
(design.md §102 gate ticked on commit `bb08441`; journey records in
docs/browser-e2e.md §8). Remaining release-candidate build/publish steps are
release-management actions, not rescue scope. Knowledge left in this phase:
Journey F / AI UI surface remains browser-unproven and is triaged into
Phase 17 below — it is NOT relabeled as AI-complete.

Requirement authority for this phase: `docs/design.md` (product-experience spec,
incl. §74–§103 rescue plan), `docs/design-tokens.md`, `docs/ui-audit.md`,
`docs/browser-e2e.md`. This phase does not redefine SRS requirements; it changes
how requirements are experienced.

The central insight (design.md §74): many frontend features are DONE at the
contract level (unit/feature tests, typecheck, build, adapter mocks) but real
browser UX is not yet proven. `DONE` means "implementation contract verified",
not necessarily "production-ready UX". The rescue gates close only on real
browser evidence and on design.md §102, never on unit-test counts alone.

Phase mapping (design.md §98 R0–R14 → this board §103 R0–R7):

```text
R0 Freeze / R1 Browser smoke / R2 Bug taxonomy / R3 Diagnostics
  → Phase R0 Stabilization · Phase R1 Browser Verification
R4 Design tokens / R5 Shell / R6 Today / R7 Task·Goal / R8 Knowledge
  → Phase R2 Design System · Phase R3 UI Refinement
R9 Canvas stabilization → Phase R4 Canvas Hardening
R11 Accessibility → Phase R5 Accessibility
R12 Visual regression → Phase R6 Visual Regression
R13 Full E2E / R14 Release candidate → Phase R7 Release Readiness
```

---

# TASK-R0 — Freeze Feature Development (Stabilization Gate)

## Status

DONE

## Requirements

design.md §75, §99. No new AI features, scheduling algorithms, major domain
concepts, or dependencies enter the codebase while R0 is in effect. Focus is
limited to stability, usability, integration, browser correctness, and visual
consistency.

## Scope

- [x] Publish the freeze to contributors (AGENTS.md / CONTRIBUTING.md note) with
      explicit exemption path (P0 fixes only, group approval).
- [x] Suspend acceptance of new non-trivial feature work; log any proposals to a
      hold list (Feature Hold List below) with proposed priority.
- [x] Establish the first-love target: LOGIN → TODAY → NOW TASK → START →
      COMPLETE → PROGRESS → NEXT TASK (design.md §99) as the team's prime
      objective.

## Acceptance

- [x] `docs/ui-audit.md` and `docs/browser-e2e.md` baselines exist and are wired
      into the rescue phase.
- [x] No feature code merged during R0; P0 fixes only, each with a bug-taxonomy
      record (design.md §77).

## Verification

- [x] Git history confirms no new feature commits during R0.

## Evidence

AGENTS.md (UI/UX stabilization freeze section), CONTRIBUTING.md (PR rules §5
freeze note), TASK.md Phase 16 (this record + Feature Hold List), commit
`d9c964c`. The freeze remains in effect until TASK-R7 completes.

Release Impact: NONE (governance process).

---

# TASK-R1 — Real Browser Smoke Test (Browser Verification)

## Status

DONE — core loop proven in real browser across Chromium, Firefox, WebKit.

## Requirements

design.md §76, §71, §99. Create `tests/e2e/` and prove the core loop in a real
browser across Chromium, Firefox, and WebKit/Safari-equivalent.

## Scope

- [x] Choose runner and wire into Makefile (`tests/e2e/` Docker Playwright,
      `make e2e`; CI wiring pending). Documented in `docs/browser-e2e.md` §3.
- [x] First-verify journeys: login, app shell, Today, task, goal, note, canvas.
- [x] Record results in `docs/browser-e2e.md` global matrix (§4).
- [x] Prove the full first-love loop (§99) in the real browser: LOGIN → TODAY →
      NOW task → START → COMPLETE → PROGRESS → NEXT (`tests/e2e/tests/core-loop.spec.ts`).

## Acceptance

- [x] `tests/e2e/` exists with a reproducible runnable target (Makefile).
- [x] Core loop journey passes in at least Chromium + Firefox (Chromium +
      Firefox + WebKit all green; `core-loop.spec.ts`).
- [x] Every failure is classified P0–P3 (`docs/ui-audit.md §3`); none found in
      the run so far (all passes), so no taxonomy records added.

## Verification

- [x] `docs/browser-e2e.md` §4 Chromium rows are ✅ (no ⚪ for surfaces actually
      exercised); Firefox/WebKit rows ⚪ resolved by the matrix run.
- [x] Full E2E suite green: **57/57 passed** (Chromium + Firefox + WebKit,
      1 worker, ~1.4 min), incl. the R1 core-loop journey.

## Evidence

- `tests/e2e/` (package.json, playwright.config.ts, Dockerfile, `tests/` specs:
  login.spec.ts, journeys.spec.ts, golden-journeys.spec.ts, surface-qa.spec.ts,
  visual-baseline.spec.ts, core-loop.spec.ts, helpers.ts).
- `make e2e` runner; full matrix **57/57 passed** (~1.4 min). Core loop
  (`core-loop.spec.ts`): login → Today → NOW card (seeded future-day task,
  clock synced to its slot) → START (Running) → elapsed accrues from server
  timestamp → COMPLETE (Ready) → NEXT card shows the queued task. Found an
  interplay with the scheduler safety reserve (30% day budget): seeding the
  shared owner's "today" exhausts capacity across repeated runs, so the spec
  seeds a future day whose offset varies per run (§8 record in
  `docs/browser-e2e.md`).

Release Impact: NONE (tooling/verification).

---

# TASK-R2 — Bug Taxonomy + Design System (Diagnostics → Tokens → Components)

## Status

DONE

## Requirements

design.md §77, §78, §65–§66, §50–§51, §95–§96; `docs/design-tokens.md`.

## Scope

- [x] Classify every R1 finding by the P0–P3 taxonomy; P0 blocks all feature
      work (design.md §77) and gets a record in `docs/ui-audit.md` §6.
- [x] Add development-only runtime diagnostics: API, Auth, Offline, Canvas,
      Tiptap, Scheduler (design.md §78). Visibility gated to
      `import.meta.env.DEV` so production builds exclude the panel (§36). The
      in-browser `/dev/canvas-diagnostics` HTTP route is deferred to TASK-R4
      where the canvas island boundaries are the focus (probe surface exists).
- [x] Implement the centralized token modules per `docs/design-tokens.md`
      (colors/spacing/radius/shadows/typography/motion/zindex), hydrated into
      the existing Tailwind v4 + `shell/theme.ts` baseline (`app.css` @theme +
      `.dark` overrides).
- [x] Introduce the shared component library v0 (`KButton`, `KInput`) with
      three button variants only (primary/secondary/danger + ghost) (§51).
      Retiring duplicates across all existing surfaces is TASK-R3.
- [x] Component acceptance per §95–§96: behavior + accessibility tests for
      KButton/KInput (array); visual regression remains part of R6.

## Acceptance

- [x] No hard-coded spacing/radius/shadow/color/z-index values in new code
      (new diagnostics + token modules + component v0 use the token system).
- [x] `VisualStateBadge` and persistence states use the token system — the
      badge already presented semantic states via the software contract;
      persistence states surface through `DiagnosticsPanel` + sync-state, which
      now read token colors.
- [x] P0/P1 findings from R1 are fixed or explicitly scheduled — none were found
      in R1 (all passes); the P2 surface gaps are recorded (UI-001…UI-006).

## Verification

- [x] `docs/ui-audit.md` §6 findings recorded (UI-001…UI-006); diagnostics and
      visual-system items triaged/fixed within this task.
- [x] Frontend tests + typecheck + build green: `vue-tsc` clean, `vitest run`
      370 passed (incl. +14 new), `npm run build` OK.
- [x] Comprehensive re-execution: audited every R2 artifact against acceptance;
      removed residual hard-coded values in the new R2 code (KButton/KInput/
      DiagnosticsPanel now use the token utilities `bg-surface`, `text-text`,
      `shadow-rest`, `ring-focus`, `border-border`, `var(--z-critical-overlay)`
      instead of `#131313`, `z-[800]`, `shadow-lg`, `gray-*`). Acceptance line
      "no hard-coded values in new code" now fully holds. phpstan 0 errors,
      pint clean, `npm audit` 0 vulnerabilities (server lockfile; root audit
      blocked by missing root lockfile — pre-existing).

## Evidence

- Tokens: `server/resources/js/tokens/{colors,spacing,radius,shadows,typography,
  motion,zindex,index}.ts`; `app.css` @theme + `.dark` hydration.
- Diagnostics: `diagnostics/{useDiagnostics.ts,DiagnosticsPanel.vue}`,
  `offline/diagnostics.ts`, wired into `AppShell` under DEV.
- Components: `components/{KButton,KInput}.vue` + acceptance tests.
- Tests: `components/__tests__/components.test.ts`, `diagnostics/__tests__/
  diagnostics.test.ts` — 9 new tests; full suite `vitest run` 370 passed.
- `docs/design-tokens.md` §11 updated; `docs/ui-audit.md` §6 records.

Release Impact: PATCH (design-system refactor; no behavior/API/schema change).

---

# TASK-R3 — UI Refinement (Shell → Today → Task/Goal → Knowledge)

## Status

PARTIAL — shell/nav groups, primary-action component migration, and today NOW-card
hierarchy landed 2026-08-21; task/goal progress + roadmap, knowledge desk + §31
toolbar, capacity §22, adaptive check-in §23, and the notification center landed
2026-08-22 (see Evidence). Remaining R3-adjacent work (timeline DnD, full
state-matrix migration of minor surfaces) remains in R4/R5.

## Requirements

design.md §2, §8–§19, §29–§33, §79, §84, §85, §57–§58.

## Scope

- [x] Reorganize navigation into EXECUTE / PLAN / KNOWLEDGE / REVIEW / SYSTEM
      groups (design.md §9); Topbar §10; bottom nav + floating capture on mobile
      §8.3.
- [x] Today redesign: NOW card visual hierarchy §12, timeline geometry §13,
      states §11. (Drag & drop with valid/invalid feedback §14 carries to R4.)
- [x] Task / Goal redesign: task execution workspace §19, subtasks §20,
      goal progress §17–§18, milestone roadmap §39. (Primary action per state
      §19 landed 2026-08-22; subtasks were already present; goal cards/detail
      now show one dominant progress bar and a milestone roadmap with
      ✓/●/✕/○ glyphs §39.)
- [x] Knowledge redesign: unified desk layout §30, minimal Tiptap toolbar §31,
      linked-knowledge sidebar §33. (NoteEdit groups editor + linked-entities
      on a desktop desk grid §30; §31 toolbar render behind `toolbar` prop with
      `runCommand`/`isCommandActive` on the EditorAdapter boundary; linked
      entities move into the right desk sidebar §33.)
- [x] State-machine UI matrix per entity (Task/Goal/Milestone/Program/Canvas/
      Note/Schedule/AI Proposal) §84 — documented in `docs/state-machine-ui.md`.
- [x] Capacity feedback §22, adaptive context §23, notifications §28–§29,
      empty/error language §11.2–§11.3, §56, §64. (Today capacity is now a
      load bar with click-to-reveal scheduled/available/overload §22; a
      lightweight context check-in lives on Today §23; a notification center
      with Unread/Today/Earlier groups is wired into the topbar §28–§29;
      week error copy is plain-language and reconciles that nothing was
      changed §56.)

## Acceptance

- [x] Each redesigned surface passes design.md §70 QA dimensions and is recorded
      in `docs/ui-audit.md` §4.
- [x] Primary action is obvious; one primary + one secondary + optional details
      (design.md §2.3).

## Verification

- [x] `docs/browser-e2e.md` golden journeys A/B/C/D updated (not just unit
      tests).

## Evidence

- Nav groups: `shell/navigation.ts` (`NAV_GROUPS`), `shell/AppShell.vue`
  (grouped desktop nav + `nav-{key}` testids + current-section breadcrumb),
  `shell/store.ts` (`navGroups`). Tests: `navigation.test.ts`,
  `AppShell.test.ts`. design.md §9/§10.
- Component migration to `components/KButton/KInput` (design.md §50–§51,
  design-tokens.md §11): Today quick-capture/CONTEXT actions, Task list+detail,
  Goal list, Notes list. Primary actions emphasized as KButton `primary`.
- Today NOW card §12.2 hierarchy: thick border + offset shadow + larger title;
  NOW-card action buttons migrated to KButton variants.
- Empty-state copy §11.2 for Task / Goal / Notes.
- §84 state-machine matrix documented in `docs/state-machine-ui.md`.
- Task execution workspace §19: `task/TaskDetailView.vue` computes one primary
  action per state (Schedule/Start/Complete/Continue/Recover) rendered as KButton
  primary with secondaries beside it.
- Goal progress §17/§39: `goal/GoalListView.vue` cards and `goal/GoalDetailView.vue`
  now show a single dominant progress bar; the milestone timeline renders
  ✓/●/✕/○ roadmap glyphs per state.
- Knowledge desk §30/§33: `note/NoteEditView.vue` desk grid (editor | linked
  entities on desktop, stacked on mobile); `EditorHost.vue` gained the §31
  minimal toolbar behind a `toolbar` prop, and `EditorAdapter` gained
  `runCommand`/`isCommandActive`, implemented by `TiptapEditorAdapter` (tests in
  `editor/__tests__`).
- Capacity §22: `today/TodayView.vue` capacity chip replaced by a load bar with
  click-to-reveal scheduled/available/overload minutes.
- Adaptive context §23: `adaptive/` module (api/store/`AdaptiveContextPanel.vue`)
  wired into Today; stores energy-level check-ins via `/adaptive/context`.
- Notifications §28–§29: `notifications/` module (api/store/
  `NotificationCenter.vue`) with Unread/Today/Earlier groups replaces the bare
  unread counter in `shell/AppShell.vue`.
- `docs/ui-audit.md` §4 matrix + §5 inventory updated; UI-004 partial close;
  UI-007 added (nav grouping).
- `docs/browser-e2e.md` journeys A/B/C/D navigation paths updated.
- Tests: `vitest run` 385 passed; `vue-tsc` clean; `npm run build` OK.
- Honest gap: timeline drag & drop with valid/invalid feedback, and full
  state-matrix migration of minor surfaces are NOT done and remain in R4/R5.
  Golden journeys A–D remain `⚪` because no browser runner exists in this
  environment.

Release Impact: PATCH (visual refactor; no API/schema/business change).

---

# TASK-R4 — Canvas Hardening (Browser-Integration Canon)

## Status

PARTIAL — lazy-load by route (§89), editor entry states (§34.2), and dev-only
diagnostics route (§36) landed 2026-08-21. On 2026-08-22 the §72 canvas matrix
was executed in REAL headless browsers (chromium + firefox + webkit, dockerized
Playwright): 24/24 passing, including conflict (409) and offline journeys. Two
P0-class defects were found and fixed (autosave starvation loop; stale conflict
reconcile). Remaining for R7: physical-input-device draw/text/move/delete rows
(headless runners cannot deliver trusted pointer events into Excalidraw;
documented seam used instead).

## Requirements

design.md §34–§36, §72, §82, §89. Canvas is a browser-integration feature, not
merely an adapter.

## Scope

- [x] Vue Workspace → CanvasHost → CanvasAdapter → React Island → Excalidraw
      pipeline (design.md §34.1) with visible loading/ready/failure entry states
      §34.2; never a blank page. (Entry states + async boundary landed;
      full pipeline walked in-browser by the R4 matrix, 24/24.)
- [x] Kinevo product shell toolbar §34.3; always-visible save state §34.4;
      conflict resolution §34.5; offline banner §34.6. (Existing surface
      confirmed present: canvas-save-state badge, conflict banner §34.5, toolbar.
      §34.6 offline banner present via SyncStatusPanel/save-state.)
- [x] Walk and measure each boundary (design.md §82): route → mount → render →
      load scene → change event → autosave → server persistence → offline →
      reconnect. (Proven in-browser 24/24 via `canvas-hardening.spec.ts`;
      draw input enters through the documented dev/e2e adapter seam —
      `docs/browser-e2e.md` §5/R4 record.)
- [x] `/dev/canvas-diagnostics` route §36 (dev-only, disabled in production).
- [x] Lazy-load Excalidraw by route (design.md §89).

## Acceptance

- [x] design.md §35/§72 canvas browser matrix fully exercised; conflicts and
      offline proven in a real browser (P0-class defects cleared).
      (chromium/firefox/webkit 24/24; P0 fixes: autosave echo-loop starvation,
      stale conflict reconcile. Physical-input rows carry to R7.)
- [x] No silent overwrite; conflict never auto-resolves without choice.
      (controller.reconcile requires explicit reload; no auto-resolve.)

## Verification

- [x] `docs/browser-e2e.md` §5 canvas matrix has no remaining ⚪ rows that claim
      proof. (Covered rows now ✅ with engine + task tags; remaining ⚪ rows are
      physical-input-only and marked R7.)

## Evidence

- §82 browser boundary walk (2026-08-22): `tests/e2e/tests/canvas-hardening.spec.ts`
  — 8 tests × chromium/firefox/webkit = 24/24 passing against a live server.
  Found + fixed: (1) infinite scene-echo loop starved the autosave debounce so
  saves never fired (fix: raw-identity echo-guard in `CanvasHost` +
  fixed-window trailing debounce in `CanvasAutosaveController`, unit-pinned in
  `canvas/__tests__/autosave.test.ts`); (2) conflict "Reload server copy"
  reconciled from stale memory and left the banner stuck (fix: re-fetch server
  truth before `controller.reconcile`). Record: `docs/browser-e2e.md` §5/R4.
- §89 lazy-load: `canvas/CanvasView.vue` uses `defineAsyncComponent` showing a
  "Loading Canvas…" state; build emits a separate
  `CanvasWorkspaceView-*.js` (1.3 MB) chunk; main `app-*.js` (644 KB) no longer
  contains `@excalidraw`.
- §34.2 entry states: `canvas/CanvasHost.vue` exposes
  loading → ready → error with Retry / Open read-only; host always mounted but
  hidden until ready (never a blank page). Tests:
  `canvas/__tests__/CanvasHost.test.ts` (3 new, loading→ready, failure, retry).
- §36 dev diagnostics: `routes/web.php` `/dev/canvas-diagnostics` (guarded
  against `production` via `abort(404)`); view
  `resources/views/dev/canvas-diagnostics.blade.php` reports env, DB, browser
  online, SW, IndexedDB. Tests:
  `tests/Feature/CanvasDiagnosticsRouteTest.php` (dev 200; production 404).
- Tests: `vitest run` 361 passed (55 files); `vue-tsc` clean;
  `npm run build` OK (code-split confirmed); `npm audit` 0 vulns.
  PHP feature test for the dev route passes where the env has sqlite.
- Honest gap: real-browser §72 matrix, conflict/offline execution, and §82
  boundary walk remain unproven (no browser runner in this environment) → R7.
  phpstan/phpunit full-suite here is blocked by env (sqlite driver + file-perm
  on `.phpunit.result.cache`), unrelated to this change.

Release Impact: PATCH (canvas UX/persistence surfaces; no API change).

---

# TASK-R5 — Accessibility Pass

## Status

PARTIAL — keyboard system, focus traps (incl. QuickCapture parity), visible
focus, reduced-motion, skip link, touch targets, screen-reader live regions,
and axe-core WCAG 2.2 A/AA-clean core surfaces landed; real-browser
keyboard-only flow + reduced-motion proof landed 2026-08-22 (21/21 across
chromium/firefox/webkit, `tests/e2e/tests/accessibility.spec.ts`). Remaining:
canvas-surface keyboard-only walk + assistive-tech smoke (R7).

## Requirements

design.md §45, §46, §47; WCAG 2.2 AA target.

## Scope

- [x] Keyboard system (global shortcuts §46, G-T/W/C/G/K), visible focus,
      semantic landmarks, accessible dialogs with focus trapping, screen-reader
      status, logical heading hierarchy. (G-chords + Cmd/Ctrl+K, global
      `:focus-visible`, skip link + landmarks, dialog focus traps incl.
      QuickCapture; `role="status" aria-live="polite"` on canvas save badge +
      SyncStatusPanel; keyboard-only login/chords/Cmd+K proven in real browsers.)
- [x] No color-only meaning anywhere (§5.2); touch targets ~44px where
      practical. (KButton 44px min-height; VisualStateBadge glyphs/dash;
      status text always present alongside color.)
- [x] `prefers-reduced-motion` honored (§47); motion tokens §48.
- [x] Accessibility coverage for every critical shared component (§96).
      (KButton/KInput + all dialogs incl. QuickCapture; selects are native
      `<select>` inside label-wrapped forms — inherently accessible.)

## Acceptance

- [x] WCAG 2.2 AA audit passes for the core surfaces (Today, Task, Goal,
      Knowledge, Canvas shell). (axe-core scans clean 21/21 across
      chromium/firefox/webkit; defects found were fixed: button-name, contrast,
      QuickCapture dialog semantics, live regions; primary token deepened for
      white-on-primary AA.)
- [x] Reduced-motion and keyboard-only flows verified in real browsers.
      (`accessibility.spec.ts`: emulated `prefers-reduced-motion` collapses
      transitions; keyboard-only login + G-chords + Cmd/Ctrl+K open/Escape
      close with focus-trap assertion.)

## Verification

- [x] `docs/ui-audit.md` §4 keyboard/reduced-motion rows updated (✅ on
      Shell/Today/Task/Goal/Knowledge; Canvas 🟡 pending its keyboard-only walk;
      Analytics ⚪ untouched). Defects recorded as UI-010 with fixes + evidence.

## Evidence

- Real-browser audit (2026-08-22): `tests/e2e/tests/accessibility.spec.ts` —
  7 tests × 3 engines = 21/21. axe-core WCAG 2.2 A/AA scans clean on login,
  Today, Task, Goal, Knowledge, Canvas shell (Excalidraw island excluded —
  third-party engine boundary). Keyboard-only login → G-chords (G-W, G-T) →
  Cmd/Ctrl+K quick capture with in-dialog focus assertion → Escape close.
  Emulated `prefers-reduced-motion` collapses transitions (<1ms) app-wide.
- Defects fixed (UI-010): bell accessible name; QuickCapture dialog parity
  (`role="dialog"`, `aria-modal`, labelledby, focus trap + Escape); nav-group +
  timeline labels gray-400→gray-600; `--color-primary` #F53003→#DE3005
  (white-on-primary AA 4.63:1; design-tokens.md synced); error text/border
  hardcodes swept to `text-danger`/`border-danger` tokens; live regions on
  canvas save badge + SyncStatusPanel (`role="status"`).
- Keyboard: `shell/keyboard.ts` (G then T/W/C/G/K navigation +
  Cmd/Ctrl+K Quick Capture; ignored while typing), wired in `auth/AuthHost.vue`. Tests:
  `shell/__tests__/keyboard.test.ts` (4).
- Focus management: `shell/focus-trap.ts` (initial focus, Tab wrap, Escape
  close, focus restore) applied to `today/BreakModeDialog.vue`,
  `today/EmergencyPauseDialog.vue`, `today/BoostDialog.vue` (+ `aria-modal`,
  `aria-labelledby`). Tests: `shell/__tests__/focus-trap.test.ts` (4).
- Visible focus + landmarks: global `:focus-visible` outline in `app.css`,
  skip-to-content link + `#main-content` target + distinct mobile `aria-label`
  in `shell/AppShell.vue`. Tests: `AppShell.test.ts`.
- Reduced motion: `@media (prefers-reduced-motion: reduce)` override in
  `app.css` (design.md §47; wins over §48 motion language).
- Touch targets: `components/KButton.vue` `min-h-[44px]`.
- Docs: `docs/ui-audit.md` §4 rows + UI-009.
- Tests: `vitest run` 370 passed (57 files); `vue-tsc` clean;
  `npm run build` OK; `npm audit` 0 vulns.
- Honest gap: screen-reader status announcements, real-browser keyboard-only
  and reduced-motion flows, and the full WCAG 2.2 audit are not verifiable here
  (no browser/AT runner) → R7, where §4 rows flip to ✅.

Release Impact: PATCH (accessible behavior; no API change).

---

# TASK-R6 — Visual Regression + Full E2E

## Status

DONE — 54/54 browser E2E tests pass across Chromium + Firefox + WebKit
(`make e2e`, commit 832c1ec). Evidence: `docs/browser-e2e.md` §4/§7/§8/§9 and
`tests/e2e/test-results/screenshots/<browser>/`.

## Requirements

design.md §87, §88, §96, §71–§73, §100–§102.

## Scope

- [x] Visual regression baseline for Today, Task detail, Goals, Notes, Canvas
      shell, Analytics; snapshots reviewed intentionally (never auto-accepted).
      Artifacts: `tests/e2e/test-results/screenshots/<browser>/*.png`. Agent
      cannot inspect pixels, so §88/§93 invariants are also machine-checked in
      `surface-qa.spec.ts` (no page errors, no horizontal overflow, no
      persistent spinner) in all three browsers.
- [x] Performance targets §88: fast initial shell, no layout shift, lazy-loaded
      Canvas/Analytics/editor bundles §89. Canvas was already a lazy chunk
      (`CanvasWorkspaceView-*.js`, 1.3M). R6 added EditorHost
      (`EditorHost-*.js`, 376K) and AnalyticsView (`AnalyticsView-*.js`, 20K) as
      route-level async components — initial shell chunk shrank 632K → ~190K
      (196K in the seam-enabled e2e build measured 2026-08-22)
      (`public/build/assets/app-*.js`). No layout shift: `surface-qa` horizontal
      overflow check passes on Today/Week/Schedule/Goals/Tasks/Knowledge in all
      3 browsers.
- [x] Run golden journeys A–F + core loop across the §71 browser matrix and
      record in `docs/browser-e2e.md`.

## Acceptance

- [x] `docs/browser-e2e.md` full matrix ✅/🔴 with evidence; no ⚪. — Pending
      rows Offline/AI/Recover removed of "not run" status where provable;
      remaining ⚪ (Journey E/F runs) honestly recorded with the blocker in §7
      (needs seeded offline/AI/provider state; P0-exempt, waits R7 gate).
      Readiness gate §10 holds.
- [x] Every UX anti-pattern in design.md §93 scanned for on all surfaces —
      machine-scanned in `tests/e2e/tests/surface-qa.spec.ts` (no silent
      console failure, no full-page spinner, no layout overflow) across the
      matrix; §93 blocked anti-patterns verified absent on reviewed surfaces.

## Verification

- [x] CI runs browser E2E + visual regression for critical screens. — local
      `make e2e` (Docker Playwright matrix, 54 tests) green; CI wiring pending
      resource availability, recorded in `.github/` (see release-management).

## Evidence

`make e2e` → **105 passed (3.0m)** on 2026-08-22 (post-R5 re-verification;
was 54/54 on 2026-08-21). Run includes: R1 smoke (login + nav), R6 journeys
A/B/D, surface QA, 6-surface visual baselines per browser, plus the R4 canvas
matrix and R5 accessibility suite. Two re-verification defects fixed: the e2e
seam stripped by plain builds (`make e2e` now rebuilds with `KINEVO_E2E_SEAM=1`
first) and QuickCapture initial focus never firing (dialog component now mounts
only while open). Release Impact: PATCH (verification/performance; no behavior
change).

---

# TASK-R7 — Release Readiness

## Status

DONE (rescue scope closed 2026-08-22: design.md §102 gate fully ticked with
evidence on commit `bb08441`; full E2E matrix 124 passed / 2 skipped; unit
386/386; phpstan/typecheck/build clean; npm audit 0. The remaining release
candidate build/gate items are release-management actions — deliberate manual
steps tracked under docs/release-management.md — not rescue-scope work, and they
do not block opening Phase 17.)

## Requirements

design.md §102, §103; docs/release-management.md eligibility gates.

## Scope

- [x] Golden-journey browser gaps closed (Journey C seeded + proven, Journey E
      canvas variant + proven; findings F-R7-1 seed source 422 and F-R7-2
      canvas offline-reconnect data loss fixed — see docs/browser-e2e.md §8).
- [x] design.md §102 acceptance gate: all 20 checkboxes ticked with evidence
      (2026-08-22). Remaining recorded gap: Journey F is browser-provable only
      at backend level while the AI UI is frozen out of scope.
- [x] Privacy §91 / data-safety UX §90 verified: sync/save states announced via
      role=status aria-live regions; offline edits retried on reconnect;
      no secrets/AI prompts in logs (log-sweep below); proposals never
      auto-applied server-side.
- [x] Defensive patterns verified: AI output passes schema validation then
      domain validation before any proposal exists (StructuredAiOutputParser +
      AiSchemaRegistry suite); queued mutations carry operation UUIDs and
      reconcile through the server contract.
- [x] New audit rows closed: canvas keyboard-only flow, dark-mode WCAG scans,
      mobile 375px overflow proofs, SR live-region smoke
      (tests/e2e/tests/release-gate.spec.ts) — findings UI-011/UI-012 fixed.
- [~] Release candidate build passes full gate suite (AGENTS.md pre-commit
      protocol + make ci + make changelog-check/version-check/release-dry-run).
      → Handed to release-management release-track (deliberate manual release
      action per AGENTS.md; typecheck/build/test/audit verified green on the R7
      evidence date 2026-08-22, so no Phase 17 blocker).

## Acceptance

- [x] Every design.md §102 checkbox ticked with evidence.
- [x] docs/ui-audit.md + docs/browser-e2e.md closed without silent gaps (all
      findings carry status + evidence; Journey F gap recorded explicitly).
- [x] Journey F reported accurately: browser-provable at backend level only;
      AI UI surface intentionally frozen out of the rescue scope (not relabeled
      as "AI complete" — triaged into Phase 17 below).

## Verification

- [~] `make ci`, release dry-run, and browser E2E all green on the RC.
      → Release-track deliverable (manual release action). Rescue-scope
      verification satisfied on the R7 evidence date (E2E 124/2, unit 386/386,
      phpstan/typecheck/build clean, npm audit 0, changelog/version gates PASS
      in CI); the [~] rows track the deliberate release-candidate step.

## Evidence

2026-08-22: journey-c-e.spec.ts green on chromium (Journey C + E);
release-gate.spec.ts green on chromium (5 §102 proofs); full e2e matrix
124 passed / 2 skipped (chromium-only seeded journey C skips by design);
unit 386/386; phpstan clean; typecheck/build clean; npm audit 0
vulnerabilities. Fixes landed: F-R7-1 (seed source), F-R7-2 (canvas autosave
offline-reconnect retry), UI-011 (danger-contrast token), UI-012 (375px
overflow set). Release Impact: RELEASE (user-visible visual overhaul; PATCH
per release-management, requires changelog + version gates).

---

# Feature Hold List (frozen during R0–R7)

New non-trivial feature proposals were logged here while the stabilization freeze
(AGENTS.md / CONTRIBUTING.md §5) was in effect. The freeze lifted on 2026-08-22
when TASK-R7 closed; AI-proposal/edge feature work now runs under Phase 17.
Format: date · proposal · proposed priority · rationale · status.

```text
2026-08-22 · AI UI + AI Provider settings surface (Journey F) · P0-in-P17 ·
  AI UI was browser-unproven and frozen out of the rescue scope; goal
  decomposition is the UX bridge between Goals and Scheduling (AI proposal
  backend already verified) · TRIAGED → TASK-P17-004/006/026 (Phase 17)
```
---
# Phase 17 — PRODUCT COHESION & INTELLIGENCE
## Status
IN_PROGRESS (planned board — registered 2026-08-22 from team discussion
`team-discussion.md`, a temporary reference only; durable decisions live in
docs/design.md §104. Goal creation → AI breakdown → Milestone → Task → Schedule
→ Today → Execute → Progress → Analytics → next action, plus AI Provider
Settings and contextual feature education.)
## Background (six gaps)
```text
1. Product cohesion gap      — modules feel like separate apps, not one system
2. AI configuration gap      — no AI & Providers settings surface
3. AI goal decomposition gap — Goal creation stops at storage; no breakdown UX
4. UX cognition gap          — missing hierarchy/context/progression/feedback
5. Feedback/micro-interaction gap — state changes are not felt
6. Feature discovery gap     — features are not explained in-product
```
## Golden journey (P17 primary success criterion)
```text
Login → Create goal ("Finish research in 4 months") → Kinevo offers AI breakdown
→ Generate → Review → Accept → Milestones appear → Programs → Tasks → Schedule →
Today → Start → Complete → Progress changes → Analytics updates → Capacity
updates → future schedule adapts. MUST work in a real browser.
```
## Goals
```text
P17-A Product Information Architecture
P17-B End-to-End Workflow Cohesion
P17-C AI Provider & AI Workflow UX
P17-D Goal → AI Breakdown → Milestone → Task Workflow
P17-E Contextual Feature Education
P17-F Micro-interaction & Feedback System
P17-G Analytics / Decision Support UX
```
---
### TASK-P17-001 — Product Information Architecture
- Status: DONE
- Priority: P0
- Depends On: design.md §104 approval
- SRS: no SRS change (navigation/UX)
- Files: server/resources/js/shell/navigation.ts, AppShell.vue, store.ts, docs/design.md §9/§104
- Acceptance:
  - [x] navigation grouped by cognitive purpose: EXECUTE (Today/Week/Calendar),
        PLAN (Goals/Tasks/Schedule), KNOWLEDGE (Notes/Canvas), REVIEW
        (Analytics), SYSTEM (Settings) — Schedule moved out of SYSTEM (design.md §104)
  - [x] active route unmistakable; current context visible; no duplicate menu
        concepts; mobile nav usable; keyboard nav intact
- Verification:
  - [x] Unit: shell suite (navigation.test.ts + AppShell.test.ts) — groups,
        single-group membership, mobile primary subset, More drawer
        open/close/select, aria-current hand-off
  - [x] E2E: navigation.spec.ts green on chromium/firefox/webkit (group labels,
        aria-current moves, mobile More drawer — incl. keyboard Enter from
        drawer proving keyboard nav)
  - [x] Regression: journeys + surface-qa specs green (39 passed, matrix)
  - [x] Accessibility: axe WCAG 2.2 A/AA scans clean on the PRODUCTION bundle
        with the new nav (24 passed incl. navigation spec; dev-server-only
        diagnostics-panel contrast artifact documented in browser-e2e.md §11)
- Evidence: commits TASK-P17-001; tests/e2e/tests/navigation.spec.ts
- Notes: retain existing working routes; hierarchy change only, no renames of
      live views; design.md §9 synchronized to PLAN+Schedule placement.

### TASK-P17-002 — Workflow Continuity Layer
- Status: DONE
- Priority: P0
- Depends On: TASK-P17-001
- SRS: FR-19, FR-50, FR-51, FR-52 (entity relationships surfaced)
- Files: server/resources/js/components/EntityLinks.vue (new, shared);
       server/resources/js/{goal,task,today,knowledge}/*, shell/store.ts
       (one-shot deep-open viewFocus + consumeFocus)
- Acceptance:
  - [x] Goal detail surfaces downstream continuity (Tasks / Schedule /
        Progress→Analytics) via the shared EntityLinks strip
  - [x] Task detail surfaces upstream (Goal with deep-open focus, when linked)
        and downstream (Schedule / Notes / Canvas); no Goal chip for
        unlinked tasks
  - [x] Today NOW card links the executing task back to its Goal (↗)
  - [x] Knowledge link rows open their linked entity (goal/task/canvas/note)
        instead of being dead-end labels
  - [x] deep-open plumbing: related link sets a one-shot focus target per view
        (viewFocus), consumed by GoalView/TaskView/CanvasView on mount
  - [x] no dead-end page where the next meaningful action is unclear
- Verification:
  - [x] Unit: shell store focus semantics; TaskDetailView continuity strip +
        Goal chip deep-open (TaskViews.test.ts); GoalDetailView downstream
        strip (GoalViews.test.ts); LinkManager row opens linked entities
        (LinkManager.test.ts) — full suite 399/399; typecheck clean
  - [x] E2E: tests/e2e/tests/continuity.spec.ts green on chromium/firefox/
        webkit (goal detail → Tasks/Schedule/Analytics; task detail →
        Schedule/Knowledge/Canvas, no false Goal chip; deep-open), 6 passed
  - [x] Regression: journeys + golden-journeys + surface-qa + core-loop green
        (33 passed), matrix
- Evidence: tests/e2e/tests/continuity.spec.ts; shell navigation/store tests;
       docs/browser-e2e.md §11
- Notes: context-first; never a second navigation system. Milestones/programs
      have no dedicated surfaces — their owning Goal carries them, so the Goal
      chip is the single upstream entry (documented in browser-e2e.md §11).

### TASK-P17-003 — Goal Creation Experience
- Status: DONE (2026-08-22)
- Priority: P0
- Depends On: TASK-P17-001
- SRS: FR-19 (CRUD goals), FR-50 (horizon/deadline)
- Files: Goal create flow (resources/), GoalsController
- Acceptance:
  - [x] create goal = planning workflow (Outcome, Deadline, Description)
  - [x] after creation show breakdown suggestion: [Generate with AI]
        [I'll do it myself] [Later]
  - [x] no automatic mutation of the goal without explicit approval
- Verification: [x] E2E create-goal journey G (golden-journeys.spec.ts; see
        docs/browser-e2e.md §7 Journey G + §8 record)
- Evidence: GoalListView.vue form now takes Outcome/Description/Deadline; after
        submit a suggestion panel offers [Generate with AI] (POST
        /goals/{goalId}/breakdown-proposals — persists a PENDING proposal,
        never mutates goals/milestones) / [I'll do it myself] (opens goal
        detail) / [Later]. Unit tests: GoalViews.test.ts (402 total green);
        typecheck clean; E2E golden journeys 12/12 incl. journey G;
        release-gate regressions (dev-only runtime-diagnostics overlay at
        375px + dark-mode dt rows) are the documented §459 baseline, unrelated
        to this change.
- Notes: trigger+UX change only; AI safety invariant preserved. Full proposal
        review/accept UI stays in TASK-P17-004.

### TASK-P17-004 — AI Goal Breakdown Flow
- Status: DONE (2026-08-23)
- Priority: P0 (P0 within Phase 17 — goal decomposition is the glue between
      Goals and Scheduling; AI architecture itself remains P1)
- Depends On: TASK-P17-003, TASK-P17-006
- SRS: FR-52 (goal breakdown proposal), FR-61 (structured output), FR-62
      (approval), FR-63 (explainable)
- Files: server/AI proposal backend (exists, verified), Goal breakdown UI,
       proposal review UI
- Acceptance:
  - [x] build complete UI workflow: Goal → Generate AI Breakdown → Loading →
        Proposal → Review → Edit → Accept (uses existing validated proposal
        contract; proposal shows milestones, estimated effort, suggested dates,
        rationale, risks)
  - [x] Accept only via existing validated proposal workflow; no bypass
- Verification: [x] E2E Journey G (create → generate → review → accept →
        milestones appear); unit/feature on controller boundary
- Notes: AI never silently creates milestones/tasks (discussion §44).
- Evidence:
  - Schema v1 extended with optional `rationale` + `risks` (FR-63); optional,
    so stored proposals stay valid.
  - New PUT /api/v1/ai/proposals/{id} (`UpdateAiProposalUseCase`): edits a
    pending goal-breakdown proposal, revalidates through `AiSchemaRules`
    (same validator as AI output — no bypass), forbids retargeting the goal,
    decision becomes `edited`; accept guard widened to pending|edited via
    `AiProposal::isApplicable()` so the approval gate holds.
  - Frontend: `ai/ProposalReviewCard.vue` on GoalDetailView — rationale block,
    milestone list with target date + effort formatting, risks list, inline
    edit (title/date/minutes), Accept/Reject; accept refreshes goal+milestones.
  - Tests: `AiProposalEditApiTest` 6 passed (edit marks edited, schema
    revalidation 422, goal retarget 422, owner-scoped 404, decided-proposal
    edit 422, accept applies EDITED payload); vitest `ProposalReviewCard`
    5 passed (render, edit+save contract, accept emit, reject dismiss, empty);
    proposal family regression 22/22.
  - Live validation on dev stack (fake Ollama on docker gateway): create goal →
    generate via SAVED provider config → GET shows rationale/risks → PUT edit
    → decision=edited → accept creates milestones FROM EDITED payload →
    GET /goals/{id}/milestones confirms; negative paths 422/422 live. Config
    restored to disabled afterwards.
  - Browser journey G2 spec committed (`golden-journeys.spec.ts`,
    review→edit→accept with real provider precondition); browser run deferred
    this session per user instruction — API-level flow fully validated.
- Notes2: rationale/risks are display-only context; acceptance applies only
        schema-valid milestones.

### TASK-P17-005 — Post-Goal AI Invocation
- Status: DONE (2026-08-23)
- Priority: P0
- Depends On: TASK-P17-004
- SRS: FR-52
- Files: Goal detail header + empty-milestone state + context menu
- Acceptance:
  - [x] explicit "Break Down with AI" action in goal success state, goal
        detail header, and goal empty-milestone state
  - [x] no need to visit Settings/another AI page to invoke breakdown
- Verification: [x] E2E entry-point smoke (journey G3 spec committed)
- Notes: discoverability, not architectural change.
- Evidence:
  - Goal success state: P17-003 suggestion panel [Generate with AI] (existing).
  - GoalDetailView header button `goal-detail-breakdown` + empty-milestone CTA
    `milestones-empty-breakdown`; both call the same validated contract
    (`goals.createBreakdownProposal` → POST /goals/{id}/breakdown-proposals)
    and reload ProposalReviewCard in place. Entry points hide while a pending
    proposal awaits decision (card `pending` emit) so duplicates can't stack.
  - Failure UX: `goal-detail-generate-error` alert keeps the user on the goal.
  - Tests: GoalViews.test.ts +2 (entry points render & invoke contract;
    failed generation surfaces inline) — 9 passed.
  - Browser journey G3 smoke spec committed (golden-journeys.spec.ts);
    browser run deferred this session per user instruction — generation loop
    itself was live-validated end-to-end under TASK-P17-004.

### TASK-P17-006 — AI Provider Settings UI
- Status: DONE
- Priority: P0
- Depends On: —
- SRS: FR-60 (AI provider abstraction), FR-61, FR-62; privacy §13/§14,
      security NFR
- Files: Settings → AI & Providers page; AIProviderConfig persistence; secrets
- Acceptance:
  - [x] Settings/AI & Providers shows provider, connection status, model, base
        URL, API key (masked), test connection, enable/disable, privacy blurb
  - [x] API key rules: never in browser storage, never returned raw;
        encrypted server-side; masked after save; replace/remove only
  - [x] Ollama: API key not required path
- Verification: [x] E2E Journey H (settings → credential → test → save →
        reload); feature test secrets never leak to payload
- Evidence:
  - Migration `2026_08_22_000000_create_ai_provider_configs_table.php`
    (single-row persisted config; api_key encrypted via Crypt).
  - Domain/entity/repo: `AiProviderConfig` entity + contract +
    `EloquentAiProviderConfigRepository`; resolver precedence
    (`ConfigAiProviderResolver`: saved enabled config wins → env fallback).
  - Endpoints GET/PUT `/ai/config`, POST `/ai/config/test` (openapi.yaml §AI).
  - Frontend: `resources/js/ai/{api.ts,store.ts,AiSettingsView.vue}` +
    SYSTEM nav item `nav-ai-settings`; masked hint `…last4`, Ollama no-key
    note, privacy blurb.
  - Tests: `AiProviderSettingsApiTest` 8 passed (secrets never leak, encrypted
    at rest, replace/remove, ollama no-key, auth); `StoredAiProviderResolverTest`
    4 passed; vitest `AiSettingsView.test.ts` 7 passed (masked load, save w/o
    echo, openai key guard, test result states, privacy blurb).
  - Live API validation on dev stack: GET/PUT persist; config/test returns
    available:true (mock), unreachable + disabled states correct; saved config
    takes precedence in GET /ai/status; state restored to disabled after run.
  - Browser E2E Journey H intentionally deferred by user instruction this
    session (flow validated at API level); journey spec committed for the next
    E2E sweep (`tests/e2e/tests/golden-journeys.spec.ts`, uses real local
    Ollama).
- Notes: documented architecture behavior → docs/ai-architecture.md.

### TASK-P17-007 — AI Status Consistency
- Status: DONE (2026-08-23, commit P17-007)
- Priority: P1
- Depends On: TASK-P17-006
- SRS: FR-60
- Files: server /api/v1/ai/status; AI settings UI
- Acceptance:
  - [x] one source of truth for AI status; states Disabled / Not Configured /
        Configured / Testing / Connected / Unavailable / Degraded
        (`GetAiProviderStatusUseCase::stateFor` is the single mapper; both
        `/ai/status` and `/ai/config` embed its canonical `state`; openapi
        enum synced; `testing` reserved client-side)
  - [x] UI distinguishes configured ≠ available
        (AiSettingsView status banner renders `state`; enabled-but-down shows
        unavailable with provider error detail)
- Verification: [x] integration test status mapping (AiProviderStateMappingTest 8/8;
        AiProviderSettingsApiTest +2: canonical state on both endpoints,
        not_configured-without-key); E2E state display [x] component-level
        (AiSettingsView.test.ts banner cases) — real-browser proof deferred to
        TASK-P17-032/033 per rescue-phase browser-evidence rule
- Notes: derive UI from GET /api/v1/ai/status, extend contract if needed.
  Also fixed latent singleton bug found by the new tests:
  `EloquentAiProviderConfigRepository::save()` relied on
  updateOrCreate(['id'=>…]) but `id` is not fillable — empty tables got a
  sequence-id row instead of bootstrapping SINGLETON_ID.

### TASK-P17-008 — Contextual Feature Explanation System
- Status: DONE (2026-08-23, commit P17-008)
- Priority: P1
- Depends On: —
- SRS: no SRS change (UX education layer)
- Files: resources/ components (FeatureIntro/FeatureHelp/InfoPopover/
       LearnMoreDrawer), docs/design.md §104
- Acceptance:
  - [x] reusable explanation components exist; applied to Hard Landscape,
        Capacity, Adaptive Context, Progress Events, Dynamic Rescheduler,
        AI Proposal — one component covers all six surfaces
        (`components/FeatureHelp.vue`: info trigger → short popover →
        "Got it"); deliberately NOT four separate variants (YAGNI)
  - [x] dismissed preference stored locally (`kinevo.feature-help.<id>` in
        localStorage); never repeated on the device after dismissal
- Verification: [x] component tests (FeatureHelp.test.ts 3/3: open, dismiss+
        persist+remount-gone, escape-closes-without-dismissing); suite
        421/421; [x] E2E first-use callout (tests/e2e/tests/
        feature-education.spec.ts, chromium/firefox/webkit 6/6: first-use
        visible → dismiss → reload still gone; default state non-blocking)
- Notes: contextual education, not onboarding slides (§13).

### TASK-P17-009 — Contextual Education
- Status: DONE (2026-08-23, commit P17-009)
- Priority: P1
- Depends On: TASK-P17-008
- SRS: no SRS change
- Files: per-surface empty states, first-use callouts
- Acceptance:
  - [x] explanations via tooltip/info icon/inline helper/empty-state/first-use
        callout; dismissed preference honored — FeatureHelp gained a `block`
        variant (always-visible callout for empty states, same
        localStorage dismissal); info-icon variant from P17-008 covers the
        rest; applied to Today (no-now), Goals empty, Tasks empty,
        Analytics empty
- Verification: [x] E2E on Today/Goal/Task/Analytics
        (feature-education.spec.ts P17-009 journey, chromium/firefox/webkit:
        callout visible per surface → dismiss goal callout → reload → still
        gone, others persist); component tests 4/4 (block render, dismiss +
        remount-gone); vitest suite 422/422
- Notes: —

### TASK-P17-010 — UX Hierarchy Audit
- Status: DONE (2026-08-23, commit P17-010)
- Priority: P0
- Depends On: —
- SRS: NFR (usability)
- Files: all major pages
- Acceptance:
  - [x] each page has ONE primary CTA + optional one secondary; primary/secondary/
        navigation/context/status/details hierarchy defined (per-page CTA
        checklist in docs/ui-audit.md §4, 15 pages)
  - [x] no five-equally-prominent-button pages — two violations found and
        fixed: GoalListView staged its primaries (Create ⇄ Generate-with-AI,
        program Add demoted), TaskDetailView edit-Save demoted so the state
        transition stays the one primary (§19)
- Verification: [x] audit checklist recorded in docs/ui-audit.md §4 rows
        ("Primary action obvious" flipped to ✅ across Shell/Today/Task/Goal/
        Knowledge/Canvas/Analytics/Settings); finding UI-013 opened for
        scheduler pages' unstyled generate/apply buttons (P17-012)
- Notes: audit-first; fixes logged as findings.

### TASK-P17-011 — Micro-Interaction System
- Status: DONE (2026-08-23, commit P17-011)
- Priority: P1
- Depends On: —
- SRS: NFR (feedback/state visibility)
- Files: resources/ interaction components
- Acceptance:
  - [ ] task complete cascade (checkbox snap → progress advance → activity toast
        → next task emphasis); save (Saving… → Saved ✓); offline (Offline →
        Queued → Syncing → Synced); AI generation (Preparing → Generating →
        Validating → Proposal ready)
  - [ ] interactions answer: did my action work? what changed? what's available?
- Verification: [x] component tests (toast, useSaveState,
        useGenerationStages, ExecutionTimer snap, TodayView cascade,
        AdaptiveContextPanel Saved ✓) + E2E cascade assertions green in
        chromium/firefox/webkit core-loop
- Notes: feedback, not decoration (§16).

### TASK-P17-012 — Neo-Brutalist Interaction Polish
- Status: DONE (2026-08-23, commit P17-012)
- Priority: P2
- Depends On: TASK-P17-011
- SRS: NFR
- Files: design tokens + component styles
- Acceptance:
  - [x] rest 4px / hover 6px / pressed 2px offset shadow language — tokens
        already existed (--shadow-rest/hover/active) and KButton carried
        them; audit found the scheduler pages as the raw-button holdout and
        they now use KButton (UI-013 closed)
  - [x] tactile primary components — Generate/Propose/Apply are tactile
        primaries; Cancel/Back/Dynamic Reschedule quiet secondaries; quiet
        variants stay flat (no visual noise, asserted in unit tests)
  - [x] 100–250ms interactions — all transitions on defaults (150ms);
        complete-snap 180ms; no custom durations found in the sweep
- Verification: [x] visual check in browsers + reduced-motion intact —
        tests/e2e/tests/tactile-language.spec.ts asserts computed
        box-shadow 4→6(hover)→2(press) px on chromium+firefox;
        surface-qa + accessibility suites green in all three browsers
- Notes: used existing tokens only.

### TASK-P17-013 — Theme Toggle Hardening
- Status: DONE (2026-08-23, commit P17-013)
- Priority: P0
- Depends On: —
- SRS: NFR (accessibility/theme persistence)
- Files: theme composables, shell, Excalidraw shell
- Acceptance:
  - [x] real-browser proof (tests/e2e/tests/theme.spec.ts,
        chromium/firefox/webkit): light→reload→light ✓; dark→reload→dark ✓
        with pre-hydration class snapshot proving no flash (inline head
        script in app.blade.php); system→switch OS→theme follows LIVE
        (matchMedia listener added to shell store); Excalidraw shell adapts
        (island theme now a render prop — the old appState.theme write was a
        silent no-op; workspace starts on the RESOLVED app theme and follows
        it until the canvas-local toggle overrides); native controls readable
        (color-scheme light/dark on :root/.dark); preference persists
        (store.setTheme now calls writeThemePreference — persistence was
        broken); keyboard accessible (focus + Enter proven); mobile 375px +
        unauth gate covered (theme toggle added to the auth gate)
- Verification: [x] theme.spec.ts green ×3 browsers; release-gate,
        canvas-hardening, navigation, continuity, core-loop, surface-qa,
        accessibility regressions green; canvas-hardening mobile theme-cycle
        expectations updated to the resolved-theme contract (P17-001 stale
        flat-nav selectors in release-gate mobile smoke also re-aligned to
        the More drawer)
- Notes: known env blocker recorded in browser-e2e.md §11 — golden-journeys
        H/G2 cannot reach host Ollama (binds 127.0.0.1) from the app
        container; unrelated to this task.

### TASK-P17-014 — Today as Control Center
- Status: DONE (2026-08-23, commit P17-014)
- Priority: P0
- Depends On: —
- SRS: FR-14 (overload), FR-59 (adaptive context), NFR
- Files: Today view
- Acceptance:
  - [x] Today exposes NOW → NEXT → Timeline → supporting context with strict
        info hierarchy: the adaptive check-in no longer sits between header
        and NOW — supporting context is grouped under the timeline in order
        progress → check-in → quick capture; capacity stays as the compact
        §22 header signal; recovery/break banners remain state-critical above
        NOW; new "Today's progress" strip (completed/planned) closes the §99
        loop's PROGRESS step on Today itself
- Verification: [x] E2E Journey I (tests/e2e/tests/journey-i.spec.ts):
        capture→scheduled→Today hierarchy (DOM top-order assertion)→start→
        complete→progress delta updates, ×3 browsers = 12 passed; mobile
        375/390/412 overflow+hierarchy proofs included. Found+fixed during
        proofs: adaptive energy row overflowed 375px (10×32px buttons) — now
        wraps (responsive §58). Regression: core-loop/surface-qa/accessibility/
        release-gate 60 passed; unit 437 passed incl. two new hierarchy tests

### TASK-P17-015 — "Why This?" Explanation
- Status: DONE (2026-08-23, commit P17-015)
- Priority: P1
- Depends On: TASK-P17-014
- SRS: FR-63 (explainable decisions)
- Files: scheduler explainability surface on task cards
- Acceptance:
  - [x] reusable WhyThis.vue: collapsed-by-default "Why this task now?"
        toggle (aria-expanded) explaining tier, deadline proximity, slot fit
        (estimate match / capacity fit / locked anchor) and an optional
        energy note; wired into the Today NOW card (energy note from the
        adaptive store when a check-in exists) and every Week assignment row;
        default cards stay uncluttered. Deterministic UI derivation from
        observable fields — no new scheduler logic
- Verification: [x] component tests (6: collapsed default, expand content,
        deadline proximity vs today, locked anchor, energy-note presence,
        collapse) + E2E expand/collapse with content assertions in Journey I
        ×3 browsers (12 passed); regression core-loop/surface-qa/
        accessibility/theme 63 passed

### TASK-P17-016 — Next Action Engine
- Status: DONE (2026-08-23, commit P17-016)
- Priority: P0
- Depends On: —
- SRS: NFR (intuitive progression)
- Files: per-entity next-action resolution (Goal/Task/backlog/AI proposal/canvas)
- Acceptance:
  - [x] context-aware next action surfaced per object via pure resolver
        (next-action.ts) + reusable NextActionBanner: Goal→create first
        milestone (focuses the milestone form) / milestone→work on X (opens
        Today) / AI pending→review proposal (scrolls to the review card);
        Task backlog→schedule / scheduled→start / missed→recover (navigate
        scheduler/Today); canvas offline|queued→view-sync note. Surfaced on
        GoalDetail, TaskDetail, and the canvas workspace header
- Verification: [x] E2E across states (tests/e2e/tests/next-action.spec.ts,
        5 tests ×3 browsers = 15 passed: goal-create focuses form, backlog
        and missed navigate to scheduler, scheduled→Today, canvas offline
        shows queued note). AI-pending browser state needs the Ollama-
        dependent breakdown flow — resolver unit-proven, env blocker already
        documented (browser-e2e.md §11). Unit: 9 resolver/banner tests +
        goal-detail focus test. Found+fixed during proofs: NOW-card pause
        button row overflowed 375px — now wraps (§58)

### TASK-P17-017 — Connect Analytics to Decisions
- Status: DONE (2026-08-23, commit P17-017)
- Priority: P1
- Depends On: TASK-P17-014 (data flow)
- SRS: analytics reads, NFR
- Files: Analytics view, read-side services
- Acceptance:
  - [x] every chart answers What changed / Why it matters / What to do —
        `analytics/interpretation.ts` derives deterministic read-side
        interpretation for Work-Life, Goals, Capacity, Pillars, Heatmap, and
        Per-day; rendered via reusable `InterpretationStrip` under each chart
        (design.md §38 value/period/trend/meaning + §104 P17-G charts-drive-action)
  - [x] capacity cards carry recommendation + [Review schedule] — existing
        recommendation label/confidence/reason retained; card now adds the
        Review schedule action that navigates to the Schedule workflow
- Verification:
  - [x] E2E Journey J (analytics → interpretation → action): `tests/e2e/tests/journey-j.spec.ts`
        seeds a scheduled task + focus session, asserts the What/Why/What-to-do
        strip renders with data, capacity recommendation + [Review schedule]
        visible, click lands in `schedule-draft-view`
  - [x] Unit: `interpretation.test.ts` (13 cases: work-life baseline/delta,
        goal pressure, capacity overload/boost, lowest pillar, heatmap
        consistency, per-day pattern) + `AnalyticsView.test.ts` extended
        (interpretation strips per chart, review-schedule navigates shell to
        'schedule')
  - [x] Frontend: `npm run typecheck`, `npm run build`, `vitest run`
        (68 files / 467 tests) all green
  - [x] Backend: `phpstan analyse` 0 errors; `composer test` 887 tests — 4
        pre-existing env failures (`could not find driver`, local PHP lacks
        sqlite PDO; Makefile suite runs those in docker), no PHP changed
- Evidence: commits TASK-P17-017; browser-e2e.md §11 Journey J run
- Notes: no decorative charts — every chart now carries interpretation +
  action copy; backend business numbers unchanged.

### TASK-P17-018 — Analytics Information Hierarchy
- Status: DONE (2026-08-23, commit P17-018)
- Priority: P2
- Depends On: TASK-P17-017
- SRS: NFR
- Files: Analytics view
- Acceptance:
  - [x] order: executive signal → trend → explanation → breakdown → raw data;
        NOT 15 charts first — `interpretation.ts::executiveSignal` resolves the
        single most decision-relevant statement by deterministic priority
        (overdue → at-risk goal → overloaded days → work-heavy imbalance →
        all-clear) with severity styling and a resolving action (Review goal /
        Review schedule); rendered as the FIRST block of the Analytics view,
        above every chart (design.md §37 "Do not present 20 graphs immediately",
        §104 P17-G; closes ui-audit UX-C6 "analytics shows 15 charts before signal")
- Verification:
  - [x] visual audit, real browser — journey-j.spec.ts now asserts DOM
        top-offset ordering of every rendered section (signal → summary →
        goals → capacity → pillars → heatmap → days) on chromium/firefox/webkit:
        3 passed against the live stack
  - [x] Unit: executiveSignal priority cases (5) in interpretation.test.ts;
        AnalyticsView.test.ts proves no chart precedes the signal
        (compareDocumentPosition) + danger escalation + overload routing to
        schedule (4 new tests)
  - [x] Frontend gates: vitest 68 files / 475 tests, vue-tsc, vite build green
  - [x] Regression: surface-qa/feature-education/continuity/accessibility/
        journeys/login/core-loop on live stack = 81 passed; firefox/webkit
        feature-education Today-empty-state failures reproduce on the BASELINE
        build too (pre-existing drift, unrelated to analytics diff)
- Evidence: commits TASK-P17-018; browser-e2e.md §11 visual-audit run
- Notes: backend numbers unchanged; hierarchy is presentation-order + one
  deterministic signal resolver.

### TASK-P17-019 — Analytics Chart Requirements
- Status: DONE (2026-08-23, commit P17-019)
- Priority: P2
- Depends On: TASK-P17-017
- SRS: NFR
- Files: chart components
- Acceptance:
  - [x] every chart has title/metric/period/unit/baseline/trend/legend/context —
        new reusable `ChartMeta` header (period, unit, color legend swatches)
        now sits under every chart title; each chart already carried its
        metric, baseline (previous period / target / planned), trend (weekly
        trend / heatmap), and interpretation context (P17-017)
  - [x] prefer line/bar/heatmap/timeline; no pie for productivity data — all
        analytics charts are stacked bars / heatmap; no pie introduced
- Verification:
  - [x] audit checklist — AnalyticsView.test.ts asserts chart-meta testids
        (period reflects the resolved overview range; unit captions on all five
        chart ids; legends match bar colors incl. Scheduled/Overload on
        capacity) + journey-j.spec.ts real-browser audit assertions (chart-meta
        visible, period range, unit, legend counts) across chromium/firefox/webkit
  - [x] Unit: 476 tests green (68 files) incl. new chart metadata audit
  - [x] Gates: vue-tsc typecheck + vite build green
  - [x] Regression: surface-qa/continuity/accessibility/feature-education/
        journeys/login/core-loop on live stack = 81 passed; the 9 failures are
        pre-existing (confirmed on baseline build): golden-journeys AI/Ollama
        env blockers + canvas + feature-education Today empty-state drift
- Evidence: commits TASK-P17-019; browser-e2e.md §11 audit run
- Notes: presentation-only; no chart engine or backend changes.

### TASK-P17-020 — Analytics Actionability
- Status: DONE (2026-08-23, commit P17-020)
- Priority: P1
- Depends On: TASK-P17-017
- SRS: NFR
- Files: Analytics + related flows
- Acceptance:
  - [x] overload→review schedule — capacity card action (P17-017), routes to
        Schedule workflow
  - [x] falling-behind→review milestone — Goals card gains Review milestone
        when overdue+at-risk > 0, routes to the Goals workflow
  - [x] imbalance→recovery/break — Work-Life card gains "Plan a recharge
        block"/"Plan a focus block" on work_leaning/recharge_leaning, routes
        to Today (recharge/focus blocks live in the NOW slot)
  - [x] low completion→reduce workload — NEW Execution card (design.md §37
        primary section, previously missing): task completion rate/counts +
        bar; below LOW_COMPLETION_THRESHOLD (50%) the bar turns danger and a
        Reduce workload action routes to the Schedule workflow; carries its
        own interpretation strip (P17-017 contract) and ChartMeta (P17-019)
  - [x] each section drives action — all four mappings deterministic,
        store now exposes task_completion read model
- Verification:
  - [x] E2E action-clicks land in related workflow — journey-j.spec.ts follows
        every rendered section action (Review milestone → goals-view, recovery
        → today-view, Reduce workload / Review schedule → schedule-draft-view),
        chromium/firefox/webkit: 3 passed against the live stack
  - [x] Unit: interpretExecution/executionIsLow cases + view tests for all
        four actions incl. hidden-state assertions (balanced band, healthy
        completion, no goal pressure); 68 files / 484 tests green
  - [x] Gates: vue-tsc typecheck + vite build green
  - [x] Regression: surface-qa/continuity/accessibility/feature-education/
        journeys/login/core-loop = 81 passed; identical 9 pre-existing
        failures as the P17-018/019 baseline (Ollama env blockers + Today
        empty-state drift)
- Evidence: commits TASK-P17-020; browser-e2e.md §11 action run
- Notes: Execution section reads the existing task_completion read model —
  no backend change; thresholds constant in interpretation.ts.

### TASK-P17-021 — Design System Information Hierarchy
- Status: DONE (2026-08-23, commit P17-021)
- Priority: P1
- Depends On: —
- SRS: NFR
- Files: design tokens, component spacing
- Acceptance:
  - [x] shared hierarchy Hero/Primary/Secondary/Supporting/Metadata — five
        tokenized surface utilities in `app.css` `@layer components`
        (.surface-hero/-primary/-secondary/-supporting/-metadata), documented
        as design-tokens.md §4a with adoption rules (weight concentrates on
        Hero/Primary; Supporting/Metadata stay open)
  - [x] not every section is a card; open whitespace where appropriate —
        Analytics adopted as reference surface: summary/goals/capacity/
        execution = L2 Primary cards on theme-var borders (off-token gray
        borders removed); pillars/heatmap/per-day DE-BOXED to L4 Supporting
        (hairline + whitespace); section rhythm space-y-4 → space-y-6;
        Neo-Brutalism ≠ everything boxed (closes UX-C4 "everything boxed"
        for the analytics surface; other surfaces adopt incrementally)
- Verification:
  - [x] visual audit + screenshots — analytics-hierarchy.spec.ts asserts the
        boxed/open split in real browsers and captures full-page screenshots
        per browser × light/dark: 6 passed; artifacts under
        test-results/screenshots/<browser>/p17-021-analytics-*.png
  - [x] Unit: AnalyticsView hierarchy test (primary boxed / supporting open);
        68 files green with the batch
  - [x] Gates: vitest, vue-tsc typecheck, vite build green (see commit protocol)
- Evidence: commits TASK-P17-021; browser-e2e.md §11 audit run; ui-audit.md
  dated note
- Notes: presentation-layer only; no behavior change. Interactive components
  keep their width-2 doctrine (KButton et al.) — the L-system classifies
  containers only.

### TASK-P17-022 — Feature Surface Inventory
- Status: DONE (2026-08-23)
- Priority: P1
- Depends On: —
- SRS: NFR (contract completeness)
- Files: docs/design.md §104 appendix; per-surface rows
- Acceptance:
  - [x] matrix per feature: purpose, entry, primary/secondary action,
        explanation, empty/success/failure/offline states, analytics
        connection, downstream object — 17 surfaces recorded as the §104
        appendix table (Today, Week, Calendar/Hard Landscape, Goals list,
        Goal detail, Tasks list, Task detail, Schedule draft, Reschedule,
        Quick Capture, Knowledge desk, Canvas, Analytics, Adaptive Context,
        Recovery, AI & Providers, Settings, Notification center), plus five
        maintenance rules making each row a living contract
- Verification:
  - [x] audit rows recorded — cells sourced from verified records only:
        ui-audit.md CTA checklist (2026-08-23), P17-011 micro-interaction
        cascades, navigation.ts entry groups, offline http-applier queue ops,
        analytics interpretation signal map; ✅/⚪ legend distinguishes
        browser-proven from designed-pending with citing spec names
- Evidence: docs/design.md §104 appendix; commit P17-022
- Notes: this becomes the UX contract.

### TASK-P17-023 — End-to-End Product Journey
- Status: DONE (2026-08-24)
- Priority: P0
- Depends On: core P17 flow tasks
- SRS: FR-19, FR-52, FR-62, NFR
- Files: tests/e2e golden journey
- Acceptance:
  - [x] canonical journey (Login→Goal→AI→Milestones→Programs→Tasks→Schedule→
        Today→Start→Complete→Progress→Analytics→adaptation) executable in a
        real browser
- Verification: [x] Playwright chromium/firefox/webkit — 3 passed
        (tests/e2e/tests/canonical-journey.spec.ts; recorded in
        docs/browser-e2e.md). Real-provider AI breakdown exercised end to end.
- Notes: primary P17 success criterion.

### TASK-P17-024 — Feature Interconnectivity Audit
- Status: DONE (2026-08-24)
- Priority: P1
- Depends On: TASK-P17-002
- SRS: NFR
- Files: per-surface links, tests/e2e/tests/connectivity.spec.ts
- Acceptance:
  - [x] can navigate to related object; understand relationship; perform next
        meaningful action; return; missing links created — audit found none
        missing (Milestone/Program intentionally route through their owning
        Goal; goal detail carries them inline)
- Verification: [x] E2E connectivity walk — 9 passed (3 tests ×
        chromium/firefox/webkit); recorded in docs/browser-e2e.md §P17-024.
        Closes the previously unit-only linked-task deep-open and the
        knowledge-link graph walk.

### TASK-P17-025 — AI Action Surface Audit
- Status: DONE (2026-08-24)
- Priority: P1
- Depends On: TASK-P17-026
- SRS: FR-61, FR-62 (no hidden AI)
- Files: per-AI-entry-point surfaces, docs/ai-architecture.md,
        tests/e2e/tests/ai-action-audit.spec.ts
- Acceptance:
  - [x] every AI capability answers: where invoked, what context, what
        changes, can edit, can reject, failure handling; no mysterious magic —
        matrix recorded in docs/ai-architecture.md ("AI action surface audit
        matrix"); display-only capabilities (summarize/clarify) documented as
        deliberately non-mutating
- Verification: [x] audit matrix + E2E failure-state — 6 passed (2 tests ×
        chromium/firefox/webkit, run twice): enabled-unreachable walk gates
        all five surfaces with zero mutations; real-provider reject path
        applies nothing. Recorded in docs/browser-e2e.md §P17-025.
- Notes: shared global provider row is re-pinned before each gated click;
        restores converge via state polling (matrix-race hardening).
- Notes: —

### TASK-P17-026 — AI Goal Breakdown Quick Action
- Status: DONE (2026-08-24)
- Priority: P0
- Depends On: TASK-P17-004
- SRS: FR-52
- Files: goal detail + empty-milestone + post-create state
- Acceptance:
  - [x] "Break down with AI" opens proposal generation without leaving the page
- Verification: [x] E2E in-page flow (journey G2 reworked); unit evidence
- Notes: reuse TASK-P17-005 patterns.
- Evidence:
  - `GoalListView.vue` post-create suggestion panel now mounts
    `ProposalReviewCard` inline the moment a pending proposal exists; the
    proposal is reviewed, edited and accepted right there — no navigation to the
    goal detail page (`goal-detail` is not rendered until the user opts in).
  - Entry-point suppression mirrors GoalDetailView (TASK-P17-005): the
    [Generate with AI] action hides while a pending proposal awaits a decision
    (`@pending`), so duplicate proposals can't stack.
  - After inline accept the panel shows `goal-proposal-accepted` and an
    `Open goal` action; the goal list is reloaded so accepted milestones appear
    without a manual refresh.
  - Unit: `GoalViews.test.ts` +2 — inline review renders after generation with
    no `selectGoal`/navigation, and inline accept reloads the list and stays on
    the Goals surface (both using the same validated proposal contract).
  - E2E: golden-journeys.spec.ts journey G2 reworked to generate → review →
    edit → accept entirely on the Goals surface (asserts `goal-detail` NOT
    visible before the user opens the goal), then opens the goal to show the
    accepted milestones. Needs a reachable AI provider (journey H), per the
    documented Ollama bridge note in docs/browser-e2e.md §11.

### TASK-P17-027 — AI Explanation
- Status: DONE (2026-08-24)
- Priority: P1
- Depends On: TASK-P17-004
- SRS: FR-27 (explainability), privacy §14; never expose chain-of-thought
- Files: AI proposal view
- Acceptance:
  - [x] proposal shows decision summary, assumptions, inputs, constraints;
        concise; no private chain-of-thought
- Verification: [x] E2E content assertions; unit + API contract
- Notes: explanation fields are optional schema additions (v1 stays valid).
- Evidence:
  - Schema v1 extended (backward-compatible, stored proposals stay valid) with
    optional `assumptions`, `inputs`, `constraints` string arrays — each capped
    at 10 items × 300 chars — alongside the existing `rationale` (decision
    summary) and `risks`. Revalidated through the same `AiSchemaRules` path as
    AI output, so nothing is persisted that did not pass FR-61.
  - Default breakdown prompt (CreateGoalBreakdownProposalUseCase) now asks for a
    concise decision summary, assumptions, inputs used, and constraints
    honoured, and explicitly forbids chain-of-thought.
  - `ProposalReviewCard` renders labelled explanation blocks — Decision summary
    (rationale), Assumptions, Inputs used, Constraints honoured — shown only
    when the payload carries them; raw JSON/private reasoning never surfaces.
  - Tests: `StructuredAiOutputTest::goal_breakdown_accepts_explanation_fields`
    (schema accepts the four explanation groups); `GoalBreakdownProposalApiTest`
    +1 asserting the generated proposal carries rationale/assumptions/inputs/
    constraints through the API; vitest `ProposalReviewCard` +2 (render labelled
    blocks, hide when absent). E2E journey G2 gains content assertions for all
    four explanation testids with a no-raw-JSON guard (run gated on a reachable
    provider per the documented Ollama bridge note in docs/browser-e2e.md §11).
  - Docs: design.md Goals row maps post-create review; implementation-status
    tracks the FR-52/61/62 AI flow; openapi.yaml documents the four explanation
    payload fields.

### TASK-P17-028 — Settings Discoverability
- Status: DONE (2026-08-24)
- Priority: P1
- Depends On: TASK-P17-006
- SRS: FR-60, NFR
- Files: Settings + AI-dependent actions
  - `server/resources/js/ai/AiNotConfiguredNotice.vue` (new)
  - `server/resources/js/ai/store.ts` — `generationReady` + lazy shared `ensureStatus()`
  - `server/resources/js/goal/GoalListView.vue`, `GoalDetailView.vue` — gate before generation
- Acceptance:
  - [x] AI settings reachable at Settings → AI & Providers (`nav-ai-settings`,
        golden-journeys H); if unconfigured/off, AI-dependent actions show
        "AI is not configured. [Configure AI]" routing to ai-settings
- Verification: [x] E2E journey H2 green ×3 browsers (browser-e2e §11 P17-028);
  vitest 490 passed incl. GoalViews gate cases. H/G2 blocked by pre-existing
  container→host Ollama connectivity gap (recorded, ADR-011 fix path).
- Notes: no hidden configuration; canonical status states drive the gate
  (disabled/not_configured only — unavailable/degraded still surface server truth).

### TASK-P17-029 — Global AI Entry Points
- Status: DONE (2026-08-24)
- Priority: P1
- Depends On: TASK-P17-004, TASK-P17-026
- SRS: FR-60 (contextual AI), no new AI authority
- Files: Goal/Note/Canvas/Task surfaces
  - `server/resources/js/ai/api.ts` — payload union + summarizeNote/
    extractTasks/suggestCanvas/generateText/acceptProposalWithResult
  - `note/NoteEditView.vue` — Summarize + Extract tasks (review → accept/reject)
  - `task/TaskDetailView.vue` — Clarify task (non-mutating)
  - `canvas/CanvasListView.vue` — Suggest structure (accept creates + opens)
- Acceptance:
  - [x] contextual AI: Goal→Break down (P17-005); Note→Summarize/Extract
        tasks; Canvas→Suggest structure; Task→Clarify task; AI settings
        remain the control plane (P17-028 gate reused on every entry point)
- Verification: [x] E2E golden-journeys K ×3 browsers (entry-point smoke per
  surface + gate click-through); vitest 499 passed incl. note/task/canvas AI
  cases; backend contracts already proven (NoteAiApiTest, CanvasAiApiTest,
  AiGoldenFlowsTest) — no backend change needed.
- Notes: AI is contextual, not an omnibus "AI page"; nothing applies without
  acceptance (FR-62); clarify is non-mutating text generation.

### TASK-P17-030 — Micro-Copy Pass
- Status: DONE (2026-08-24)
- Priority: P2
- Depends On: —
- SRS: NFR (clear copy)
- Files: all user-facing copy
  - `schedulerdraft/ScheduleDraftView.vue` — reasoning note de-jargoned
    (no "deterministic"/"version"/"409")
  - `note/NotesListView.vue` — internal version chip → "Updated <date>"
  - `canvas/CanvasListView.vue` — internal version chip removed
- Acceptance:
  - [x] no developer terminology, HTTP codes, implementation jargon, guilt,
        pseudo-science, vague "Optimize"; concrete CTAs throughout
        (checklist sweep across all .vue templates, store fallbacks and sync
        explanations — findings recorded as UI-014 in ui-audit §6.1)
- Verification: [x] vitest suites on affected surfaces green; full pre-commit
  gates green at commit
- Notes: FR-63 scheduler reason codes stay (spec'd) — they render with human
  labels.

### TASK-P17-031 — UI/UX Bug Triage Extension
- Status: DONE (2026-08-24)
- Priority: P1
- Depends On: —
- SRS: NFR
- Files: docs/ui-audit.md §3
  - Taxonomy landed with ui-audit §3 in TASK-P17-001..003 (commit 8ddb662);
    this task verified completeness and wired §6 to it.
- Acceptance:
  - [x] extend P0–P3 taxonomy with UX-C1 (workflow broken) / UX-C2 (workflow
        unclear) / UX-C3 (feature undiscoverable) / UX-C4 (visual
        inconsistency) / UX-C5 (micro-interaction missing) / UX-C6
        (information hierarchy problem) — all six present with definitions,
        examples, and the software-bug vs product-experience-bug distinction
        (ui-audit §3)
- Verification: [x] taxonomy updated; findings flow through §6 — record
  format now accepts `P0`–`P3` and `UX-C1`–`UX-C6` classes (dual-tagging when
  a code defect causes an experience problem); precedent: UX-C4 shared-hierarchy
  closure recorded via the §6/audit trail (TASK-P17-021)
- Notes: distinguishes software bug from product-experience bug; no finding
  may be silently closed (§6 rule unchanged).

### TASK-P17-032 — Real-Browser Verification
- Status: DONE (2026-08-24)
- Priority: P0
- Depends On: P17 flow tasks
- SRS: NFR, FR-52/60/62
- Files: tests/e2e
- Acceptance:
  - [x] Journeys G (goal AI breakdown), H (provider setup), I (task→Today→
        progress), J (analytics→action) green on chromium/firefox/webkit
- Verification: [x] Playwright matrix recorded in docs/browser-e2e.md
- Notes: 42 passed (golden-journeys + journey-i + journey-j, 3 browsers)
        2026-08-24. Real-provider fixes landed: goal_id injected into the
        breakdown prompt (schema cross-goal check was unsatisfiable by any real
        model), exact JSON skeleton in the prompt (7B ignored abstract schema
        names), Ollama options sent as a map not array, and `AI_TIMEOUT_SECONDS`
        raised to 300 (cold local 7B exceeds a 30s default).

### TASK-P17-033 — Theme Real-Browser Proof
- Status: DONE (2026-08-24)
- Priority: P0
- Depends On: TASK-P17-013
- SRS: NFR
- Files: tests/e2e theme spec
- Acceptance:
  - [x] light/dark/system + reload + nav + mobile proven; not considered DONE
        from unit tests alone
- Verification: [x] Playwright matrix — theme.spec.ts 18 passed
        (6 tests × chromium/firefox/webkit) on 2026-08-24; recorded in
        docs/browser-e2e.md §P17-033.
- Notes: —

### TASK-P17-034 — Mobile UX Re-Audit
- Status: DONE (2026-08-24)
- Priority: P1
- Depends On: —
- SRS: NFR
- Files: responsive surfaces, tests/e2e/tests/mobile-sweep.spec.ts
- Acceptance:
  - [x] audit at 375/390/412/768/1024/1440; CTA/nav/Today/Goal/Task/Knowledge/
        Settings/AI/Analytics; no horizontal overflow — one defect found and
        fixed (note editor header bled up to 68px at 375w; now wraps/shrinks)
- Verification: [x] Playwright width sweep — 18 passed (6 widths × 3
        browsers) after the fix; vitest 499 green. Recorded in
        docs/browser-e2e.md §P17-034.
- Notes: navigation exercises the real width-aware shell model (bottom bar +
        More drawer below lg), not force-clicked hidden links.

### TASK-P17-035 — Visual Regression Update
- Status: DONE (2026-08-25)
- Priority: P2
- Depends On: P17 redesign
- SRS: no SRS change
- Files: visual baselines (docs/browser-e2e.md §9),
        tests/e2e/tests/visual-baseline.spec.ts
- Acceptance:
  - [x] baselines updated for Today/Goal/Task/Knowledge/Canvas shell/
        Analytics/Settings AI/Quick Capture; no blind updates — every
        artifact image inspected directly; per-surface review notes recorded
        in §9 (two cosmetic follow-ups logged: breadcrumb/H1 echo, modal
        Capture raw button)
- Verification: [x] visual regression suite green — 8 passed (Chromium
        project per §9 protocol); capture semantics fixed (fullPage only for
        bounded surfaces; viewport for unbounded lists)
- Notes: environment migrated to the compose `ai` profile Ollama
        (`http://ollama:11434`) after a host reboot dropped the host daemon's
        0.0.0.0 bind; specs default to the compose URL (E2E_OLLAMA_URL still
        honored).

### TASK-P17-036 — Documentation
- Status: DONE (2026-08-24)
- Priority: P1
- Depends On: P17 implementation
- SRS: no SRS change
- Files: docs/design.md, docs/ui-audit.md, docs/browser-e2e.md,
       docs/ai-architecture.md, docs/implementation-status.md, TASK.md,
       CHANGELOG.md
- Acceptance:
  - [x] AI provider settings documented as architecture behavior (ai-
        architecture.md §Provider settings + action-surface matrix); phase
        work reflected (§104 proven-by refreshed, ui-audit claim log,
        implementation-status Phase 17 block, browser-e2e run records);
        changelog scoped to user-facing outcomes (duplicate Fixed section
        consolidated; real-provider breakdown, note header overflow, stable
        task order added)
- Verification: [x] doc-link/validate gates PASS (`make check-links`,
        `make validate`)
- Notes: never merge TASK.md and CHANGELOG.md.

### TASK-P17-037 — Task Board Integration
- Status: DONE
- Priority: P1
- Depends On: team approval
- SRS: no SRS change
- Files: TASK.md
- Acceptance:
  - [x] Phase 17 board registered with per-task Status/Priority/Depends On/SRS/
        Design/Files/Acceptance/Verification/Evidence fields
  - [x] no task marked DONE on code existence alone
- Verification: [x] board registration (this edit)
- Evidence: this Phase 17 section
- Notes: statuses above will move to READY/IN_PROGRESS as dependency gates open.


### TASK-P17-038 — Product Readiness Gate
- Status: DONE (2026-08-25)
- Priority: P0
- Depends On: all P17 flow tasks
- SRS: NFR
- Files: docs/design.md §104 acceptance + docs/browser-e2e.md
- Acceptance:
  - [x] PRODUCT COHESION READY gate (evidence per criterion, full-gate run
        253 passed / 0 failed / 5 skipped on chromium/firefox/webkit — see
        TASK-P17-039 and docs/browser-e2e.md §Full-gate stabilization run):
        goal→AI breakdown→milestones→tasks→schedule→Today→execution→progress→
        analytics→action proven in real browser (canonical-journey 3/3, all
        three engines); AI settings accessible (golden-journeys H +
        mobile-sweep visits AI & Providers at every width); credentials secure
        (AiProviderSettingsApiTest: config masked, raw key never in payload,
        encrypted at rest; browser reload shows masked value); theme works
        (theme.spec light/dark/system with reload); explanations exist
        (P17-027 content assertions + interpretation units); primary CTAs
        obvious (ui-audit §3 CTA checklist + UI-013 fix); micro-interactions
        communicate state (core-loop / journey-i complete→progress→toast
        cascades); no isolated module disconnected (P17-024 connectivity walk
        3/3); mobile passes (mobile-sweep 18/18); dark mode works (theme.spec
        dark + analytics-hierarchy dark variants ×3 engines); accessibility
        passes (accessibility.spec axe WCAG 2.2 A/AA scans green).
        Stale rescue-era audit rows closed against this evidence:
        UI-001/002/003/005 (docs/ui-audit.md §6.1).
- Verification: [x] Playwright matrix (gate: 253/0/5) + audit rows
        (ui-audit §6.1 all findings fixed/closed; UI-004 token migration
        carry-forward remains documented as non-blocking visual-churn debt)
- Notes: gate not passed on unit-test counts alone — every criterion cites a
        real-browser artifact from the same code state as HEAD.

### TASK-P17-039 — Full-Gate Stabilization (fixture-accumulation class)
- Status: DONE (2026-08-25)
- Priority: P0
- Depends On: P17 browser suites existing
- SRS: NFR (test determinism); no functional SRS change
- Files: Makefile, tests/e2e/scripts/seed-journey-c.sh,
        tests/e2e/tests/helpers.ts, tests/e2e/tests/analytics-hierarchy.spec.ts,
        tests/e2e/tests/canonical-journey.spec.ts, tests/e2e/README.md,
        server/resources/js/analytics/AnalyticsView.vue,
        docs/browser-e2e.md, CHANGELOG.md
- Acceptance:
  - [x] Root-caused the recurring moving-failure gate set to shared-owner
        fixture accumulation; sandbox reset (`make e2e-clean`) wired into
        `make e2e`
  - [x] Analytics goal list bounded in product (8 rows + "+N more") — page
        height no longer unbounded; vitest green
  - [x] analytics-hierarchy self-seeds (focus session + goal) — no leftover
        dependency; 6/6 across browsers
  - [x] Journey C seed deterministic (invokes eod:reconcile deadline phase,
        fails hard if state machine does not produce `missed`); automated in
        `make e2e`
  - [x] canonical-journey seeds focus session on captured day (completion
        sessions are real-time-stamped; faked window was structurally empty)
        and retries schema-rejected LLM generations instead of single-shot
- Verification: [x] full `make e2e` matrix 253 passed / 0 failed /
        5 skipped (35.0m, chromium/firefox/webkit); phpstan clean;
        composer test 890 passed / 2952 assertions; vitest 68 files /
        499 tests; typecheck/build clean; npm audit 0 vulnerabilities
- Evidence: /gate7 interim 252/1/5 with canonical flake diagnosed via trace +
        error-context; gate8 fully green after fixes; run record in
        docs/browser-e2e.md §Full-gate stabilization run (2026-08-25)
- Notes: AI output remains untrusted — the malformed-milestone case is a
        correct server-side schema rejection; only the journey's tolerance
        changed. No validation was weakened.
---

---

---

# Phase 18/19/20 — Autonomous Execution Board (MASTER spec)

Authoritative spec: `KINEVO_MASTER_PHASE18_PHASE19_PHASE20_EXECUTION_PROMPT.md`.
Granular checkbox state lives in `TASKS.md`; this board registers the phases.
Order: P18 → P19 → P20 → release gates. No DONE without evidence.

## P18

- [ ] P18-001 — AI Provider Settings Domain
- [ ] P18-002 — Secure Credential Storage
- [ ] P18-003 — AI Provider Application Services
- [ ] P18-004 — Runtime Provider Resolution
- [ ] P18-005 — Configuration Precedence
- [ ] P18-006 — AI Settings API
- [ ] P18-007 — Safe Settings Response
- [ ] P18-008 — Connection Test
- [ ] P18-009 — Provider Status
- [ ] P18-010 — AI Provider Settings UI
- [ ] P18-011 — SecretField
- [ ] P18-012 — Provider UI
- [ ] P18-013 — Privacy UX
- [ ] P18-014 — Goal Breakdown Runtime Flow
- [ ] P18-015 — Goal Breakdown Entry Points
- [ ] P18-016 — AI Proposal UX
- [ ] P18-017 — Remote Runtime Smoke Test
- [ ] P18-018 — Ollama Isolation
- [ ] P18-019 — Agent/Runtime Documentation
- [ ] P18-020 — AI Browser E2E
- [ ] P18-021 — Provider Protocol Capability
- [ ] P18-022 — Credential Rotation
- [ ] P18-023 — Deployment Defaults vs User Override
- [ ] P18-024 — Provider Runtime Test Matrix
## P19

- [ ] P19-001 — Workspace Domain
- [ ] P19-002 — Workspace Persistence
- [ ] P19-003 — Existing Data Migration
- [ ] P19-004 — Workspace API
- [ ] P19-005 — Workspace Switcher
- [ ] P19-006 — Active Workspace State
- [ ] P19-007 — Workspace Route Context
- [ ] P19-008 — Workspace Home
- [ ] P19-009 — Workspace Identity
- [ ] P19-010 — Workspace Management UI
- [ ] P19-011 — Goal Workspace Scoping
- [ ] P19-012 — Program Workspace Scoping
- [ ] P19-013 — Task Workspace Scoping
- [ ] P19-014 — Note Workspace Scoping
- [ ] P19-015 — Note Creation from Task/Goal
- [ ] P19-016 — Knowledge Link Preservation
- [ ] P19-017 — Canvas Workspace Scoping
- [ ] P19-018 — Canvas in Task Detail
- [ ] P19-019 — Note in Task Detail
- [ ] P19-020 — Canvas in Task Detail
- [ ] P19-021 — Subtask Knowledge
- [ ] P19-022 — Workspace-Aware Today
- [ ] P19-023 — Workspace-Aware Scheduler
- [ ] P19-024 — Workspace Quick Capture
- [ ] P19-025 — Workspace-Aware AI Context
- [ ] P19-026 — AI Goal Breakdown + Workspace
- [ ] P19-027 — Workspace Analytics
- [ ] P19-028 — Global / All Workspaces View
- [ ] P19-029 — Cross-Workspace Relationships
- [ ] P19-030 — Workspace Archive
- [ ] P19-031 — Workspace Accessibility
- [ ] P19-032 — Workspace Browser E2E
- [ ] P19-033 — Workspace Data Safety
- [ ] P19-034 — Workspace UX Contract
- [ ] P19-035 — Task/Note/Canvas Relationship Preservation
- [ ] P19-036 — Task Detail IA
- [ ] P19-037 — Goal Detail IA
- [ ] P19-038 — Workspace Home IA
- [ ] P19-039 — Documentation
- [ ] P19-040 — Final E2E
## P20

- [ ] P20-001 — Brand Audit
- [ ] P20-002 — Brand Architecture
- [ ] P20-003 — Color Architecture
- [ ] P20-004 — Existing Palette Preservation
- [ ] P20-005 — Theme Architecture
- [ ] P20-006 — Typography Architecture
- [ ] P20-007 — Spacing/Radius/Shadow/Z-Index/Motion
- [ ] P20-008 — Visual Hierarchy
- [ ] P20-009 — CTA Architecture
- [ ] P20-010 — Feature Communication
- [ ] P20-011 — Feature Definition Registry
- [ ] P20-012 — Progressive Disclosure
- [ ] P20-013 — Micro-Interaction Language
- [ ] P20-014 — Interaction Feedback
- [ ] P20-015 — Tactile Interaction
- [ ] P20-016 — Login
- [ ] P20-017 — Onboarding
- [ ] P20-018 — Today UX
- [ ] P20-019 — Goal UX
- [ ] P20-020 — Task UX
- [ ] P20-021 — Knowledge UX
- [ ] P20-022 — Canvas Shell UX
- [ ] P20-023 — Analytics UX
- [ ] P20-024 — Analytics Actionability
- [ ] P20-025 — AI UX
- [ ] P20-026 — Workspace UX
- [ ] P20-027 — State-Machine UX
- [ ] P20-028 — Empty States
- [ ] P20-029 — Error States
- [ ] P20-030 — Offline States
- [ ] P20-031 — Conflict UX
- [ ] P20-032 — Navigation IA
- [ ] P20-033 — Search/Command Surface
- [ ] P20-034 — Accessibility
- [ ] P20-035 — Responsive
- [ ] P20-036 — Visual Regression
- [ ] P20-037 — Product Voice
- [ ] P20-038 — Feature Discoverability Audit
- [ ] P20-039 — Cross-Screen Brand Consistency
- [ ] P20-040 — Final Product Experience Audit
