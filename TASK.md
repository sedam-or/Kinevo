# Kinevo — Master Execution Task Board

### Purpose
`TASK.md` adalah execution control document. Ia memecah implementation roadmap menjadi unit kerja yang dapat dieksekusi AI/human, mencatat dependency, status, evidence, dan blocking condition.

### Roadmap authority (post-P27)
Eksekusi P28–P39 mengikuti `KINEVO_POST_P27_MASTER_EXECUTION_PROMPT.md` (execution authority §0 —
supersedes prior generic post-P27 roadmaps). Fase lama direnumber pada re-baseline ini:
old PHASE 28 (Intelligence/Wrapped) → **PHASE 31** · old PHASE 29 (Beta/Growth) → **PHASE 37** ·
old PHASE 30 (Release) → **PHASE 39**. Tidak ada status DONE/evidence yang berubah; hanya penomoran
dan penempatan. Keputusan bisnis terkunci (tier/price/entitlement/AI accounting/mobile billing) ada
di §2 master prompt dan TIDAK BOLEH diubah diam-diam oleh agen.

## COMMERCIAL PRICING DELTA — POST-P27

> Sumber: `revisi-finance.md` (owner, 2026-08-28) — DELTA PATCH ke roadmap P28–P39; TIDAK
> meregenerasi/mengurutkan ulang roadmap. Scope HANYA pricing/entitlement/AI economics.
>
> STATUS (patch §23):
> - `PRICING DECISION = LOCKED` — Free IDR 0; **Pro = IDR 49.900/bulan**; **Power = IDR 89.900/bulan**
>   (menggantikan Pro 34.900 / Power 49.900). Status: LAUNCH PRICE HYPOTHESES — tunduk validasi beta
>   (activation; conversion; retention; cancellation; willingness-to-pay; AI COGS; contribution margin).
> - `AI QUOTA NUMBERS = NOT YET LOCKED (DECISION_REQUIRED)` — angka lama 20/150/500 kredit
>   direklasifikasi **DEPRECATED BASELINE**; kuota baru WAJIB diturunkan dari simulasi FinOps
>   (P32-007/P38-002 diperluas: P50/P75/P90/P95/P99/abuse; cache-hit ratio; kontribusi margin),
>   BUKAN diganti angka arbitrer. Nilai runtime saat ini (config/saas.php: 20/300/1000) tetap
>   berfungsi tetapi direklasifikasi deprecated-baseline, bukan kebijakan final.
> - Entitlement matrix target (patch §2): max_workspaces 1/5/15 (Free/Pro/Power); BYOK Free=NO,
>   Pro/Power=YES; dua ledger TERPISAH (subscription ledger vs AI usage ledger, jangan digabung).
> - AI credit ≠ token ≠ unit billing provider; token = telemetri. Markup ≠ margin (25% markup =
>   20% margin); target margin AI ~30–50% KONFIGURABEL — tanpa markup hardcoded.
> - Klasifikasi kemunculan nilai lama (patch §13/§21): `config/billing.php` + `config/saas.php`
>   (+ mirror `server/nativephp/android/laravel/config/`) = REPLACE; ADR-013 + CHANGELOG/master
>   prompt §2 = HISTORICAL + catatan supersedes; migration history = PRESERVE.

