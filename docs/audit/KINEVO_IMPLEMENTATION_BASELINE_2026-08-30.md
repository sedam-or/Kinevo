# KINEVO IMPLEMENTATION BASELINE — 2026-08-30

Mode: READ-ONLY repository audit. No code or existing documents were modified by this audit.
Method: executable code > migrations > tests > routes > config > manifests > runtime files > docs > TASK.md.
TASK.md statuses were never treated as implementation evidence. Items lacking evidence are marked UNKNOWN.

---

## A. Exact repository snapshot

| Field | Value | Source |
|---|---|---|
| Branch | `main` | git |
| HEAD | `ce64edbc10f9cf0f96640082814b23b2044ec14e` | git rev-parse |
| Commit time | 2026-08-28T23:45:18+07:00 | git log -1 |
| Working tree | DIRTY — 43 modified tracked files (docs, compose, css, views, TASK.md …) | git status |
| Tags | none (`git tag` empty) | git |
| Latest CHANGELOG version | `[Unreleased]` + `[0.4.0] 2026-08-17` | CHANGELOG.md:13,535 |
| PHP constraint | `^8.4` | server/composer.json:14 |
| Laravel (installed) | v13.29.0 (constraint `^13.17`) | composer.lock / composer.json |
| Sanctum | v4.3.3 | composer.lock |
| NativePHP mobile | 4.2.0 (embedded PHP 8.4.24 per nativephp.lock) | composer.lock, nativephp.lock |
| pdfparser | smalot/pdfparser v2.12.5 | composer.lock |
| Local audit CLI | PHP 8.5.10 without pdo_sqlite (blocked Feature suite — see K) | php -m |
| Node | no engines field; CI uses node 22, Dockerfile.prod node:20-alpine (drift) | .github/workflows/ci.yml, Dockerfile.prod |
| Vue / Pinia / Vite / Vitest / Tailwind | 3.5.41 / 4.0.3 / 8.2.2 / 4.1.11 / 4.3.3 | server/package-lock.json |
| Database engine | PostgreSQL 17-alpine (runtime); sqlite :memory: (test script) | docker-compose*.yml, composer.json scripts |
| Package managers | composer + npm (lockfiles committed) | repo root, server/ |
| Test frameworks | PHPUnit 12.5.12 (`php artisan test`), Vitest 4, Playwright (root `tests/e2e`), Pint, Larastan, vue-tsc | composer.json, package.json, configs |
| Docker topology | dev: `app` (artisan serve) + `postgres` + optional `ollama` (profile `ai`); prod: nginx → php-fpm + postgres + queue-worker + scheduler + certbot(profile) + backup loop | infrastructure/docker-compose*.yml |

---

## B. Current implemented product capabilities (evidence-based)

### B.1 Structure
- Modular monolith in `server/app/{Application,Domain,Infrastructure,Http/Controllers/Api,Models,Console,Providers,NativeComponents}` — 39 thin JSON controllers, 38 Eloquent models (persistence-only), use cases per module (28 modules incl. Workspaces, Scheduling, Ai, Billing, Imports, Knowledge, Saas, Recovery, Recharge, Execution, Adaptive, Boosts, Breaks, Focus, Analytics, Exports, Observability).
- Canonical migrations: ROOT `database/migrations/` (43 files; loaded via `AppServiceProvider.php:181`). `server/database/migrations/` does not exist.
- API prefix `/api/v1`; full route map in P28 matrix companion file §Route map. Web routes are a 4-line SPA shell (`routes/web.php`).
- Frontend: Vue 3 SPA (no vue-router; state-based views in `auth/AuthHost.vue:192-204`), 19 Pinia stores, custom `fetch` wrapper, Sanctum Bearer in localStorage. NO Inertia despite AGENTS.md flow language.
- Mobile: NativePHP 4.2 EDGE PHP components + Blade → native Android views; 15 native routes (`routes/native.php`).

### B.2 Capability statuses (condensed matrix; full evidence inline in audit session)

