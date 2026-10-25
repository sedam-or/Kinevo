# Phase 0 — Repository analysis and verdict

Status: **Accepted** (owner approved the plan on 2026-10-25). Evidence date: 2026-10-25.
This is the frozen analysis of the legacy code. Legacy paths are cited as `legacy-v0:<path>`;
read them with `git show legacy-v0:<path>`, never from the ZIP (D-06).

Labels: **[run]** = a command I executed (output summarised). **[src]** = read in source.
**[inf]** = my inference. **[doc]** = a claim made in legacy docs, not verified.

## 1. What existed

| Area | Facts |
|---|---|
| Backend | Laravel 13 / PHP 8.4, DDD-style modular monolith: 579 PHP files ≈ 42k LOC across 29 modules (`legacy-v0:server/app/{Domain,Application,Infrastructure}`) [src] |
| Frontend | Vue 3 + Pinia SPA ≈ 31k LOC, 70 `.vue` files. No router; views switched by `v-if` (`legacy-v0:server/resources/js/auth/AuthHost.vue:233-242`). React shipped only to host Excalidraw [src] |
| API | 183 `Route::` calls in a single `legacy-v0:server/routes/api.php` [src] |
| Database | 49 PostgreSQL migrations at the repo root `legacy-v0:database/migrations`, loaded by `legacy-v0:server/app/Providers/AppServiceProvider.php:210` [src] |
| Docs | ≈ 40k lines in 103 Markdown files, about as large as the code: constitution, SRS v3, 17 ADRs, a P0→P39 phase program [src] |
| History | 255 commits between 2026-08-17 and 2026-08-31, agent-driven [run: `git log`] |

### Verification baseline at `legacy-v0` (c3bab8c) [run, 2026-10-25]

| Check | Environment | Result |
|---|---|---|
| PHPUnit | `php:8.4-cli` container | **1125 tests, 3943 assertions, 1 failure, 3 notices.** Failure: `Tests\Feature\Api\FocusSessionsApiTest::test_recommendation_uses_recent_completion_patterns` expected `user_patterns`, got `baseline`. [inf] The test depends on wall-clock time, so it passed when written and now fails. This is direct evidence for the injected-clock rule (D-17). |
| PHPStan | same | No errors |
| Pint | same | Pass (754 files) |
| vue-tsc typecheck | Node 22.17 | Pass |
| Vitest | Node 22.17 | 76 files, **535/535 passed** (legacy docs claimed 531) |
| Vite build | Node 22.17 | Pass, with a warning about chunks over 500 kB |
| npm audit | Node 22.17 | **14 vulnerabilities**: 3 critical, 5 high, 2 moderate, 4 low |
| composer audit (`--locked`) | PHP 8.5 host | **4 advisories**: laravel/framework 1, league/commonmark 2, symfony/http-foundation 1 |
| Playwright E2E + axe | — | **Not run.** Legacy docs claim 105/105 across 3 engines; no CI artifact exists. |
| Host PHP 8.5 | host | `composer install` fails without `--ignore-platform-reqs` (lock resolved for 8.4); `pdo_sqlite` missing on the host. Environment limits, not code results. |

## 2. Contradictions between docs and code

1. **Stack.** Legacy ADR-002 and AGENTS say "Vue + Inertia". The code has no Inertia and no router, and ships both Vue and React (`legacy-v0:server/package.json`). [src]
2. **Calendar.** SRS FR-11 says there is no Calendar nav item. One exists: `legacy-v0:server/resources/js/shell/navigation.ts:22`, `keyboard.ts:12`. [src]
3. **Canvas offline.** FR-57 says canvas offline edits reconcile. The server allowlist omits canvas (`legacy-v0:server/app/Domain/OfflineSync/ValueObjects/OperationType.php:14-33`) while the client maps `canvas:update` (`legacy-v0:server/resources/js/offline/http-applier.ts:32`). [src]
4. **"No last-write-wins"** vs dead code `legacy-v0:server/resources/js/offline/lww-policy.ts`. [src]
5. **"Stable 409 on conflict."** Offline reconcile always returns HTTP 200 with a per-operation status (`legacy-v0:server/app/Http/Controllers/Api/SyncReconcileController.php:26-87`). [src]
6. **Transaction rule.** START/COMPLETE of execution run without a DB transaction (`legacy-v0:server/app/Application/Execution/CompleteExecutionUseCase.php:37-73`). [src]
7. **"Core never hollowed out."** Self-host is capped by Free-plan entitlements (`legacy-v0:server/config/saas.php:37-75`, `legacy-v0:server/app/Application/Workspaces/CreateWorkspaceUseCase.php:25-27`). [src]
8. **"WCAG 2.2 AA verified."** axe runs only the `wcag2a`/`wcag2aa` tags on 4 pages, not in CI (`legacy-v0:tests/e2e/tests/accessibility.spec.ts:32,65`). [src]
9. **UI freeze.** CONTRIBUTING enforces a freeze that AGENTS calls lifted. TASK.md is in Indonesian; the rest is English. [src]
10. **Stale docblock.** `legacy-v0:server/app/Domain/Scheduling/Resolution/EffectiveLandscapeResolver.php:25,53` says overrides "MUST NOT alter output", but the code applies them. [src]
11. **AI breakdown.** The product promises "goal → milestones → tasks". Accept creates milestones only (`legacy-v0:server/app/Application/Ai/AcceptAiProposalUseCase.php:47`). The default driver is `disabled`. The mock driver returns non-JSON, which yields 422. [src]

