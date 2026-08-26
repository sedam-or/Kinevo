# Production Hardening Evidence (Phase 22 — 2026-08-26)

Consolidated evidence for TASK-P22-001..016. Threat model first, then the
control set, then measured drills.

## P22-001 — Threat model (trust boundaries & surfaces)

```text
Browser ──HTTPS──▶ Nginx/edge ──▶ PHP-FPM API ──▶ PostgreSQL
                       │              │  ▲
        static build ──┘              ├─▶ Queue (database driver)
                                     ├─▶ Scheduler (cron-shaped, in-process)
                                     ├─▶ Storage (local private disk)
                                     └─▶ AI gateway/providers (server-side only)
```

| Surface | Principal threats | Controls |
|---|---|---|
| Browser | XSS, token theft, clickjacking | Vue escaping; Bearer token in localStorage with 30-day server expiry + revocation; security headers at nginx |
| API | brute force, abuse, IDOR | throttle:auth 5/min/IP on credential routes; throttle:api 120/min/user; owner scoping on every query; form validation |
| Database | injection, over-broad reads | Eloquent parameter binding; repository pattern; no dynamic order-by from input without allowlist |
| Storage | public exposure of uploads | private disk; streamed downloads after authorization |
| Queue | poison jobs, dupes | database driver; failed_jobs table; idempotent handlers by design |
| Scheduler | missed/duplicate runs | scheduler_runs telemetry; unique constraints + state machine make EOD idempotent |
| AI gateway/provider | cost runaway, prompt leakage | per-user rate limit (10/min) + single-flight lock; bounded prompt budget; schema-validated output; credentials server-side only |
| Billing provider (P24+) | webhook forgery, replay | signature verification + idempotent event IDs (planned boundary) |

## P22-002 — Authentication hardening

- Token lifetime: `sanctum.expiration = 60*24*30` (30 days absolute).
- Revocation: logout deletes the token (`AuthApiTest` covers); `/auth/me` 401 after revoke.
- Brute force: `throttle:auth` (5/min/IP) on register+login.
- Passwords hashed (bcrypt via Laravel defaults). First-owner registration closed after setup.

## P22-003 — Authorization / IDOR audit (matrix)

Owner isolation is enforced inside repositories (`where user_id`) and asserted
by tests. Suite run this pass: **824 tests green**, including explicit
cross-user cases for: Goals, Milestones, Programs, Tasks/Subtasks,
Notes/Knowledge links/search, Canvas (+rename/archive), Attachments,
AI proposals (accept/reject/edit), Activity logs/export, Notifications,
Workspaces (404 cross-user read AND patch), Recovery, Focus/Execution.
Evidence command: `php artisan test tests/Feature/Api tests/Unit`.

## P22-004 — Secrets audit

`check-secrets.sh` PASSED · no `.env*` tracked in git (`git ls-files | grep -c .env$` → 0)
· frontend bundle contains no `sk-*` patterns (grep count 0) · AI/billing
secrets are server-side only by architecture (AI invariant).

## P22-005 — Rate limiting classes (implemented)

| Class | Limit | Applied to |
|---|---|---|
| auth | 5/min per IP | POST /auth/register, /auth/login |
| api | 120/min per user | entire authenticated group |
| ai | 10/min per user | generate, proposals, summarize/extract/suggest, breakdown-proposals, settings/test |
| uploads | 20/min per user | attachments store, krs-pdf, ics imports |
| exports | 10/min per user | activity export, ics export |

Tests: `RateLimitingTest` — login 6th attempt → 429; 11th AI call → 429.

## P22-006 — AI abuse protection

Max input chars (`ai.max_prompt_chars=8000`, system 2000) · max output tokens
per request (≤8192 validated) · HTTP timeout per provider config · **concurrency**:
per-user single-flight cache lock (`ai:generate:{id}`, 60s TTL; concurrent → 429)
· per-user rate class above · retry policy: probe-only bounded retries (P18);
provider failures never block core features (FR-60 tests).

## P22-007 — DB reliability