| Capability | Status | Key evidence |
|---|---|---|
| Authentication (login/register/session) | IMPLEMENTED_TESTED | AuthController; AuthApiTest(8); throttle:auth 5/min |
| Email verification / password reset / 2FA | NOT_STARTED | no routes/use cases; `email_verified_at` column unused |
| Users/profile | IMPLEMENTED_TESTED | ProfileController; ProfileApiTest(7) |
| Workspaces CRUD + default | IMPLEMENTED_TESTED | WorkspaceController; WorkspaceApiTest(10); migration 2026_08_26_000001 |
| Workspace switching | IMPLEMENTED_TESTED (client-authoritative) | localStorage active workspace + `ResolveWorkspaceContext`; server default via `POST /workspaces/{id}/default` |
| Goals / Milestones / Programs | IMPLEMENTED_TESTED | GoalApiTest(10), MilestoneApiTest(11), ProgramApiTest(11) |
| Tasks (CRUD, subtasks, partial-complete, auto-swap, quick capture) | IMPLEMENTED_TESTED | TaskController (12 routes); TaskApiTest(14) |
| Today / Week / Month (read models) | IMPLEMENTED_TESTED | ScheduleQueryService dayView:49/weekView:144/monthView:179; ScheduleApiTest(10) |
| Progress events + Analytics | IMPLEMENTED_TESTED | ProgressEventController; AnalyticsController (work-life/overview/pillars/heatmap); AnalyticsApiTest(17) |
| Review (reflection) | PARTIAL | no dedicated /review API; EOD reconcile command + native ReviewScreen consume /today + analytics |
| Knowledge links / Notes (+search tsvector) / Canvas | IMPLEMENTED_TESTED | KnowledgeLinkApiTest(27), NoteApiTest(11), CanvasApiTest(23); NO note deletion endpoint |
| Notifications | IMPLEMENTED_TESTED (DB-poll only; no mail/push/broadcast channel) | NotificationsApiTest(5); creators: EOD prompt, break-end |
| Assets/attachments | IMPLEMENTED_TESTED | 3 files/task, 5MB, MIME allowlist, private disk; AttachmentApiTest(9) |
| Offline sync (server reconciliation) | NOT_STARTED | no sync endpoints/ledger; client-side IndexedDB queue exists (`resources/js/offline`) |
| Recovery (missed-task) | IMPLEMENTED_TESTED | RecoveryApiTest(11) |
| Search | IMPLEMENTED_TESTED (notes only) | KnowledgeSearchApiTest(9) |
| Account deletion | NOT_STARTED | no endpoint/use case |
| Data export | PARTIAL (activity logs JSON/CSV only; ICS export) | ActivityLogController:52; ScheduleExportController |
| Admin/operator surfaces | NOT_STARTED | none (all 39 controllers searched) |
| Scheduler engine | IMPLEMENTED_TESTED | ScheduleDraftGenerator + 8 hard rules + 9-component lexicographic ranking; deterministic (no RNG); simulation suite 14 tests |
| Hard Landscape CRUD + collision | IMPLEMENTED_TESTED | HardLandscapeController; 409 overlap; HardLandscapeApiTest(9) |
| Recurrence expansion | PARTIAL (defect gap) | expanded ONLY in ICS export (`ExportScheduleIcsUseCase:100-114`); Today/drafts match raw timestamps → recurring events invisible on future dates in views |
| Schedule Overrides (Permanent Shift / One-Time Exception) | SCAFFOLDED→PARTIAL | stored+validated, never applied to effective schedule (`SchedulePrecedence` unused; no resolution consumer) |
| Locked tasks | PARTIAL | enforcement tested; no producer sets locked=true (`ScheduleDraftController:336-337` hardcodes false) |
| Dynamic Rescheduler | IMPLEMENTED_TESTED (user-initiated only; no automatic trigger) | DynamicRescheduler; stale→409 |
| Preview before apply | IMPLEMENTED_TESTED | /schedule/draft + /schedule/reschedule pure; apply endpoints transactional |
| Schedule history | PARTIAL | superseded assignment rows hard-deleted (no archival table); overrides/locked/manual preserved |
| Scheduler run lock / weekly job / Sync Now | NOT_STARTED | FR-27 job + FR-29 Sync Now absent; drafts synchronous on demand |
| KRS PDF import | IMPLEMENTED_TESTED | KrsPdfParser (smalot), stage→preview→confirm/discard, transaction; KrsImportApiTest(9). Caveat: confirmed rows become recurring events → recurrence gap applies |
| ICS import/export | IMPLEMENTED_TESTED | IcsParser (RFC-5545 subset, 17 unit tests); export expands recurrence |
| AI provider abstraction | IMPLEMENTED_TESTED | AiProvider contract; Ollama/OpenAI-compatible/Mock/Disabled; resolver BYOK→saved→env |
| AI proposal workflow | IMPLEMENTED_TESTED | pending-only persistence; accept/reject/edit; NO auto-accept path (grep 0) |
| AI usage ledger/metering | IMPLEMENTED_TESTED | ai_runs + credits + cost columns; AiUsageTest(12) |
| AI quotas/BYOK | IMPLEMENTED_TESTED | throttle:ai 10/min + daily caps + budget preflight; BYOK encrypted, entitlement-gated |
| Entitlements/Plans | IMPLEMENTED_TESTED | free/pro/power; max_workspaces 1/5/15; ai_credits 20/300/1000 (deprecated-baseline values); reserved keys unenforced |
| Billing/Midtrans | IMPLEMENTED_TESTED (sandbox) | MidtransGateway (Subscriptions API, no SDK); sha512 webhook verify; idempotency; one-active guard; cancel/resume; refund recorded; chargeback→uncertain; ChargebackResolved unmapped |
| AI observability/analytics events (product) | PLANNED | zero SDKs installed |
| P28 UX layer | see companion P28 matrix | — |

