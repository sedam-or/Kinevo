# Commercial Pricing Delta — POST-P27 (owner, 2026-08-28) + D-001..D-008

> ARCHIVED 2026-08-31 (R0 documentation rebaseline). Owner pricing decisions migrated: locked prices (Free 0 / Pro 49.900 / Power 89.900) are summarized in TASK.md and docs/billing.md; D-001..D-008 all DONE. Legacy P24/P25 microtask detail preserved verbatim.
> Task IDs are immutable and preserved verbatim below. This file is historical
> evidence — NOT an execution authority. Authority: TASK.md (control plane) +
> docs/roadmap/KINEVO_MASTER_EXECUTION_PROGRAM.md.

---

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