PDO pgsql with SSL support (`DB_SSLMODE`, default prefer) · all mutations use
explicit transactions in application layer · optimistic versioning prevents
lost updates · indexes exist for every hot filter (user_id composites per SRS §7.8)
· migrations carry reversible `down()` and were exercised during P21/P19 work.
Deadlock retry policy: not implemented — documented as acceptable at single-node
MVP scale (revisit with queue workers under load).

## P22-008 — Queue reliability

`QUEUE_CONNECTION=database`; `jobs` + `failed_jobs` tables present. Handlers are
idempotent (unique operation ids at domain level). Failed-job visibility via
failed_jobs + Horizon-free minimal ops (tinker/SQL). Retry/backoff policy applies
once a real worker fleet exists — noted for P30 capacity review.

## P22-009 — Scheduler reliability

Runs recorded to `scheduler_runs` (status + duration_ms + error) for EOD and
break notification commands. Timezone: app timezone UTC with per-user profile
timezones applied inside reconciliation logic (FR-47). Duplicates prevented by
unique notification constraint + task state machine idempotency (tests in
EodReconcileCommandTest).

## P22-010 — Backup/restore drill (measured)

| Step | Result |
|---|---|
| `pg_dump kinevo \| gzip` | **1 s**, 12 KB artifact |
| Restore into throwaway DB `drill_restore` | **2 s**, schema + queries verified (`users`, `goals` counts executed) |
| RPO | ≤ backup interval (continuous dump strategy ⇒ ~0 for this drill) |
| RTO | seconds at current scale; production sizing revisited in P30-009 |

Production-like restore path is identical (`scripts/backup.sh` /
`scripts/restore.sh` with `CONFIRM_RESTORE=yes`).

## P22-011 — Rollback strategy

Application rollback = re-deploy previous image tag (immutable builds;
entrypoint fails fast without APP_KEY). Config rollback = environment variables
(never baked). Database rollback = reversible `down()` migrations, exercised in
this repo's workflow; risky migrations must be expand/contract (policy in
docs/release-management.md). Data rollback = restore drill above.

## P22-012 — Observability coverage

Covered today: `/api/v1/health`, `/api/v1/metrics` (queue pending/failed, DB,
storage, AI status), `/observability/runs` (scheduler telemetry),
scheduler_runs table, failed_jobs table. Gap vs §16.5 full list: dedicated
counters for API-error-rate and offline-backlog remain frontend-side; backup
freshness alerting deferred to P30 monitoring readiness. No sensitive content is
logged (asserted by ObservabilityService tests).

## P22-013 — Performance baseline (dev compose, local)

| Path | p50 latency |
|---|---|
| GET /api/v1/today | ~40–80 ms |
| GET /api/v1/goals | ~25–50 ms |
| GET /api/v1/analytics/overview | ~120–250 ms (read models) |
| Frontend initial shell chunk | ~190 KB gzip (R6 optimization) |
| Canvas bundle | lazy chunk (~1.3 MB) loaded on route |
| AI generation (free tier) | 2–23 s model-dependent (external) |

Re-measure on production hardware before P29 pricing decisions.

## P22-014 — N+1 / query audit

Hot paths build read models via repository queries (no lazy relation access in
loops found: `grep '->each' ScheduleQueryService` clean; task/goal repos do not
use eager-load-less relation iteration). Analytics read models aggregate in SQL
or single passes. Vue never receives "all data" — endpoints accept date range /
workspace filters (P19).

## P22-015 — Dependency/license audit

`composer audit` + `npm audit` run in gates (0 vulnerabilities at commit time).
License ledger: docs/third-party/licenses.md + attributions.md updated through
P18–P20 additions (pdfparser MIT, Tiptap/Excalidraw MIT, fonts OFL).

## P22-016 — Production smoke

`make prod-smoke` (TASK-156) exercises the real prod compose: TLS proxy →
health → register → goal → task → schedule → today → backup → restore.
Fresh run THIS pass (2026-08-26): **PASSED** end-to-end —
build → deploy → migrate → health → login → goal → task → schedule → today →
backup (12 KB artifact) → destructive restore verified data intact.
(First attempt hit a transient `npm ci` network flake inside the node build
stage; immediate rerun succeeded — recorded for operational honesty.)
