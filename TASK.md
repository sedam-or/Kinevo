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
- Status: TODO
- Priority: P0
- SRS: FR-22, FR-26.

#### TASK-014 — Task/subtask lifecycle
- Status: TODO
- Priority: P0
- SRS: FR-09, FR-45.

#### TASK-015 — Activity log
- Status: TODO
- Priority: P0
- SRS: FR-34.

### Phase 2 — Scheduling
#### TASK-020 — TimeRange/slot primitives
- Status: TODO
- Priority: P0
- SRS: FR-01, FR-02.

#### TASK-021 — Hard constraint engine
- Status: TODO
- Priority: P0
- SRS: FR-27, FR-28, FR-64.

#### TASK-022 — Task ranking engine
- Status: TODO
- Priority: P0
- SRS: FR-23, FR-64.

#### TASK-023 — Auto-schedule draft engine
- Status: TODO
- Priority: P0
- Acceptance: deterministic simulation suite passes.

#### TASK-024 — Dynamic rescheduler preview/apply
- Status: TODO
- Priority: P0
- SRS: FR-28.

#### TASK-025 — Capacity feedback
- Status: TODO
- Priority: P1
- SRS: FR-49.

#### TASK-026 — Scheduler explainability
- Status: TODO
- Priority: P0
- SRS: FR-63.

### Phase 3 — Knowledge
#### TASK-030 — Note aggregate
- Status: TODO
- Priority: P0
- SRS: FR-53.

#### TASK-031 — Tiptap editor adapter
- Status: TODO
- Priority: P0

#### TASK-032 — Knowledge linking
- Status: TODO
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