---

## C. Current P28 reality

Verified: **19 DONE / 11 TODO / 1 GATED = 31 items** (not 30). TASK.md DONE labels for 001-008 + TPI-000..010 are backed by repository evidence (ui-audit.md §10.1-10.9, `tests/e2e/tests/p28-ux-audit.spec.ts` 11 tests ×3 engines, adoption-matrix.md, licenses.md, architecture.md, deployment.md, compose profiles).
Contradictions found (details in P28 matrix file):
- P28-009 (TODO) — analytics interpretation is already substantially implemented (P17-017/019 legacy: `InterpretationStrip.vue`, `analytics/interpretation.ts`, `ChartMeta.vue` period/unit/legend, period comparison, empty-state handling, `NextActionBanner`).
- P28-010 (TODO) — FeatureHelp.vue wired into 7 views + `feature-education.spec.ts` (P17-008/009).
- P28-011 (TODO) — `docs/state-machine-ui.md` + `NativeStateTest` cover parts.
- P28-012 (TODO) — `accessibility.spec.ts` (axe critical/serious, keyboard-only, reduced-motion) exists.
- P28-013 (TODO) — golden-journeys/journeys/canonical/journey-c-e specs exist; specific A-E legs + 3-engine record not captured under P28-013.
- RET mapping rows RET-004/011/012 say TODO while their owners (P28-006/007/TPI-009) are DONE — stale mapping table.
- P28-RET-007 depends on P32-001 (future phase, TASK.md:8696) and its Notes say "P31 event taxonomy" — ambiguous forward dependency.
- `tests/e2e/tests/retention-failures.spec.ts` (P28-RET-013 target) does not exist — TODO is accurate.

## D. Current architectural reality
- Clean layering enforced in practice: controllers thin → use cases → domain → Eloquent repositories → Postgres. No business logic in controllers found; scheduling algorithm fully in Domain.
- Authorization: NO Laravel Policies; manual `user_id` wheres + `ResolveWorkspaceContext` validation. Single-owner product; no RBAC.
- Optimistic versioning on tasks + schedule assignments (409 conflicts) — implemented and tested.
- Transactions on all multi-entity mutations audited (draft apply, imports confirm, AI accepts, webhook apply).
- AI never mutates authoritative state without user accept (verified by search; no queue jobs exist at all — `app/Jobs` absent).

## E. Current Workspace semantics (CURRENT BEHAVIOR, from code)
- Single-owner container: `workspaces.user_id` FK, no membership/roles table.
- workspace_id exists ONLY on: goals, programs, tasks, notes, canvases (migration 2026_08_26_000001 SCOPED_TABLES). Everything else user-scoped.
- Active workspace: browser localStorage (`kinevo.active-workspace`, id or 'all'), precedence: `?workspace=` deep link > stored > server default. Mobile "switch" = `POST /workspaces/{id}/default` (server-persisted); web "switch" is device-local.
- Today/Week/Month/Hard Landscape/Notifications: cross-workspace by design (user-scoped queries; no workspace filter). Lists + analytics honor workspace (analytics accepts ?workspace_id but web UI never sends it).
- AI context: workspace-bounded only in goal-breakdown prompt (name/type); other AI paths have no workspace dimension; ai_runs/ai_proposals have no workspace_id.
- Hard Landscape: global (user-scoped, no workspace_id).