## 3. Hard-coded business values belonging to one customer

| Value | Location | New home |
|---|---|---|
| Prices IDR 49,900 / 89,900; QRIS/GoPay fees; Midtrans | `legacy-v0:server/config/billing.php:9-50`, `.../Infrastructure/Billing/MidtransGateway.php:106` | billing policy (cloud, D-18/D-25) |
| "Rp", `id-ID` formatting, one Bahasa string in an English UI | `legacy-v0:server/resources/js/saas/PlanSettingsView.vue:48-62` | i18n catalogue (D-28) |
| AI credits 20/300/1000 | `legacy-v0:server/config/saas.php:22-26` | `ai.allowance.*` policy |
| 7-zone timezone allow-list; locale `en` only | `legacy-v0:server/app/Domain/Identity/ValueObjects/ProfileSettings.php:12-22` | any IANA zone (D-17), en+id (D-28) |
| KRS parser with Bahasa day names | `legacy-v0:server/app/Application/Imports/KrsPdfParser.php:23-31` | dropped (D-16) |
| Default task duration 45 min | `legacy-v0:server/app/Application/Scheduling/AssembleScheduleInput.php` | `tasks.default_estimate_minutes` policy |
| Weekly draft cron at 04:00 | `legacy-v0:server/bootstrap/app.php:31` | `scheduler.weekly_draft.local_time` policy |
| Token TTL 30 days; rate limits | `legacy-v0:server/config/sanctum.php:53`, `.../AppServiceProvider.php:178-206` | instance auth settings (D-11) |

## 4. Data-model and architecture limits that block the vision

- **No module system.** No toggle, registry or edition switch exists. One provider binds every repository (`legacy-v0:server/app/Providers/AppServiceProvider.php:104-159`). Nav and views are hardcoded. Coupling [src: grep of `use App\...`]: Scheduling imports 11 modules, Analytics 10, Ai 7, Boosts 6. Removing a feature means editing many files. This is the main blocker for D-01.
- **Ownership by `user_id`** on about 36 tables. `workspace_id` scoping is applied by hand in 6 controllers (`legacy-v0:server/app/Application/Workspaces/ResolveWorkspaceContext.php`). Today, Schedule and Execution ignore workspaces. There is no database-enforced isolation, so a cloud deployment depends on every query being written correctly.
- **Commerce in core.** Billing and SaaS live in the MIT core. `legacy-v0:server/app/Application/Billing/BillingService.php:11,31` depends on the concrete Midtrans class. The Core/Cloud split was never done.
- **Postgres-specific schema** (tsvector trigger, jsonb) but tests run on SQLite, and CI has no Postgres job.

## 5. Security gaps

1. No authorization policies. Ownership relies on about 149 hand-written `where('user_id')` filters. [src]
2. Bearer token in `localStorage` (`legacy-v0:server/resources/js/api/token.ts:1`), 30-day life, no rotation, empty `token_prefix`. [src]
3. No log redaction, despite SECURITY.md promising no PII, prompts or note contents in logs. [src]
4. Draft apply trusts a client-sent draft without re-running hard constraints, and the version check sits outside the transaction (`legacy-v0:server/app/Application/Scheduling/ApplyScheduleDraftUseCase.php:45-197`). [src + inf race]
5. Offline retry mints a new operation id, so an operation can be applied twice (`legacy-v0:server/resources/js/offline/queue.ts:103`) [inf]. Transient errors are recorded permanently as `rejected` (`legacy-v0:server/app/Application/OfflineSync/OfflineReconciliationService.php:132`). [src]
6. Dependency advisories: 14 npm, 4 composer (§1). [run]
7. `opencode.json` grants blanket edit/bash permission. The dev compose publishes Postgres on 5432 with a fixed password. [src]
8. **Personal data in fixtures**: a real-looking personal email address is hardcoded as the E2E owner
   account in `legacy-v0:tests/e2e/tests/helpers.ts`, `legacy-v0:tests/e2e/tests/accessibility.spec.ts`,
   `legacy-v0:tests/e2e/scripts/seed-journey-c.sh` and `seed-journey-s.sh`. It remains in public git history. New
   fixtures must use `example.test` addresses only (`spec/testing.md`). [src]
