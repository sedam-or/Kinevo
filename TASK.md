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
- Status: TODO
- Priority: P0
- Acceptance: PR checks fail correctly and pass on baseline.

#### TASK-003 — Docker development environment
- Status: TODO
- Priority: P0
- Acceptance: app + PostgreSQL boot from clean checkout.

#### TASK-004 — Environment/config/secrets baseline
- Status: TODO
- Priority: P0
- Acceptance: `.env.example`, secret rules, non-secret defaults documented.

### Phase 1 — Core Domain
#### TASK-010 — Identity/profile
- Status: TODO
- Priority: P0
- SRS: security/access requirements.

#### TASK-011 — Goal aggregate
- Status: TODO
- Priority: P0
- SRS: FR-50, existing goal FRs.

#### TASK-012 — Milestone aggregate
- Status: TODO
- Priority: P0
- SRS: FR-51.

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