## F. Current runtime reality
CURRENT: postgres 17; database queue + dedicated worker; `schedule:work` (eod:reconcile 21:00/23:59, break:notify-end 20:30); local disk storage (S3 env-gated, EXPERIMENTAL); certbot TLS profile; backup/restore scripts + compose backup service (daily, gzip, optional S3/R2 copy); prod smoke script covering deploy→migrate→journey→backup→restore.
EXPERIMENTAL: S3 disk; Android APK build (manual script, PHP 8.5 lock flip hack).
PLANNED/ABSENT: Redis, Horizon, object-storage service, observability stack (Sentry/GlitchTip/Langfuse/OpenPanel — zero SDKs), Cloudflare wiring (docs only), Coolify (zero mentions).
ANOMALY: `infrastructure/scripts/backup.sh` and `restore.sh` are EMPTY (0-byte) files shadowing real `scripts/` versions (compose mounts the real ones).

## G. Current commercial reality
- Plans free/pro/power; prices Pro 49.900 IDR / Power 89.900 IDR whole-Rupiah (`config/billing.php:36-37`) — matches revisi-finance delta; ADR-013 (34.9k/49.9k) superseded-as-history.
- AI credits 20/300/1000 (config) vs docs 20/150/500 (DECISION_REQUIRED / deprecated) — live values diverge from every documented baseline; explicitly labeled deprecated-baseline.
- Midtrans sandbox CONFIGURED (6 env vars present locally; `.env.example` lacks them); production flip pending.
- Lifecycle: pending→active→past_due→cancel_at_period_end→canceled→expired; out-of-order protection; idempotent webhooks; refunds recorded; chargeback→uncertain (no silent entitlement change); ChargebackResolved NOT handled.
- Gaps: no proration (deferred), no grace duration (deferred to P30), paid→paid upgrade blocked by one-active guard (unresolved inconsistency), `/saas/plan` missing from openapi.yaml.

## H. Current mobile reality
- NativePHP 4.2 PHP-component shell (not WebView); 15 screens; bottom nav Today/Capture/Workspace/More.
- Auth: Sanctum token in PLAINTEXT `storage/app/kinevo_token.txt` (hardening deferred). 401→logout.
- Offline: capture marked `queued` with operation_id — state only, NO persisted on-device queue; no server reconciliation exists.
- Missing on mobile: Hard Landscape, Import, uploads/attachments, push (needs provider), native billing (web-first, read-only plan state + deep link).
- Tests: NativeMobileShellTest (15 routes + views), NativeStateTest (entitlement/AI-unavailable/network/conflict branches). Device evidence recorded in TASK.md P27 entries (AVD, TalkBack, font-scale).
- Build: manual `infrastructure/nativephp/linux-build/build-android-apk.sh`; app id `com.developer.lightglowrapid`; no CI/Makefile APK target.