9. OSS hygiene: no provenance for `docs/assets/*` and the favicon; `CITATION.cff` has no `authors`; stale license ledger; vendored root `vendor/` (22 MB Pint binary); `:latest` images; Node 20 vs 22 mismatch. [src]

## 6. Keep / rewrite / drop

| Keep as behaviour (spec input, not code) | Rewrite | Drop from 1.0 |
|---|---|---|
| Deterministic two-layer scheduler semantics (legacy ADR-003/015/016) | All application code (D-02, D-07) | Midtrans/IDR in core (billing becomes a cloud module, D-25) |
| Hard Landscape + recurrence + overrides | Auth (D-11), authorization (D-12), isolation (D-08) | KRS parser (D-16) |
| Offline operation ledger, idempotent replay (legacy ADR-017) | Module system (D-01) | NativePHP (D-21) |
| AI propose → validate → approve (D-31) | Offline client queue (fix the duplicate-id bug) | Boosts/Recharge/Breaks/Pauses/Adaptive/Recovery (D-24) |
| Core loop and non-goals (legacy product constitution §4, §9) | UI and design system | The P0→P39 governance machinery |
| Legacy test scenarios as acceptance fixtures | Entitlements, replaced by module toggles + policies | |

## 7. Verdict

**What must be true for the vision to work** (first principles):
1. One build artifact serves cloud, self-host and 1-click templates.
2. Features switch on/off per install without code edits, behind enforced boundaries.
3. Scheduling is deterministic, and the same logic can preview offline on clients.
4. One maintainer plus AI implementers can keep it correct.
5. Hosting cost stays low.
6. The line between open core and cloud is clear.

**Vision — YES, narrowed.** Open core + self-host + an owned cloud is coherent. The target user
(ambitious students and professionals with many projects) matches the legacy ICP. "Drag and
drop" is delivered as per-install module switches plus easy install (D-01). A plugin API or no-code
builder is not justified by any evidence and would multiply cost. **User demand is not
validated:** the repo contains no interviews or usage data. The direction stays provisional
until the validation exercise in `spec/roadmap.md` runs.

**Rust rebuild — YES (owner choice D-02), as a respecified rebuild.** Benefits:
- a single static binary with the SPA embedded (needs 1 and 5);
- one scheduler crate shared by server, WASM and, later, Kotlin (need 3);
- compiler-enforced types and module visibility, which suit AI implementers (needs 2 and 4).

Costs:
- the working legacy product is frozen while 1.0 is rebuilt;
- CRUD work is slower;
- the contributor pool is smaller than for PHP or TypeScript.

Mitigations:
- `legacy-v0` stays runnable;
- milestones deliver the core loop first;
- scheduler parity fixtures come from legacy tests.

**Main risk:** second-system scope creep, controlled by the milestone gates in `spec/roadmap.md`.

**Stack and main risk of each** (detail in `spec/architecture.md` and ADRs):

| Choice | Main risk |
|---|---|
| axum + sqlx + PostgreSQL | sqlx compile-time checks need a database (or offline metadata) in CI |
| React + Vite SPA embedded | Full UI rewrite |
| PWA → Capacitor Android | WebView UX ceiling |
| PostgreSQL as queue, no Redis | Throughput ceiling; acceptable at the expected scale [inf] |
| Alpine image | Larger attack surface than distroless |

Facts checked against official docs on 2026-10-25:
- axum 0.8.9 is built on tokio/hyper/tower (docs.rs/axum).
- UniFFI fully supports Kotlin (mozilla.github.io/uniffi-rs).
- Capacitor Android plugins may be written in Kotlin (capacitorjs.com/docs/plugins/android).
- A TWA needs PWA criteria plus Digital Asset Links (developer.android.com).
- FrankenPHP can embed PHP apps into one binary (frankenphp.dev/docs/embed). This was the legacy-stack alternative, rejected by D-02.