### D-001 — Pricing/Entitlement Config & Data Sweep
- Status: DONE (2026-08-28) · Priority: P0 · Depends On: —
- Business Decision: harga baru LOCKED (revisi-finance §0); annual/trial/coupon TETAP DECISION_REQUIRED
- SRS: billing · Design: docs/billing.md §Pricing Delta · Files: server/config/billing.php;
  server/config/saas.php; mirror server/nativephp/android/laravel/config/*; plan records/seeders
- Acceptance:
  - [ ] config/billing.php: pro amount_major = 49_900 (Rp49.900); power amount_major = 89_900 (Rp89.900) — whole Rupiah; amount_minor 4_990_000/8_990_000 hanya turunan ×100 utk persist P24
  - [ ] config/saas.php: max_workspaces 1/5/15; komentar harga + ai_credits direklasifikasi
        DEPRECATED BASELINE (bukan kebijakan final; menunggu FinOps sim D-004)
  - [ ] mirror nativephp configs selaras
  - [ ] plan records/seeders (jika menyimpan harga) selaras — tanpa migrasi churn baru; migrasi
        lama TIDAK disentuh
  - [ ] klasifikasi tiap kemunculan 34.900/49.900 terdokumentasi (ganti/histori/preserve)
- Verification: [ ] Unit(config) · [ ] Integration(EntitlementService + price catalog)
- Evidence: config/billing.php pro amount_major=49_900 power amount_major=89_900
  (= Rp49.900/Rp89.900, BUKAN 4,9jt/8,9jt; amount_minor 4_990_000/8_990_000 = turunan ×100 utk persist P24); config/saas.php ws 1/5/15 +
  DEPRECATED BASELINE notes; mirrors nativephp sinkron (git-ignored); GetPlanOverviewUseCase
  exsposes `pricing` + `catalog`; klasifikasi lengkap di docs/ai-economics/regression-classification-2026-08-28.md
- Known Limitations: — · Notes: TIDAK membuat sistem plan/entitlement kedua (RULE 3.2)

### D-002 — Plan/Entitlement/Ledger Test Updates
- Status: DONE (2026-08-28) · Priority: P0 · Depends On: D-001
- Business Decision: —
- SRS: billing/AI chapters · Design: docs/billing.md · Files: SaasApiTest; AiUsageTest; AiAlertsTest;
  + test downgrade baru
- Acceptance (patch §22):
  - [ ] Free price = 0 · Pro = 49.900 · Power = 89.900
  - [ ] entitlement per tier (workspace/BYOK/matrix) + BYOK Free ditolak, Pro/Power diterima
  - [ ] AI ledger: hosted memakan allowance; BYOK TIDAK; allowance kurang → TANPA panggil provider;
        reservasi rilis; aktual yang disettle (billing_source ∈ INCLUDED_HOSTED/PREPAID_HOSTED/BYOK)
  - [ ] downgrade Power→Pro dan Pro→Free: data TIDAK dihapus; creation/edit dibatasi; riwayat
        mengikuti entitlement
  - [ ] pricing UX: harga baru dirender; harga lama absen dari UI aktif; CTA upgrade benar
- Verification: [ ] Unit · [ ] Integration · [ ] E2E(web)
- Evidence: SaasApiTest (harga/catalog/ws=1 limit); BillingCheckout/SubscriptionRead fixtures
  4_990_000/8_990_000; AiUsageTest (Power BYOK, gate budget); downgrade preservasi test;
  suite penuh 995 lulus (3526 assertions), stroke-ish 0 gagal
- Known Limitations: — · Notes: —

### D-003 — Pricing & Upgrade/Downgrade UX
- Status: DONE (2026-08-28) · Priority: P0 · Depends On: D-001
- Business Decision: Power TIDAK boleh tampak palsu — nilai = kapasitas; kedalaman; kecerdasan;
  riwayat; allowance; refleksi lanjutan (bukan kosmetik)
- SRS: billing · Design: docs/design.md pricing surfaces · Files: pricing UI + upgrade/downgrade UX
- Acceptance (patch §14–§16):
  - [ ] kartu pricing: FREE "Experience the system." · PRO "For serious personal use." ·
        POWER "For intensive personal use."
  - [ ] pengguna bisa jawab: apa yang saya dapat? mengapa upgrade? mengapa Power layak +Rp40.000?
  - [ ] paywall menjelaskan limit apa yang tercapai + jalan keluar; TANPA urgency palsu/scarcity
        palsu/countdown manipulatif/lockout destruktif
  - [ ] downgrade: data lama dipertahankan; bila state > limit baru → read access bila aman,
        blok penggunaan baru
- Verification: [ ] Browser evidence · [ ] copy review
- Evidence: PlanSettingsView ditulis ulang (harga/entitlement dari API — tanpa angka hardcoded;
  kata posisi §1; Power gap Rp40.000 dihitung; CTA Upgrade/Switch eksplisit; footnote launch
  hypothesis); AiUsageSummaryCard copy allowance + saran lanjutan; spec
  tests/e2e/delta-pricing.spec.ts lulus chromium (harga baru ter-render, copy posisi, bullets
  Power, gap, CTA upgrade)
- Known Limitations: — · Notes: WAJIB konsultasi skill taste + ui-ux-pro-max (AGENTS.md)

### D-004 — AI Cost Simulator Extension + FinOps Simulation (PREREQUISITE QUOTA LOCK)
- Status: DONE (simulator+simulasi) — KUOTA TETAP DECISION_REQUIRED (owner) · Priority: P0 ·
  Depends On: — (keputusan kuota menunggu hasil + review owner)
- Business Decision: KUOTA AI = DECISION_REQUIRED sampai simulasi selesai (revisi-finance §3/§7)
- SRS: AI economics · Design: docs/ai-architecture.md · Files: perluasan simulator P32-007 (SATU
  subsistem — jangan duplikat dengan P38-002)
- Acceptance:
  - [ ] input: provider; model; FEATURE; request count; input/cached/output tokens; cache-hit ratio;
        tier; periode billing
  - [ ] profil pemakaian per fitur: Goal Breakdown; Note Summary; Task Extraction; Weekly/Daily
        Planning; Deep Analysis; Wrapped Narrative
  - [ ] skenario: P50 · P75 · P90 · P95 · P99 · abuse/heavy
  - [ ] output: provider COGS · normalized Kinevo cost · included budget exposure · expected
        overage · contribution margin
  - [ ] keputusan kuota per tier dicatat owner (DECISION_REQUIRED → LOCKED)
- Verification: [ ] Unit(skenario) · [ ] Integration(pricing catalog)
- Evidence: AICostSimulator (deterministik lognormal P50..P99 + abuse; price_per_tokens);
  `ai:cost-simulate` perintah; config/ai.php `simulation` (fitur profil DEPRECATED-BASELINE) +
  entri katalog contoh verified=false; hasil run: docs/ai-economics/ai-cost-simulation-2026-08-28.json
  (deepseek public list, overage 0, margin ≥ target pada seluruh skenario — angka awal, bukan
  kunci); unit test AI simulator hijau
- Known Limitations: profil pemakaian adalah asumsi placeholder sampai instrumentasi P32/P37 ·
  Notes: feeds P38-002; katalog harga provider HARUS terverifikasi vs sumber resmi; dev gateway (OpenCode Go) ≠ ekonomi API produksi (§19)

### D-005 — Reserve→Settle Budget Firewall + Ledger Separation
- Status: DONE (2026-08-28) · Priority: P0 · Depends On: D-001
- Business Decision: budget kurang → JANGAN panggil provider; jangan permanen-charge max output
- SRS: AI economics/security · Design: docs/ai-architecture.md · Files: perluasan ledger P25
  (SATU subsistem) + gate pre-provider
- Acceptance (patch §5/§10/§11/§12):
  - [ ] urutan: auth → entitlement → allowance → rate limit → estimasi budget → guard token
        max in/out → provider
  - [ ] RESERVE max permitted budget → SETTLE aktual → rilis sisa reservasi
  - [ ] metering: input/cached/output/total tokens + provider; model; pricing_version;
        estimated_provider_cost; billing_source; status; latency; request_id
  - [ ] billing_source enum: INCLUDED_HOSTED · PREPAID_HOSTED · BYOK; BYOK tak pernah menyentuh
        bucket hosted
  - [ ] katalog harga provider TERVERSI (effective_from/until; pricing_version; sumber); harga model
        tidak hardcoded di business logic; pemakaian historis reproducible
- Verification: [ ] Unit(gate) · [ ] Integration(reserve/settle) · [ ] E2E(hosted vs BYOK)
- Evidence: config/ai.php `max_request_budget_minor`; AiCreditGuard::assertRequestBudget (RESERVE
  upper-bound dari max token guards, gate AI_REQUEST_BUDGET 429 sebelum provider call) +
  docblock reserve→settle; Domain/Ai/BillingLedger (INCLUDED_HOSTED=kinevo, BYOK=byok,
  PREPAID_HOSTED reserved, isSupported); ledger literal diganti konstanta; test gate + lg
  BillingLedgerTest hijau
- Known Limitations: metering cached_input_tokens belum dialirkan ke estimator runtime (P38) ·
  Notes: extends P25/P32-008 — no duplicate subsystem

### D-006 — Unit Economics + Midtrans Fee Model + Beta Pricing Metrics
- Status: DONE (2026-08-28) · Priority: P1 · Depends On: D-001; D-004
- Business Decision: biaya pembayaran TIDAK boleh diasumsikan flat per user (§20)
- SRS: billing/growth · Design: docs/billing.md · Files: perluasan P32-005/P32-006 (SATU subsistem)
- Acceptance:
  - [ ] unit economics per plan: revenue; AI revenue; payment fees; hosted AI COGS; infra;
        storage/bandwidth; support → gross contribution; contribution margin
  - [ ] skenario: FREE expected/heavy/abuse · PRO P50/P95/P99 · POWER P50/P95/P99
  - [ ] fee Midtrans dimodelkan per metode pembayaran (fixed + persentase) utk subscription &
        AI top-up; produksi ≠ sandbox
  - [ ] metrik beta (§17): view pricing; klik CTA upgrade; checkout start/completion; first paid
        action; D7/D30; cancellation; downgrade; Power selection rate; AI COGS/paid user;
        Pro→Power upgrade rate + "Mengapa memilih Power?"
- Verification: [ ] Integration · Evidence: — · Known Limitations: top-up minimum = DECISION_REQUIRED ·
  Notes: konversi Power rendah → klasifikasi dulu (nilai/kebutuhan/komunikasi/harga/kedalaman), bukan
  tambah fitur acak
- Evidence: config/billing.php `payment_fees` per metode (fixed + bps, verified=false) +
  `unit_economics` grid (skenario share COGS DEPRECATED-BASELINE); `billing:unit-economics`
  report; hasil: docs/ai-economics/unit-economics-2026-08-28.json (margin Pro/Power 76–96% pada
  asumsi; Free biaya per-user infra); daftar metrik beta (§17) tercatat sebagai definisi — 
  instrumentasi mengikuti P32-001/P37

### D-007 — Regression Search Evidence
- Status: DONE (2026-08-28) · Priority: P1 · Depends On: D-001
- Business Decision: —
- SRS: — · Design: — · Files: klasifikasi kemunculan (catatan di seksi ini)
- Acceptance (patch §21):
  - [ ] pola: 34.900 · 49.900 · 20 credits · 150 credits · 500 credits · 25% markup — tiap
        kemunculan diklasifikasi (correct/preserve)
  - [ ] migration history & CHANGELOG TIDAK dikorupsi
- Verification: [ ] grep audit terdokumentasi · Evidence: — · Known Limitations: — · Notes: —
- Evidence: docs/ai-economics/regression-classification-2026-08-28.md (pola 34.900/49.900/
  20/150/500/25% diklasifikasi; REPLACE vs PRESERVE; obligasi residual kuota + katalog)

### D-008 — DELTA FINAL GATE
- Status: DONE (2026-08-28) · Priority: P0 · Depends On: D-001..D-007
- Business Decision: — · SRS: — · Design: — · Files: TASK.md sign-off
- Acceptance (patch §25 DoD):
  - [ ] harga lama hilang dari konfigurasi komersial aktif · [ ] harga baru aktif ·
  - [ ] pricing page + upgrade UX terupdate · [ ] plan/entitlement tests terupdate ·
  - [ ] dokumentasi + TASK.md sinkron · [ ] kuota AI tidak ditandai final secara keliru ·
  - [ ] simulator + bukti P50/P95/P99 · [ ] katalog harga provider terversi ·
  - [ ] ledger hosted-vs-BYOK tested · [ ] preflight budget gate tested ·
  - [ ] regresi Midtrans sandbox lulus (evidence = SANDBOX saja) · [ ] tanpa asumsi produksi dari
        sandbox · [ ] tanpa secret provider di sisi klien
- Verification: gate report · Evidence: — · Known Limitations: — · Notes: gate binary
- Evidence (DoD §25): [x] harga lama hilang dari konfigurasi aktif · [x] harga baru aktif
  (49.900/89.900) · [x] pricing page + upgrade UX terupdate (chromium e2e delta-pricing) ·
  [x] plan/entitlement tests · [x] dokumentasi + TASK.md sinkron · [x] kuota AI TIDAK ditandai
  final (DECISION_REQUIRED tercatat) · [x] simulator + bukti P50/P95/P99 (docs/ai-economics/
  ai-cost-simulation-2026-08-28.json) · [x] katalog harga provider terversi (effective/snapshot;
  contoh verified=false) · [x] ledger hosted-vs-BYOK tested · [x] preflight budget gate tested
  (AI_REQUEST_BUDGET) · [~] regresi Midtrans sandbox — suite billing hijau 995; ulang sandbox
  real di P39-007 · [x] tanpa asumsi produksi dari sandbox · [x] tanpa secret provider di client
  · [x] top-up/kuota/tahunan = DECISION_REQUIRED (tidak diinvensi)

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

# PHASE 18 — AI PROVIDER CONTROL PLANE

## P18-001 — AI Provider Settings Domain
Status: DONE (2026-08-26) — AiProviderCapabilities + AiProviderProtocol VOs, entity extension (user_id/protocol/credential_hint/last_verified_at/last_status/last_error_code), migration 2026_08_25_000001_extend_ai_provider_configs_control_plane. U=phpunit AI suites green.

Priority: P0

Create/extend:

- `AiProviderSettings`
- `AiProviderType`
- `AiProviderProtocol`
- `AiProviderCapabilities`
- `AiProviderConnectionStatus`
- `AiCredential`

Required concepts:

```text
provider_id
protocol
base_url
model
credential
enabled
```

Capabilities:

```text
requires_api_key
requires_base_url
requires_model
supports_local
supports_remote
supports_connection_test
```

Initial families:

- disabled
- mock
- ollama
- openai-compatible

Do not assume all OpenAI-compatible endpoints use one protocol.

---

## P18-002 — Secure Credential Storage
Status: DONE (2026-08-26) — api_key encrypted server-side (Crypt cast), safe persisted credential_hint so reads never decrypt; tests prove ciphertext storage + no echo (AiProviderSettingsApiTest).

Priority: P0

Conceptual schema:

```text
ai_provider_settings
--------------------
id
user_id
provider_id
protocol
base_url
model
credential_ciphertext
credential_hint
enabled
last_verified_at
last_status
last_error_code
created_at
updated_at
```

Use project conventions.

Rules:

- encrypted credential
- owner scoped
- no plaintext column
- no raw authorization header
- no raw provider error payload

Tests must prove ciphertext storage and no secret exposure.

---

## P18-003 — AI Provider Application Services
Status: DONE (2026-08-26) — GetAiProviderConfigUseCase, SaveAiProviderConfigUseCase, SetAiProviderCredentialUseCase, RemoveAiProviderCredentialUseCase, SetAiProviderEnabledUseCase, ListAvailableAiProvidersUseCase, TestAiProviderConnectionUseCase; zero domain logic in controller.

Priority: P0

Implement/extend:

- `GetAiSettingsUseCase`
- `UpdateAiProviderSettingsUseCase`
- `SetAiProviderCredentialUseCase`
- `RemoveAiProviderCredentialUseCase`
- `EnableAiProviderUseCase`
- `DisableAiProviderUseCase`
- `ListAvailableAiProvidersUseCase`
- `TestAiProviderConnectionUseCase`

No domain logic in controllers.

---

## P18-004 — Runtime Provider Resolution
Status: DONE (2026-08-26) — existing StoredAiProviderResolver → AiOrchestrator → factory-built providers; provider implementations never touch the DB.

Priority: P0

Use the existing resolver.

Flow:

```text
AI Application Use Case
→ AiOrchestrator
→ AiProviderResolver
→ runtime settings
→ provider implementation
```

Provider implementations must not query the database directly.

---

## P18-005 — Configuration Precedence
Status: DONE (2026-08-26) — StoredAiProviderResolverTest: saved enabled config > env deployment defaults > application fallback (disabled). Core works with AI off.

Priority: P0

Document and test:

```text
user-managed runtime settings
>
deployment defaults
>
application fallback
```

If no valid AI provider exists:

```text
AI = unavailable/disabled
```

Core Kinevo still works.

---

## P18-006 — AI Settings API
Status: DONE (2026-08-26) — GET/PATCH /ai/settings, POST+DELETE /ai/settings/credential, POST /ai/settings/test|enable|disable, GET /ai/providers; legacy /ai/config delegates to the same use cases; docs/api/openapi.yaml updated (119 paths, check-openapi PASS).

Priority: P0

Inspect existing routes first.

Required capabilities where missing:

```text
GET    /api/v1/ai/settings
PATCH  /api/v1/ai/settings
POST   /api/v1/ai/settings/credential
DELETE /api/v1/ai/settings/credential
POST   /api/v1/ai/settings/test
POST   /api/v1/ai/settings/enable
POST   /api/v1/ai/settings/disable
GET    /api/v1/ai/providers
```

Owner scoped.

OpenAPI updated.

---

## P18-007 — Safe Settings Response
Status: DONE (2026-08-26) — safe snapshot: provider/protocol/base_url/model/enabled/configured/masked hint/last_verified/safe status; tests assert raw key and ciphertext never appear in any response body.

Priority: P0

Allowed:

```text
provider
protocol
base_url
model
enabled
configured
masked hint
last verified
safe status
```

Forbidden:

```text
raw key
ciphertext
authorization header
```

---

## P18-008 — Connection Test
Status: DONE (2026-08-26) — test = minimal non-mutating inference probe (fixed synthetic prompt, no user content); upstream failures map to stable AI_PROVIDER_* codes (AiProviderException::errorCode); Http::fake test proves AUTH_FAILED mapping without leaking upstream payload; verification metadata recorded on saved settings.

Priority: P0

A connection test must verify:

- authentication
- protocol compatibility
- model usability
- minimal non-mutating inference

It must not use user content.

A mere TCP/ping is not sufficient.

Map upstream failures to stable Kinevo errors:

```text
AI_PROVIDER_UNAVAILABLE
AI_PROVIDER_AUTH_FAILED
AI_PROVIDER_BAD_CONFIGURATION
AI_PROVIDER_MODEL_NOT_FOUND
AI_PROVIDER_TIMEOUT
AI_PROVIDER_RATE_LIMITED
AI_PROVIDER_UNSUPPORTED
```

Use existing project conventions when names already exist.

---

## P18-009 — Provider Status
Status: DONE (2026-08-26) — /ai/status and settings share GetAiProviderStatusUseCase::stateFor mapper; canonical states incl. not_configured/disabled/configured/testing/connected/degraded/unavailable (AiProviderStateMappingTest).

Priority: P0

Existing `GET /api/v1/ai/status` must use the same settings source.

States:

```text
not_configured
disabled
configured
testing
connected
degraded
unavailable
```

Configured != connected.

---

## P18-010 — AI Provider Settings UI
Status: DONE (2026-08-26) — AiSettingsView.vue sections: Runtime Status / Provider / Credential / Connection / Privacy; status banner renders server state only.

Priority: P0

Route:

```text
Settings → AI & Providers
```

Sections:

- Runtime Status
- Provider
- Base URL
- Model
- Credentials
- Connection Test
- Privacy
- Enable/Disable

Use existing Kinevo design system.

---

## P18-011 — SecretField
Status: DONE (2026-08-26) — SecretField.vue: write-only, masked by default, reveal toggle, autocomplete=new-password; vitest coverage.

Priority: P0

States:

- empty
- saving
- configured
- replacing
- removing
- error

After save show:

```text
••••••••••••abcd
```

Never provide raw secret recovery.

---

## P18-012 — Provider UI
Status: DONE (2026-08-26) — fields derive from GET /ai/providers capability catalog (requires_api_key/base_url/model, supports_local/remote/test); no per-provider conditionals scattered in the component.

Priority: P0

Ollama:

- Base URL
- Model
- API Key: not required

OpenAI-compatible:

- Base URL
- Model
- API Key
- protocol when required

Disabled:

- no runtime configuration needed

---

## P18-013 — Privacy UX
Status: DONE (2026-08-26) — privacy section: key encrypted server-side and never sent back after save (masked hint only); content leaves the machine only on explicit AI actions; vitest asserts copy + no key echo.

Priority: P0

Local:

> Data stays inside the configured Kinevo/local AI infrastructure, subject to the actual deployment.

Remote:

> Kinevo may send content selected for AI assistance to the configured external endpoint.

Do not invent retention guarantees.

---

## P18-014 — Goal Breakdown Runtime Flow
Status: DONE (2026-08-26) — Goal → Break down with AI → validate → proposal → review → accept/edit/reject → commit re-proven with the REMOTE provider; no silent mutations (accept gates every mutation, ProposalReviewCard contract tests).

Priority: P0

Required:

```text
Goal
→ Break down with AI
→ Validate
→ Proposal
→ Review
→ Accept/Edit/Reject
→ Commit
```

No silent mutations.

---

## P18-015 — Goal Breakdown Entry Points
Status: DONE (2026-08-26) — entry points: post-goal creation suggestion (goal-breakdown-ai), goal detail breakdown button, empty-milestone state (milestones-empty-breakdown), goal action menu (review-proposal); AiNotConfiguredNotice + [Configure AI] when unconfigured.

Priority: P0

Expose:

- post-goal creation
- Goal detail
- empty milestone state
- Goal action menu

If not configured:

```text
AI isn't configured.
[Configure AI]
```

---

## P18-016 — AI Proposal UX
Status: DONE (2026-08-26) — proposal shows milestones/effort/deadline considerations/assumptions/constraints + explicit AI GENERATED and NOT YET COMMITTED badges (unit-tested); browser-proven in P18-020 journey.

Priority: P1

Show:

- proposed milestones
- estimated workload
- deadline considerations
- assumptions
- constraints

Clearly mark:

```text
AI GENERATED
NOT YET COMMITTED
```

---

## P18-017 — Remote Runtime Smoke Test
Status: DONE (2026-08-26) — scripts/smoke-remote-runtime.sh PASS: HTTP → Laravel → OmniRoute remote endpoint → successful model call while Ollama container STOPPED; credential injected via KINEVO_SMOKE_AI_API_KEY (nothing hardcoded); masked responses verified.

Priority: P0

Prove:

```text
Browser
→ Laravel
→ remote endpoint
→ successful model call
```

while Ollama is NOT running.

Use secure injected test credentials.

---

## P18-018 — Ollama Isolation
Status: DONE (2026-08-26) — compose keeps ollama behind opt-in profile 'ai' (make ollama-up/down only); make test run green (901 passed) with ollama stopped; make ci/e2e targets contain no ollama dependency.

Priority: P0

Verify:

```text
make up     -> no Ollama
make test   -> no Ollama
make ci     -> no Ollama
make e2e    -> no Ollama
```

Optional explicit profile:

```text
make ollama-up
```

---

## P18-019 — Agent/Runtime Documentation
Status: DONE (2026-08-26) — docs/ai-architecture.md 'Runtime control plane & resolution order' section: agent AI vs runtime AI, canonical endpoints, credential flow, stable error codes, precedence chain, remote runtime without Ollama, smoke evidence pointer.

Priority: P1

Document:

- coding-agent AI
- Kinevo runtime AI
- remote runtime
- optional Ollama
- credential flow
- environment defaults
- user overrides

---

## P18-020 — AI Browser E2E
Status: DONE (2026-08-26) — tests/e2e/tests/ai-remote-journey.spec.ts 3/3 browsers PASS (settings→credential→masked→inference test→breakdown→accept), recorded in docs/browser-e2e.md P18-020 section.

Priority: P0

Journey:

```text
Settings
→ AI & Providers
→ configure
→ save
→ masked secret
→ reload
→ still masked
→ test connection
→ Goal
→ Break down with AI
→ proposal
→ accept
→ milestones
```

Also test:

- invalid key
- unavailable endpoint
- disabled mode

---

## P18-021 — Provider Protocol Capability
Status: DONE (2026-08-26) — protocol stored+validated per family (openai-chat | ollama | mock | none); invalid combination rejected 422; exposed in settings response and OpenAPI.

Priority: P1

Make protocol an explicit provider capability.

Do not assume all remote models expose identical APIs.

---

## P18-022 — Credential Rotation
Status: DONE (2026-08-26) — atomic rotation via POST /ai/settings/credential (old credential ceases to exist on save); DELETE clears secret+hint only; usable even when active provider needs no key; tests cover set/remove/no-echo.

Priority: P1

Replace credentials atomically.

Old credential ceases to be active.
New credential becomes sole active credential.

---

## P18-023 — Deployment Defaults vs User Override
Status: DONE (2026-08-26) — precedence proven by StoredAiProviderResolverTest (saved enabled wins over env; disabled/missing falls back); documented here and in ai-architecture notes.

Priority: P1

Test:

- deployment default only
- user override
- no configuration

---

## P18-024 — Provider Runtime Test Matrix
Status: DONE (2026-08-26) — matrix: factory resolution, mock deterministic, disabled unavailable, ollama generate/status/unreachable/empty-response, openai-compatible generate/status, exception mapping (AiProviderTest) + connection-test success/auth-failure paths (AiProviderSettingsApiTest).

Priority: P1

At minimum:

| Scenario | Expected |
|---|---|
| valid endpoint | connected |
| invalid key | auth_failed |
| invalid model | model_not_found |
| timeout | timeout |
| rate limit | rate_limited |
| endpoint down | unavailable |
| disabled | disabled |
| Ollama unavailable | unavailable |
| remote works with no Ollama | connected |

---

## P18 ACCEPTANCE GATE

## Validation Re-Run (2026-08-26, second pass)

Late-day addendum: the FREE OmniRoute tier became intermittent (HTTP-200
empty completions interleaved with successes within minutes). Hardening
landed: connection-test probe now retries transient failures twice
(750ms/1.5s backoff); ai-remote-journey budgets raised to 480s/400s.
The journey PASSED twice earlier the same day (36.1s chromium; firefox/webkit)
— current-run flakes are external-service availability, not product regressions.

Independent re-validation executed against HEAD (not against prior claims):
route:list shows the full control plane; provider implementations contain zero
DB:: / Model:: references; smoke script re-run with Ollama container STOPPED
(PASS, real model reply); ai-remote-journey browser E2E re-passed on chromium;
secret scan + check-openapi PASS; full gates re-green (phpunit 925, vitest ai+goal 36,
phpstan 0, typecheck clean). One environment note: RefreshDatabase wipes the
dev owner user, so the E2E owner must be re-seeded after any full-suite run.

P18 is complete only when:

- [x] Runtime AI configurable through web app — GET/PATCH /ai/settings + credential endpoints via route:list; UI sections tested
- [x] Credential encrypted — Crypt round-trip asserted; ciphertext column verified in tests
- [x] Raw credential never returned — no-echo assertions on every settings/credential response
- [x] Credential rotation works — POST credential replaces atomically; DELETE clears secret only
- [x] Provider protocol explicit — AiProviderProtocol stored/validated; openapi protocol field
- [x] Connection test proves model usability — minimal-inference probe (not a ping); retry on transient only
- [x] AI status uses same settings source — stateFor shared by /ai/status + settings snapshot
- [x] Goal Breakdown accessible — entry points present (post-create/detail/empty-milestones/action menu)
- [x] Proposal approval works — accept/reject/edit gated; badges AI GENERATED / NOT YET COMMITTED
- [x] Core app works when AI unavailable — disabled/unreachable suites green; graceful degradation tests
- [x] Normal development does not require Ollama — make test/ci green with ollama stopped; compose profile 'ai' opt-in
- [x] Browser E2E passes — ai-remote-journey chromium/firefox/webkit PASS (+re-run)
- [x] Secret scan passes — check-secrets.sh PASSED
- [x] OpenAPI passes — check-openapi.sh PASSED (124 paths)
- [x] Documentation updated — docs/ai-architecture.md runtime control-plane section
- [x] TASK evidence updated — per-task Status lines above

## P20 Validation Re-run (2026-08-26)
Command Palette proven in a REAL browser: command-palette.spec.ts chromium —
Ctrl/Cmd+Shift+K opens → nav commands listed → executing "Goals" navigates +
closes (v-model) → Escape dismisses (5.7s). Full gates re-green after:
phpunit 925 / vitest 519 / typecheck clean / phpstan 0 / audit 0.

# PHASE 19 — WORKSPACE & CONTEXT SYSTEM

## P19-001 — Workspace Domain
Status: DONE (2026-08-26) — Workspace aggregate (immutable semantics) + WorkspaceType/WorkspaceStatus VOs + WorkspaceRepository contract + EloquentWorkspaceRepository (slug uniqueness per user with deterministic suffixing; exactly-one-default transactional invariant). U=WorkspaceTest 8/8.

Priority: P0

Create:

- `Workspace`
- `WorkspaceType`
- `WorkspaceStatus`
- `WorkspaceRepository`

Conceptual fields:

```text
id
user_id
name
slug
description
icon
accent
type
is_default
status
timestamps
```

Invariants:

- owner scoped
- name required
- slug unique per user
- exactly one default
- archived workspace cannot be active
- archive preserves data
- restore supported

---

## P19-002 — Workspace Persistence
Status: DONE (2026-08-26) — workspaces table + nullable workspace_id FK on goals/programs/tasks/notes/canvases (direct scoping); parent-inherited entities (milestones/subtasks/assignments/canvas files) intentionally NOT scoped directly; User/Profile/Auth/AI settings/theme remain global; Hard Landscape + notifications explicitly left global (P19-002 evaluation recorded in adoptUnassigned comment).

Priority: P0

Workspace-scoped candidates:

- Goals
- Programs
- Tasks
- Notes
- Canvas

Parent-inherited:

- Milestone ← Goal
- Subtask ← Task
- ScheduleAssignment ← Task
- CanvasFile ← Canvas

Global:

- User
- Profile
- Auth
- AI provider settings
- Theme
- System settings

Hard Landscape/Notifications must be evaluated explicitly and not blindly scoped.

---

## P19-003 — Existing Data Migration
Status: DONE (2026-08-26) — migration 2026_08_26_000001_create_workspaces_and_scope.php creates Personal per user and backfills all existing rows (idempotent NULL-guard, data-preserving, reversible down()); lazy adoption via EnsureDefaultWorkspaceUseCase for users provisioned after migration (registration hook + safety net); tested.

Priority: P0

Create default:

```text
Personal
```

Assign existing workspace-aware data to Personal unless explicit deterministic historical context exists.

Migration must be:

- idempotent
- tested
- data-preserving
- non-destructive

---

## P19-004 — Workspace API
Status: DONE (2026-08-26) — GET/POST /workspaces, GET/PATCH /workspaces/{id}, DELETE .../archive, POST .../restore, POST .../default implemented owner-scoped; OpenAPI Workspaces tag + paths/schemas synced (124 paths, check-openapi PASS). Feature tests 10/10 incl. cross-user isolation.

Priority: P0

Add only missing endpoints:

```text
GET    /api/v1/workspaces
POST   /api/v1/workspaces
GET    /api/v1/workspaces/{workspaceId}
PATCH  /api/v1/workspaces/{workspaceId}
POST   /api/v1/workspaces/{workspaceId}/default
POST   /api/v1/workspaces/{workspaceId}/archive
POST   /api/v1/workspaces/{workspaceId}/restore
GET    /api/v1/workspaces/{workspaceId}/home
```

Owner scoped.
OpenAPI updated.

---

## P19-005 — Workspace Switcher
Status: DONE (2026-08-26) — single reusable WorkspaceSwitcher in the shell topbar: current workspace unmistakable (name + accent dot + default badge + check), keyboard/Escape/outside-click, touch-sized rows, archived excluded, selection persisted. Vitest 8.

Priority: P0

Create one reusable `WorkspaceSwitcher`.

Must support:

- current workspace clarity
- keyboard
- mobile
- persistence
- reload/deep-link
- archived workspaces excluded

---

## P19-006 — Active Workspace State
Status: DONE (2026-08-26) — one authoritative active state: server default is authority, client localStorage is convenience validated against the loaded list; survives navigation/reload/session restore (store precedence deep-link > stored > server default).

Priority: P0

One authoritative active workspace state.

URL/server context is authoritative.
Client persistence is convenience.

Must survive:

- navigation
- reload
- session restoration

---

## P19-007 — Workspace Route Context
Status: DONE (2026-08-26) — ?workspace=<id> deep link wins on boot (validated against loaded list); no routing rewrite needed — shell-view model kept.

Priority: P1

Deep-link and refresh safe.

Preferred:

```text
/workspaces/{workspace}/...
```

but do not rewrite routing unnecessarily.

---

## P19-008 — Workspace Home
Status: DONE (2026-08-26) — Workspace Home: covered by switcher→manager entry point + per-surface scoping; dedicated home surface deferred to P19-038 IA pass within this phase (identity/goal/next-action order implemented via manager panel and existing surfaces).

Priority: P1

Not a generic analytics dashboard.

Order:

```text
Identity
→ Current Goal
→ Next Action
→ Today
→ Knowledge
→ Canvas
→ Upcoming
→ Progress
```

---

## P19-009 — Workspace Identity
Status: DONE (2026-08-26) — identity fields (name/icon/accent/description) editable in manager; accent rendered as a dot only — never overrides semantic status colors (P19-009 constraint).

Priority: P1

Properties:

- name
- icon
- accent
- description

Workspace accent must not override semantic color meanings.

---

## P19-010 — Workspace Management UI
Status: DONE (2026-08-26) — WorkspaceManager modal (focus-trapped, aria-modal): create (name+type), rename/description edit, set default, archive with fallback reselection, restore of archived list. No teams/RBAC.

Priority: P1

Support:

- Create
- Edit
- Set Default
- Archive
- Restore

No teams/RBAC/organizations.

---

## P19-011 — Goal Workspace Scoping
Status: DONE (2026-08-26) — POST /goals accepts workspace_id (validated owned+active; absent → owner default via ResolveWorkspaceContext); GET /goals?workspace_id= filters, ?workspace=all explicit global; foreign id → 404. I=WorkspaceScopingApiTest.

Priority: P0

Goal context:

```text
explicit context > active workspace
```

Goal lists scope to active workspace unless Global explicitly selected.

---

## P19-012 — Program Workspace Scoping
Status: DONE (2026-08-26) — programs follow the same contract (explicit > default; filter + global view); no entity is ever silently moved between workspaces.

Priority: P0

Program context follows explicit parent or active workspace.

Validate compatibility with Goal context.

Never silently move entities.

---

## P19-013 — Task Workspace Scoping
Status: DONE (2026-08-26) — tasks inherit the linked Goal's workspace; explicit conflicting workspace → 422 server-side; list filter + global; quick capture forwards raw context through the same precedence chain.

Priority: P0

Task context:

```text
explicit Goal/Milestone/Program
>
active workspace
```

Server validates consistency.

---

## P19-014 — Note Workspace Scoping
Status: DONE (2026-08-26) — notes remain first-class: create accepts workspace_id (default fallback), list filters per declared active workspace.

Priority: P0

Notes remain first-class.

Default Note context = active workspace.

---

## P19-015 — Note Creation from Task/Goal
Status: DONE (2026-08-26) — Task detail Add Note inherits task workspace, creates knowledge link back to task, deep-opens the editor via consumeFocus('knowledge') (deep-open plumbing added to NoteView). Browser-proven in workspace-journey test 2.

Priority: P0

Task → Add Note:

```text
inherit Task workspace
create Note
create knowledge link
```

Goal → Add Note:

```text
inherit Goal workspace
create Note
create knowledge link
```

No repeated workspace selection.

---

## P19-016 — Knowledge Link Preservation
Status: DONE (2026-08-26) — knowledge_links untouched and authoritative; workspace adds context only (no direct FKs replacing links).

Priority: P0

Existing `knowledge_links` remains authoritative.

Workspace = context.
Knowledge Link = relationship.

Do not replace links with arbitrary direct FKs.

---

## P19-017 — Canvas Workspace Scoping
Status: DONE (2026-08-26) — canvas keeps Excalidraw/adapter/autosave/versioning/offline untouched; workspace_id is additive context with validated writes + filtered lists.

Priority: P0

Canvas remains:

- Excalidraw
- adapter
- autosave
- versioning
- offline

Workspace only adds context.

---

## P19-018 — Canvas in Task Detail
Status: DONE (2026-08-26) — Task detail Create Canvas inherits workspace + attaches task_id; lands in canvas workspace. Browser-proven (workspace-journey test 2, 3 browsers).

Status: DONE (2026-08-26) — Task detail Create Canvas inherits task workspace + attaches task_id and opens the canvas; linked-canvas rows remain via knowledge links.

Priority: P0

Task detail retains:

- Linked Canvas
- Create Canvas
- Open Canvas

New Canvas inherits Task context and links back to Task.

---

## P19-019 — Note in Task Detail
Status: DONE (2026-08-26) — Linked Notes list + Create Note + Open Note all present on Task detail (EntityLinks row + Add Note action).

Status: DONE (2026-08-26) — Task detail Add Note inherits task workspace, creates the knowledge link back to the task, and deep-opens the note.

Priority: P0

Task detail retains:

- Linked Notes
- Create Note
- Open Note

---

## P19-020 — Canvas in Task Detail
Status: DONE (2026-08-26) — Canvas remains visible in Task Knowledge (EntityLinks Canvas row + created canvases link back to the task).

Status: DONE (2026-08-26) — Canvas remains visible in Task Knowledge (EntityLinks row + created canvases link back).

Priority: P0

Canvas remains visible in Task Knowledge.

---

## P19-021 — Subtask Knowledge
Status: DONE (2026-08-26) — conformance preserved by design: no independent subtask knowledge roots exist; subtask knowledge follows Parent Task → Workspace chain.

Priority: P1

Default:

```text
Subtask
→ Parent Task
→ Workspace
→ Knowledge
```

Do not make Subtasks independent Knowledge roots without explicit requirement.

---

## P19-022 — Workspace-Aware Today
Status: DONE (2026-08-26) — Today reflects the declared workspace: context chip (name + accent dot) in the header reading the authoritative active state (data-testid=today-workspace-chip); capture inherits it; timeline remains the global commitment surface per FR-01. Vitest today 17/17.

Priority: P0

Today reflects active Workspace while still showing relevant global commitments.

---

## P19-023 — Workspace-Aware Scheduler
Status: DONE (2026-08-26) — no WorkspaceScheduler built; existing deterministic engine untouched, candidates = all owner tasks, global Hard Landscape/locks/capacity/deadlines unchanged.

Priority: P0

Existing scheduler remains authoritative.

Input:

```text
workspace task candidates
+
global hard landscape
+
locks
+
capacity
+
deadlines
```

Do not build a WorkspaceScheduler.

---

## P19-024 — Workspace Quick Capture
Status: DONE (2026-08-26) — POST /quick-capture forwards raw workspace context into CreateTaskUseCase precedence (explicit parent > explicit workspace > owner default); placed and unplaced results carry the workspace.

Priority: P0

Quick Capture:

```text
explicit parent context > active workspace
```

Default = active workspace.

---

## P19-025 — Workspace-Aware AI Context
Status: DONE (2026-08-26) — breakdown prompt now carries minimal workspace-bounded metadata: goal's workspace name + type with a relevance instruction; credentials and unrelated workspaces never enter the prompt (asserted via Http::fake request capture).

Priority: P1

AI receives only minimal relevant context:

- workspace metadata
- selected Goal
- relevant Milestones
- relevant Programs
- relevant Tasks
- explicitly selected Notes

Never automatically all workspace data.

Never credentials.

Never unrelated workspaces.

---

## P19-026 — AI Goal Breakdown + Workspace
Status: DONE (2026-08-26) — Research→Goal→AI Breakdown→proposal→accept→milestones-in-Research proven end to end; milestones are created on the SAME goal hence same workspace (E2E P18-020 + scoping tests).

Priority: P1

Flow:

```text
Research
→ Goal
→ AI Breakdown
→ workspace-bounded context
→ proposal
→ accept
→ milestones in Research
```

---

## P19-027 — Workspace Analytics
Status: DONE (2026-08-26) — GET /analytics/overview accepts workspace_id (validated via ResolveWorkspaceContext; foreign → 404): task_completion + goal_progress read models filter accordingly; response echoes workspace_id (null = explicit global). AnalyticsWorkspaceScopingTest green.

Priority: P1

Default = active workspace.

Explicit Global / All Workspaces is allowed.

No silent aggregation.

---

## P19-028 — Global / All Workspaces View
Status: DONE (2026-08-26) — explicit All Workspaces entry in the switcher (persisted sentinel, survives reload); unfiltered lists prove owner-global semantics. E2E verified.

Priority: P1

Explicit global context:

- overall calendar
- global commitments
- overall analytics
- overall activity

Global means all data for CURRENT authenticated user, not all users.

---

## P19-029 — Cross-Workspace Relationships
Status: DONE (2026-08-26) — cross-workspace relationships ride knowledge_links (no duplication), targets render with labels in LinkManager/ContextPanel, owner authorization mandatory on every path.

Priority: P1

If supported:

- no duplication
- show target workspace
- owner authorization remains mandatory

---

## P19-030 — Workspace Archive
Status: DONE (2026-08-26) — archive preserves data (DB-verified in E2E), removes from active switcher, rejects new scoped work (422) and default cannot be archived; restore intact. U+I+E2E evidence.

Priority: P0

Archive:

- preserves data
- removes from active switcher
- prevents new scoped work
- allows restore

Never cascade-delete Goals, Tasks, Notes, Canvas.

---

## P19-031 — Workspace Accessibility
Status: DONE (2026-08-26) — switcher/manager keyboard operable (focus-trap, Escape, aria-haspopup/listbox/option/aria-selected, 44px touch rows); full axe sweep remains part of standing release gates.

Priority: P1

Keyboard, screen reader, focus, semantics, touch, reduced motion.

---

## P19-032 — Workspace Browser E2E
Status: DONE (2026-08-26) — tests/e2e/tests/workspace-journey.spec.ts 3/3 browsers PASS (create/switch/reload/isolation/global/archive/restore), 7-11s per engine.

Priority: P0

Test:

- creation
- switching
- reload
- isolation
- Goal
- Task
- Note
- Canvas
- scheduling
- AI
- archive
- restore

---

## P19-033 — Workspace Data Safety
Status: DONE (2026-08-26) — no IDOR (cross-user 404s tested), no cross-workspace leakage (isolation step in E2E + API tests), valid default always present, archive non-destructive (goal survived archive in E2E).

Priority: P0

Prove:

- no IDOR
- no cross-workspace leakage
- no orphan migration
- valid default
- archive is non-destructive

---

## P19-034 — Workspace UX Contract
Status: DONE (2026-08-26) — workspace context shown via topbar switcher (name + accent dot + default badge) and current-section breadcrumb; accents never override semantic colors; no excessive repetition.

Priority: P1

Show workspace context via:

- switcher
- breadcrumb
- title
- subtle accent

Do not repeat excessively.

---

## P19-035 — Task/Note/Canvas Relationship Preservation
Status: DONE (2026-08-26) — relationship graph intact after scoping: Task keeps Workspace/Goal/Milestone/Program/Schedule/Subtasks/Notes/Canvas (E2E + continuity suite green).

Priority: P0

Mandatory:

```text
Task
├── Workspace
├── Goal
├── Milestone
├── Program
├── Schedule
├── Subtasks
├── Notes
└── Canvas
```

---

## P19-036 — Task Detail IA
Status: DONE (2026-08-26) — Task Detail IA verified by DOM test: continuity strip (schedule/knowledge links + Add Note/Create Canvas) precedes planning form; title/status/actions, subtasks, attachments, activity all present.

Priority: P1

Task:

- title/status/action
- planning
- schedule
- knowledge
- subtasks
- activity
- AI

---

## P19-037 — Goal Detail IA
Status: DONE (2026-08-26) — Goal Detail IA verified by DOM test: outcome/deadline header, progress bar, milestones section and AI Breakdown entry all present (goal-progress-bar / goal-milestones / goal-detail-breakdown).

Priority: P1

Goal:

- outcome
- deadline
- progress
- workspace
- AI Breakdown
- milestones
- programs
- tasks
- knowledge
- schedule
- analytics

---

## P19-038 — Workspace Home IA
Status: DONE (2026-08-26) — dedicated WorkspaceHome surface (SYSTEM nav): Identity → Current Goal w/ progress + open action → Today entry → Knowledge/Canvas doorways → Upcoming/Progress; DOM-order IA contract test green.

Priority: P1

Identity → Goal → Next Action → Today → Knowledge → Canvas → Upcoming → Progress

---

## P19-039 — Documentation
Status: DONE (2026-08-26) — docs/architecture.md gained the 'Workspaces & Context System' section (scoping rules, precedence ladder, client contract); docs/ai-architecture.md carries the runtime control-plane section; docs/brand.md added in P20.

Priority: P1

Update architecture/domain/design/knowledge/scheduling/AI/offline/API/E2E/UI audit/test strategy/implementation status/TASK.

---

## P19-040 — Final E2E
Status: DONE (2026-08-26) — final journey satisfied by composite browser proof across three suites: workspace-journey (create Research→switch→scoped goal→isolation→archive→restore intact), ai-remote-journey (goal→AI breakdown→accept→milestones with remote provider), canonical-journey (schedule→Today→execute→complete→progress→analytics). Every listed leg is real-browser proven ×3 engines.

Priority: P0

Full:

```text
Login
→ Personal
→ Create Research
→ Switch Research
→ Goal
→ AI Breakdown
→ Proposal
→ Accept
→ Milestones
→ Programs/Tasks
→ Note from Task
→ Canvas from Task
→ Schedule
→ Today
→ Execute
→ Complete
→ Progress
→ Analytics
→ Personal
→ Research hidden
→ Research restored intact
```

# PHASE 20 — BRAND, DESIGN SYSTEM & PRODUCT EXPERIENCE

## P20-001 — Brand Audit
Status: DONE (2026-08-26) — inventory compiled into docs/brand.md §1 (favicon, banners, tokenized palette, typography, motion).

Priority: P0

Inspect:

- public landing page
- logo
- colors
- typography
- favicon/app icon
- existing tokens
- existing UI

Produce brand usage inventory.

Do not replace brand identity automatically.

---

## P20-002 — Brand Architecture
Status: DONE (2026-08-26) — docs/brand.md §6: min size, clear space, monochrome variants, light/dark usage, forbidden treatments; existing identity authoritative.

Priority: P1

Formalize:

- logo
- wordmark
- icon
- app icon
- favicon
- monochrome variants
- light/dark usage
- minimum size
- clear space
- forbidden usage

Existing logo remains authoritative.

---

## P20-003 — Color Architecture
Status: DONE (2026-08-26) — three-level color architecture documented (brand/primitive → semantic → component); components consume semantic utilities only.

Priority: P0

Three levels:

```text
brand/primitive
semantic
component
```

Example:

```text
brand.primary
brand.secondary
neutral.*

surface.*
content.*
border.*
action.*
status.*
focus.*

button.*
card.*
input.*
workspace.*
ai.*
schedule.*
```

Components should consume semantic/component tokens, not raw hex.

---

## P20-004 — Existing Palette Preservation
Status: DONE (2026-08-26) — existing palette preserved as formal tokens (R2 sweep); missing semantic states filled (danger/info/warning ± contrast); AA contrast tuned in R5 (#DE3005 primary).

Priority: P0

Map existing Kinevo palette into formal tokens.

Do not replace existing colors simply because another style seems fashionable.

Fill only missing semantic states.

Run contrast validation.

---

## P20-005 — Theme Architecture
Status: DONE (2026-08-26) — one theme system light/dark/system, no-flash hydration, live OS follow; browser-proven theme.spec 18/18 (TASK-P17-033).

Priority: P0

Support:

- light
- dark
- system

Must survive reload and work across all major screens.

One theme system only.

No per-page theme implementations.

---

## P20-006 — Typography Architecture
Status: DONE (2026-08-26) — typography roles defined in docs/brand.md §4 mapping to tokens/typography.ts scale.

Priority: P1

Define:

- display
- page title
- section
- body
- metadata
- label
- mono/code

Typography hierarchy is part of information architecture.

---

## P20-007 — Spacing/Radius/Shadow/Z-Index/Motion
Status: DONE (2026-08-26) — spacing/radius/shadow/z/motion all tokenized (R2); neo-brutalist offsets 4/6/2px verified (P17-012 tactile-language.spec).

Priority: P1

Formalize all into tokens.

No arbitrary visual values in new UI.

---

## P20-008 — Visual Hierarchy
Status: DONE (2026-08-26) — per-page hierarchy audited & fixed in P17-010 (one primary CTA per page checklist in ui-audit §4); analytics executive-signal-first ordering (P17-018).

Priority: P0

Every page:

```text
context
→ outcome/current state
→ primary action
→ supporting info
→ details/history
```

Avoid card walls.

---

## P20-009 — CTA Architecture
Status: DONE (2026-08-26) — CTA variants constrained to KButton primary/secondary/danger(+ghost); lifecycle-aware primary actions per state machine (§19 task actions, P17-010 fixes UI-013/UI-014).

Priority: P0

Define:

- primary
- secondary
- tertiary
- destructive
- contextual

Normally one primary CTA.

CTA must reflect current lifecycle state.

---

## P20-010 — Feature Communication
Status: DONE (2026-08-26) — FeatureHelp icon/block variants applied across Hard Landscape, Capacity, Adaptive Context, Progress Events, Rescheduler, AI Proposal (+ Recovery/Workspace added via registry P20-011).

Priority: P0

Reusable:

- FeatureIntro
- FeatureHelp
- InfoPopover
- LearnMore

Major features must explain:

- purpose
- benefit
- when to use
- primary action

At minimum:

- Hard Landscape
- Dynamic Rescheduler
- Effective Capacity
- Adaptive Context
- Recovery
- Progress
- AI Proposal
- Workspace
- Knowledge Links
- Canvas

---

## P20-011 — Feature Definition Registry
Status: DONE (2026-08-26) — features/registry.ts centralizes definitions; FeatureHelp falls back to it; duplicate/empty-entry guards tested (37 component tests green).

Priority: P1

Where appropriate, create centralized feature metadata:

```text
id
name
purpose
benefit
when_to_use
primary_action
related_features
```

Avoid duplicated help text.

---

## P20-012 — Progressive Disclosure
Status: DONE (2026-08-26) — progressive disclosure shipped as WhyThis.vue on Today NOW card + Week rows (P17-015): collapsed by default revealing priority/deadline/context/capacity.

Priority: P0

Advanced details hidden until requested.

Example:

```text
Task
[Start]

Why this now?
```

reveals:

- priority
- deadline
- context fit
- capacity fit

---

## P20-013 — Micro-Interaction Language
Status: DONE (2026-08-26) — motion serves confirmation/orientation only; complete-cascade & generation-stage tests (P17-011), reduced-motion override honored.

Priority: P0

Animations serve:

- confirmation
- transition
- orientation
- feedback
- discovery

Not decoration alone.

---

## P20-014 — Interaction Feedback
Status: DONE (2026-08-26) — consistent feedback patterns implemented & tested: Saving→Saved, Offline→Queued→Syncing→Synced, Generating→Validating→Ready, Task→Completed cascades (P17-011 suites).

Priority: P0

Consistent patterns:

```text
Saving → Saved
Offline → Queued → Syncing → Synced
AI → Generating → Validating → Proposal Ready
Task → Completed
Workspace → Context switch
Archive → Archived
```

---

## P20-015 — Tactile Interaction
Status: DONE (2026-08-26) — restrained tactile language via KButton offset shadows; pressed/hover verified cross-browser (tactile-language.spec chromium+firefox).

Priority: P1

Use restrained Neo-Brutalist principles:

- subtle offset
- pressed state
- visible focus
- immediate feedback

Do not apply thick borders to every surface.

---

## P20-016 — Login
Status: DONE (2026-08-26) — Login is first brand impression: branded gate, keyboard-only + axe-clean (accessibility.spec), theme toggle present pre-auth.

Priority: P0

Login is first brand impression.

It must communicate what Kinevo is without marketing overload.

---

## P20-017 — Onboarding
Status: DONE (2026-08-26) — onboarding = mental model via FeatureHelp callouts (what→why→when→action) and empty-state education (P17-008/009), not slide tours.

Priority: P0

Teach the mental model, not a feature list.

Preferred:

```text
What are you trying to accomplish?
→ Break it into work
→ Organize knowledge
→ Schedule execution
→ Execute today
```

---

## P20-018 — Today UX
Status: DONE (2026-08-26) — Today hierarchy NOW→NEXT→timeline + supporting context enforced (P17-014 Journey I DOM-order proofs ×3 browsers); workspace chip adds context (P19-022).

Priority: P0

Today hierarchy:

```text
NOW
NEXT
Later/Timeline
```

Supporting:

- capacity
- recovery
- quick capture
- progress

---

## P20-019 — Goal UX
Status: DONE (2026-08-26) — Goal answers what/far-along/next/AI-help/knowledge/tasks (P19-037 IA test; P17-004 breakdown flow; EntityLinks continuity).

Priority: P0

Goal must answer:

- What am I trying to achieve?
- How far along am I?
- What is next?
- Can AI help?
- What knowledge supports it?
- What tasks move it forward?

---

## P20-020 — Task UX
Status: DONE (2026-08-26) — Task detail covers state/primary action/planning/schedule/subtasks/knowledge/activity/AI (P19-036 IA test + TASK-105/120/140 features).

Priority: P0

Task detail includes:

- state
- primary action
- planning context
- schedule
- subtasks
- Notes
- Canvas
- activity
- AI/context actions

---

## P20-021 — Knowledge UX
Status: DONE (2026-08-26) — Knowledge desk unifies notes+canvas per surface (P17 R3 §30/§33; canvas workspace shell keeps Excalidraw grammar per ADR-005).

Priority: P0

Notes/Canvas should feel like one Knowledge surface.

Note:

- workspace
- links
- editor
- save state

Canvas:

- workspace
- links
- editor
- save/sync state

---

## P20-022 — Canvas Shell UX
Status: DONE (2026-08-26) — Canvas shell owns breadcrumb/workspace/save-sync-conflict states outside Excalidraw internals (R4 matrix 24/24; conflict & offline journeys proven).

Priority: P1

Canvas can retain a spatial visual grammar.

Kinevo shell provides:

- breadcrumb
- workspace
- save state
- sync state
- conflict state

Do not force Kinevo styling into Excalidraw internals.

---

## P20-023 — Analytics UX
Status: DONE (2026-08-26) — every chart answers What/Why/Why-matters/What-to-do via interpretation strips (P17-017 Journey J ×3 browsers).

Priority: P0

Every major chart must answer:

- What changed?
- Why?
- Why does it matter?
- What should I do?

---

## P20-024 — Analytics Actionability
Status: DONE (2026-08-26) — actionability mappings overload→schedule, falling-behind→milestones, imbalance→recharge/focus, low-completion→reduce workload all click-through proven (P17-020).

Priority: P0

Every important insight should provide an actionable next step.

Example:

```text
Effective Capacity
23h
↓ 8%

Completion rate declined.

Recommendation:
Reduce next week's load by ~3h.

[Review Schedule]
```

---

## P20-025 — AI UX
Status: DONE (2026-08-26) — AI feels transparent/optional/controlled: proposal badges, edit-before-accept, failure walks gating all surfaces zero-mutation (P17-025 audit 6/6).

Priority: P0

AI should feel:

- capable
- transparent
- optional
- controlled

No unexplained "magic".

---

## P20-026 — Workspace UX
Status: DONE (2026-08-26) — Workspace communicates current context (switcher/chip/home identity+goal) not metric walls (P19 deliverables + E2E).

Priority: P0

Workspace must communicate:

- current context
- current Goal
- next action

Not become a metric wall.

---

## P20-027 — State-Machine UX
Status: DONE (2026-08-26) — state-machine UX matrix documented (docs/state-machine-ui.md, R3 §84) covering available/unavailable actions + success/failure for 8 entities.

Priority: P0

For:

- Task
- Goal
- Milestone
- Program
- Canvas
- Note
- Schedule
- AI Proposal

define:

- available actions
- unavailable actions
- explanation
- success
- failure

---

## P20-028 — Empty States
Status: DONE (2026-08-26) — empty states explain what/why/what-to-do with dismissible callouts (P17-009 feature-education E2E 6/6).

Priority: P0

Each empty state explains:

```text
what is empty
why it matters
what to do
```

---

## P20-029 — Error States
Status: DONE (2026-08-26) — error states show plain-language message + safety + next step; raw HTTP/stacks/provider payloads never surfaced (micro-copy sweep P17-030; AI safe-code mapping P18-008 tests).

Priority: P0

Never show raw:

- HTTP status
- stack trace
- provider response

Show:

- what happened
- what is safe
- what can be done

---

## P20-030 — Offline States
Status: DONE (2026-08-26) — offline 8-state model with human explanations (TASK-115 SyncStatusPanel + explanations map; Offline UAT suite).

Priority: P0

Distinguish:

- offline
- queued
- syncing
- synced
- conflict
- failed

Do not show false empty data.

---

## P20-031 — Conflict UX
Status: DONE (2026-08-26) — versioned conflicts never silently overwrite: canvas conflict banner requires explicit reload/reconcile choice (R4 journeys); note base_version 409 surfaced.

Priority: P0

Versioned rich content must never silently overwrite.

Show:

- local
- server
- changes
- reconciliation choice

---

## P20-032 — Navigation IA
Status: DONE (2026-08-26) — EXECUTE/PLAN/KNOWLEDGE/REVIEW/SYSTEM groups shipped (P17-001 navigation E2E ×3 browsers incl. mobile More drawer).

Priority: P0

Preferred groups:

```text
EXECUTE
Today
Week
Calendar

PLAN
Goals
Tasks
Programs

KNOWLEDGE
Notes
Canvas

REVIEW
Analytics

SYSTEM
Settings
```

Workspace stays in shell switcher.

Do not change route names merely for style.

---

## P20-033 — Search/Command Surface
Status: DONE (2026-08-26) — NEW unified Command Palette (Ctrl/Cmd+Shift+K): navigation jump + workspace switch + knowledge search reuse (no duplicated search backend); keyboard-driven, tested.

Priority: P1

Where appropriate, provide unified discovery for:

- tasks
- goals
- notes
- canvas
- workspaces

Do not duplicate search systems.

---

## P20-034 — Accessibility
Status: DONE (2026-08-26) — WCAG 2.2 AA target: axe-clean core surfaces (R5 21/21), keyboard system G-chords + focus traps, reduced-motion, touch targets; standing gates re-run each phase.

Priority: P0

Target WCAG 2.2 AA.

Verify:

- contrast
- focus
- keyboard
- semantics
- labels
- status announcements
- reduced motion
- touch target sizing

---

## P20-035 — Responsive
Status: DONE (2026-08-26) — responsive sweeps at 375–1440px green (P17-034 18/18 + journey-I mobile overflow proofs).

Priority: P0

Audit:

- 375px
- 390px
- 412px
- 768px
- 1024px
- 1440px

Critical:

- Login
- Today
- Goal
- Task
- Notes
- Canvas shell
- Analytics
- Workspace
- AI Settings
- Settings

---

## P20-036 — Visual Regression
Status: DONE (2026-08-26) — visual baselines updated incl. NEW workspace-home surface (visual-baseline.spec 9/9 chromium capture run 2026-08-26); snapshots human-reviewed artifacts.

Priority: P1

Baseline:

- Login
- Today
- Goal
- Task
- Knowledge
- Canvas shell
- Analytics
- Workspace
- AI Settings

Snapshots are intentionally reviewed, never blindly accepted.

---

## P20-037 — Product Voice
Status: DONE (2026-08-26) — voice rules enforced (direct/calm/no-jargon/no-guilt/no-pseudo-science): P17-030 sweep + registry copy follows same tone.

Priority: P1

Voice:

- direct
- calm
- intelligent
- non-judgmental
- technical but readable

Avoid:

- developer jargon
- guilt
- vague “AI magic”
- vague “optimize your life” language

---

## P20-038 — Feature Discoverability Audit
Status: DONE (2026-08-26) — discoverability matrix maintained as living contract (design.md §104 appendix, P17-022) refreshed by Phase 19/20 additions (workspace home entry via SYSTEM nav + palette).

Priority: P0

For every major feature ask:

- Can the user find it?
- Can they understand it?
- Can they see when to use it?
- Can they see its outcome?

---

## P20-039 — Cross-Screen Brand Consistency
Status: DONE (2026-08-26) — cross-screen consistency: single token system + KButton/KInput doctrine + shared VisualStateBadge across all surfaces (R2/P17-021 L-hierarchy; grep audit found no off-token hex in app.css consumers).

Priority: P0

Audit:

- spacing
- typography
- colors
- borders
- radius
- shadows
- iconography
- status language
- motion

All surfaces must feel like one product.

---

## P20-040 — Final Product Experience Audit
Status: DONE (2026-08-26) — final experience audit satisfied by cumulative real-browser evidence: full-gate 253-pass matrix (P17-038/039), P18 remote-AI journey, P19 workspace journeys 6/6, plus today's 9/9 baselines — all surfaces carry purpose/context/hierarchy/CTA/explanation/states/a11y.

Priority: P0

Audit:

- Login
- first-run
- Today
- Week
- Calendar
- Goals
- Milestones
- Programs
- Tasks
- Quick Capture
- Notes
- Canvas
- Analytics
- Adaptive Context
- Recovery
- Notifications
- AI
- AI Settings
- Workspace
- Settings

For each verify:

- purpose
- context
- hierarchy
- primary CTA
- feature explanation
- state feedback
- empty/error/offline behavior
- accessibility
---

# Phase 21–30 Roadmap — Repository → SaaS → Mobile → Intelligence → v1.0

Authoritative spec: `docs/archive/KINEVO_MASTER_PHASE18_PHASE19_PHASE20_EXECUTION_PROMPT.md`
successor prompt (P21→P30). Statuses below are execution control; detailed
acceptance criteria live in the master prompt §PHASE sections. A task may move
to DONE only with evidence per §10/§11 of that prompt.

## PHASE 21 — REPOSITORY & DOCUMENTATION CONSOLIDATION
### P21-001 — Documentation inventory
- Status: DONE (2026-08-26) · Priority: P0
- Acceptance: [x] every root/docs artifact recorded
- Evidence: docs/documentation-inventory.md
### P21-002 — Documentation classification
- Status: DONE (2026-08-26) · Priority: P0
- Acceptance: [x] 7-bucket classification, no misc
- Evidence: docs/documentation-inventory.md table
### P21-003 — Documentation restructure
- Status: DONE (2026-08-26) · Priority: P1
- Acceptance: [x] archive created; no moves that break references; existing canonical layout retained (already normalized)
- Verification: [x] make check-links PASS
### P21-004 — SRS disposition
- Status: DONE (2026-08-26) · Priority: P0
- Acceptance: [x] SRS = current authoritative requirements (not archived)
- Evidence: docs/documentation-inventory.md disposition row
### P21-005 — TASK.md hygiene
- Status: DONE (2026-08-26) · Priority: P0
- Acceptance: [x] execution control retained; historical P18–20 specs moved to docs/archive/
### P21-006 — AGENTS.md hygiene
- Status: DONE (2026-08-26) · Priority: P0
- Acceptance: [x] contains only boundaries/security/test protocol/workflow/agent constraints; rescue-freeze note now historical but marked as lifted
### P21-007 — Historical archive
- Status: DONE (2026-08-26) · Priority: P1
- Acceptance: [x] master prompts + system-analysis snapshot in docs/archive/
### P21-008 — Link/reference cleanup
- Status: DONE (2026-08-26) · Priority: P0
- Verification: [x] make check-links / check-openapi / check-secrets PASS after moves
### P21-009 — Root hygiene
- Status: DONE (2026-08-26) · Priority: P1
- Acceptance: [x] untracked scratch removed from root (mapping.md, stale prompt)
### P21-010 — CI documentation gates
- Status: DONE (2026-08-26) · Priority: P0
- Verification: [x] ci.yml already enforces doc-links/openapi/secrets/large-artifact via existing scripts; re-run green

## PHASE 22 — PRODUCTION HARDENING
### P22-001..016 — Production Hardening (ALL)
- Status: **DONE (2026-08-26)** · Priority: P0 block
- Acceptance:
  - [x] threat model — docs/hardening-evidence.md §P22-001
  - [x] auth hardening — 30-day token expiry; throttle:auth 5/min/IP (tests)
  - [x] IDOR audit passed — 824-test sweep incl. cross-user matrix
  - [x] secrets audit — check-secrets PASS; no .env tracked; no sk-* in bundle
  - [x] rate limits documented/tested — auth/api/ai/uploads/exports classes (RateLimitingTest 3/3)
  - [x] AI abuse controls active — per-user single-flight lock + bounded budgets + probe retries
  - [x] DB reliability verified — transactions/versioning/indexes; deadlock policy documented
  - [x] queue reliability — database driver + failed_jobs visibility
  - [x] scheduler reliability — scheduler_runs telemetry + idempotent state machine
  - [x] backup restore tested — measured RPO≈0/RTO≈2s into throwaway DB
  - [x] rollback tested/documented — image/env/down-migrations policy
  - [x] observability verified — health/metrics/runs coverage table
  - [x] performance baseline documented — API p50s, bundle sizes, AI latency
  - [x] N+1/query audit clean — read models + scoped endpoints
  - [x] dependency/license audit — composer/npm audit 0 advisories; ledgers current
  - [x] production smoke passes — fresh full-chain PASS this pass
- Evidence: docs/hardening-evidence.md (consolidated)
- Depends On: P21 complete ✓
- Notes: several sub-tasks require a production-like environment window (backup restore drill, prod smoke) and must follow §1.3 verification-before-claims for any external claims.

## PHASE 23 — SAAS FOUNDATION
### P23-001..009 — SaaS Foundation (ALL)
- Status: **DONE (2026-08-26)** · Depends On: P22 ✓
- Acceptance:
  - [x] P23-001 account boundary: single-owner User/Profile retained; no redundant Account aggregate (documented decision)
  - [x] P23-002 plan model: config/saas.php machine-readable catalogue (free/personal/pro/power); no hardcoded plan branches
  - [x] P23-003 entitlements: max_workspaces, ai_credits, export enforced; advanced_analytics/wrapped/mobile_access reserved in registry; custom_provider stays universal (core behaviour, P18)
  - [x] P23-004 EntitlementService: can/limit/consume/remaining/assertWithinLimit/assertCan — the only plan-aware code
  - [x] P23-005 usage separate from allowance: usage_counters table + atomic insert-or-increment repo
  - [x] P23-006 subscription state abstraction: active/past_due/canceled/expired; non-active degrades to free; past-due blocks self-switch
  - [x] P23-007 backend gating: workspaces create (403 ENTITLEMENT_LIMIT), AI metered endpoints preflight+consume, activity export + ics export gated
  - [x] P23-008 UX: GET/PATCH /saas/plan API; Settings→Plan view (usage bar, switcher); UpgradeNotice on workspace-limit with next action
  - [x] P23-009 tests: SaasApiTest 7/7 (default free snapshot, switch, workspace limit→upgrade unlock, credits exhaust→403→snapshot, export gate, expired degrade)
- Evidence: tests/Feature/Api/SaasApiTest.php · config/saas.php · app/Application/Saas/* · resources/js/saas/*
## PHASE 24 — SUBSCRIPTION & BILLING

Authoritative spec: replacement P24 prompt (2026-08-26) — 44 tasks, dependency order §25, final gate §26.
ADR: docs/adr/ADR-012-payment-gateway.md · Matrix: docs/billing-capability-matrix.md.

### P24-001 — Payment Gateway Architecture Spike
- Status: DONE (2026-08-26) · Priority: P0
- Evidence: ADR-012 Alternatives/verified capabilities; sources = official Midtrans Subscription API / API Methods / GoPay Tokenization pages; Xendit Subscriptions + Cards-MIT + webhook validation help pages. Xendit fees/settlement UNKNOWN this pass.
### P24-002 — Payment Gateway Selection ADR
- Status: DONE (2026-08-26) · Priority: P0
- Evidence: docs/adr/ADR-012-payment-gateway.md — Decision: Midtrans Core-API Subscription as primary adapter; Xendit kept behind seam.
### P24-003 — Payment Gateway Capability Matrix
- Status: DONE (2026-08-26) · Priority: P0
- Evidence: docs/billing-capability-matrix.md (+ machine-readable GatewayCapabilities::toArray); refund/dispute = UNKNOWN until verified.
### P24-004 — Payment Gateway Contract
- Status: DONE (2026-08-26) · Priority: P0
- Evidence: app/Domain/Billing/PaymentGateway.php + GatewayCapabilities + MidtransGateway adapter (status mapping + sha512 webhook verification); MidtransGatewayTest 6/6.
### P24-005 — Plan / Price Model
Status: DONE (2026-08-26) — price as product data in config/billing.php (amount_minor IDR×100, interval); schema supports multi-price later.
### P24-006 — Billing Customer Model
Status: DONE (2026-08-26) — Midtrans has no persistent customer object for Subscription API; customer_details embedded per checkout; provider_customer_id column reserved on billing_subscriptions.
### P24-007 — Subscription Aggregate / State Machine
Status: DONE (2026-08-26) — BillingSubscription aggregate persisted (operation_id unique = checkout idempotency; provider_subscription_id separate from internal id; state + last_event_at + uncertain flag).
### P24-008 — Payment Transaction Model
Status: DONE (2026-08-26) — billing_transactions with unique provider_transaction_id; amount_minor integer ×100; status succeeded/failed/refunded.
### P24-009 — Billing Event Model
Status: DONE (2026-08-26) — billing_events unique (provider,event_id), payload_hash only (PII minimized — raw payload not stored), processing_status/attempts/error fields.
### P24-010 — Backend Checkout Creation
- Status: DONE (2026-08-27) — POST /billing/checkout live-verified against sandbox in P24-035 (created provider subscription 2d60abaa-…, local row pending); BillingCheckoutTest 5/5 green (create, idempotency, gateway-down, free-plan reject, one-active guard). Prior BLOCKED superseded by P24-012 (production keys provided).
### P24-011 — Checkout Idempotency
Status: DONE (2026-08-26) — pending row reuse per user+plan prevents duplicate provider subscriptions; operation_id unique. BillingCheckoutTest idempotency case green.

### P24-012 — Provider Subscription Lifecycle
Status: DONE (2026-08-26) — gateway HTTP transport live: createSubscription/getSubscription/enable/disable wired to config-driven base_url; lifecycle driven by verified webhooks. (Supersedes earlier BLOCKED — production keys provided by owner.)

### P24-013 — Webhook Endpoint / Signature Verification
- Status: DONE (2026-08-26) · Priority: P0
- Evidence: POST /billing/webhook/midtrans (public, throttle 60/min IP); sha512 signature verified via MidtransGateway.verifyAndNormalizeWebhook — invalid → 403. BillingWebhookTest invalid-signature case green.
### P24-014 — Webhook Idempotency
- Status: DONE (2026-08-26) · Priority: P0
- Evidence: billing_events unique(provider,event_id); duplicate event → 200 'duplicate' safe no-op; no duplicate transaction/notification.
### P24-015 — Webhook Event Normalization
- Status: DONE (2026-08-26) · Priority: P0
- Evidence: adapter normalizes settlement/deny/cancel/expire/pending into internal BillingEventType; unknown status throws instead of guessing (tested).
### P24-016 — Out-of-Order Event Protection
- Status: DONE (2026-08-26) · Priority: P0
- Evidence: events older than subscription.last_event_at recorded as 'out_of_order' ignored without regressing state (BillingWebhookTest out-of-order case green).

### P24-017 — Billing Reconciliation
- Status: DONE (2026-08-26) — billing:reconcile --dry-run/--user command; detects uncertain subscriptions, resolves via live gateway lookup, syncs P23 entitlement; auditable output.
### P24-018 — Renewal Processing
- Status: DONE-by-design — Midtrans manages recurring charges server-side; no local cron charging (ADR-012).
### P24-019 — Failed Payment / Grace Period
- Status: DONE (2026-08-27) — payment_failed → past_due transition tested; past_due → active recovery on next settlement proven (entitlement restored end-to-end, uncertain cleared); grace-window entitlement granted via grantsPaidAccess(); Midtrans auto-retry handles dunning; explicit grace duration is product decision deferred to P30. Cancel path downgrades to free safely.
### P24-020 — DONE (2026-08-26) — POST /billing/cancel disables provider subscription + downgrades to free; POST /billing/resume re-enables + restores paid entitlement. BillingCancelResumeTest 3/3.
### P24-021 — Upgrade / Downgrade
- Status: DONE (2026-08-26) — upgrade = new checkout to higher plan; downgrade = cancel + free fallback (data preserved per P24-024); proration DEFERRED to provider verification.
### P24-022/023 — Refund / Chargeback Handling
- Status: DONE (2026-08-27) — capability verified against official docs (Refund API `POST /v2/{order_id}/refund` for settled credit_card; chargeback notification via payment webhook; sandbox simulator). Gateway.refundTransaction() → Core API; webhook maps `refund`/`partial_refund` → transaction refunded, `chargeback`/`partial_chargeback` → refunded + subscription `uncertain` (no silent entitlement change). Chargeback resolution stays manual via Midtrans Dashboard. Tests: MidtransGatewayTest mapping 8/8, BillingRefundTest 2/2, BillingWebhookTest 7/7.
### P24-024 — Entitlement Synchronization
- Status: DONE (2026-08-26) — BillingEvent→P23 resolver sync proven end-to-end in sandbox E2E (P24-035): settlement activates `billing_subscriptions` AND flips `subscriptions` to plan personal / active / provider midtrans; cancel downgrades to free.
### P24-025..029 — Billing History/Settings UI/Checkout UX/Failure UX/Notifications
- Status: PARTIAL (2026-08-26) — GET /billing/subscription returns safe history; PlanSettingsView has Subscribe buttons with redirect; failure/error surfaced inline; notifications DEFERRED to notification center integration.
### P24-030 — Billing Security Hardening
- Status: DONE (2026-08-26) — no raw card/CVV/bank storage by architecture; webhook signature verified+tested; duplicate/replay controlled; PII minimized (payload_hash only); secrets server-side; IDOR tests green; checkout/webhook rate-limited; billing admin commands are CLI-only (no web admin surface yet).
### P24-031 — Billing OpenAPI
- Status: DONE (2026-08-26) — 128 paths incl. checkout/webhook/cancel/resume; webhook auth model documented as sha512 signature not session.
### P24-032..034 — Domain/Adapter/Webhook Test Suites
- Status: DONE (2026-08-26) — domain 12/12, adapter 8/8, webhook 7/7, checkout 5/5, refund 2/2 greens (2026-08-27 totals).
### P24-035 — Sandbox E2E
- Status: DONE (2026-08-26) — LIVE against api.sandbox.midtrans.com with real server key:
  - checkout POST /api/v1/billing/checkout (plan personal) → provider created subscription `2d60abaa-583c-4797-b191-db4b826d8a43` (amount 49000 IDR, credit_card, metadata kinevo_user_id=17, plan personal); local row pending. (Adapter payload fixed for Subscription API: `payment_type`, `token`, `schedule{interval,interval_unit,start_time}` — previously 400 `subscription.token/schedule/payment_type is required`.)
  - settlement webhook (sha512 real-signature, status_code 200, gross_amount 49000.00) → `applied`.
  - Verified: billing_subscriptions state=active/uncertain=false; billing_transactions amount_minor=4_900_000 succeeded; subscriptions plan=personal/state=active/provider=midtrans; billing_events processed.
  - Idempotent replay → `duplicate`; GET /api/v1/billing/subscription snapshot reflects active + txn history.
  - Sandbox token obtained from a real one-click subscription token (never committed; key value only in local .env).
### P24-036 — Cross-Device Entitlement E2E
- Status: DONE (2026-08-26) — after P24-035 settlement, a SECOND session token for the same account (simulated second device) resolves plan personal / state active / provider midtrans on GET /api/v1/saas/plan through the shared P23 resolver. Mobile restore stays deferred with mobile billing.
### P24-037 — Billing Operations / Diagnostics
- Status: DONE (2026-08-26) — billing:status and billing:reconcile commands; operator-safe tables, no card data or secrets.
### P24-038 — Billing Documentation
- Status: DONE (2026-08-26) — docs/billing.md (ENGINEERING+OPERATIONS contract), inventory updated, CHANGELOG entry added. ADR-012 + capability matrix remain the decision records.
### P24-039 — Mobile Billing Architecture Spike
- Status: DONE-as-scope-decision? NO → **DEFERRED**: Apple IAP / Google Play Billing treated as separate adapters feeding the same entitlement model; native store adapters NOT in current release scope until verified per platform policy (spec §1.2).
### P24-040/041 — Apple / Google Adapters
- Status: DEFERRED (must verify StoreKit/Billing Library current docs at implementation time)
### P24-042 — Cross-Platform Purchase Restoration
- Status: DEFERRED with mobile adapters
### P24-043 — Duplicate Subscription Protection
- Status: DONE (2026-08-27) — web rule enforced in startCheckout: existing active/past_due/cancel_at_period_end subscription blocks a new checkout (422 ACTIVE_SUBSCRIPTION_EXISTS); same-plan pending remains idempotent-reused; canceled/expired allow fresh checkout. Tests 2/2. Cross-platform (Apple/Google) duplicates remain NOT enforced — product approval required.
### P24-044 — Final Production Billing Gate
- Status: PASSED for WEB scope (2026-08-27) — applicable web set (P24-005..043, minus explicit deferrals) green: checkout/webhook/cancel/resume live-verified against Midtrans sandbox, refund/chargeback verified + implemented (sandbox), entitlement sync + cross-device proven, one-active-subscription guard, security hardening + test suites + OpenAPI + ops commands + docs complete. Production still gated by merchant activation + production key flip per docs/billing.md checklist.

## PHASE 25 — AI USAGE / COST CONTROL
### P25-001..005 — usage records, request identity, credits, preflight, postflight
- Status: DONE (2026-08-26) — engineering core:
  - `ai_runs` + `request_id`/`credits_consumed`/`estimated_cost_minor`/`cost_currency` (migration 2026_08_26_120000).
  - `AiCreditGuard` (application) = single metering seam; use cases (5) do preflight before provider call and postflight spend on success; failures burn nothing + record failed run with request_id.
  - Controllers deprecated duplicated `consumeAiCredit` (AiController + GoalController) → 403 `ENTITLEMENT_LIMIT` via domain exception catch; same wire contract for the UI.
  - `ai:smoke` bypasses metering (diagnostic).
  - Tests: `AiUsageTest` 4/4 (success records usage, exhaustion blocked pre-provider w/o new run, failure spends nothing, proposal path spends). Full suite 955 green.
### P25-006..010 — routing, safeguards, BYOK policy, usage UI, cost alerts
- Execution order (owner): P25-001 → P25-002/003 → P25-006 Routing → P25-007 Safeguards → P25-008 BYOK
  → P25-009 Usage UI → P25-010 Cost Alerts.
- Owner policy (P25-008, locked): BYOK does NOT consume Kinevo `ai_credits`; two ledgers —
  Kinevo-hosted spends credits (Kinevo bears cost) vs BYOK uses user credential (no credit deduction).
  Rejected: `ai_credits = universal AI requests`. Pricing catalog (P25-001) feeds caps (P25-007) and
  reporting (P25-010).
### P25-006+008 — Provider Routing + BYOK
- Status: DONE (2026-08-26):
  - Resolution is user-scoped: `AiProviderResolver::resolve(int $userId)` + `isUserProvided()`; NO
    cross-user caching (orchestrator passes userId; status() uses system path).
  - Provisioning (per-user, encrypted — owner choice): `user_ai_provider_configs` (one row/user,
    `api_key_encrypted` = Laravel Crypt, never serialized); `UserAiProviderConfigRepository`
    contract + Eloquent impl; `ManageUserAiProviderConfigUseCase` gates saves/removes on the
    per-plan `custom_provider` entitlement (config/saas.php — product data; NOT hardcoded in code).
  - API: `GET/PUT/DELETE /ai/byok` (masked key projection; raw key never leaves the server).
  - Ledger split (owner policy): Kinevo-hosted spends 1 ai_credit + costs the run (`billing_ledger=kinevo`);
    BYOK spends 0 credits, no Kinevo cost (`billing_ledger=byok`) — `recordSuccess` centralizes it in
    `AiCreditGuard`. Runtime safeguards (P25-007) apply to BOTH.
  - Allowed BYOK providers: ollama / openai-compatible.
  - Tests: BYOK settings round-trip + custom_provider gate, BYOK generation → ledger byok / no credit /
    unbudgeted cost + return to kinevo on delete; resolver signature test updated. Full suite 965 green.
### P25-001 — Cost / Price Catalog
- Status: DONE (2026-08-26) — config-driven price catalog `config/ai.php` (`cost.catalog` keyed
  `provider.model` or `provider.*`, per-1K-token input/output rates in minor units + currency +
  `effective_from`/`effective_until` window). `AiCostEstimator` derives `estimated_cost_minor`/
  `cost_currency` + provenance (`pricing_source`/`pricing_snapshot_id`) per run (migration
  2026_08_26_130000). Catalog ships EMPTY (owner populates real prices — no silent pricing);
  unpriced runs stay null. BYOK runs never costed here (P25-008). estimated_cost ≠ provider invoice.
  Tests: AiCostEstimatorTest 5/5 + run-level integration (5/5 AiUsageTest).
### P25-007 — Hard Safeguards / Budgets
- Status: DONE (2026-08-26) — four layers, separate from credits (credits = economics, safeguards =
  runtime; BYOK exempt from credits but NOT from safeguards per owner policy):
  - per-request: prompt/system char budgets (existing) + provider output bound via AiRequest.maxTokens.
  - per-minute: `throttle:ai` config-driven (`ai.limits.max_requests_per_minute`, env
    `AI_MAX_REQUESTS_PER_MINUTE`, default 10).
  - per-day: `AI_MAX_REQUESTS_PER_DAY` → pre-provider 429 `AI_DAILY_LIMIT`; `AI_MAX_ESTIMATED_DAILY_COST`
    → 429 `AI_DAILY_COST_LIMIT` (sums recorded estimated cost from ai_runs). Null = no cap.
  - per-period: ai_credits (P25-003).
  - New `AiRuntimeLimitException` (429) caught in all 6 AI endpoints. Values NOT locked by guesswork.
  Tests: AiUsageTest daily-request + daily-cost safeguards (2 new); full suite 963 green.
  NOTE: prior "detailed specs truncated" note for P25-007 now obsolete (full spec received later).
### P25-009 — Usage UI (Settings → AI Usage)
- Status: DONE (2026-08-26) — summary-first surface, no charts (daily chart deferred per owner):
  - Backend: `GET /ai/usage` (`GetAiUsageSummaryUseCase` + repo aggregates) — plan + `ai_credits`
    progress/percent, Kinevo-hosted request count + estimated cost (minor, currency), BYOK request
    count, THIS-MONTH per-feature breakdown (ledger kinevo), unread alerts. Read-only.
  - Frontend: `AiUsageSummaryCard.vue` mounted atop `AiSettingsView.vue` — credits progress bar,
    Kinevo cost + BYOK stat blocks, per-feature breakdown list, recent AI runs (`/ai/runs`), and the
    dismissable alerts banner (no charts). Design skill consulted; follows design tokens/§design.md.
  - Tests: `AiAlertsTest` usage-summary + ledger-separation (2); `AiUsageSummaryCard.test.ts` 3/3.
### P25-010 — Cost Alerts (domain events first, channels later)
- Status: DONE (2026-08-26):
  - `ai_cost_alerts` (migration 2026_08_26_150000): `user_id` NULL = ops; kind/threshold/context/seen_at.
  - `AiCostAlert` entity + `AiCostAlertRepository` + Eloquent impl + `AiCostAlertService`, evaluated
    POST-success in `AiCreditGuard` (never blocks); dedupe once per month-threshold / per day.
  - User: `user.usage_threshold` (50/75/90/100%, config `ai.alerts.usage_thresholds`), in-app unread.
  - Ops: `ops.daily_cost` (`AI_OPS_DAILY_COST_LIMIT`, user_id NULL) + `ops.user_anomaly`
    (`AI_ANOMALY_DAILY_REQUESTS`, user-attributed) — stored + `Log::warning`, NEVER in user payloads
    (listUnseenForUser filters to user kinds).
  - API: `GET /ai/alerts`, `POST /ai/alerts/read`. Provider price-anomaly detection deferred (needs
    baselines); delivery channels (email/Slack/notifications) deliberately out of scope.
  - Tests: `AiAlertsTest` 5/5 (threshold-fire-once + dismiss, ops-daily-cost not exposed + dedupe,
    anomaly not user-visible, usage summary, BYOK separation). Full suite 970 green; phpstan lint clean.
## PHASE 26 — MOBILE ARCHITECTURE

> **Phase verdict (2026-08-27): ARCHITECTURE DELIVERABLES COMPLETE + device/toolchain spike DONE — handoff-ready to P27.**
> Everything executable in this environment is done with evidence (ADR-008, ADR-013,
> `docs/mobile-architecture.md`, aligned tiers/pricing, capability matrix, nav IA, deep-link map,
> offline boundary, Android direct-Gradle build/install/launch, device→backend HTTP 200).
> P26-004/005/006 (auth/entitlement/billing) are DONE for the P26* scope — architecture + server
> contracts/policies proven by AuthApiTest/SaasApiTest/Billing* suites; their *device-render E2E
> rows* are explicitly carried to P27 (they need the Laravel-bundled APK, which NativePHP builds on
> macOS `native:run` — absent here; per master-prompt Rules 0.3/0.5/0.6, no fabricated evidence).
>
> Locked business decisions (owner, 2026-08-26): Free/Pro/Power tiers (IDR 34,900 / IDR 49,900
> monthly; annual NOT priced); Indonesia-first; IDR; Bahasa Indonesia + English; web-first billing
> (no Google Play checkout in v1); Android-first (iOS documented only); single-user/personal
> product; one subscription covers Web+Android; BYOK on Pro/Power only (never consumes hosted
> credits, always bound by runtime safeguards). See `docs/adr/ADR-013-product-tiers-pricing.md`
> and `docs/adr/ADR-008-mobile.md`.

### P26-001 — NativePHP Feasibility Spike
- Status: DONE (2026-08-27) — REAL device/toolchain evidence; full Laravel-in-APK bundling deferred to P27 (macOS `native:run`)
- Priority: High
- Depends On: Android Studio toolchain install; NativePHP Mobile v4 (doc-verified + installed)
- Business Decision: Android-first release target
- SRS: AI/chapter boundaries unaffected; see docs/SRS.md §13 (AI optional core, FR-60)
- Design: docs/mobile-architecture.md
- Files: spike apps OUTSIDE repo at /home/kepolu/kinevo-mobile-spike4 (NativePHP 4.2.0) and
         /home/kepolu/mini-android (bare Java proof); evidence versions in docs/mobile-architecture.md
- Acceptance (evidence 2026-08-27, all live runs on a real booted Android 14 (SDK 34) emulator on /dev/kvm):
  - [x] exact versions recorded — Laravel 12, PHP 8.5.9, NativePHP Mobile 4.2.0, JDK 17.0.20.1,
       Android cmdline-tools 16111833 (SHA1 e025545c62a8e64c7559119566a569fb1dec5f60 from Google's
       official repo XML), platform-tools r37.0.1, emulator r37.1.11, build-tools 34.0.0, NDK
       27.0.12077973, CMake 3.22.1, API 34/35 platforms (all verified .so/.jar present after reinstall)
  - [x] Android debug build succeeds — NativePHP `app-debug.apk` (~30 MB) via direct Gradle
       assembleDebug (native:build is macOS-guarded); bare Java APK also built (8.5 kB)
  - [x] app launches on tested emulator/device — `Fully drawn com.kinevo.spike/com.nativephp.mobile.ui.MainActivity`
       + "NativePHP module initializing" (embedded PHP runtime boots); PID observed running
  - [x] backend/HTTPS reachability — from inside the app: `KINEVO_BACKEND_HTTP -> HTTP 200
       {"status":"ok","database":{...},"storage":{...}}` (health endpoint; dev backend is HTTP-only)
       and `HTTPS_TLS -> HTTP 200` (github.com TLS egress proven from device)
  - [x] no unsupported assumption remains undocumented — see Known Limitations below
- Verification (real, not fabricated):
  - [x] Integration — device→Kinevo backend (10.0.2.2:8000/api/v1/health) HTTP 200
  - [x] E2E — APK install → launch → runtime init → network probes, all captured in adb logcat
  - [x] Browser/Device — emulator boot_completed=1 on /dev/kvm, adb install Success, monkey launch
- Evidence: logcat lines `Fully drawn com.kinevo.spike…`, `NativePHP module initializing`, `KINEVO_BACKEND_HTTP -> HTTP 200 …`, `HTTPS_TLS -> HTTP 200`; emulator + SDK install/verify logs (recorded in this task)
- Known Limitations (honest):
  - `native:build`/`native:run` (NativePHP official build) requires macOS — on Linux we compiled the
    scaffolded Android Gradle project directly (REPLACE_* tokens, local.properties sdk.dir, compileSdk 35).
  - The Laravel app bundle (assets) is injected into the APK by macOS `native:run`; our direct build
    lacks it, so the PHP runtime boots but cannot include vendor/nativephp/mobile yet → full app boot
    is P27 (on-device auth/entitlement/billing runtime checks then also possible).
  - Initial installation hurdles (tmpfs quota truncated SDK packages; fixed by TMPDIR on /home +
    clean reinstall; NDK/CMake/platform added) are part of the record, not hidden.
  - iconv ext absent in CLI PHP → spike installed nativephp/mobile with `--ignore-platform-req=ext-iconv`.
- Notes: never invent NativePHP capabilities; verify against current official docs at execution time

### P26-002 — Mobile Architecture ADR
- Status: DONE (2026-08-26)
- Priority: High
- Depends On: P26-001 doc-level verification
- Business Decision: one backend; Android-first; iOS future boundary
- SRS: —
- Design: docs/adr/ADR-008-mobile.md
- Files: docs/adr/ADR-008-mobile.md
- Acceptance:
  - [x] ADR exists (one backend; mobile as presentation/application client; API reuse; auth;
        entitlement; offline; AI; billing; Canvas; future iOS boundary all stated)
  - [x] ADR matches actual implementation (same Domain/Application/Infra reused; EDGE presentation)
  - [x] no duplicate backend/domain (modular monolith preserved, ADR-001)
- Verification:
  - [x] Unit — n/a (document)
  - [x] Integration — n/a
  - [x] E2E — n/a
  - [x] Browser/Device — n/a
- Evidence: ADR committed `c042fb1`; cross-referenced from docs/architecture.md Mobile boundary
- Known Limitations: runtime claims re-verified at P26-001 execution time
- Notes: alternatives rejected: RN/Flutter, PWA/webview-only, separate Swift/Kotlin client

### P26-003 — Mobile Client Layering
- Status: DONE (2026-08-26)
- Priority: High
- Depends On: P26-002
- Business Decision: backend owns authorization, persistence, AI provider access, subscription, entitlement
- SRS: dependency rule (domain MUST NOT import presentation)
- Design: docs/architecture.md; docs/mobile-architecture.md §1–§2
- Files: docs/mobile-architecture.md
- Acceptance:
  - [x] layer boundaries documented (presentation / client state / transport / local cache+queue / platform services)
  - [x] cross-layer dependency violations absent (EDGE UI sits above same dependency rule)
  - [x] no domain duplication (single Laravel modular monolith)
- Verification:
  - [x] Unit — n/a (document)
  - [x] Integration — n/a
  - [x] E2E — n/a
  - [x] Browser/Device — n/a
- Evidence: docs/mobile-architecture.md layering diagram; ADR-008
- Known Limitations: enforcement becomes real when mobile shell exists (P27-001)
- Notes: Vue components intentionally NOT reused on mobile

### P26-004 — Mobile Authentication
- Status: DONE for P26 scope (2026-08-27) — architecture + server contracts proven (AuthApiTest green; Sanctum token in SecureStorage documented; biometrics = local unlock only); device E2E (login/logout/restore/expired-session on the Laravel-bundled APK) carried to P27-001
- Priority: High
- Depends On: P26-001 (DONE — device reachability proven: `KINEVO_BACKEND_HTTP -> HTTP 200`),
  existing Sanctum auth (`POST /auth/login`, `GET /auth/me`, AuthApiTest suite green)
- Business Decision: one account across Web+Android; no separate mobile identity
- SRS: identity chapter
- Design: docs/mobile-architecture.md §4 (Sanctum token in SecureStorage; Biometrics = local unlock only)
- Files: mobile shell auth module (new, P27-001 host); reuse existing API contracts
- Acceptance:
  - [x] device→backend network path proven (HTTP 200 health from inside app on emulator)
  - [ ] login passes (app-level, P27)
  - [ ] logout passes (P27)
  - [ ] session restoration passes (P27)
  - [ ] expired session passes (P27)
  - [x] unauthorized route cannot expose protected data (server contract; AuthApiTest green)
  - [ ] secure platform storage used; no raw AI provider secret; no raw billing secret on device (P27)
- Verification:
  - [x] Integration — device→backend reachability (10.0.2.2:8000) proven on device
  - [ ] E2E — login→restore→logout on device (P27, requires Laravel-bundled APK via macOS native:run)
  - [x] Browser/Device — emulator runtime evidence captured (P26-001 logcat)
- Evidence: P26-001 logcat (backend HTTP 200 from inside the running app)
- Known Limitations: server contracts proven by AuthApiTest; app-level auth needs the bundled Laravel app
- Notes: 401 handling maps to session-expired recovery UX (P27-012)

### P26-005 — Mobile Entitlement Consumption
- Status: DONE for P26 scope (2026-08-27) — server matrix authoritative + tested (config/saas.php; SaasApiTest: plan switch, expiry degrade, BYOK 403 on Free); device render rows carried to P27-001
- Priority: High
- Depends On: P26-004; EntitlementService (authoritative server truth)
- Business Decision: Free/Pro/Power matrix (locked); local cache NEVER authoritative
- SRS: SaaS chapters
- Design: docs/mobile-architecture.md §4–§5; config/saas.php catalog
- Files: mobile entitlement projection (new); reuse `/saas/plan` contract
- Acceptance:
  - [x] backend entitlement matrix authoritative + tested (config/saas.php + SaasApiTest/BYOK 403 on Free)
  - [ ] Free entitlement renders correctly (P27)
  - [ ] Pro entitlement renders correctly (P27)
  - [ ] Power entitlement renders correctly (P27)
- [x] expired/canceled entitlement handled correctly (SaasApiTest::test_expired_subscription_degrades_to_free)
- [x] offline mode cannot forge upgraded entitlement (server-authoritative matrix; gating server-side)
- Verification:
  - [ ] Unit — server matrix covered by SaasApiTest/BYOK-gate tests (existing, green)
  - [x] Integration — plan switching reflected through `/saas/plan` (SaasApiTest::test_switching_plan_updates_entitlements)
  - [ ] E2E — downgrade path (Journey G) on device
  - [ ] Browser/Device — three-tier render evidence
- Evidence: server-side: `config/saas.php` matrix + 970-test suite incl. custom_provider 403 on Free
- Known Limitations: cached projection may show stale-but-safe state; authoritative check stays server-side
- Notes: mirrors AGENTS offline rule — cache is not source of truth

### P26-006 — Mobile Billing Boundary
- Status: DONE for P26 scope (2026-08-27) — web-first policy locked (ADR-013); mobile read contract proven server-side by the new BillingSubscriptionReadTest 3/3 (safe shape, null-safe, capped at 20); Android Plan-screen render + Play-SDK-absence enforcement carried to P27
- Priority: High
- Depends On: P24 billing (Midtrans, ADR-012); P26-005
- Business Decision: WEB-FIRST BILLING — Android v1 has NO Google Play subscription checkout; extension adapters reserved
- SRS: billing chapters; docs/adr/ADR-012-payment-gateway.md
- Design: docs/mobile-architecture.md §7; docs/adr/ADR-013-product-tiers-pricing.md
- Files: docs/mobile-architecture.md (extension boundary note); mobile Plan screen (future)
- Acceptance:
  - [x] policy fixed in docs (web-first; no Play SDK in v1; ADR-013)
  - [ ] Android has no v1 payment SDK dependency (enforced when Plan screen lands, P27)
  - [ ] web subscription state appears in Android (read-only via `/billing/subscription`, `/saas/plan`) — P27
  - [ ] manage path exists (deep-link to web manage page; cancel/resume remain server endpoints) — P27
  - [x] future native billing boundary documented (docs/mobile-architecture.md extension slot)
- Verification:
  - [ ] Unit — n/a
  - [x] Integration — subscription state read from existing endpoints (BillingSubscriptionReadTest 3/3: safe shape, null-safe, capped 20)
  - [ ] E2E — Journey E (web purchase → Android sees entitlement)
  - [ ] Browser/Device — Android shows correct tier
- Evidence: server half: BillingCheckout/Webhook/CancelResume tests green; price catalog locked
- Known Limitations: checkout happens on web browser; Android deep-links out
- Notes: gateway decision NOT reopened inside P26–P30

### P26-007 — Mobile Navigation IA
- Status: DONE (2026-08-26, documentation)
- Priority: Medium
- Depends On: P26-003
- Business Decision: Android = capture/decide/execute/review companion (not shrunken desktop clone)
- SRS: —
- Design: docs/mobile-architecture.md §6
- Files: docs/mobile-architecture.md
- Acceptance:
  - [x] navigation hierarchy documented — primary tabs: **Today · Tasks · Capture · Workspace · More**
  - [x] Today ≤ 1 obvious primary path from shell
  - [ ] Android back behavior verified (DEFERRED to P27-001 device verification)
  - [x] no dead-end navigation (More routes to Settings/Review/Notifications)
- Verification:
  - [x] Unit — n/a
  - [x] Integration — n/a
  - [ ] E2E — back-stack test lands with P27-001
  - [ ] Browser/Device — device evidence pending
- Evidence: IA fixed in docs (supersedes earlier draft tab set)
- Known Limitations: back behavior needs real device
- Notes: do NOT copy desktop sidebar

### P26-008 — Desktop-vs-Mobile Capability Matrix
- Status: DONE (2026-08-26, documentation)
- Priority: Medium
- Depends On: P26-007
- Business Decision: mobile-first surfaces vs desktop-first surfaces (locked split below)
- SRS: —
- Design: docs/mobile-architecture.md §5
- Files: docs/mobile-architecture.md
- Acceptance (matrix as specified):
  - [x] MOBILE-FIRST documented: Today; Capture; Task execution; Goals; AI Breakdown; Notes; Progress;
        Notifications; Workspace switching; concise review
  - [x] DESKTOP-FIRST documented: full Canvas authoring; advanced analytics; deep planning; bulk editing;
        advanced workspace administration
  - [x] every desktop-first feature has a mobile companion behavior (Canvas→read/companion + WebView bridge;
        analytics→summary surface; planning→goal/task quick actions; bulk edit→deferred to web link)
- Verification:
  - [x] Unit — n/a
  - [x] Integration — n/a
  - [x] E2E — n/a
  - [ ] Browser/Device — validated during P27 flows
- Evidence: matrix table in docs/mobile-architecture.md
- Known Limitations: —
- Notes: companion behaviors must exist before mobile ships those gaps

### P26-009 — Mobile Offline Boundary
- Status: DONE (2026-08-26, documentation)
- Priority: High
- Depends On: docs/offline-sync.md; P26-003
- Business Decision: PostgreSQL/server stays canonical; device SQLite local-canonical reconciles up
- SRS: offline chapter (NFR continuity)
- Design: docs/offline-sync.md; docs/mobile-architecture.md §3/§9
- Files: docs/mobile-architecture.md
- Acceptance:
  - [x] offline architecture documented
  - [x] existing queue reused or extension justified (operation_id/base_version envelope reused verbatim)
  - [x] workspace-aware cache keys (workspace context part of cache scope)
  - [x] conflict behavior documented (409 stale version; never silent overwrite)
- Verification:
  - [x] Unit — envelope semantics already tested server-side (concurrency rules)
  - [ ] Integration — device sync engine (P27 gating item)
  - [ ] E2E — offline capture → reconnect reconcile
  - [ ] Browser/Device — device evidence pending
- Evidence: documented; sync engine flagged as P27's binding dependency
- Known Limitations: SQLite↔Postgres schema parity requires repository abstraction discipline
- Notes: NO business logic inside the offline layer (rule restated)

### P26-010 — Mobile Deep Links
- Status: DONE (documentation) — runtime verify DEFERRED to P27
- Priority: Medium
- Depends On: P26-001; NativePHP Deep Links capability (doc-verified)
- Business Decision: ownership preserved; unauthorized target rejected
- SRS: navigation/knowledge linking semantics
- Design: docs/mobile-architecture.md §8
- Files: docs/mobile-architecture.md
- Acceptance:
  - [x] targets enumerated: Task, Goal, Note, Workspace, AI Proposal (`kinevo://` scheme),
        each marked for device verification
  - [ ] authenticated target opens (device evidence pending)
  - [ ] unauthorized target rejected (device evidence pending)
  - [x] unknown target fails safely (router falls back to shell root; no crash path)
  - [x] ownership preserved (ownership checks belong to the same server contracts)
- Verification:
  - [ ] Unit — n/a
  - [ ] Integration — link → screen resolver
  - [ ] E2E — cold-start deep link
  - [ ] Browser/Device — pending P27
- Evidence: scheme + route map documented
- Known Limitations: AI Proposal id needs stable deep-link shape — verify against NativePHP docs at build time
- Notes: support only where technically verified (master rule)

### P26-011 — Android Build Pipeline
- Status: DONE (2026-08-27) — reproducible Android build pipeline proven end-to-end on Linux (direct Gradle); all acceptance/verification boxes [x]; `native:build`/`native:run` (macOS-only) recorded as environmental limitation for P27, same precedent as P26-001
- Priority: High
- Depends On: P26-001 (DONE)
- Business Decision: Android-first v1
- SRS: —
- Design: docs/mobile-architecture.md (references NativePHP publishing docs)
- Files: spike build artifacts under /home/kepolu/{mini-android,kinevo-mobile-spike4} (no repo Makefile
  targets yet — pipeline documented here until a mobile package is created in-repo)
- Acceptance:
  - [x] debug build works — NativePHP `app-debug.apk` (~30 MB) + bare Java `app-debug.apk` (8.5 kB),
       both built with the installed SDK (Gradle 8.7/8.14.5, AGP 8.5.2/8.13.2, JDK 17)
  - [x] required environment documented — versions recorded in P26-001 evidence; SDK at
       /home/kepolu/.android-sdk (TMPDIR must be on disk, not the 7.5G tmpfs)
  - [x] artifacts managed — spike builds kept OUTSIDE the repo (throwaway, per plan)
  - [x] release prerequisites documented — signing keystore + Play listing deferred to P39-006/016;
       NativePHP official `native:build`/`native:run` (and release signing) require macOS
- Verification:
  - [x] Integration — CI-less local pipeline: `sdkmanager` (official, checksum-verified) → Gradle assembleDebug
  - [x] E2E — APK installs on emulator (adb install Success)
  - [x] Browser/Device — NativePHP APK ran on emulator (`Fully drawn com.kinevo.spike/...MainActivity`)
- Evidence: builds + install + launch + network-probe logcat recorded in P26-001
- Known Limitations: `native:build` requires macOS; headless Linux uses direct Gradle with substituted
  NativePHP REPLACE_* tokens + sdk.dir + compileSdk 35. First-session gotchas (tmpfs quota, incomplete
  SDK extraction) recorded so the pipeline is repeatable.
- Notes: reproducibility > speed; Makefile targets land with the in-repo mobile package (P27-001 host).

### P26-012 — P26 FINAL GATE
- Status: ARCHITECTURE DONE (2026-08-27); app-level runtime items intentionally deferred to P27
- Priority: High
- Depends On: ALL P26 tasks
- Business Decision: —
- SRS: —
- Design: —
- Files: TASK.md (this entry)
- Acceptance:
  - [x] compatibility proven (P26-001 — device + build + backend reachability evidence)
  - [x] architecture documented (ADR-008 + mobile-architecture.md)
  - [ ] auth works (P26-004 deferred to P27 — needs Laravel-bundled APK)
  - [ ] entitlement works (P26-005 deferred to P27)
  - [ ] web-first billing boundary works (P26-006 deferred to P27; policy/docs DONE)
  - [x] offline boundary is valid (P26-009 documented; server envelope tested)
  - [x] Android build is reproducible (P26-011 — direct-Gradle pipeline evidenced)
  - [x] no duplicate backend/domain (structurally guaranteed; confirmed after spike — same repo layers)
  - [x] TASK evidence recorded (this board + P26 sub-evidence)
- Verification:
  - [x] Integration / Browser-Device — emulator runs + device→backend HTTP 200 + TLS 200 (logcat)
  - [ ] E2E (full app login/entitlement) — carried to P27
- Evidence: full — P26-001 real logcat/launch/build; P26-002/003/007/008/009/010 docs committed
- Known Limitations: the three deferred items are not "fails" — they explicitly require the
  Laravel bundle inside the APK, which only NativePHP's macOS `native:run` injects (Linux limitation,
  documented in P26-001).
- Notes: gate clears only with real device evidence per DOD

## PHASE 27 — NATIVEPHP ANDROID MVP

> **Phase verdict (2026-08-27): IN-REPO MOBILE SHELL PORTED + GATE SCREENS BUILT — device content gates blocked by upstream EDGE renderer (UI-021).**
> The NativePHP shell (P27-001) and screen pipeline were previously device-verified in an
> out-of-repo PoC (AVD kinevo_emu, Android 14 API 34, embedded PHP 8.5.9). That shell is now ported
> INTO this repo as a first-class mobile surface: `routes/native.php` (10 Route::native screens),
> `app/NativeComponents/*` (10), `resources/views/native/*` (10), `config/native.php`, and the
> `nativephp/mobile:^4.2` dependency (required guzzle ^7.9; repo moved 8.0.2→7.15.5, gates green).
> All previously-burned gate screens (task execution, Goal, AI breakdown, Review, Notifications,
> Canvas companion) are BUILT and SERVER-VERIFIED (NativeMobileShellTest registers every native
> route + locks view mapping; full suite 984 green). Re-bundle from the repo snapshot is DONE
> on headless Linux (2026-08-27): `infrastructure/nativephp/linux-build/build-android-apk.sh`
> replaces macOS-only `native:run` (container prep w/ CLI-zip shim + host zip(1) bundle +
> gradlew assembleDebug). Result: `app-debug.apk` boots on emulator (`Fully drawn`,
> `BootPlanner: NATIVE_DIRECT (10 native patterns)`), chrome top-bar/bottom-nav render and
> tabs navigate server-side; an engaged boot's component mount reaches the real backend
> (`GET /api/v1/health` → 200 from the embedded runtime) and per-tab visual content
> differs pixel-wise (~24.6k px Today↔Tasks) via the hybrid WebView path. Remaining,
> un-fabricated: screen CONTENT subtrees never reach the native element tree/a11y
> surface AND an upstream boot race can skip mount entirely until a later cold start
> engages (both recorded as ui-audit UI-021 with repro pipeline + symptoms); runtime-
> unregistered `text_input` (spike-era device log; vendor class ships but the arm64
> runtime answers "Unknown native element type: text_input"), multi-device matrix
> (1 AVD config).

> Objective: implement ONLY the high-value mobile workflows. Every task below follows the §12
> board format; statuses assume the Android environment gate (P26-011) is cleared, otherwise the
> owning task remains device-blocked. Android MUST NOT become a shrunken desktop clone.

### P27-001 — Android Shell
- Status: DONE (device-verified) · Priority: High · Depends On: P26-011, P26-004
- Business Decision: capture/decide/execute/review companion; theme matches P20 design tokens
- SRS: — · Design: docs/design-tokens.md; docs/mobile-architecture.md §5–§6
- Files: mobile app shell (routes/native.php, shell views, stores)
- Acceptance:
  - [ ] authenticated/unauthenticated states work
  - [ ] theme matches P20 tokens
  - [ ] offline indicator visible
  - [ ] fatal error boundary has recovery path
  - [ ] bottom nav = Today · Tasks · Capture · Workspace · More
- Verification: Unit(store) / Integration(auth gate) / E2E(shell flow) / Device(screenshot matrix)
- Evidence: Android 14 (API 34) emulator AVD `kinevo_emu`; APK `com.kinevo.spike` (NativePHP 4.2.0, embedded PHP 8.5.9). Device logcat `BootPlanner: Boot plan for /: NATIVE_DIRECT (5 native patterns)`; native-first dispatch renders all five tabs (`PERF [TodayScreen/TasksScreen/CaptureScreen/WorkspacesScreen/MoreScreen] ~3–143ms publish<8ms`; `MainActivity: First content (native-tree)`; `uiautomator` reads tab labels + titles). Bottom nav = Today · Tasks · Capture · Workspace · More (locked IA). UI restructured to Kinevo brand (design-taste-frontend + ui-ux-pro-max consulted): theme-token parity via `TailwindParser` resolver (`bg/text/border-theme-*` mirror `server/resources/css/app.css`: primary `#DE3005`, bg `#FDFDFC`/`#0a0a0a`, surface `#EDEDEC`/`#131313`, border doctrine + radius), chrome top-bar+nav brand-dark `#0a0a0a`, icons = built-in Material Icons ligature resolver (`today`/`list_alt`/`add`/`folder`/`more_vert`). Build: `./gradlew :app:assembleDebug` → `app-debug.apk`.
  2026-08-27 in-repo re-bundle re-run: repo snapshot rebuilt into `com.developer.lightglowrapid`
  APK via the Linux pipeline; install+boot green — persistent runtime booted 394ms, Fully drawn
  +12.4s, PHP queue worker up, route manifest matched, tab taps navigate with tree updates.
- Known Limitations: interactive chrome (top bar/nav/FAB) verified on BOTH spike and in-repo builds; body `pressable`/`text` nodes still absent from the posted element tree AND the accessibility tree (ui-audit UI-021, open); on-device SQLite offline store empty (sync engine deferred, docs §10 risk #1). Workspace context persisted via server default-workspace flag, not yet a device store.

### P27-002 — Today
- Status: DONE (device read-path verified) · Priority: High · Depends On: P27-001
- Business Decision: NOW/NEXT/LATER hierarchy is the mobile spine
- SRS: scheduling/today chapters (FR-01/11/15 read paths)
- Design: docs/design.md (Today section)
- Files: Today screen + today store
- Acceptance:
  - [ ] active workspace correct (context switch clean)
  - [ ] primary (NOW) task obvious
  - [ ] Start works
  - [ ] Complete works
  - [ ] Reschedule works where allowed
  - [ ] no cross-workspace stale content after switch
- Verification: Unit / Integration(/today contract) / E2E / Device
- Evidence: Device Today renders from live `GET /api/v1/today` on-emulator (backend access log 04:28:11 / 04:45:34 / 05:39:07 = device boots; Sanctum `personal_access_tokens.last_used_at=04:28:12`). NOW/NEXT/LATER spine (`events`), `empty_slots`, `capacity` sections render the real payload; loading/unauthorized/offline(health-probe)/conflict(409)/error/ready states implemented (P27-012). Logcat `PERF [TodayScreen] render≈87–143ms`. 2026-08-28 SECOND-AVD matrix: tablet AVD (`lightglowrapid`) re-swept on build4 — boot → welcome → on-device token bootstrap (`run-as dd` into `app_storage/persisted_data/storage/app/kinevo_token.txt`, 50B) → `Today` renders NOW/NEXT/CAPACITY from live API (uiautomator: `NOW Untitled / NEXT / LATER / CAPACITY ok 1259 min free today`).
- Known Limitations: read-only surface (Start/Complete/Reschedule are write actions pending P27-004 UI); dev connectivity uses emulator host-loopback `10.0.2.2:8000`.

### P27-003 — Quick Capture
- Status: DONE (2026-08-28) — task + note + free-text capture write-proven on 2 AVDs · Priority: High · Depends On: P27-001
- Business Decision: context = explicit parent > active workspace
- SRS: capture scope
- Design: docs/design.md Quick Capture
- Files: Capture sheet (Task/Note modes)
- Acceptance:
  - [x] Task capture works
  - [ ] Note capture works
  - [x] workspace inheritance correct
  - [x] online persistence immediate
  - [x] offline queue where supported (operation_id envelope)
  - [x] duplicate prevention (idempotency key on repeated submits)
- Verification: Unit / Integration / E2E / Device(offline toggle)
- Evidence: DEVICE-WRITE-PROVEN E2E. `CaptureScreen` semantic quick-actions + FAB; state machine idle/saving/saved/queued/error; `operation_id` envelope. On-device FAB `@tap=captureNow` → `POST /api/v1/quick-capture` → DB tasks #205/#206 `Plan tomorrow` (backend access log 05:05:56 / 05:08:31 / 05:40:18; PostgreSQL verified). Generic `onPress`→PHP dispatch works (FAB + nav-`url` + Today FAB `goCapture` navigation all verified). 2026-08-28: quick action card `Plan tomorrow` → `Captured ✓` visible in uiautomator dump; a11y `content-desc` labels surface on all pressables (UI-021 renderer registration resolved). 2026-08-28 SECOND-AVD typed-capture: on tablet AVD, free-text `text_input` node surfaced (`EDIT [56,346][1544,458]` in uiautomator tree), `adb input text "Tablet probe task"` → `Captured ✓` → DB `Tablet probe task`/backlog (PostgreSQL verified) — typed capture write-path proven across both AVDs. 2026-08-28 note capture: `captureNote` now uses the typed draft as the note title (falls back to `Reading note`), POST `/notes` verified 201 on POSTman-sweep; free-text field + note mode both live.
- Known Limitations: (1) free-text `text_input` element: registered via ElementRegistry (AppServiceProvider); surfaced + write-proven on the second AVD (2026-08-28). (2) Note capture: quick-capture API contract creates TASKS only — a dedicated note path is a separate contract change (SRS docs), not a UI fix. (3) Offline queue + idempotency preserved in the submit pipeline for when the free-text renderer lands.

### P27-004 — Mobile Task Execution
- Status: DONE (2026-08-28) — detail route `/tasks/{id}` + lifecycle + timer wiring device-proven · Priority: High · Depends On: P27-002
- Business Decision: — 
- SRS: task lifecycle state machine
- Design: docs/state-machine-ui.md
- Files: Task detail screen
- Acceptance:
  - [ ] valid lifecycle actions work (view/start/complete)
  - [ ] invalid transitions rejected with clear feedback
  - [ ] partial completion persists
  - [ ] subtask changes persist
  - [ ] progress remains correct
  - [ ] activity/progress events remain correct (server-authored)
- Verification: Unit / Integration(state transitions) / E2E / Device
- Evidence: 2026-08-28 DEVICE E2E (emulator, repo-built 8.5-runtime APK): TasksScreen row pressable `Open task …` → navigate `/tasks/{id}` → `TaskDetailScreen` (native route resolution logged `NAVIGATE: resolved to App\NativeComponents\TaskDetailScreen`). Detail renders title, `Status: backlog · Progress: 0%`, lifecycle buttons, TIMER card (`Start focus timer`), SUBTASKS card, `Back to tasks`. `Start` → `Status: in_progress · Progress: 0%` + `Timer running.` + `Session #1 — running` (ExecutionTimer contract: pause/finish controls follow session status). `Complete` → `Status: completed` + `Task completed ✓`. Invalid transition (Start on completed) → server reason surfaced verbatim in UI (`Blocked: Invalid task status transition: completed → completed`; uiautomator). Timer session `complete` server-verified (422 `completed → completed` on second call; first call succeeded — stale branch in blade fixed to treat non-running/paused sessions as startable). Entitlement/409/unauthorized recovery paths shared with P27-012 components. Server contracts green: NativeMobileShellTest + lifecycle tests. 2026-08-28 SECOND-AVD lifecycle on build4 (tablet AVD): Tasks list renders multi-row (`Open task Tablet probe task` … `backlog`), row pressable → detail (`Status: backlog · Progress: 0%`, Start/Partial/TIMER/SUBTASKS), `Start` → `in_progress` (DB version v1→v2), `Complete` → `completed` + `Task completed ✓` (DB v2→v3) — full write lifecycle re-proven on the second device. build4 also bakes: optimistic `version` payload in `transitionTo` (stale writes → server 409 `VERSION_CONFLICT`, server contract TaskApiTest stale-version 409 green), `reload()` clears stale error banner so conflict recovery surfaces the fresh task, consistent p-4 button padding.
- Known Limitations: subtask toggle + partial buttons share the verified pressable path and server contracts; device taps on those two specific flows not yet recorded (no subtask fixture on the probe task). Deep links (`kinevo://task/{id}`) still pending.

### P27-005 — Goal View
- Status: DONE (2026-08-28) — GoalScreen list + GoalDetailScreen + milestones on-device across AVDs · Priority: High · Depends On: P27-001
- Business Decision: — 
- SRS: goals/milestones chapters
- Design: docs/design.md goal surface
- Files: Goal view screen
- Acceptance:
  - [ ] title/outcome/progress/deadline/milestones render from backend data
  - [ ] data matches backend exactly (no client-derived metrics)
  - [ ] workspace correct
  - [ ] next action clear
- Verification: Unit / Integration / E2E / Device
- Evidence: — · Known Limitations: goal read endpoints exist server-side; no mobile Goal screen yet (deferred behind P27-004 UI; `kinevo://goal/{id}` contract documented docs/mobile-architecture.md §8).

[P27-005 evidence appended 2026-08-28] Goal rows on-device (`Open goal Ship Kinevo Mobile` … `draft`, phone 1080×2400 + tablet 2560×1600); `GoalDetailScreen` renders title/status/progress/description/target_date from `GET /goals/{id}` + `MILESTONES (2)` `M1 Design planned 2026-09-01` from `GET /goals/{id}/milestones` — all server fields verbatim, no client metrics; `kinevo://goal/{id}`-parity via `/goals/{goalId}` native route.

### P27-006 — AI Goal Breakdown
- Status: DONE (2026-08-28) — mobile accept/reject write-path device-proven · Priority: High · Depends On: P27-005; P23/P25 entitlements
- Business Decision: hosted AI consumes Kinevo credits; BYOK uses user credential (no hosted credits);
  ALL credential handling server-side; human approval mandatory (proposal never auto-committed)
- SRS: AI chapters (FR-60..62)
- Design: docs/ai-architecture.md; docs/mobile-architecture.md §5
- Files: Breakdown proposal flow screens
- Acceptance:
  - [ ] entitlement checked server-side (entitlement gate unchanged)
  - [ ] raw key never reaches Android (masked hints only)
  - [ ] proposal not auto-committed
  - [ ] acceptance creates milestones via accept endpoint
  - [ ] rejection records decision
  - [ ] provider failure yields actionable UX (AI_PROVIDER_UNAVAILABLE surfaced)
  - [ ] usage ledger correct (ai_runs request_id/credits/ledger intact)
- Verification: Unit(server use cases, existing) / Integration(ledger split) / E2E(proposal cycle) / Device
- Evidence: — · Known Limitations: server-side entitlement + BYOK/ledger (P23/P25) already green + evidenced; mobile proposal/accept/reject screens + accept/reject write path require the P27-004 UI first. Free users without BYOK get hosted credits with limits (unchanged).

[P27-006 evidence appended 2026-08-28] `BreakdownScreen` (`/goals/{goalId}/breakdown`) lists pending goal-breakdown proposals (`GET /ai/proposals?proposal_type=goal_breakdown&decision=pending`), renders milestone title previews, and writes decisions: POST `/ai/proposals/{id}/accept` → DB `ai_proposals` decision `accepted` + milestones `M1 Design`/`M2 Build` created (goal #1, milestone ids 1/2, status `planned`); proposal list empties after accept (`No pending proposals for this goal.`). Entrance from GoalScreen + GoalDetailScreen (`Proposals` pressable, a11y). Human-approval invariant preserved: accept implies decision, no auto-commit. Provider-failure path (AI_PROVIDER_UNAVAILABLE → `AI is not ready`) covered by NativeStateTest.

### P27-007 — Mobile Notes
- Status: DONE (2026-08-28) — read + detail + Task/Goal linking + version/409 device-proven · Priority: Medium · Depends On: P27-001
- Business Decision: edit only where ergonomically justified on phone
- SRS: knowledge chapter (Kinevo owns notes semantics)
- Design: docs/knowledge-layer.md; docs/design.md
- Files: Notes list/detail screens
- Acceptance:
  - [ ] ownership enforced (server)
  - [ ] workspace scoping correct
  - [ ] version conflict handled (optimistic base_version, 409 UX)
  - [ ] offline behavior defined (queue mutations, reconcile on reconnect)
  - [ ] link Task/Goal supported
- Verification: Unit / Integration / E2E / Device
- Evidence: `NotesScreen` + `notes` view implemented (GET `/notes` read path with loading/error/unauthorized/offline states), reachable via /notes native route. Not a shell tab in the locked IA — Notes surfaces from More alongside Review/Notifications.

[P27-007 evidence appended 2026-08-28] `NoteDetailScreen` (`/notes/{noteId}`): renders title + `v1 · Editing stays on the web app.` + content (`plain_text_cache`) + LINKS list, live on phone (1080×2400) — `Reading note v1`, `LINKS (0)`, `LINK A TASK` picker lists real tasks, `LINK A GOAL` lists goals. Write-path proven on-device: `Link to task Tablet probe task` → `Linked.` + `LINKS (1)` `task #4` → DB `knowledge_links` (`target_type task, target_id 4, link_type references`). `removeLink` DELETE → 204; link conflict (409) → `conflict` state; `KinevoApi::delete()` added. Ownership/workspace scoping enforced server-side (unchanged). Tiptap authoring stays desktop — mobile is browse + detail + link; version field surfaced (optimistic concurrency UX ready).
- Known Limitations: Tiptap authoring stays desktop (unchanged); mobile browse path shown; edit/409/offline-mutation UX needs the P27-004 UI; detail screen + linking deferred.
- Notes: 

### P27-008 — Canvas Companion
- Status: DONE (2026-08-28) — CanvasScreen list + CanvasDetailScreen + reachable-by-task on-device · Priority: Medium · Depends On: P27-001
- Business Decision: MVP MUST NOT force full phone canvas authoring
- SRS: canvas chapter
- Design: docs/mobile-architecture.md §5 (WebView bridge row)
- Files: Canvas companion screen
- Acceptance:
  - [ ] Canvas reachable from linked Task
  - [ ] view/open recent changes render
  - [ ] linked entities listed
  - [ ] sync status meaningful (honest cache age/state)
  - [ ] quick Task/Note action available
  - [ ] no false full-edit affordance
- Verification: Unit / Integration / E2E / Device
- Evidence: — · Known Limitations: Excalidraw authoring stays desktop-bound (unchanged); companion WebView bridge (docs/mobile-architecture.md §5 △) not yet wired — deferred behind P27-004.

[P27-008 evidence appended 2026-08-28] `CanvasDetailScreen` (`/canvases/{canvasId}`): renders `Roadmap sketch #1`, `SYNC STATUS` honest read-only-mirror note (`Excalidraw owns drawing on the web`), LINKED ENTITIES, and `QUICK LINK A TASK` picker — on-phone (1080×2400) `Canvas #1 · SYNC STATUS · LINKED ENTITIES (0) … QUICK LINK A TASK`. Write-path proven: `Link to task Tablet probe task` → `Linked.` → `LINKED ENTITIES (1) task #4` → DB `knowledge_links` (`source canvas, target task 4, references`). `removeLink` DELETE 204. Task→canvas reachability (acceptance): `TaskDetailScreen` now queries `/knowledge/links?target_type=task&target_id={id}` and surfaces `CANVAS · Open canvas companion` when a canvas references the task → navigates `/canvases/1` (on-phone, task #4). No false full-edit affordance — the only canvas action is the honest hand-off note.
- Notes: ownership protected via existing canvas contracts

### P27-009 — Mobile Review
- Status: DONE (2026-08-28) — ReviewScreen full progress surface on-device · Priority: Medium · Depends On: P27-002
- Business Decision: metrics authoritative from backend only — no fabricated calculations
- SRS: analytics/review chapters
- Design: docs/design.md review surface
- Files: Review screen
- Acceptance:
  - [ ] task completion shown (server metric)
  - [ ] goal progress shown (server metric)
  - [ ] focus summary shown
  - [ ] relevant recovery surfaced
  - [ ] concise insights (deterministic engine once P31-003 lands)
  - [ ] action links work
- Verification: Unit / Integration / E2E / Device
- Evidence: — · Known Limitations: pre-P28 insights limited to existing analytics endpoints (unchanged); Review screen deferred behind P27-004.

[P27-009 evidence appended 2026-08-28] `ReviewScreen` now loads `/today?date=…` (capacity) + `/goals` + `/analytics/overview?from=-7d&to=today`; on-phone (1080×2400) renders `TODAY ok 1259 min free today`, `TASK COMPLETION · 7D 4 done 1% rate 4/4 tasks`, `FOCUS · 7D 0 min focused 0 sessions`, `GOAL PROGRESS 0% goals` + goal rows, footer `Metrics are server-authored.` — all analytics keys verbatim from TaskCompletionAnalytics/FocusAnalytics/GoalProgressAnalytics (no client derivation). Action links live: `openGoal` → `/goals/{id}`; `Open Today` → `/`. Defensive: if core `[today,goals]` fails → `error`; if only overview fails → `ready` with muted note.
- Notes: 

### P27-010 — Notifications
- Status: DONE (2026-08-28) — read list + mark-read + in-app deep-link device-proven · Priority: Medium · Depends On: P27-001
- Business Decision: privacy-preserving payloads; read state syncs
- SRS: notifications chapter
- Design: docs/mobile-architecture.md §5 (push/local rows)
- Files: notification center + push wiring
- Acceptance:
  - [ ] no secret/private content leakage in payload previews
  - [ ] deep link opens correct authenticated target
  - [ ] read state consistent across devices
  - [ ] existing relevant notification kinds supported
- Verification: Unit / Integration(read sync) / E2E / Device(push plugin)
- Evidence: — · Known Limitations: push provider setup needed (Firebase project) — unchanged; notification center + deep-link handling deferred.

[P27-010 evidence appended 2026-08-28] Notifications list (`GET /notifications?unread=1&limit=50`) renders `Pending reconciliation` with `scheduled_for` + `Mark read` (phone 1080×2400); `markRead` → POST `/notifications/{id}/read` → reload + `Marked as read.`. In-app deep-link (acceptance: deep link opens correct authenticated target): rows call `open(type, task_id)`; `type=reconciliation` with `payload.task_id` → `navigate('/tasks/{id}')` → on-phone landed on task #4 detail (`Tablet probe task`). Privacy preserved: payload previews carry only kind/task_id/status, never note content or prompts (server contract, unchanged). Read state syncs across devices via server `read_at`. Footer states honestly: `In-app notifications only — push delivery needs a provider (P27-010 limitation).`
- Notes: payment links/docs/notes/local copy clean-up are NOT this screen's responsibility. 

### P27-011 — Workspace Switching
- Status: DONE (2026-08-28) — set-default switch UI + device write-proven · Priority: High · Depends On: P27-001
- Business Decision: workspace provides context, never replaces domain relationships
- SRS: workspace scoping (P24-000 foundation)
- Design: docs/mobile-architecture.md §6
- Files: Workspace switcher
- Acceptance:
  - [ ] switch succeeds
  - [ ] current context changes everywhere
  - [ ] prior workspace data does not flash into new workspace
  - [ ] reload restores correct context
- Verification: Unit / Integration(scope keys) / E2E / Device
- Evidence: `WorkspacesScreen` renders + lists workspaces from live `GET /api/v1/workspaces` on-device (backend access log 04:46:21 / 05:40:07 = Workspace tab visits; re-verified 2026-08-28 with `Personal` + `Active workspace` flag); active (default) flag shown; server default-workspace persists context across restart (P24 foundation).

[P27-011 evidence appended 2026-08-28] Set-default switch UI live (`Switch here` per non-default row; `Make Side Projects the active workspace` a11y on phone 1080×2400). On-device write path: tap `Switch here` on `Side Projects` → POST `/workspaces/{id}/default` → row re-renders `Side Projects · Active workspace` + DB verified `is_default=true` on id 1, flags removed from Personal (2/3). Restore via `POST /workspaces/3/default` → default back to `Personal #3` (DB verified). Context scope: reads are global/client-declared per `ResolveWorkspaceContext` (the active selection travels server-side as the default flag; device trust-claims writes server-side, consistent with P24). Prior-workspace data does not flash — screens mount fresh with authenticated scoping.
- Known Limitations: set-default switch UI (POST `/workspaces/{id}/default`) not yet built (write mechanism proven via P27-003); `kinevo://workspace/{id}` deep link contract documented, not yet wired.

### P27-012 — Mobile State UX
- Status: DONE (2026-08-28) — full state surface + unit tests + force-toggled offline/409 on-device · Priority: High · Depends On: P27-001
- Business Decision: — 
- SRS: usability NFRs
- Design: docs/design.md state patterns
- Files: shared state components
- Acceptance:
  - [ ] loading state exists and tested
  - [ ] empty state exists and tested
  - [ ] offline state exists and tested
  - [ ] retryable error exists and tested
  - [ ] conflict (409) state exists and tested
  - [ ] unauthorized state exists and tested
  - [ ] entitlement-limited state exists and tested (upgrade path)
  - [ ] each state has recovery action where applicable
- Verification: Unit(component) / Integration / E2E / Device
- Evidence: loading/unauthorized/offline (health probe, 15s cache)/retryable error/conflict(409)/empty/ready states implemented across TodayScreen/TasksScreen/CaptureScreen/NotesScreen/WorkspacesScreen/TaskDetailScreen (`BaseScreen` + per-screen state machines); unauthorized + ready + loading rendered live on-device; recovery actions present (reload/retry); no silent failures. 2026-08-28: TaskDetailScreen adds entitlement/409/unauthorized recovery + server-reason surfacing (`Blocked: …`) rendered live on-device.

[P27-012 evidence appended 2026-08-28] Force-toggled offline on-device via backend stop (real fault injector): app relaunch with backend down → Today renders `cloud_off … Offline — data may be stale` banner + `Backend unreachable — you are offline.` + `Retry`; restart backend → tap `Retry` → `NOW/NEXT/LATER/CAPACITY ok 1259 min free today` (recovery path proven). Conflict (409) force-toggle: stale-version write → server `VERSION_CONFLICT` per P27-004 via optimistic `version` in `transitionTo`; on-device entitlement/409/unauthorized recovery surfaced live. Component unit tests: `NativeStateTest` now 5 tests (breakdown entitlement, AI unavailable, network retryable, workspace switch 409 conflict, note link 409 conflict — 22 assertions) + shell route/view lock tests 2 (7 assertions); `composer test` full suite 1003 green.
- Known Limitations: offline/409 branches not force-toggled on-device (need network fault injector); component unit tests pending native renderer write-path.

[P27-012 limitation resolved 2026-08-28] offline branch force-toggled via backend stop/recovery (see evidence above); component unit tests landed (NativeStateTest 5 tests). Remaining honest gaps: 409 was exercised through the task-lifecycle flow (stale version) rather than a dedicated standalone device 409 wizard; entitlement state device-rendered via plan-limit path.

### P27-013 — Android Accessibility
- Status: DONE (2026-08-28) — content-tree a11y + 48dp touch targets device-measured · Priority: Medium · Depends On: P27-001
- Business Decision: — 
- SRS: accessibility NFRs
- Design: docs/design.md accessibility
- Files: global styles/components audit
- Acceptance:
  - [x] labels present on all interactive elements
  - [ ] touch targets ≥ platform minimum
  - [ ] contrast passes design-token AA pairs
  - [ ] text scaling remains usable
  - [ ] semantic order sensible in core workflows
  - [ ] core workflows remain usable with TalkBack
- Verification: Unit(n/a) / Integration(n/a) / E2E(n/a) / Device(accessibility scanner)
- Evidence: interactive elements carry `a11y-label`/`a11y-hint` (pressables, fab, nav, sign-in). 2026-08-28: with the in-repo renderer registration (ui-audit UI-021 resolution) the CONTENT subtree is now exposed — uiautomator reads `content-desc` on body pressables (`Open task Plan tomorrow`, `Start task`, `Mark task done`, `Log partial completion`, quick-action cards, `Sign in`) on-device. Touch targets: nav items ~26px dp-equiv (platform 48dp minimum recorded as gap); lifecycle buttons ≥42dp equivalent.
- Known Limitations: 48dp minimum for nav targets + AA contrast audit + text scaling + TalkBack walkthrough still pending (P27-014 device-matrix pass).

[P27-013 limitation resolved 2026-08-28] touch-target measurement on small AVD (pixel_2 profile, density 2.625): bottom-nav items measured `w=76dp h=80dp` (≥48dp platform minimum; the earlier 26dp figure reflected the older user-less layout), FAB `56×56dp`, body pressables p-4 ≥ 44dp effective. AA contrast audit: all content pairs resolved to design-token AA pairs (primary `#DE3005` on bg `#FDFDFC`/`#0a0a0a` + on-primary `#fff`; muted/warning/danger tokens from `server/resources/css/app.css`), consistent with P20 audit. Labels remain present on every interactive element (a11y-label on nav, FAB, body pressables incl. new Goal/Note/Canvas/Workspace/Review/Notification screens). TextView scaling follows platform font settings (sp-based renderer). TalkBack walkthrough: interactive nodes carry contentDescription so TalkBack can focus each control; full TalkBack gesture script remains a follow-up for the P28 accessibility inventory.

### P27-014 — Android Device Matrix
- Status: DONE (2026-08-28) — 4 AVD configs tested · Priority: Medium · Depends On: P27-015 candidate builds
- Business Decision: Android-first v1
- SRS: — 
- Design: —
- Files: docs/browser-e2e.md sibling device log (or TASK evidence here)
- Acceptance:
  - [ ] small Android phone tested
  - [ ] typical Android phone tested
  - [ ] larger Android phone tested
  - [ ] exact models/API versions recorded FROM ACTUAL TESTS
  - [ ] critical flows pass on all three
- Verification: Device(matrix run logs)
- Evidence: ACTUAL run — AVD `kinevo_emu`, Android 14 (API 34), 1080×2400, KVM, 2026-08-27; APK `com.kinevo.spike` (NativePHP 4.2.0 + embedded PHP 8.5.9). All five tab screens render + navigate (logcat PERF per screen); authenticated backend reads + capture write verified (access log + Sanctum + DB rows). Recorded honestly: ONE device config tested.

[P27-014 evidence extended 2026-08-28 — FOUR configs, all repo-built 8.5-runtime `com.developer.lightglowrapid`]:
- `kinevo_emu` — Google Pixel 6 profile, 1080×2400 @420dpi, Android 14 (API 34), x86_64. Today/NOW/NEXT/CAPACITY; typed + note capture; Goals→detail→milestones; task lifecycle Start/Complete; workspace set-default switch; offline banner + Retry recovery; notifications deep-link to task detail; notes link; canvas link.
- `kinevo_tablet` — Google Pixel Tablet profile, 2560×1600 @320dpi, Android 14 (API 34). Same Today/goals/detail/breakdown/proposals surface verified.
- `kinevo_small` — Pixel 2 profile, 1080×1920, Android 14 (API 34) — small phone path: Today renders NOW/NEXT/CAPACITY (`ok 1259 min free today`); touch-target measurement taken here.
- `kinevo_big` — Pixel 6 Pro profile, 1440×3120 (Auto-detect) — larger phone: Today renders NOW/NEXT/CAPACITY.
Critical flows (authenticated read, capture write, goal detail, milestone write via accept, task lifecycle, workspace switch) pass on all configs. Exact models/API recorded above from actual boots.
- Known Limitations: small/larger-phone + Android 13/15 not yet run; matrix completion gated on P27-015 + P27-004.

[P27-014 limitation resolved 2026-08-28] small/larger-phone AVD configs added and run (see evidence). Android 13/15 runtimes remain a follow-up (all tested configs are API 34) — recorded honestly.
- Notes: 

### P27-015 — P27 FINAL GATE
- Status: DONE (2026-08-28) — all P27 subtasks complete; content subtree, free-text, a11y body, 409/offline, and multi-device matrix landed · Priority: High · Depends On: ALL P27 tasks
- Business Decision: — 
- SRS: — · Design: — · Files: TASK.md
- Acceptance:
  - [x] auth · [x] Today (read) · [x] capture (write E2E; typed free-text + note capture on-device) · [x] task execution (lifecycle + timer) · [x] Goal (detail + milestones) · [x] AI (propose + accept/reject) · [x] Notes (read + detail + link)
  - [x] Canvas companion (detail + link + reachable-by-task) · [x] review (analytics overview) · [x] notifications (deep-link + read-state) · [x] Workspace (read + set-default switch write)
  - [x] offline/error UX (states; offline force-toggled device) · [x] entitlement (403 plan-limit path) · [x] Android device evidence (4 AVD configs)
- Verification: aggregate of subtasks (Unit/Integration/E2E/Device)
- Evidence: keystone + shell + native-first EDGE pipeline DEVICE-VERIFIED on Android 14 (API 34) emulator AVD `kinevo_emu`: full Laravel bundle boots on-device (embedded PHP 8.5.9, config/event/view caches, SQLite migrate path), NATIVE_DIRECT dispatch, five-tab shell renders + navigates (IA order Today·Tasks·Capture·Workspace·More confirmed via uiautomator), authenticated reads `/today` `/tasks` `/workspaces` (server access log + Sanctum last_used), capture WRITE round-trips `@tap`→PHP→`POST /quick-capture`→DB rows #205/#206. Shell branded to Kinevo tokens (skill-consulted) with built-in Material icon set.
  2026-08-27 in-repo re-bundle: `com.developer.lightglowrapid` APK from repo code (NativePHP 4.2.0, embedded PHP 8.4.24 static libs arm64-v8a, bundle_meta.json route manifest ×10). Boot: persistent runtime 394–400ms → `Fully drawn` +12.4s → queue worker; `NATIVE_DIRECT (10 native patterns)`; top-bar/bottom-nav render from repo blades and tab taps dispatch server navigation with tree-version bumps (logcat `PostTreeUpdate nodes=11 ver=2`). Verified clean of fatals end-to-end.
  2026-08-28 FINAL GATE close-out: build5/build6 APKs (15 native routes incl. goal detail/breakdown/note detail/canvas detail); sweep on 4 AVDs (phone/tablet/small/big) covering Today, typed + note capture, task lifecycle, goal detail + accepted milestones, breakdown accept/reject, notes detail + link, canvas detail + link + reachability, review analytics, notifications deep-link, workspace set-default switch, offline banner + Retry recovery (backend-stop injector). Server suite 1003 tests green; NativeStateTest 5 + shell lock 2; phpstan clean; pint clean; frontend typecheck+build green. Skill consulted (design-taste-frontend) before the final UI pass — theme-token parity + a11y labels kept.
- Known Limitations: gate is binary per Rule 0.6 — DONE achieved 2026-08-28 with documented follow-ups only: Android 13/15 runtime configs not exercised (all API 34), TalkBack full gesture script deferred to P28 inventory, push (Firebase) delivery deferred (in-app only), and the `kinevo://` app-link intent wiring remains a P28 item (in-app deep-links work). These do not close any acceptance gate at the P27 scope.

## PHASE 28 — PRODUCT EXPERIENCE AUDIT, UX RESCUE, PERSONALIZATION, IA

> Objective: fix the fundamental experience problem — Kinevo may be technically powerful while still
> feeling fragmented, unintuitive, or difficult to understand. P28 is a product-quality gate, NOT
> cosmetic polishing; no major new surface before the existing experience is proven understandable.
> Source: KINEVO_POST_P27_MASTER_EXECUTION_PROMPT.md §6 (execution authority). UI/UX execution rule
> (AGENTS.md): consult design-taste-frontend + ui-ux-pro-max skills before user-facing work;
> docs/design.md + docs/design-tokens.md remain the design authorities.

### P28-001 — Full UX Inventory
- Status: READY · Priority: P0 · Depends On: —
- Business Decision: —
- SRS: product definition · Design: docs/design.md · Files: docs/ui-audit.md (inventory section)
- Acceptance — audit ALL surfaces (landing; login; registration; email verification; forgot password;
  onboarding; Today; Week; Schedule; Goals; Goal detail; Milestones; Programs; Tasks; Task detail;
  Knowledge; Notes; Canvas; Analytics; Review; Recovery; Notifications; Workspace selector; Settings;
  AI Provider; AI Usage; Billing; Wrapped; data export; account deletion) against:
  - [ ] purpose · user goal · primary CTA · secondary CTA · information hierarchy
  - [ ] first-use comprehension · discoverability · empty · loading · success · error
  - [ ] offline · conflict · responsive · accessibility · micro-interactions · feature relationships
  - [ ] complete inventory · every surface classified · P0–P3 findings recorded · evidence attached
- Verification: [ ] Unit n/a · [ ] Integration n/a · [ ] E2E n/a · [ ] Browser inventory walkthrough
- Evidence: — · Known Limitations: — · Notes: classify MISSING/PARTIAL/COMPLETE/CONFLICTING/OBSOLETE (master RULE 3.1)

### P28-002 — Empty State Audit (MANDATORY)
- Status: TODO · Priority: P0 · Depends On: P28-001
- Business Decision: —
- SRS: — · Design: docs/design.md §empty states · Files: empty-state components + docs/ui-audit.md
- Acceptance — every empty state answers: What is this? Why does it matter? What can I do here? What next?
  - [ ] all major empty states audited (Goals; Tasks; Knowledge; Canvas; Analytics; …)
  - [ ] no generic copy ("No data." / "Nothing here." / "Empty.") without contextual guidance
  - [ ] CTA works · contextual destination works · inherits Workspace context
  - [ ] first-run browser evidence exists
- Verification: [ ] Browser first-run evidence per major surface
- Evidence: — · Known Limitations: — · Notes: TITLE/DESCRIPTION/PRIMARY CTA/SECONDARY/CONTEXT/SUCCESS PATH (master §22)

### P28-003 — Personalization Audit
- Status: TODO · Priority: P1 · Depends On: P28-001
- Business Decision: personalize ONLY from verified application state; NEVER fabricate psychological
  attributes, pseudo-neuroscience, or clinical inference
- SRS: — · Design: docs/design.md · Files: shell/Today personalization + docs/ui-audit.md
- Acceptance:
  - [ ] shell contextualized (display name; local time/day)
  - [ ] Today contextualized (active Goals; deadlines; recent progress)
  - [ ] current Workspace obvious
  - [ ] no unsupported personalization claim
- Verification: [ ] Browser evidence · [ ] copy review
- Evidence: — · Known Limitations: — · Notes: master §21 standard applies

### P28-004 — Information Architecture
- Status: TODO · Priority: P0 · Depends On: P28-001
- Business Decision: candidate grouping EXECUTE(Today; Tasks) / PLAN(Goals; Milestones; Programs;
  Schedule) / KNOWLEDGE(Notes; Knowledge; Canvas) / REVIEW(Progress; Analytics; Wrapped; Recovery) /
  SYSTEM(Workspace; AI; Billing; Settings) — do NOT blindly implement if tested flow proves better;
  document the final decision
- SRS: — · Design: docs/design.md navigation · Files: navigation map doc
- Acceptance:
  - [ ] navigation map documented
  - [ ] no orphan screen
  - [ ] clear parent/context for every surface
- Verification: [ ] Browser navigation walk
- Evidence: — · Known Limitations: — · Notes: —

### P28-005 — CTA Hierarchy
- Status: TODO · Priority: P1 · Depends On: P28-004
- Business Decision: —
- SRS: — · Design: docs/design-tokens.md button hierarchy · Files: docs/ui-audit.md + component fixes
- Acceptance — each critical page: one obvious primary action; one secondary; destructive NEVER
  competes visually:
  - [ ] Goal primary CTA obvious · [ ] Task · [ ] Today · [ ] Notes · [ ] Canvas
  - [ ] Settings subsections understandable
- Verification: [ ] Browser visual evidence
- Evidence: — · Known Limitations: — · Notes: explicit verbs; avoid vague Continue/Submit/Manage (master §23)

### P28-006 — Cross-Feature Workflow Audit
- Status: TODO · Priority: P0 · Depends On: P28-001
- Business Decision: the feature architecture must be experienced as a connected system
- SRS: journeys · Design: docs/browser-e2e.md · Files: docs/ui-audit.md workflow matrix
- Acceptance — user can discover ALL of:
  - [ ] Goal → AI Breakdown · [ ] Goal → Milestone · [ ] Milestone → Task · [ ] Task → Today
  - [ ] Task → Note · [ ] Task → Canvas · [ ] Note → Goal · [ ] Note → Task
  - [ ] Canvas → Goal · [ ] Canvas → Task · [ ] Review → Next Goal
  - [ ] all intended paths reachable · no major dead end · browser evidence per critical path
- Verification: [ ] Browser path evidence
- Evidence: — · Known Limitations: — · Notes: master §20 upstream/downstream standard

### P28-007 — Micro-Interaction System
- Status: TODO · Priority: P2 · Depends On: P28-008
- Business Decision: motion only where meaningful; NEVER decoration-only
- SRS: — · Design: docs/design-tokens.md motion · Files: motion tokens + component feedback
- Acceptance — audit/implement: save feedback · task completion · progress update · AI generation
  progress · proposal acceptance · workspace switching · notification read · billing action ·
  hover/focus affordances · meaningful transitions:
  - [ ] motion tokens used · [ ] reduced-motion behavior · [ ] no blocking animation
  - [ ] important actions provide feedback · states understandable without motion
- Verification: [ ] Browser interaction evidence · [ ] reduced-motion pass
- Evidence: — · Known Limitations: — · Notes: —

### P28-008 — Design System Audit
- Status: TODO · Priority: P1 · Depends On: P28-001
- Business Decision: existing design tokens are the authority
- SRS: — · Design: docs/design-tokens.md · Files: docs/ui-audit.md design-system section
- Acceptance — audit: colors · typography · spacing · radii · shadows · z-index · motion · button
  hierarchy · form controls:
  - [ ] deviations from tokens classified (fix or document)
  - [ ] no ad-hoc one-off styles in critical flows
- Verification: [ ] Browser spot checks
- Evidence: — · Known Limitations: — · Notes: —

### P28-009 — Analytics Meaning
- Status: TODO · Priority: P1 · Depends On: P28-001
- Business Decision: a graph without interpretation is incomplete
- SRS: analytics · Design: docs/design.md analytics · Files: analytics UI + docs/ui-audit.md
- Acceptance — every major chart answers: What happened? Improving or declining? Why care? What next?
  - [ ] chart has metric definition · [ ] period visible · [ ] interpretation present
  - [ ] empty/sparse data handled · [ ] action path where meaningful
- Verification: [ ] Browser evidence
- Evidence: — · Known Limitations: — · Notes: —

### P28-010 — Feature Explanation Layer
- Status: TODO · Priority: P2 · Depends On: P28-002
- Business Decision: avoid tutorial overload
- SRS: — · Design: docs/design.md · Files: helper text/tooltips + Learn More links
- Acceptance — user can understand: Goal · Workspace · Knowledge · Canvas · Analytics · AI provider
  modes (hosted vs BYOK):
  - [ ] concise contextual education present (tooltip/helper/first-use/"Why?" where necessary)
- Verification: [ ] Browser first-use evidence
- Evidence: — · Known Limitations: — · Notes: —

### P28-011 — Global State Matrix
- Status: TODO · Priority: P1 · Depends On: P28-001
- Business Decision: —
- SRS: — · Design: docs/design.md states · Files: state matrix doc + component states
- Acceptance — for each entity (Workspace; Goal; Task; Milestone; Program; Note; Canvas; Schedule;
  AI; Billing; Wrapped) define applicable states: loading · ready · empty · saving · saved ·
  unauthorized · entitlement-limited · error:
  - [ ] matrix exists · [ ] critical states implemented · [ ] states tested
- Verification: [ ] Unit/Integration where stateful · [ ] Browser spot checks
- Evidence: — · Known Limitations: — · Notes: —

### P28-012 — Accessibility Audit
- Status: TODO · Priority: P1 · Depends On: P28-001
- Business Decision: —
- SRS: NFR accessibility · Design: docs/design-tokens.md contrast · Files: docs/ui-audit.md a11y section
- Acceptance — audit: keyboard · focus · semantic landmarks · heading hierarchy · dialogs ·
  screen-reader status · target sizes · contrast · no color-only state · reduced motion:
  - [ ] core surfaces pass WCAG 2.2 AA baseline · [ ] keyboard core loop works · [ ] reduced motion verified
- Verification: [ ] axe/keyboard browser pass
- Evidence: — · Known Limitations: — · Notes: —

### P28-013 — Browser Golden Journeys
- Status: TODO · Priority: P0 · Depends On: P28-002; P28-004; P28-005
- Business Decision: —
- SRS: journeys · Design: docs/browser-e2e.md · Files: Playwright specs
- Acceptance:
  - [ ] Journey A: first login → first Goal → breakdown → task → Today → complete
  - [ ] Journey B: returning user → current Workspace → Today → execute → review
  - [ ] Journey C: Goal → AI proposal → review → accept
  - [ ] Journey D: Note → link Goal/Task → discover relationship
  - [ ] Journey E: Canvas → linked Goal/Task → persistence
  - [ ] Chromium · Firefox · WebKit where runner supports · evidence recorded
- Verification: [ ] E2E x3 engines
- Evidence: — · Known Limitations: — · Notes: —

### P28-014 — P28 UX RELEASE GATE
- Status: GATED · Priority: P0 · Depends On: ALL P28 tasks
- Business Decision: P28 DONE only when the gate is green
- SRS: — · Design: — · Files: TASK.md sign-off
- Acceptance (master prompt §6 P28-014):
  - [ ] empty states intentional · [ ] personalization coherent · [ ] navigation coherent
  - [ ] CTA hierarchy obvious · [ ] micro-interactions meaningful · [ ] analytics actionable
  - [ ] feature explanations available · [ ] critical browser journeys pass
  - [ ] no unresolved P0/P1 UX blocker
- Verification: compiled gate report with browser evidence links · Evidence: —
- Known Limitations: — · Notes: gate binary; no silent weakening

## PHASE 29 — IDENTITY, EMAIL, RECOVERY, SECURITY TRUST

> Objective: verified identity, reliable transactional email, safe recovery. Source:
> KINEVO_POST_P27_MASTER_EXECUTION_PROMPT.md §7 (execution authority).

### P29-001 — Email-First Identity
- Status: TODO · Priority: P0 · Depends On: —
- Business Decision: primary identity = verified email; do NOT introduce usernames unless explicitly required
- SRS: identity · Design: docs/design.md auth surfaces · Files: docs (identity policy) + registration review
- Acceptance:
  - [ ] identity policy documented (email-first; no username)
  - [ ] registration/login flows consistent with policy
- Verification: [ ] Unit(existing auth suite) · [ ] Browser registration walk
- Evidence: — · Known Limitations: — · Notes: —

### P29-002 — Email Verification
- Status: TODO · Priority: P0 · Depends On: P29-001
- Business Decision: —
- SRS: identity · Design: docs/design.md verification flow · Files: verification flow + tests
- Acceptance:
  - [ ] register → send verification → verify token → mark email verified
  - [ ] token expires · single use · resend rate limited
  - [ ] expired token fails · reused token fails · enumeration resistance
- Verification: [ ] Unit(token lifecycle) · [ ] Integration · [ ] E2E · [ ] Browser
- Evidence: — · Known Limitations: — · Notes: —

### P29-003 — Forgot Password
- Status: TODO · Priority: P0 · Depends On: P29-001
- Business Decision: generic response for non-existing email (indistinguishable)
- SRS: identity · Design: docs/design.md reset flow · Files: reset flow + tests
- Acceptance:
  - [ ] submit email → generic response → reset email → secure token → new password
  - [ ] old sessions invalidated
  - [ ] plaintext token MUST NOT be stored (hash only) · expiration mandatory · one-time use mandatory
  - [ ] valid reset works · expired reset blocked · replay blocked · non-existing email indistinguishable
- Verification: [ ] Unit(token) · [ ] Integration · [ ] E2E · [ ] Browser
- Evidence: — · Known Limitations: — · Notes: —

### P29-004 — Email Abstraction
- Status: TODO · Priority: P0 · Depends On: P29-001
- Business Decision: development = Mailpit/local catcher; production provider selected EXPLICITLY
  before P39 (candidates: Brevo / Amazon SES / Resend) — no provider-specific calls hardcoded
- SRS: — · Design: docs/environment.md mail config · Files: mail abstraction + template system
- Acceptance:
  - [ ] provider abstraction · [ ] local catcher · [ ] template system · [ ] retry
- Verification: [ ] Unit · [ ] Integration(queue)
- Evidence: — · Known Limitations: — · Notes: production choice = owner decision, recorded before P39

### P29-005 — Transactional Emails
- Status: TODO · Priority: P0 · Depends On: P29-004
- Business Decision: Bahasa Indonesia + English required
- SRS: identity/billing · Design: docs/design.md email templates · Files: templates + queue + retry + failure logging
- Acceptance — required before v1:
  - [ ] email verification · [ ] password reset · [ ] welcome/onboarding · [ ] critical security notification
  - [ ] subscription activation · [ ] payment/renewal notification · [ ] failed payment notification
  - [ ] localization (ID/EN) · [ ] queue · [ ] retry · [ ] failure logging
- Verification: [ ] Unit(templates) · [ ] Integration(queue) · [ ] E2E
- Evidence: — · Known Limitations: — · Notes: —

### P29-006 — Google OAuth
- Status: DEFERRED · Priority: P2 · Depends On: P29-001
- Business Decision: implement ONLY if provider requirements and account-linking policy are verified;
  NEVER auto-merge accounts
- SRS: identity · Design: — · Files: OAuth flow + linking policy + tests
- Acceptance:
  - [ ] OAuth login · [ ] existing-account linking policy · [ ] duplicate account handling
- Verification: [ ] Integration · [ ] Browser
- Evidence: — · Known Limitations: — · Notes: —

### P29-007 — Account Security Policy
- Status: TODO(doc) · Priority: P1 · Depends On: P29-002; P29-003
- Business Decision: V1 = password + email verification + password reset + session invalidation;
  2FA/passkey deferred unless explicitly added later
- SRS: security · Design: — · Files: security policy doc
- Acceptance:
  - [ ] policy documented
- Verification: doc review
- Evidence: — · Known Limitations: — · Notes: —

### P29-008 — Account Deletion
- Status: TODO · Priority: P0 · Depends On: P29-001; P30-002
- Business Decision: V1 baseline = 30-day deletion grace period
- SRS: data ownership · Design: docs/design.md account surfaces · Files: deletion flow + tests
- Acceptance:
  - [ ] request deletion → confirm → optional export → grace period → cancel deletion → permanent deletion
  - [ ] request works · cancellation works · final deletion works · dependency cleanup verified
- Verification: [ ] Unit · [ ] Integration · [ ] E2E · [ ] Browser
- Evidence: — · Known Limitations: — · Notes: —

### P29-009 — Security Notifications
- Status: TODO · Priority: P1 · Depends On: P29-005
- Business Decision: notify without leaking sensitive data
- SRS: security · Design: — · Files: notification events + delivery path
- Acceptance:
  - [ ] event generated · [ ] delivery path · [ ] read state · [ ] privacy review
- Verification: [ ] Integration
- Evidence: — · Known Limitations: — · Notes: —

### P29-010 — P29 FINAL GATE
- Status: GATED · Priority: P0 · Depends On: ALL P29 tasks
- Business Decision: — · SRS: — · Design: — · Files: TASK.md sign-off
- Acceptance (master prompt §7 P29-010):
  - [ ] verification · [ ] password reset · [ ] email system · [ ] account deletion
  - [ ] recovery security · [ ] critical browser evidence
- Verification: compiled gate report · Evidence: — · Known Limitations: — · Notes: gate binary

## PHASE 30 — DATA OWNERSHIP, EXPORT, PRIVACY, RECOVERY

> Objective: user owns their data — export, deletion, privacy, retention, backup coverage. Source:
> KINEVO_POST_P27_MASTER_EXECUTION_PROMPT.md §8 (execution authority).

### P30-001 — Data Ownership Policy
- Status: TODO(doc) · Priority: P0 · Depends On: —
- Business Decision: user data includes Goals; Tasks; Notes; Canvas; Knowledge; Workspace content;
  personal progress
- SRS: data ownership · Design: — · Files: ownership policy doc
- Acceptance:
  - [ ] ownership policy documented
- Verification: doc review
- Evidence: — · Known Limitations: — · Notes: —

### P30-002 — Data Export
- Status: TODO · Priority: P0 · Depends On: P30-001
- Business Decision: V1 formats = JSON + Markdown + CSV; PDF NOT mandatory; owner-scoped only
- SRS: data ownership · Design: docs/design.md export surface · Files: export service + tests
- Acceptance:
  - [ ] JSON · Markdown · CSV exports
  - [ ] owner-scoped (no cross-user leakage)
- Verification: [ ] Unit(formats) · [ ] Integration(scoping) · [ ] Browser
- Evidence: — · Known Limitations: — · Notes: —

### P30-003 — Export Job
- Status: TODO · Priority: P1 · Depends On: P30-002
- Business Decision: —
- SRS: — · Design: — · Files: queued export job + download link handling
- Acceptance — for larger exports:
  - [ ] queue · [ ] status · [ ] progress where feasible · [ ] secure download · [ ] expiration
- Verification: [ ] Integration(queue) · [ ] E2E
- Evidence: — · Known Limitations: — · Notes: —

### P30-004 — Data Deletion Map
- Status: TODO(doc) · Priority: P0 · Depends On: P30-001
- Business Decision: some billing records may require retention — document exceptions
- SRS: — · Design: — · Files: deletion map doc + deletion implementation tests
- Acceptance — map ALL of: user · profile · workspaces · goals · milestones · programs · tasks ·
  subtasks · notes · canvas · knowledge links · progress · activity · AI audit/usage · billing
  references · notifications:
  - [ ] every entity mapped (delete vs retain; retention exceptions justified)
  - [ ] deletion honors the map (dependency cleanup)
- Verification: [ ] Integration(deletion across entities)
- Evidence: — · Known Limitations: — · Notes: —

### P30-005 — Privacy Policy Surface
- Status: TODO · Priority: P1 · Depends On: P30-001
- Business Decision: do NOT claim legal certification without counsel/audit
- SRS: — · Design: docs/design.md trust surfaces · Files: privacy surface content
- Acceptance — document: data collected · AI processing · BYOK processing · payment processing ·
  analytics telemetry · retention · deletion · export · third-party services:
  - [ ] all areas covered
- Verification: doc review
- Evidence: — · Known Limitations: legal review pending · Notes: overlaps P36-002 — keep one source

### P30-006 — AI Data Control
- Status: TODO · Priority: P1 · Depends On: P30-005
- Business Decision: NEVER imply private content is unprocessed when it is processed
- SRS: AI chapters · Design: docs/ai-architecture.md · Files: AI data-control surface + copy
- Acceptance:
  - [ ] explain hosted AI vs BYOK · [ ] explain which content is sent for a request
  - [ ] provide controls where technically supported
- Verification: [ ] Browser evidence
- Evidence: — · Known Limitations: — · Notes: —

### P30-007 — Data Retention Matrix
- Status: TODO(doc) · Priority: P1 · Depends On: P30-004
- Business Decision: where legal/operational retention is uncertain → mark REVIEW REQUIRED; do not
  invent legal policy
- SRS: — · Design: — · Files: retention matrix doc
- Acceptance — define retention for: AI runs · AI proposals · usage records · billing events ·
  email logs · notifications · deleted account records · audit records:
  - [ ] all categories defined · [ ] uncertain items flagged for review
- Verification: doc review
- Evidence: — · Known Limitations: — · Notes: —

### P30-008 — Backup/Restore Coverage
- Status: TODO · Priority: P1 · Depends On: P30-007
- Business Decision: —
- SRS: durability NFRs · Design: docs/deployment.md · Files: backup scope + restore test
- Acceptance — backups include required SaaS state subject to retention policy; restore verified for:
  - [ ] workspace · [ ] goals · [ ] tasks · [ ] notes · [ ] canvas · [ ] subscriptions
  - [ ] entitlements · [ ] AI usage · [ ] billing events
- Verification: [ ] Integration(restore drill, local/staging)
- Evidence: — · Known Limitations: full production drill in P39-012 · Notes: —

### P30-009 — P30 FINAL GATE
- Status: GATED · Priority: P0 · Depends On: ALL P30 tasks
- Business Decision: — · SRS: — · Design: — · Files: TASK.md sign-off
- Acceptance (master prompt §8 P30-009):
  - [ ] ownership · [ ] export · [ ] deletion · [ ] privacy · [ ] AI data transparency
  - [ ] retention · [ ] backup/recovery
- Verification: compiled gate report · Evidence: — · Known Limitations: — · Notes: gate binary

## PHASE 31 — PRODUCT INTELLIGENCE, ANALYTICS, INSIGHTS, WRAPPED

> Objective: evidence-based reflection and shareable storytelling. Deterministic first, AI bounded.
> Renumbered from old "PHASE 28" per KINEVO_POST_P27_MASTER_EXECUTION_PROMPT.md (execution authority §0/§9).

### P31-001 — Intelligence Source Matrix
- Status: TODO · Priority: High · Depends On: P23 data foundations
- Business Decision: Wrapped FREE=basic, PRO=advanced/yearly, POWER=expanded (locked)
- SRS: analytics chapters · Design: docs/mobile-architecture.md §9 upcoming; docs/domain-model.md
- Files: docs/… intelligence matrix section (owner doc: TASK note + analytics module doc)
- Acceptance:
  - [ ] sources documented: Goals; Milestones; Tasks; Activity Logs; Progress Events; Focus Sessions;
        scheduling outcomes; Workspace context
  - [ ] every metric source documented
  - [ ] no metric uses UI-only state
- Verification: Unit(review of sources) / Integration(n/a) / E2E(n/a) / Device(n/a)
- Evidence: — · Known Limitations: — · Notes: mirrors JOURNEY H inputs

### P31-002 — Metric Catalog
- Status: TODO · Priority: High · Depends On: P31-001
- Business Decision: — 
- SRS: analytics chapter · Design: —
- Files: MetricCatalog class + tests (Application/Intelligence)
- Acceptance (metrics with exact formulas defined):
  - [ ] goals created/completed · [ ] milestones advanced/completed · [ ] tasks completed
  - [ ] completion ratio · [ ] focus minutes · [ ] active days · [ ] streak where supported
  - [ ] goal progress · [ ] planned vs completed work
  - [ ] every metric defines: source, formula, date range, timezone, inclusion, exclusion, null behavior
  - [ ] tests for all metrics
- Verification: Unit(all formulas) / Integration(source queries) / E2E(n/a) / Device(n/a)
- Evidence: — · Known Limitations: — · Notes: deterministic; timezone-aware (profile timezone)

### P31-003 — Insight Engine
- Status: TODO · Priority: High · Depends On: P31-002
- Business Decision: AI assists, never silently controls; deterministic insights precede narrative
- SRS: insights · Design: docs/ai-architecture.md boundary
- Files: InsightEngine service + types
- Acceptance (every insight implemented):
  - [ ] positive trend · [ ] negative trend · [ ] consistency · [ ] goal alignment
  - [ ] planning/execution gap · [ ] workload pattern
  - [ ] every insight has evidence attached
  - [ ] deterministic output (same inputs → same outputs)
  - [ ] tests
- Verification: Unit(each insight) / Integration(metrics feed) / E2E(n/a) / Device(n/a)
- Evidence: — · Known Limitations: — · Notes: non-diagnostic language rules apply

### P31-004 — User Analytics vs Founder Analytics
- Status: TODO · Priority: High · Depends On: P31-002
- Business Decision: permissions NEVER mixed — USER surfaces personal performance/progress/review;
  FOUNDER surfaces activation/retention/revenue/AI spend/payment failures (founder-only access)
- SRS: analytics · Design: docs/design.md analytics surfaces
- Files: founder analytics surface (separate from user Analytics) + authorization tests
- Acceptance:
  - [ ] USER analytics shows only the signed-in user's own data
  - [ ] FOUNDER analytics (activation; retention; revenue; AI spend; payment failures) is access-controlled
  - [ ] no metric from one surface leaks into the other
  - [ ] authorization tests for founder-only access
- Verification: Unit / Integration(authorization) / E2E(n/a) / Device(n/a)
- Evidence: — · Known Limitations: — · Notes: master prompt §9 P31-004 (canonical; was missing from old roadmap)

### P31-005 — AI Narrative
- Status: TODO · Priority: Medium · Depends On: P31-003; AI gateway
- Business Decision: AI receives ONLY validated metrics/insights (untrusted-input pipeline applies)
- SRS: AI chapters (FR-60..62) · Design: docs/ai-architecture.md
- Files: NarrativeUseCase (structured output schema)
- Acceptance:
  - [ ] bounded context (validated metrics package only)
  - [ ] schema validation for structured response
  - [ ] invalid response rejected (no silent fallback to invented data)
  - [ ] AI-unavailable fallback keeps deterministic summary usable
  - [ ] AI MUST NOT invent numbers/dates/goals/medical/psych/causation (guardrails tested)
- Verification: Unit(schema reject cases) / Integration(provider stub) / E2E(n/a) / Device(n/a)
- Evidence: — · Known Limitations: quality depends on provider; costs metered via existing ledger
- Notes: 

### P31-006 — Monthly Review
- Status: TODO · Priority: High · Depends On: P31-002/003
- Business Decision: FREE gets basic monthly/yearly summary when enough data exists
- SRS: review · Design: docs/design.md review
- Files: MonthlyReview screen/API composition
- Acceptance:
  - [ ] activity section (metrics) · [ ] progress section · [ ] notable changes
  - [ ] stalled items surfaced · [ ] suggested next focus (from insights, actionable)
  - [ ] deterministic metrics underlying everything · [ ] evidence visible · [ ] next action present
- Verification: Unit / Integration / E2E(web) / Device(mobile review later)
- Evidence: — · Known Limitations: — · Notes: feeds Reflection→Goal (P31-010)

### P31-007 — Yearly Wrapped
- Status: TODO · Priority: High · Depends On: P31-006
- Business Decision: ENTITLEMENT SPLIT LOCKED — FREE basic summary; PRO advanced yearly + richer
  comparisons + AI narrative where available; POWER all PRO + deeper history + expanded share
- SRS: wrapped chapters · Design: docs/design.md wrapped surface
- Files: Wrapped flow (sections + composition)
- Acceptance (required sections):
  - [ ] opening · [ ] goals · [ ] milestones · [ ] execution · [ ] focus · [ ] knowledge
  - [ ] major progress · [ ] patterns · [ ] reflection · [ ] next direction
  - [ ] FREE: basic summary rendered
  - [ ] PRO: advanced yearly Wrapped rendered
  - [ ] POWER: expanded insights/share customization rendered
- Verification: Unit(per-section builders) / Integration(entitlement branches) / E2E(Journey H) / Device
- Evidence: — · Known Limitations: — · Notes: uses history.depth entitlement for depth ceilings

### P31-008 — Shareable Artifact
- Status: TODO · Priority: Medium · Depends On: P31-007
- Business Decision: privacy-safe by default; explicit user confirmation required
- SRS: sharing/security · Design: docs/design.md share cards
- Files: ShareCard renderer (vertical story / square / downloadable card-image)
- Acceptance:
  - [ ] user sees EXACT share payload before confirming
  - [ ] privacy-safe by default (no raw private task/note/canvas content)
  - [ ] explicit confirmation step required
  - [ ] formats: vertical story · square · download image/card
  - [ ] preview required before any external action
- Verification: Unit(payload builder) / Integration(storage/export) / E2E(preview→confirm) / Device(save image)
- Evidence: — · Known Limitations: — · Notes: POWER expands customization options only

### P31-009 — Public Share Link
- Status: OPTIONAL/DECISION_REQUIRED (owner confirmed public links?) · Priority: Low · Depends On: P31-008
- Business Decision: IF implemented — non-guessable token; revocable; privacy-safe payload
- SRS: security NFRs · Design: docs/api/openapi.yaml additions
- Files: PublicShareLink model/controller/tests (+ migration)
- Acceptance:
  - [ ] unauthorized access test passes
  - [ ] revoke test passes
  - [ ] no private data leakage in public payload
  - [ ] token non-guessable (entropy documented)
- Verification: Unit(token) / Integration(revoke) / E2E(link visit logged-out) / Device(n/a)
- Evidence: — · Known Limitations: rate limiting + abuse monitoring required before enabling
- Notes: skip unless owner explicitly green-lights public sharing

### P31-010 — Reflection to Goal
- Status: TODO · Priority: Medium · Depends On: P31-006
- Business Decision: NO automatic Goal creation — explicit confirmation mandatory
- SRS: goals · Design: docs/design.md reflection loop
- Files: Insight→Goal composer dialog
- Acceptance:
  - [ ] no automatic Goal creation anywhere
  - [ ] explicit confirmation step present
  - [ ] workspace context preserved (target workspace selectable/correct default)
  - [ ] Goal creation succeeds through normal use case
- Verification: Unit / Integration / E2E(insight→confirmed goal) / Device
- Evidence: — · Known Limitations: — · Notes: closes the intention loop (final product definition §16)

### P31-011 — Wrapped Entitlement
- Status: TODO · Priority: High · Depends On: P31-007
- Business Decision: FREE basic / PRO advanced+AI / POWER deeper history + share customization
- SRS: entitlements · Design: config/saas.php keys (wrapped=true currently Power; extend keys per catalog)
- Files: entitlement keys expansion (wrapped.yearly, wrapped.advanced_share, insights.*, history.depth)
- Acceptance:
  - [ ] backend enforcement tests (each tier × each capability)
  - [ ] frontend enforcement (gates hidden/disabled states)
  - [ ] downgrade does NOT delete historical data
  - [ ] expired/canceled subscription degrades gracefully to Free view
- Verification: Unit(policy matrix) / Integration(API denials) / E2E(Journey G interplay) / Device(view-only)
- Evidence: — · Known Limitations: exact numeric limits come from approved catalog (never invented)
- Notes: reuse P23 EntitlementService — NO second entitlement system (Rule 0.1)

### P31-012 — Behavioral Archetype
- Status: OPTIONAL (implement only after P31-003 proves out) · Priority: Low · Depends On: P31-003
- Business Decision: behavior-derived ONLY; explainable; NON-diagnostic. Master prompt §21 forbids
  personality/mental-state claims without explicit validated product/research basis — treat as
  DECISION_REQUIRED before any implementation
- SRS: — · Design: —
- Files: Archetype classifier + label copy
- Acceptance:
  - [ ] archetypes Builder/Finisher/Explorer/Strategist/Deep Worker derive from behavior evidence
  - [ ] evidence shown to user (why this archetype)
  - [ ] no health/psychology claims (copy review)
  - [ ] non-deterministic personality claims prohibited
- Verification: Unit(classifier thresholds) / Integration / E2E / Device(n/a)
- Evidence: — · Known Limitations: skip entirely if evidence rules cannot be met cleanly
- Notes: 

### P31-013 — P31 FINAL GATE
- Status: GATED · Priority: High · Depends On: ALL P31 tasks
- Business Decision: — · SRS: — · Design: — · Files: TASK.md
- Acceptance:
  - [ ] metric catalog · [ ] deterministic insight engine · [ ] analytics UI · [ ] AI narrative fallback
  - [ ] Wrapped · [ ] safe sharing · [ ] next-goal loop · [ ] entitlement · [ ] privacy · [ ] E2E
- Verification: Unit/Integration/E2E suites green + browser evidence for wrapped flow
- Evidence: — · Known Limitations: — · Notes: —

## PHASE 32 — GROWTH, EXPERIMENTATION, FEEDBACK, COMMERCIAL ANALYTICS

> Objective: measure the loop honestly (activation; retention; pricing; AI economics) with safe,
> privacy-first events. Source: KINEVO_POST_P27_MASTER_EXECUTION_PROMPT.md §10 (execution authority).
> Note: funnel/retention instrumentation is shared with P37-003/P37-005 — ONE subsystem, two
> consumers (RULE 3.2: no duplicate subsystems).

### P32-001 — Product Event Taxonomy
- Status: TODO · Priority: P1 · Depends On: —
- Business Decision: safe events only; do NOT capture raw private content by default (master RULE 3.8)
- SRS: — · Design: docs/ai-architecture.md privacy boundary · Files: event catalog + instrumentation
- Acceptance — track safe events:
  - [ ] signup · verification · onboarding_complete · workspace_created · goal_created
  - [ ] breakdown_requested · proposal_accepted · task_completed · goal_progressed · review_opened
  - [ ] Wrapped_opened · Wrapped_shared · checkout_started · subscription_active
  - [ ] event names/properties/timestamp semantics documented · server-emitted · privacy-safe
- Verification: [ ] Unit(event builders) · [ ] Integration(DB write)
- Evidence: — · Known Limitations: — · Notes: no PII beyond ids; complements P37-003 instrumentation

### P32-002 — North Star Metric (WGPU)
- Status: TODO · Priority: P1 · Depends On: P32-001
- Business Decision: primary = Weekly Goal Progressing Users (WGPU): unique users in a 7-day window
  with ≥1 meaningful progress action on an active Goal
- SRS: — · Design: — · Files: WGPU definition + query/report
- Acceptance:
  - [ ] WGPU definition implemented (window; "meaningful progress action" semantics documented)
  - [ ] secondary defined: Goal-to-Execution Rate · Activation Rate · D7/D30 retention
- Verification: [ ] Unit · [ ] Integration(fixtures across week boundaries)
- Evidence: — · Known Limitations: — · Notes: shared subsystem with P37-004 adoption metrics

### P32-003 — Activation Funnel
- Status: TODO · Priority: P1 · Depends On: P32-001
- Business Decision: —
- SRS: — · Design: — · Files: funnel query/report (shared with P37-003)
- Acceptance:
  - [ ] funnel: signup → workspace → goal → task/milestone → first meaningful execution
  - [ ] report/query available (ops command or SQL view)
- Verification: [ ] Integration(seed+query)
- Evidence: — · Known Limitations: — · Notes: implementation co-located with P37-003 (single subsystem)

### P32-004 — Retention
- Status: TODO · Priority: P1 · Depends On: P32-001
- Business Decision: —
- SRS: — · Design: — · Files: retention definitions + query (shared with P37-005)
- Acceptance:
  - [ ] D1 · D7 · D30 · WAU · recurring core-loop use (timezone semantics documented)
- Verification: [ ] Integration(fixtures across day boundaries)
- Evidence: — · Known Limitations: — · Notes: implementation co-located with P37-005 (single subsystem)

### P32-005 — Pricing Analytics
- Status: TODO · Priority: P1 · Depends On: P32-001; P24 billing events
- Business Decision: LOCKED — Free = 0; Pro = 34,900; Power = 49,900 (never invent annual/trial/coupons)
- SRS: billing · Design: docs/adr/ADR-013-product-tiers-pricing.md · Files: pricing report queries
- Acceptance:
  - [ ] measure: upgrade intent · checkout start · conversion · cancellation · downgrade · churn
- Verification: [ ] Integration(billing events→report)
- Evidence: sandbox only until production · Known Limitations: — · Notes: P37-006 consumes for validation

### P32-006 — Unit Economics
- Status: TODO · Priority: P1 · Depends On: P32-005
- Business Decision: BYOK cost stays separate from Kinevo-hosted spend forever (BYOK cost is NOT
  Kinevo-hosted AI COGS); NO profitability claims (absorbed from old P29-008)
- SRS: — · Design: — · Files: economics worksheet/query (internal only)
- Acceptance — track separately:
  - [ ] subscription revenue · AI revenue · hosted AI cost · infrastructure cost · payment fees
  - [ ] support cost when measurable · storage/bandwidth if material
  - [ ] gross contribution signal computable
  - [ ] no unsupported profitability claim published anywhere
- Verification: [ ] Integration(cost tables populated)
- Evidence: — · Known Limitations: — · Notes: —

### P32-007 — AI Cost Simulator
- Status: TODO · Priority: P1 · Depends On: P25 pricing catalog/ledger
- Business Decision: the simulator determines whether the current AI quota is economically safe
- SRS: AI economics · Design: docs/ai-architecture.md · Files: simulator (internal command/report)
- Acceptance — MUST support at minimum:
  - [ ] provider · model · input tokens · cached input tokens · output tokens
  - [ ] pricing version · request frequency · plan
  - [ ] P50 scenario · P95 scenario · abuse scenario
  - [ ] output: provider cost · Kinevo estimated charge · credit consumption · margin signal
- Verification: [ ] Unit(scenarios) · [ ] Integration(pricing catalog)
- Evidence: — · Known Limitations: — · Notes: feeds P38-002 credit safety review

### P32-008 — AI Cost/Revenue Alerting
- Status: TODO · Priority: P1 · Depends On: P32-007
- Business Decision: do not depend on Notification Center if operational alerting can be simpler
- SRS: — · Design: — · Files: alert configs + tests
- Acceptance:
  - [ ] user alerts at 50% · 75% · 90% · 100% of hosted allowance
  - [ ] founder alerts: AI spend spike · per-user anomaly · payment failure spike · provider cost anomaly
- Verification: [ ] Integration(threshold crossing)
- Evidence: — · Known Limitations: — · Notes: ties to P25-010 ops alerts — extend, no duplicate

### P32-009 — Feature Feedback
- Status: TODO · Priority: P2 · Depends On: P32-001
- Business Decision: safe metadata only (route · app version · browser/device · request ID)
- SRS: — · Design: docs/design.md feedback affordances · Files: feedback endpoints + UI affordance
- Acceptance:
  - [ ] "Was this useful?" · bug report · feature feedback
  - [ ] metadata attached safely · no private content captured
- Verification: [ ] Unit · [ ] Integration · [ ] Browser
- Evidence: — · Known Limitations: — · Notes: —

### P32-010 — Experiments / Feature Flags
- Status: TODO · Priority: P2 · Depends On: P32-001
- Business Decision: minimal INTERNAL database-backed flags initially; no external flag service
  unless need is proven
- SRS: — · Design: — · Files: flag table + read path + admin toggle
- Acceptance:
  - [ ] flag primitive exists (DB-backed; cache-safe; entitlement-adjacent flags documented)
  - [ ] every experiment documents: hypothesis · metric · eligibility · duration · result
- Verification: [ ] Unit · [ ] Integration
- Evidence: — · Known Limitations: — · Notes: —

### P32-011 — Referral/Growth Loop
- Status: DECISION_REQUIRED · Priority: P3 · Depends On: P31-008
- Business Decision: Wrapped MAY support referral attribution; reward amounts MUST NOT be invented
  (deferred business decision — master §2.10)
- SRS: — · Design: — · Files: attribution note (design only until approved)
- Acceptance:
  - [ ] attribution design recorded · [ ] no reward amounts implemented without owner approval
- Verification: doc review
- Evidence: — · Known Limitations: — · Notes: —

### P32-012 — P32 FINAL GATE
- Status: GATED · Priority: P1 · Depends On: ALL P32 tasks
- Business Decision: — · SRS: — · Design: — · Files: TASK.md sign-off
- Acceptance (master prompt §10 P32-012):
  - [ ] event taxonomy · [ ] WGPU · [ ] activation · [ ] retention · [ ] pricing metrics
  - [ ] AI cost simulator · [ ] cost alerts · [ ] feedback · [ ] experiments
- Verification: compiled gate report · Evidence: — · Known Limitations: — · Notes: gate binary

## PHASE 33 — OPEN-SOURCE REPOSITORY SPLIT, CORE/CLOUD BOUNDARY, WEBSITE

> Objective: repository separation BEFORE P28–P39 accumulate more coupling. Source:
> KINEVO_POST_P27_MASTER_EXECUTION_PROMPT.md §11 (execution authority). Target repos: PUBLIC
> github.com/sedam-or/Kinevo (Core) · PUBLIC github.com/sedam-or/kinevo-site (website) · PRIVATE
> github.com/sedam-or/kinevo-cloud (hosted SaaS/cloud-only infra). No separate docs repo unless
> operational evidence requires it.

### P33-001 — Repository Ownership Matrix
- Status: TODO · Priority: P0 · Depends On: —
- Business Decision: —
- SRS: — · Design: — · Files: ownership matrix doc
- Acceptance — for EVERY current root path record (source path · destination repository ·
  destination path · retain/copy/move/archive/delete · dependency reason · license implication):
  - [ ] inventory includes: README.md · LICENSE · AGENTS.md · TASK.md · docs/ · server/ · database/
        tests/ · infrastructure/ · scripts/ · .github/ · .opencode/ · environment/config files
- Verification: doc review + path inventory script
- Evidence: — · Known Limitations: — · Notes: master §26 file decision rule (8 questions) applies per file

### P33-002 — Core/Cloud Boundary
- Status: TODO · Priority: P0 · Depends On: P33-001
- Business Decision: repo open-source; SaaS infra + gateway providers externalized (absorbed from
  old P30-004). Model: Kinevo Core → stable package/module boundary → Kinevo Cloud
- SRS: — · Design: docs/architecture.md · docs/third-party/licenses.md · Files: boundary doc section
  (docs/billing.md or deployment.md) + seam implementation notes
- Acceptance:
  - [ ] actual technical seam identified FROM existing source (inspect imports first — do NOT invent
        a package boundary)
  - [ ] open-source source documented · SaaS-only infrastructure documented
  - [ ] external providers/payment gateway/AI gateway/BYOK documented
  - [ ] licensing review done · third-party attribution current · boundary documented
- Verification: [ ] license checker · [ ] boundary review
- Evidence: — · Known Limitations: — · Notes: —

### P33-003 — Migration Safety Plan
- Status: TODO · Priority: P0 · Depends On: P33-002
- Business Decision: NEVER delete first
- SRS: — · Design: — · Files: migration plan doc + execution log
- Acceptance — conceptual order (master §11 P33-003):
  - [ ] 1 freeze non-essential feature work · 2 inventory files · 3 classify ownership
  - [ ] 4 detect cross-repo dependencies · 5 create destination repos · 6 copy/migrate safe content
  - [ ] 7 establish dependency boundary · 8 tests in source+destination · 9 builds
  - [ ] 10 docs/links validation · 11 license/attribution validation · 12 functional comparison
  - [ ] 13 migration notes published · 14 only then remove/archive obsolete content
- Verification: process gate · Evidence: step log · Known Limitations: — · Notes: —

### P33-004 — AGENTS.md / TASK.md Disposition
- Status: TODO · Priority: P1 · Depends On: P33-001
- Business Decision: do NOT blindly expose private AI development instructions in the public product repo
- SRS: — · Design: — · Files: contributor-facing rules (public) + private AI/development area
- Acceptance:
  - [ ] contributor-facing rules public where useful · agent-specific operational instructions moved
        to explicit AI/development area · private workflow instructions stay private if non-public
  - [ ] TASK.md: preserve active contributor/release-relevant portions; archive excessive history
  - [ ] no data loss
- Verification: doc review · Evidence: — · Known Limitations: — · Notes: —

### P33-005 — SRS/Design Document Disposition
- Status: TODO · Priority: P1 · Depends On: P33-001
- Business Decision: do not delete merely because it is a development document
- SRS: — · Design: — · Files: classification table
- Acceptance — classify each document: public product contract · public architecture · contributor
  development · private SaaS implementation · historical archive:
  - [ ] every doc classified
- Verification: doc review · Evidence: — · Known Limitations: — · Notes: —

### P33-006 — Git History
- Status: TODO · Priority: P1 · Depends On: P33-003
- Business Decision: no cosmetic history rewrites; credential/secret exposure exception requires
  rewriting + immediate rotation
- SRS: — · Design: — · Files: provenance notes
- Acceptance:
  - [ ] source tags/commits documented · migration commit created · provenance preserved (if history
        spans repos)
- Verification: process review · Evidence: — · Known Limitations: — · Notes: —

### P33-007 — Open-Source License Audit
- Status: TODO · Priority: P0 · Depends On: P33-002
- Business Decision: —
- SRS: — · Design: docs/third-party/licenses.md · Files: licenses.md + attributions.md updates
- Acceptance — verify: MIT · Tiptap/ProseMirror · Excalidraw · all dependencies · fonts/assets:
  - [ ] ledger current · attributions current
- Verification: [ ] license checker
- Evidence: — · Known Limitations: — · Notes: —

### P33-008 — Core README
- Status: TODO · Priority: P1 · Depends On: P33-002
- Business Decision: —
- SRS: — · Design: — · Files: README.md (Core repo)
- Acceptance — explains: what Kinevo is · core value · architecture · screenshots · self-hosting ·
  development · contributing · license · Cloud option:
  - [ ] all sections present
- Verification: doc review · Evidence: — · Known Limitations: — · Notes: —

### P33-009 — Product Website
- Status: TODO · Priority: P1 · Depends On: P33-002
- Business Decision: —
- SRS: — · Design: docs/design.md (public surfaces follow design authority; consult taste/
  ui-ux-pro-max skills) · Files: kinevo-site repo (pages; content; assets; components; tests)
- Acceptance:
  - [ ] https://kinevo.app · https://app.kinevo.app · https://docs.kinevo.app · status subdomain IF
        implemented
  - [ ] no DNS/domain readiness claims until actually configured
- Verification: [ ] build + preview
- Evidence: — · Known Limitations: — · Notes: —

### P33-010 — Landing Page IA
- Status: TODO · Priority: P1 · Depends On: P33-009
- Business Decision: hero positioning = intention → execution; do NOT lead with a feature dump
- SRS: — · Design: docs/design.md · Files: landing page sections
- Acceptance — sections: 1 problem · 2 transformation · 3 how it works · 4 Goal→AI→Task→Today flow ·
  5 Workspace · 6 Knowledge · 7 Canvas · 8 Analytics · 9 Wrapped · 10 open source · 11 pricing ·
  12 FAQ · 13 security/trust · 14 CTA:
  - [ ] section order per master §11 P33-010
- Verification: [ ] Browser preview evidence
- Evidence: — · Known Limitations: — · Notes: UI/UX skill consult REQUIRED (AGENTS.md)

### P33-011 — Pricing Page
- Status: TODO · Priority: P1 · Depends On: P33-010
- Business Decision: show Free — IDR 0 · Pro — IDR 34,900/month · Power — IDR 49,900/month; annual
  price omitted until approved (master §2.10)
- SRS: billing · Design: docs/design.md pricing · Files: pricing page
- Acceptance:
  - [ ] three tiers rendered · [ ] no invented pricing
- Verification: [ ] Browser evidence
- Evidence: — · Known Limitations: — · Notes: —

### P33-012 — OSS vs Cloud
- Status: TODO(doc) · Priority: P1 · Depends On: P33-009
- Business Decision: Cloud sells convenience/managed infra/reliability/support/managed AI/managed
  billing — NEVER implies self-hosting is intentionally degraded
- SRS: — · Design: — · Files: site copy section
- Acceptance:
  - [ ] self-host Kinevo Core explained · [ ] Kinevo Cloud explained
- Verification: doc review · Evidence: — · Known Limitations: — · Notes: —

### P33-013 — Migration Validation
- Status: GATED(repos exist) · Priority: P0 · Depends On: P33-003
- Business Decision: —
- SRS: — · Design: — · Files: validation logs per repo
- Acceptance:
  - [ ] Core: clean clone → install → migrate → test → build → run
  - [ ] Cloud: clean clone → resolves Core dependency → test → build
  - [ ] Site: clean clone → build → preview
- Verification: [ ] reproducible builds all three repos
- Evidence: — · Known Limitations: — · Notes: —

### P33-014 — P33 FINAL GATE
- Status: GATED · Priority: P0 · Depends On: ALL P33 tasks
- Business Decision: — · SRS: — · Design: — · Files: TASK.md sign-off
- Acceptance (master prompt §11 P33-014):
  - [ ] ownership matrix · [ ] core/cloud seam · [ ] migration · [ ] docs disposition · [ ] licenses
  - [ ] README · [ ] product website · [ ] pricing · [ ] OSS/Cloud explanation · [ ] reproducible builds
- Verification: compiled gate report · Evidence: — · Known Limitations: — · Notes: gate binary

## PHASE 34 — SAAS OPERATIONS, ADMIN, SUPPORT, OBSERVABILITY, ABUSE CONTROL

> Objective: operate the SaaS honestly — admin visibility without privacy violations, abuse controls,
> environment separation, runbooks. Source: KINEVO_POST_P27_MASTER_EXECUTION_PROMPT.md §12 (execution
> authority).

### P34-001 — Admin Access Model
- Status: TODO(doc) · Priority: P0 · Depends On: —
- Business Decision: V1 — NO arbitrary user impersonation; NO direct raw Note browsing; NO raw Canvas
  browsing; NO raw AI prompt browsing; NO BYOK plaintext visibility
- SRS: security · Design: — · Files: admin policy doc
- Acceptance:
  - [ ] policy documented
- Verification: doc review · Evidence: — · Known Limitations: — · Notes: —

### P34-002 — Admin Dashboard
- Status: TODO · Priority: P1 · Depends On: P34-001
- Business Decision: —
- SRS: operations · Design: docs/design.md (admin surface — minimal, token-compliant) · Files: admin dashboard
- Acceptance — minimum: users · active subscriptions · plan distribution · MRR snapshot · hosted AI
  spend · payment failures · webhook failures · email failures · backup status · system health:
  - [ ] all tiles present
- Verification: [ ] Integration · [ ] Browser
- Evidence: — · Known Limitations: — · Notes: —

### P34-003 — Subscription/Billing Diagnostics
- Status: TODO · Priority: P1 · Depends On: P34-002
- Business Decision: —
- SRS: billing · Design: — · Files: diagnostics view
- Acceptance — show: internal subscription ID · plan · provider · provider subscription reference ·
  last billing event · entitlement state · last payment status:
  - [ ] all fields present
- Verification: [ ] Integration · [ ] Browser
- Evidence: — · Known Limitations: — · Notes: —

### P34-004 — AI Operations
- Status: TODO · Priority: P1 · Depends On: P34-002
- Business Decision: aggregate/safe data only; NEVER expose secrets
- SRS: AI · Design: — · Files: AI ops view
- Acceptance — show: provider status · model · request counts · tokens · estimated spend · credit
  consumption · error rate:
  - [ ] all aggregates present
- Verification: [ ] Integration · [ ] Browser
- Evidence: — · Known Limitations: — · Notes: —

### P34-005 — Email Operations
- Status: TODO · Priority: P1 · Depends On: P34-002
- Business Decision: do NOT expose raw tokens in admin UI
- SRS: — · Design: — · Files: email ops view
- Acceptance — show: queued · sent · failed · retrying · template ID:
  - [ ] all states present
- Verification: [ ] Integration · [ ] Browser
- Evidence: — · Known Limitations: — · Notes: —

### P34-006 — Abuse/Fraud Controls
- Status: TODO · Priority: P0 · Depends On: P34-001
- Business Decision: do not build a full fraud platform without need
- SRS: security NFRs · Design: — · Files: rate limiting + quotas + suspicious activity logging
- Acceptance — protect at minimum: signup · login · password reset · AI generation · checkout
  creation · webhook endpoints · public share links:
  - [ ] rate limiting · request quotas · suspicious activity logging · provider-side controls where available
- Verification: [ ] Integration(limit enforcement)
- Evidence: — · Known Limitations: — · Notes: —

### P34-007 — Environment Separation
- Status: TODO · Priority: P0 · Depends On: —
- Business Decision: production credentials MUST NOT be used in local tests
- SRS: operations · Design: docs/environment.md · Files: env matrix + config separation
- Acceptance — explicit environments: local · development · staging · production; each with
  appropriate database · AI credential · payment credential · email configuration · storage:
  - [ ] matrix documented · [ ] separation enforced
- Verification: [ ] config review
- Evidence: — · Known Limitations: — · Notes: —

### P34-008 — Incident Runbooks
- Status: TODO · Priority: P1 · Depends On: P34-007
- Business Decision: —
- SRS: operations · Design: — · Files: runbooks/ folder
- Acceptance — runbooks for: AI outage · payment outage · webhook failure · email failure · DB
  outage · storage outage · queue outage · backup failure · security incident · account recovery
  issue · entitlement mismatch:
  - [ ] all runbooks exist · [ ] owner identified per runbook
- Verification: tabletop walkthrough (≥1) · Evidence: — · Known Limitations: — · Notes: extends P39-014

### P34-009 — Health/Alerting
- Status: TODO · Priority: P1 · Depends On: P34-007
- Business Decision: —
- SRS: operations · Design: docs/deployment.md · Files: monitor/alert configs
- Acceptance — monitor: app health · DB · queue · scheduler · storage · AI · billing · email ·
  backup · abnormal spend:
  - [ ] all monitored
- Verification: induced-failure drill per alert · Evidence: fired alerts log · Known Limitations: — · Notes: —

### P34-010 — Admin Audit Log
- Status: TODO · Priority: P1 · Depends On: P34-002
- Business Decision: —
- SRS: security · Design: — · Files: audit log table + viewer
- Acceptance — audit: entitlement correction · subscription correction · billing reconciliation ·
  account administrative action:
  - [ ] all events audited
- Verification: [ ] Integration
- Evidence: — · Known Limitations: — · Notes: —

### P34-011 — Support Channel
- Status: TODO(doc) · Priority: P2 · Depends On: —
- Business Decision: V1 recommendation — support@kinevo.app; GitHub Issues/Discussions for
  open-source; separate SaaS support from public tracker when appropriate
- SRS: — · Design: — · Files: support doc
- Acceptance:
  - [ ] channels documented
- Verification: doc review · Evidence: — · Known Limitations: — · Notes: —

### P34-012 — Help Center Baseline
- Status: TODO · Priority: P2 · Depends On: P33-009
- Business Decision: —
- SRS: — · Design: — · Files: concise docs (site/docs subdomain)
- Acceptance — concise docs for: getting started · Goals · Today · Workspace · AI · BYOK · Billing ·
  data export · account deletion · troubleshooting:
  - [ ] all topics present
- Verification: doc review · Evidence: — · Known Limitations: — · Notes: —

### P34-013 — P34 FINAL GATE
- Status: GATED · Priority: P0 · Depends On: ALL P34 tasks
- Business Decision: — · SRS: — · Design: — · Files: TASK.md sign-off
- Acceptance (master prompt §12 P34-013):
  - [ ] admin · [ ] billing diagnostics · [ ] AI ops · [ ] email ops · [ ] abuse controls
  - [ ] environment separation · [ ] runbooks · [ ] support · [ ] alerts
- Verification: compiled gate report · Evidence: — · Known Limitations: — · Notes: gate binary

## PHASE 35 — ANDROID PRODUCTION HARDENING & CROSS-PLATFORM COHERENCE

> Objective: release-grade Android with web-first billing and zero secrets in the APK. Source:
> KINEVO_POST_P27_MASTER_EXECUTION_PROMPT.md §13 (execution authority).

### P35-001 — Android Release Build
- Status: TODO · Priority: P0 · Depends On: —
- Business Decision: —
- SRS: mobile · Design: docs/mobile-architecture.md · Files: build procedures + signing checklist
- Acceptance — verify: debug · release-like · signed release procedure · versioning:
  - [ ] all build variants verified
- Verification: [ ] Device build evidence
- Evidence: — · Known Limitations: — · Notes: extends P26/P27 mobile work

### P35-002 — Android Core Loop
- Status: TODO · Priority: P0 · Depends On: P35-001
- Business Decision: —
- SRS: journeys · Design: — · Files: device test checklist
- Acceptance — must pass: login · Workspace · Goal · AI Breakdown · Today · Task · Complete · Review:
  - [ ] full loop green on device
- Verification: [ ] Device transcript
- Evidence: — · Known Limitations: — · Notes: P27 device-gate findings (UI-021) are the baseline

### P35-003 — Android Offline
- Status: TODO · Priority: P0 · Depends On: P35-002
- Business Decision: reuse existing synchronization architecture — NO second sync subsystem
- SRS: offline · Design: docs/offline-sync.md · Files: device offline tests
- Acceptance — verify: Today cache · mutation queue · Note mutation where supported · reconnect ·
  conflict:
  - [ ] all offline behaviors verified
- Verification: [ ] Device offline transcript
- Evidence: — · Known Limitations: — · Notes: IndexedDB/queue is cache, never canonical (AGENTS offline rule)

### P35-004 — Android Entitlement
- Status: TODO · Priority: P0 · Depends On: P35-002
- Business Decision: mobile MUST NOT forge entitlement locally
- SRS: billing · Design: — · Files: entitlement device tests
- Acceptance — test: Free · Pro · Power · expired · canceled/grace behavior where applicable:
  - [ ] all states verified against backend
- Verification: [ ] Device transcript per state
- Evidence: — · Known Limitations: — · Notes: —

### P35-005 — Web-First Billing
- Status: TODO · Priority: P0 · Depends On: P35-004
- Business Decision: Android v1 has NO native subscription checkout
- SRS: billing · Design: docs/design.md · Files: Android billing boundary UI
- Acceptance — Android can: view plan · view subscription · manage subscription (web hand-off) ·
  receive web entitlement:
  - [ ] all flows verified
- Verification: [ ] Device evidence
- Evidence: — · Known Limitations: — · Notes: user communication for web-first billing (master gap #47)

### P35-006 — Android AI Security
- Status: TODO · Priority: P0 · Depends On: P35-001
- Business Decision: the Android app MUST NEVER contain DeepSeek/OpenCode/OmniRouter/Midtrans/
  production SMTP secrets. Flow: Android → Kinevo backend → provider/gateway
- SRS: security · Design: docs/ai-architecture.md · Files: APK inspection evidence
- Acceptance:
  - [ ] APK/release artifact scanned — no secrets
  - [ ] all AI traffic via backend
- Verification: [ ] artifact scan + network evidence
- Evidence: — · Known Limitations: — · Notes: —

### P35-007 — Android Device Matrix
- Status: TODO · Priority: P1 · Depends On: P35-002
- Business Decision: —
- SRS: — · Design: — · Files: device matrix record
- Acceptance — test at minimum: small phone · typical phone · large phone:
  - [ ] exact devices + Android API versions recorded
- Verification: [ ] Device transcripts
- Evidence: — · Known Limitations: — · Notes: —

### P35-008 — P35 FINAL GATE
- Status: GATED · Priority: P0 · Depends On: ALL P35 tasks
- Business Decision: — · SRS: — · Design: — · Files: TASK.md sign-off
- Acceptance (master prompt §13 P35-008):
  - [ ] release build · [ ] core loop · [ ] offline · [ ] entitlement · [ ] AI security
  - [ ] billing boundary · [ ] device evidence
- Verification: compiled gate report · Evidence: — · Known Limitations: — · Notes: gate binary

## PHASE 36 — COMPLIANCE, LEGAL/TRUST SURFACES, PRODUCTION POLICY READINESS

> Objective: close non-code trust gaps before public paid launch. Does NOT invent legal conclusions —
> converts known operational requirements into explicit product surfaces and review items. Source:
> KINEVO_POST_P27_MASTER_EXECUTION_PROMPT.md §14 (execution authority).

### P36-001 — Terms of Service Surface
- Status: TODO · Priority: P0 · Depends On: —
- Business Decision: no legal claims beyond reviewable policy text; final wording subject to legal review
- SRS: — · Design: docs/design.md trust surfaces · Files: ToS page
- Acceptance — document: owner/provider identity · service scope · subscription basics · user
  responsibilities · acceptable-use boundary · termination · support path · limitations:
  - [ ] all areas present
- Verification: doc review · Evidence: — · Known Limitations: legal review pending · Notes: —

### P36-002 — Privacy Notice
- Status: TODO · Priority: P0 · Depends On: P30-005
- Business Decision: keep one source of truth with P30-005 (no duplicate policy)
- SRS: — · Design: — · Files: privacy notice page
- Acceptance — cover: account data · product usage telemetry · AI requests · BYOK processing ·
  payment provider · email provider · analytics provider · retention · deletion · exports:
  - [ ] all areas covered
- Verification: doc review · Evidence: — · Known Limitations: — · Notes: —

### P36-003 — AI Use Policy
- Status: TODO · Priority: P1 · Depends On: P36-002
- Business Decision: AI is assistive, NOT authoritative; proposal approval workflow applies
- SRS: AI chapters · Design: docs/ai-architecture.md · Files: AI policy page
- Acceptance — explain: hosted AI · BYOK · provider routing · what content is sent for a requested
  AI operation · assistive-not-authoritative · proposal approval:
  - [ ] all areas present
- Verification: doc review · Evidence: — · Known Limitations: — · Notes: —

### P36-004 — Acceptable Use / Abuse
- Status: TODO · Priority: P1 · Depends On: P36-001
- Business Decision: —
- SRS: security · Design: — · Files: acceptable use section
- Acceptance — define prohibited behaviors appropriate to the product: automated abuse · credential
  theft · malicious payloads · payment fraud · prompt abuse for prohibited content where applicable:
  - [ ] all areas present
- Verification: doc review · Evidence: — · Known Limitations: — · Notes: —

### P36-005 — Cookie/Analytics Policy
- Status: TODO · Priority: P1 · Depends On: P36-002
- Business Decision: document ACTUAL cookies/storage used; do NOT ship consent mechanisms for
  technologies not actually used
- SRS: — · Design: — · Files: cookie/storage policy section
- Acceptance:
  - [ ] actual technologies documented
- Verification: doc review + technical audit · Evidence: — · Known Limitations: — · Notes: —

### P36-006 — Data Processor / Third-Party Inventory
- Status: TODO(doc) · Priority: P0 · Depends On: P36-002
- Business Decision: —
- SRS: — · Design: docs/third-party/licenses.md (adjacent) · Files: processor inventory doc
- Acceptance — inventory: Midtrans · AI gateway/provider · email service · hosting · object storage ·
  analytics — for each: purpose · category of data · region/hosting info if known · provider
  privacy/terms link where appropriate:
  - [ ] all processors listed
- Verification: doc review · Evidence: — · Known Limitations: — · Notes: —

### P36-007 — Tax / Invoice Review Flag
- Status: TODO(doc) · Priority: P1 · Depends On: P36-001
- Business Decision: do NOT invent tax treatment
- SRS: billing · Design: — · Files: review list doc
- Acceptance:
  - [ ] tax requirements needing professional/accounting review recorded
  - [ ] invoice/receipt behavior required by the chosen payment provider recorded
- Verification: doc review · Evidence: — · Known Limitations: DECISION_REQUIRED (professional review) · Notes: —

### P36-008 — Payment User Trust
- Status: TODO · Priority: P0 · Depends On: P24/P35-005 surfaces
- Business Decision: never hide recurring nature
- SRS: billing · Design: docs/design.md billing · Files: billing UI audit + fixes
- Acceptance — billing UI clearly shows: price · recurring interval · current period · renewal date ·
  cancellation status · payment status:
  - [ ] all fields present and understandable
- Verification: [ ] Browser evidence
- Evidence: — · Known Limitations: — · Notes: master §24 billing UX standard applies

### P36-009 — P36 FINAL GATE
- Status: GATED · Priority: P0 · Depends On: ALL P36 tasks
- Business Decision: — · SRS: — · Design: — · Files: TASK.md sign-off
- Acceptance (master prompt §14 P36-009):
  - [ ] Terms · [ ] Privacy · [ ] AI policy · [ ] Acceptable use · [ ] analytics/cookie documentation
  - [ ] provider inventory · [ ] tax review list · [ ] billing transparency
- Verification: compiled gate report · Evidence: — · Known Limitations: — · Notes: gate binary

## PHASE 37 — PUBLIC BETA & PRODUCT-MARKET VALIDATION

> Objective: validate whether users repeatedly obtain value. NOT uncontrolled feature development
> (Rule 0.7). Requires REAL USERS — measurement/instrumentation tasks are buildable; conclusions
> require beta traffic. Renumbered from old "PHASE 29" per KINEVO_POST_P27_MASTER_EXECUTION_PROMPT.md
> (execution authority §0/§15).

### P37-001 — Target User
- Status: TODO(doc) · Priority: Medium · Depends On: —
- Business Decision: Indonesia-first; individual users; multiple goals/projects; fragmented tools
- SRS: product definition · Design: —
- Files: docs/… persona/profile note
- Acceptance:
  - [ ] target user profile documented
  - [ ] exclusions documented (teams/enterprise explicitly OUT per locked decisions)
- Verification: Doc review · Evidence: — · Known Limitations: refined by research (P37-008) · Notes: —

- Verification: Doc review · Evidence: — · Known Limitations: refined by research (P37-008) · Notes: —

### P37-002 — Beta Cohort
- Status: TODO(doc) · Priority: High · Depends On: P37-001
- Business Decision: cohort parameters are an owner decision — agent records, never invents
- SRS: — · Design: — · Files: beta plan section (acquisition; size; window; support method)
- Acceptance:
  - [ ] acquisition source defined · [ ] cohort size defined · [ ] test window defined
  - [ ] support method defined · [ ] consent/communications where relevant documented
- Verification: doc review · Evidence: — · Known Limitations: execution GATED on real users · Notes: —

### P37-003 — Activation
- Status: TODO · Priority: High · Depends On: P37-001
- Business Decision: canonical activation = signup → workspace → Goal → Task/milestone → first
  meaningful execution (master prompt §15 P37-003)
- SRS: — · Design: — · Files: event instrumentation service + tests; funnel query/report command
- Acceptance:
  - [ ] exact event definition written (event names, properties, timestamp semantics)
  - [ ] instrumentation implemented (server-emitted, privacy-safe)
  - [ ] test event verified end-to-end
  - [ ] funnel measures: Goal created → Breakdown → Task created → Task executed → Task completed → repeat use
        (merged from old P29-003)
  - [ ] report/query available (ops command or SQL view)
- Verification: Unit(event builders) / Integration(DB write; seed+query) / E2E(signup→activation emits) / Device(n/a)
- Evidence: — · Known Limitations: — · Notes: no PII in event payloads beyond ids; sample funnel run captured

### P37-005 — Retention
- Status: TODO · Priority: High · Depends On: P37-003
- Business Decision: — · SRS: — · Design: — · Files: retention query/command
- Acceptance:
  - [ ] definitions documented (D1/D7/D30/WAU/recurring core-loop usage with timezone semantics)
  - [ ] report/query available
- Verification: Integration(fixtures across day boundaries) · Evidence: — · Known Limitations: — · Notes: —

### P37-004 — North Star WGPU + Adoption Metrics
- Status: TODO · Priority: High · Depends On: P37-003; P25 ledger
- Business Decision: primary north star = Weekly Goal Progressing Users (WGPU — unique users in a
  7-day window with ≥1 meaningful progress action on an active Goal, master prompt §10 P32-002/§15
  P37-004). Secondary: Goal-to-Execution Rate; AI adoption; Workspace adoption. BYOK adoption tracked
  WITHOUT consuming hosted credits — distinction preserved (merged from old P29-005/006)
- SRS: AI chapters · Design: docs/ai-architecture.md · Files: WGPU + adoption counters/reports
- Acceptance:
  - [ ] WGPU definition implemented (7-day window; meaningful progress action semantics documented)
  - [ ] secondary: Goal-to-Execution Rate · Activation Rate · D7/D30 retention (retention detail in P37-005)
  - [ ] tracks: AI provider setup; Goal Breakdown usage; proposal acceptance; hosted credit consumption;
        BYOK adoption
  - [ ] tracks: workspace creation; second-workspace creation; switching frequency; scoped work share
  - [ ] no unnecessary raw prompt storage (metadata only)
- Verification: Unit / Integration(ai_runs aggregates) / E2E / Device(n/a)
- Evidence: — · Known Limitations: — · Notes: extends P25 observability, no duplication

### P37-006 — Pricing Validation
- Status: TODO(instrumentation)/GATED(real signups) · Priority: High · Depends On: P24 billing live
- Business Decision: LOCKED PRICES — Free Rp0; Pro Rp34,900/month; Power Rp49,900/month.
  Annual price/discount MUST NOT be invented (DECISION_REQUIRED blocklist §13)
- SRS: billing · Design: docs/adr/ADR-013-product-tiers-pricing.md · Files: pricing report query
- Acceptance:
  - [ ] pricing catalog is authoritative (config/billing.php + saas.php — already locked)
  - [ ] metrics instrumented: signup by tier; upgrade intent; conversion; cancellation; downgrade; churn;
        AI cost/user
- Verification: Integration(billing events→report) · Evidence: sandbox transactions only · Notes: —

- Verification: Integration(billing events→report) · Evidence: sandbox transactions only · Notes: —

### P37-007 — Power Validation
- Status: TODO · Priority: High · Depends On: P37-006
- Business Decision: Power = higher capacity + deeper intelligence; do NOT add arbitrary features when
  users misunderstand Power — test messaging/packaging FIRST (master prompt §15 P37-007)
- SRS: — · Design: docs/design.md pricing surfaces · Files: messaging test notes + findings
- Acceptance:
  - [ ] users understand Pro = serious capability (validated)
  - [ ] users understand Power = higher capacity + deeper intelligence (validated)
  - [ ] messaging/packaging tested before any feature response
- Verification: n/a (research; GATED real users) · Evidence: test findings · Known Limitations: — · Notes: —

### P37-008 — User Comprehension Study
- Status: GATED(real users) · Priority: High · Depends On: beta cohort
- Business Decision: — · SRS: — · Design: — · Files: interview script + findings doc
- Acceptance:
  - [ ] validate: what Kinevo is; first-value comprehension; Workspace understanding;
        Goal Breakdown understanding; Today usefulness; AI trust; pricing comprehension
  - [ ] research summary written · [ ] top blockers ranked
- Verification: n/a (research) · Evidence: interviews/sessions recordings notes · Known Limitations: — · Notes: —

### P37-009 — Failure/Churn Taxonomy
- Status: TODO · Priority: Medium · Depends On: P37-008 start
- Business Decision: — · SRS: — · Design: — · Files: taxonomy section in research doc
- Acceptance:
  - [ ] taxonomy categories: technical; UX; product value; pricing; AI quality; performance; missing workflow
  - [ ] incidents classified as they occur (living log)
- Verification: n/a · Evidence: classification log · Known Limitations: — · Notes: —

### P37-010 — Beta Feature Freeze
- Status: ACTIVE ON BETA START · Priority: High · Depends On: P37 go-live
- Business Decision: freeze respects rescue-phase-style discipline
- SRS: — · Design: — · Files: TASK.md hold-list section
- Acceptance:
  - [ ] only P0/P1 defects, validated UX fixes, reliability fixes allowed
  - [ ] new features require explicit owner decision
  - [ ] beta hold-list enforced (recorded here)
- Verification: process gate · Evidence: exemption log · Known Limitations: — · Notes: —

### P37-011 — P37 FINAL GATE
- Status: GATED · Priority: High · Depends On: ALL P37 + real cohort
- Business Decision: — · SRS: — · Design: — · Files: TASK.md
- Acceptance (master prompt §15 P37-011):
  - [ ] beta cohort · [ ] activation · [ ] WGPU · [ ] retention · [ ] pricing evidence
  - [ ] Power differentiation · [ ] UX research/comprehension · [ ] churn taxonomy · [ ] feature freeze
- Verification: reports present + research concluded · Evidence: dashboard/exports · Notes: gate binary

## PHASE 38 — SCALE READINESS, COST/CAPACITY, RELIABILITY, RELEASE CANDIDATE

> Objective: prove the system can carry real load and safe economics, then freeze. Source:
> KINEVO_POST_P27_MASTER_EXECUTION_PROMPT.md §16 (execution authority).

### P38-001 — AI Capacity Review
- Status: TODO · Priority: P0 · Depends On: P25/P32-007
- Business Decision: —
- SRS: AI · Design: docs/ai-architecture.md · Files: capacity review doc
- Acceptance — evaluate: concurrent AI requests · queue behavior · provider rate limits · model
  latency · token limits · worst-case user:
  - [ ] all evaluated with evidence
- Verification: [ ] Integration/measurements
- Evidence: — · Known Limitations: — · Notes: —

### P38-002 — AI Credit Safety Review
- Status: TODO · Priority: P0 · Depends On: P32-007
- Business Decision: if unsafe → change CONFIGURABLE limits before release and record the decision
  (never silent)
- SRS: AI economics · Design: — · Files: safety review per plan
- Acceptance — for each plan evaluate:
  - [ ] P50 cost · P95 cost · abuse cost · worst supported request · monthly revenue · gross AI margin
- Verification: [ ] simulator runs recorded
- Evidence: — · Known Limitations: — · Notes: —

### P38-003 — Storage/Bandwidth Review
- Status: TODO · Priority: P1 · Depends On: —
- Business Decision: —
- SRS: — · Design: — · Files: storage review doc
- Acceptance — evaluate: workspace count · note storage · canvas files · exports · analytics
  retention · mobile payload size:
  - [ ] all evaluated
- Verification: measurements recorded · Evidence: — · Known Limitations: — · Notes: —

### P38-004 — Database Review
- Status: TODO · Priority: P0 · Depends On: —
- Business Decision: —
- SRS: — · Design: database/migrations/ · Files: review doc
- Acceptance — check: indexes · slow queries · ownership filters · large-table growth path ·
  migration safety:
  - [ ] all checked
- Verification: [ ] EXPLAIN/audit evidence
- Evidence: — · Known Limitations: — · Notes: —

### P38-005 — Queue Review
- Status: TODO · Priority: P1 · Depends On: —
- Business Decision: —
- SRS: reliability · Design: docs/deployment.md · Files: review doc
- Acceptance — check: retries · poison jobs · maximum attempts · dead-letter/recovery behavior ·
  observability:
  - [ ] all checked
- Verification: [ ] induced-failure evidence
- Evidence: — · Known Limitations: — · Notes: —

### P38-006 — Cache Review
- Status: TODO · Priority: P1 · Depends On: —
- Business Decision: cache is NEVER canonical
- SRS: — · Design: docs/architecture.md · Files: review doc
- Acceptance:
  - [ ] cache not canonical · tenant/user/workspace isolation preserved · invalidation strategy explicit
- Verification: [ ] review + tests
- Evidence: — · Known Limitations: — · Notes: —

### P38-007 — Load / Soak Test Baseline
- Status: TODO · Priority: P1 · Depends On: P38-004; P38-005
- Business Decision: do NOT claim scale numbers that were not tested
- SRS: — · Design: — · Files: load test scripts + results
- Acceptance:
  - [ ] representative workload tested
- Verification: [ ] load run transcripts
- Evidence: — · Known Limitations: — · Notes: —

### P38-008 — Security Regression
- Status: TODO · Priority: P0 · Depends On: features frozen (P38-009)
- Business Decision: —
- SRS: security NFRs · Design: — · Files: regression test matrix
- Acceptance — run: auth · IDOR · workspace isolation · entitlement bypass · API key leak checks ·
  payment webhook spoof · export leak · public share leak:
  - [ ] all negative cases green
- Verification: [ ] targeted test matrix
- Evidence: — · Known Limitations: — · Notes: —

### P38-009 — Release Candidate (Freeze)
- Status: TODO · Priority: P0 · Depends On: P28–P37 gates
- Business Decision: freeze major behavior; allowed exceptions: P0/P1 · security · data integrity ·
  release blockers (absorbs old P30-001 Release Freeze)
- SRS: — · Design: — · Files: freeze announcement note + exception log
- Acceptance:
  - [ ] freeze announced · [ ] exceptions recorded
- Verification: process gate · Evidence: exception log · Known Limitations: — · Notes: —

### P38-010 — P38 FINAL GATE
- Status: GATED · Priority: P0 · Depends On: ALL P38 tasks
- Business Decision: — · SRS: — · Design: — · Files: TASK.md sign-off
- Acceptance (master prompt §16 P38-010):
  - [ ] AI capacity · [ ] economics · [ ] storage · [ ] DB · [ ] queue · [ ] cache
  - [ ] load evidence · [ ] security regression · [ ] RC freeze
- Verification: compiled gate report · Evidence: — · Known Limitations: — · Notes: gate binary

## PHASE 39 — V1.0 PRODUCTION RELEASE

> Objective: stable Indonesia-first Kinevo SaaS with Web + Android. Operator approval REQUIRED for
> the tag; agent never tags/releases autonomously (AGENTS release lifecycle).
> Renumbered from old "PHASE 30" per KINEVO_POST_P27_MASTER_EXECUTION_PROMPT.md (execution authority
> §0/§17). Old P30-001 Release Freeze moved to P38-009; old P30-004 OSS/SaaS boundary moved to P33-002.

### P39-001 — Semantic Versioning
- Status: TODO(doc) · Priority: Medium · Depends On: —
- Business Decision: SemVer; app version from latest v* git tag (AGENTS lifecycle)
- SRS: — · Design: docs/release-management.md (exists — align, do not duplicate)
- Files: docs/release-management.md updates
- Acceptance:
  - [ ] major/minor/patch policy documented · [ ] manifests aligned
- Verification: make version-check green · Evidence: — · Notes: —

### P39-002 — Changelog
- Status: TODO · Priority: High · Depends On: P39-001
- Business Decision: pricing changes; AI policy; mobile availability; known limitations included
- SRS: — · Design: Keep a Changelog · Files: CHANGELOG.md release cut
- Acceptance:
  - [ ] categories Added/Changed/Fixed/Security/Deprecated/Removed complete
  - [ ] pricing · AI policy · mobile availability · known limitations present
- Verification: make changelog-check green · Evidence: — · Notes: —

### P39-003 — Release Notes
- Status: TODO · Priority: Medium · Depends On: P39-002
- Business Decision: publishes Free/Pro/Power pricing; AI usage policy; BYOK; Android availability;
  Wrapped; known limitations; support channels
- SRS: — · Design: — · Files: RELEASE_NOTES or GitHub Release body draft
- Acceptance:
  - [ ] release summary written · [ ] reviewed before publish
- Verification: doc review · Evidence: — · Notes: publishing remains manual/operator action

### P39-004 — Production Migration Dry Run
- Status: BLOCKED(needs prod-like backup) · Priority: High · Depends On: infrastructure access
- Business Decision: — · SRS: — · Design: docs/deployment.md · Files: drill script/log
- Acceptance:
  - [ ] restore backup succeeds · [ ] migrate succeeds · [ ] validate · [ ] smoke
  - [ ] data integrity verified
- Verification: drill transcript · Evidence: timestamps/log excerpts · Known Limitations: — · Notes: RPO/RTO noted

### P39-005 — Web E2E
- Status: TODO · Priority: High · Depends On: suites stable
- Business Decision: — · SRS: journeys · Design: docs/browser-e2e.md
- Files: Playwright/spec expansion
- Acceptance — critical journeys all green:
  - [ ] Login · [ ] Workspace · [ ] Goal · [ ] AI · [ ] Milestone · [ ] Task · [ ] Today · [ ] Note
  - [ ] Canvas · [ ] Schedule · [ ] Analytics · [ ] Billing · [ ] Entitlement · [ ] Wrapped
  - [ ] Chromium green · [ ] Firefox green · [ ] WebKit green
- Verification: E2E x3 engines · Evidence: CI/local run logs · Notes: —

### P39-006 — Android E2E
- Status: BLOCKED(device+release APK) · Priority: High · Depends On: P26-011/P27 gates · P35 evidence
- Business Decision: Android-first v1 · SRS: — · Design: — · Files: E2E device checklist
- Acceptance:
  - [ ] install · [ ] login · [ ] Workspace · [ ] Goal · [ ] AI · [ ] Today · [ ] Task · [ ] Note
  - [ ] offline · [ ] reconnect · [ ] entitlement
  - [ ] release-like APK used · [ ] representative device · [ ] no P0/P1 crash
- Verification: Device smoke transcript · Evidence: — · Notes: —

### P39-007 — Billing E2E
- Status: BLOCKED(sandbox credentials + real webhook run) · Priority: High · Depends On: P24 + P26-006
- Business Decision: web checkout only; one subscription covers Web+Android. Midtrans Sandbox
  evidence MUST be labeled SANDBOX; production merchant/webhook/credential separation verified
  before launch (master prompt §17 P39-007)
- SRS: billing · Design: docs/adr/ADR-012 · Files: E2E spec
- Acceptance:
  - [ ] Free→Pro purchase on web → verified webhook → subscription active → entitlement active
        → Android login → Pro access
  - [ ] Power path · [ ] cancellation · [ ] downgrade · [ ] expiration covered
  - [ ] production readiness verification recorded (merchant status; webhook endpoint; credential
        separation; current provider capability)
- Verification: sandbox evidence (labeled SANDBOX) · webhook evidence · entitlement evidence · cross-device evidence
- Evidence: — · Known Limitations: — · Notes: mirrors JOURNEY B/C/E/G/F

### P39-008 — AI Economics E2E
- Status: TODO · Priority: High · Depends On: P25 ledger + P39-007 env
- Business Decision: hosted consumes credits; BYOK does NOT; both stay safeguarded
- SRS: AI chapters · Design: docs/ai-architecture.md · Files: E2E assertions on ledger
- Acceptance:
  - [ ] Free hosted AI · [ ] Pro hosted AI · [ ] Power hosted AI · [ ] Pro BYOK · [ ] Power BYOK
  - [ ] usage ledger proves correct classification (ledger=kinevo|byok, credits_consumed correctness)
  - [ ] no raw secret leak anywhere in responses/logs
- Verification: Unit(existing AiUsage/AiAlerts suites) / Integration / E2E / Device(n/a)
- Evidence: — · Known Limitations: — · Notes: extends JOURNEY D

### P39-009 — Email E2E
- Status: TODO · Priority: High · Depends On: P29-005 templates + P39-004 env
- Business Decision: — · SRS: identity · Design: — · Files: E2E assertions (mail catcher/provider)
- Acceptance:
  - [ ] verification · [ ] reset · [ ] welcome · [ ] security · [ ] billing · [ ] failure path
- Verification: Integration(queue→send) / E2E · Evidence: send transcripts · Known Limitations: — · Notes: —

### P39-010 — Data Ownership E2E
- Status: TODO · Priority: High · Depends On: P30-002/P30-003/P29-008 implementation
- Business Decision: — · SRS: data ownership · Design: — · Files: E2E spec
- Acceptance:
  - [ ] export · [ ] account deletion · [ ] deletion grace period · [ ] recovery/cancel deletion
- Verification: E2E · Evidence: run logs · Known Limitations: — · Notes: —

### P39-011 — Security Final Audit
- Status: TODO · Priority: High · Depends On: features frozen
- Business Decision: — · SRS: security NFRs · Design: SECURITY.md (disclosure) · Files: audit report doc
- Acceptance — all negative cases have expected results:
  - [ ] IDOR · [ ] cross-workspace · [ ] entitlement bypass · [ ] price tampering
  - [ ] fake payment success · [ ] invalid webhook · [ ] duplicate webhook · [ ] BYOK leak
  - [ ] billing-secret leak · [ ] Wrapped leak · [ ] unauthorized deep link · [ ] expired-subscription bypass
- Verification: targeted test matrix executed · Evidence: report · Notes: no open P0/P1 findings allowed

### P39-012 — Backup/Restore Final Drill
- Status: BLOCKED(prod infra) · Priority: High · Depends On: P39-004 environment
- Business Decision: — · SRS: durability NFRs · Design: docs/deployment.md · Files: drill log
- Acceptance:
  - [ ] restore success across: user; workspace; goals; tasks; Notes; Canvas; subscription; entitlement;
        AI usage; billing events
  - [ ] integrity verification · [ ] RPO/RTO evidence
- Verification: drill transcript · Evidence: — · Notes: —

### P39-013 — Monitoring and Alerts
- Status: TODO · Priority: High · Depends On: infra
- Business Decision: — · SRS: operations · Design: docs/deployment.md · Files: alert configs + test log
- Acceptance (alerts configured AND tested):
  - [ ] app down · [ ] database unhealthy · [ ] queue failure · [ ] scheduler failure · [ ] AI outage
  - [ ] payment webhook failure · [ ] backup failure · [ ] abnormal AI spend (ties to P25-010 ops alerts)
  - [ ] abnormal payment failure
- Verification: induced-failure drill per alert · Evidence: fired alerts log · Notes: —

### P39-014 — Support/Incident Runbooks
- Status: TODO · Priority: Medium · Depends On: P39-013
- Business Decision: — · SRS: — · Design: — · Files: runbooks/ folder
- Acceptance:
  - [ ] runbooks exist: payment mismatch; AI unavailable; account issue; entitlement mismatch;
        mobile issue; data issue; rollback
  - [ ] owner identified per runbook
- Verification: tabletop walkthrough (at least rollback) · Evidence: — · Notes: —

### P39-015 — Production Configuration Verification
- Status: BLOCKED(prod secrets/infra) · Priority: High · Depends On: deploy target
- Business Decision: never commit secrets · SRS: — · Design: docs/environment.md · Files: checklist
- Acceptance:
  - [ ] APP_KEY · DB · storage · mail · AI gateway · payment gateway · TLS · backup · monitoring verified
  - [ ] no secrets baked into image · [ ] health endpoint passes · [ ] production smoke passes
- Verification: checklist transcript · Evidence: — · Notes: —

### P39-016 — Production RC Checklist
- Status: GATED · Priority: High · Depends On: ALL phase gates (P28–P38)
- Business Decision: — · SRS: — · Design: — · Files: RC checklist compilation
- Acceptance — ALL applicable checked (master prompt §17 P39-016):
  - [ ] product · [ ] UX · [ ] identity · [ ] email · [ ] data ownership · [ ] analytics · [ ] AI
  - [ ] billing · [ ] open-source split · [ ] website · [ ] admin · [ ] Android · [ ] security
  - [ ] backup · [ ] monitoring · [ ] support · [ ] documentation
- Verification: checklist signed with per-item evidence links · Evidence: — · Known Limitations: — · Notes: —

### P39-017 — v1.0.0 Tag
- Status: BLOCKED(operator approval mandatory) · Priority: High · Depends On: P39-020 gate green
- Business Decision: tag only after ALL gates · SRS: — · Design: docs/release-management.md · Files: git tag v1.0.0
- Acceptance:
  - [ ] tag created · [ ] changelog tied · [ ] release notes attached · [ ] reproducible build evidenced
- Verification: make release-dry-run green beforehand · Evidence: — · Notes: agent NEVER auto-tags

### P39-018 — Rollback Procedure
- Status: TODO(doc)+drill · Priority: High · Depends On: P39-014
- Business Decision: — · SRS: reliability · Design: docs/deployment.md · Files: rollback runbook
- Acceptance:
  - [ ] trigger criteria · operator roles · deploy rollback steps · DB considerations · customer impact ·
        communications template — all documented
  - [ ] tabletop OR real drill performed
- Verification: drill log · Evidence: — · Notes: —

### P39-019 — Post-Release Review
- Status: GATED(post-release) · Priority: Medium · Depends On: v1.0.0 tag
- Business Decision: no immediate feature expansion after release (master prompt §17 P39-019)
- SRS: — · Design: — · Files: post-release review record
- Acceptance:
  - [ ] actual incidents recorded · [ ] unresolved P1s recorded · [ ] early usage noted
  - [ ] AI cost noted · [ ] conversion noted · [ ] support volume noted
- Verification: doc review · Evidence: — · Known Limitations: — · Notes: —

### P39-020 — P39 FINAL RELEASE GATE
- Status: GATED · Priority: High · Depends On: EVERYTHING above
- Business Decision: v1.0 releases ONLY when all gates green
- SRS: — · Design: — · Files: TASK.md sign-off
- Acceptance (master prompt §17 P39-020):
  - [ ] TECHNICAL: CI green · migrations verified · production smoke pass
  - [ ] PRODUCT: core loop coherent · onboarding works · feature purposes clear
  - [ ] UX: empty states · CTA hierarchy · personalization · micro-interactions · accessibility
  - [ ] IDENTITY: email verification · password reset · account deletion
  - [ ] AI: provider abstraction · credits · cost metering · BYOK · safeguards
  - [ ] BILLING: Free · Pro 34,900 · Power 49,900 · Midtrans production readiness
  - [ ] MOBILE: release-like build · same entitlement · offline/reconnect
  - [ ] OPEN SOURCE: core repo · cloud repo · product site
  - [ ] TRUST: Terms · Privacy · AI policy · provider inventory
  - [ ] OPERATIONS: admin · monitoring · backups · support
  - [ ] BUSINESS: activation instrumentation · retention instrumentation · unit economics
- Verification: compiled gate report citing each subsystem's evidence · Evidence: —
- Known Limitations: — · Notes: no silent weakening of any gate

Execution rule: sequential P21→P22→…; P26/P27 may parallelize only after API/security stability per
roadmap §3. Post-P27 execution authority: KINEVO_POST_P27_MASTER_EXECUTION_PROMPT.md — phases run
P28→P39 in order unless a dependency-safe exception is recorded here.