## I. Current third-party reality
| Dependency | Installed | Mode | Notes |
|---|---|---|---|
| laravel/framework 13.29.0, sanctum 4.3.3, tinker | yes | EMBED | |
| nativephp/mobile 4.2.0 | yes | EMBED | embedded PHP 8.4.24 |
| smalot/pdfparser 2.12.5 | yes | EMBED | KRS import |
| @excalidraw/excalidraw 0.18.1 (+react 19.2.8 island) | yes | EMBED behind adapter | ExcalidrawCanvasAdapter implements Kinevo CanvasAdapter; hosts never import Excalidraw directly |
| @tiptap/* 3.30.2 | yes | EMBED behind adapter | TiptapEditorAdapter implements EditorAdapter |
| vue 3.5.41, pinia 4.0.3, tailwind 4.3.3, vite 8.2.2 | yes | EMBED | |
| Midtrans | NO SDK | ADAPTER+SERVICE (self-written) | MidtransGateway |
| Ollama | service image (profile ai) | SERVICE behind AiProvider | opt-in |
| Pic Smaller / Uppy / Filament / Gotify / Lago / OpenPanel / Langfuse / GlitchTip / Open SaaS patterns | ZERO lockfile presence | DOCUMENTED_ONLY | adoption-matrix/licenses ledger; ADR-014 |
| Inertia | NOT installed | — | AGENTS.md/README mention Inertia-era flow language (drift) |
| Heroicons SVG subset | vendored in KIcon.vue | EMBED | licenses ledger |

## J. Documentation drift (high-level; details in DRIFT REGISTER file)
1. SRS FR-27 weekly scheduler job + FR-29 Sync Now + FR-25 override semantics: NOT implemented.
2. `docs/offline-sync.md` promises IndexedDB queue + server reconciliation; server side NOT_STARTED.
3. Identity: email verification / password reset required by SRS/P29, absent in code.
4. AGENTS.md/README Inertia-era flow language vs fetch-based SPA.
5. `docs/api/openapi.yaml` (131 paths) missing implemented `/saas/plan`.
6. AI credits: config 20/300/1000 vs billing docs 20/150/500 (decision-required) vs revisi-finance (forbids new numbers until FinOps).
7. ADR-009/010/011 referenced in 5+ files but files ABSENT from docs/adr/.
8. `docs/browser-e2e.md` last record 2026-08-26 while TASK.md cites 2026-08-29 browser runs.
9. `.env.example` missing AI_MAX_* and MIDTRANS_* variable families.
10. `docs/billing.md:150` cites nonexistent "BillingDomainTest".
11. documentation-inventory.md (2026-08-26) predates three root planning/patch docs now tracked at root.
12. CHANGELOG versions without git tags (release lifecycle unexercised).
13. billing upgrade path (docs "upgrade = new checkout") vs one-active guard rejection — unreconciled.

## K. Highest-risk inconsistencies
1. **Recurrence/Override resolution gap** — recurring Hard Landscape + KRS-confirmed weekly courses never appear on future dates in Today/drafts; Permanent Shift/One-Time Exception change nothing. Tests pass because no test asserts effective-date visibility. Highest product-risk defect.
2. **Offline promise vs zero server reconciliation** — offline-sync.md + client queue + mobile `queued` states exist, but no operation-UUID ledger endpoint; offline mutations have no canonical reconciliation path.
3. **Locked tasks / overrides have no producer** — safety machinery is tested but unreachable from the API surface.
4. **Scheduler docs promise job + lock + Sync Now** — engine is synchronous, unlocked, manual only.
5. **Commercial upgrade path contradiction** (guard vs documented upgrade-by-checkout).
6. **Superseded schedule assignments hard-deleted** — no history table (SRS FR-25 history postcondition).
7. **P28 status drift** — TODO items with substantial existing implementation (009/010/012) and stale RET mapping rows distort the gate baseline.
8. **Env/contract drift** — openapi missing /saas/plan; .env.example gaps; ADR-009..011 dangling.

## L. What must be resolved before further fundamental implementation
1. Decide and implement effective-schedule resolution (recurrence expansion + override application) OR formally descope — this changes Today/Week/Month semantics everywhere.
2. Reconcile the offline contract: either implement the server operation-ledger reconciliation or descope offline-sync.md to cache-only.
3. Resolve TASK.md P28 status corrections + RET mapping (cheap; unblocks an honest P28-014 gate).
4. Resolve AI credit numbers (FinOps decision) and the paid→paid upgrade path.
5. Rebuild the missing ADRs (009-011) or remove dangling references.
6. Complete identity baseline (email verification + password reset) before billing production flip.
7. Regenerate docs/api/openapi.yaml from routes; sync .env.example.

---

## FINAL SUMMARY

1. Exact implementation status: core personal-productivity product (auth, workspaces, goals/milestones/programs/tasks, Today/Week/Calendar, deterministic scheduler engine, KRS/ICS import, knowledge/notes/canvas, notifications DB-poll, analytics, AI with proposal-gate + metering + BYOK, sandbox billing, NativePHP Android shell) is IMPLEMENTED_TESTED at contract level; offline-sync server reconciliation, Sync Now, weekly scheduler job, override/recurrence application, account deletion, admin surfaces, push, email identity flows are NOT_STARTED/PARTIAL.
2. Verified P28 completion count: 19/31 DONE (TASK claim "19/30" undercounts items; 5 TODO items hold substantial pre-existing implementation evidence).
3. Highest-risk mismatches: recurrence/override never applied to effective schedule; offline reconciliation absent vs documented; locked/override no producer; scheduler job/lock/Sync Now absent; upgrade-path contradiction; ADR-009..011 dangling.
4. Unresolved facts (UNKNOWN): whether paid→paid upgrade is intended product behavior; whether P28-013 journeys A-E were ever run as specified (records not found in browser-e2e.md); runtime coverage of billing console commands; Coolify applicability.
5. Recommended starting point for convergence: (a) apply the P28 status corrections + RET mapping sync from the P28 reality matrix; (b) make the recurrence/override resolution decision (ADR); (c) reconcile offline contract scope; (d) then resume phase execution (P28 remaining → P29 identity).
6. **"NO IMPLEMENTATION CHANGES WERE MADE DURING THIS AUDIT."**
