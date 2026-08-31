# Kinevo — Execution Control Plane

> TASK.md adalah **control plane** — bukan transkrip eksekusi. Detail task berada di
> `docs/roadmap/`; riwayat lengkap ada di `docs/roadmap/archive/` dan git history.
> Rekonstruksi: R0 documentation rebaseline, 2026-08-31.

## Execution authority

Eksekusi P28 closure → P39 RC mengikuti **`docs/roadmap/KINEVO_MASTER_EXECUTION_PROGRAM.md`**
(canonical master execution program, owner-issued V3). Master prompt lama diarsipkan di
`docs/roadmap/archive/master-prompts/` (riwayat, bukan otoritas). AUTO_CONTINUE_PHASES = FALSE:
satu fase terotorisasi per waktu; STOP di akhir fase; lanjut hanya dengan otorisasi owner.

## Current baseline (verified 2026-08-31)

- HEAD `main` @ `cc603b1`, tree clean, synced dengan origin.
- Stabilization epics selesai dengan evidence:
  - **ADR-015** — Effective Schedule resolution & override precedence (recurrence expansion,
    UNTIL canonical, Today/Week/Month effective landscape, Permanent Shift, One-Time Exception,
    schedule assignment history, locked-task producer; E2E journeys B/C/D/LOCK).
  - **ADR-016** — Scheduler trigger, Sync Now, draft approval lifecycle (weekly draft,
    `POST /schedule/sync`, reality-impact review, run locks, Sacred Anchor producer; E2E S1–S4).
  - **ADR-017** — Offline mutation reconciliation & operation ledger (`offline_operations`,
    `POST /sync/reconcile`, idempotent replay, optimistic conflicts, web MutationQueue drain,
    conflict UX; E2E O1–O4).
- Blockers: ES-01..05, SCHED-01, OFFLINE-01 — semua RESOLVED dengan evidence
  (`docs/roadmap/archive/convergence/PRE_CONVERGENCE_BASELINE.md`). BLOCKER-DOC-01
  (ADR-009/010/011 dangling) — RESOLVED 2026-08-31 via ADR reconstruction (R0.13).
- Verification state: backend 1125 tests · Vitest 531 · PHPStan 0 · typecheck/build/audit green.

## Current phase

**P28 — Product Experience Closure** — ACTIVE (detail: `docs/roadmap/active/P28-product-experience-closure.md`).

Status: 21 DONE · 9 TODO · 1 GATED. Eksekusi task berikutnya menunggu otorisasi eksplisit
(terakhir: R0 documentation rebaseline dijalankan sebagai P0 sebelum eksekusi fitur).

## Active gate

**P28-014 — Product Experience Baseline Gate** (bukan production launch gate): semua task P28
wajib selesai; blocker pengalaman P0/P1 selesai; golden journeys A–F green; browser matrix
Chromium+Firefox+WebKit green atau gate exception disetujui owner; accessibility green;
offline/scheduler core journeys green; state matrix koheren; tanpa defect correctness di core loop.
Saat hijau: checkpoint + tag + arsip dokumen fase P28 → STOP.

## Next phase

**P29 — Product & Architecture Convergence** (`docs/roadmap/planned/P29-product-architecture-convergence.md`)
— JANGAN dimasuki otomatis.

## Conventions

### Status vocabulary
- `TODO`: belum dimulai. · `READY`: dependency terpenuhi. · `IN_PROGRESS`: sedang dikerjakan.
- `BLOCKED`: terhenti dependency/decision/technical. · `IN_REVIEW`: menunggu verifikasi.
- `DONE`: acceptance criteria terpenuhi + evidence tersedia. · `DEFERRED`: ditunda eksplisit.
- `CANCELLED`: tidak berlaku via keputusan terdokumentasi. · `GATED`: gate fase.

### Priority vocabulary
- `P0`: wajib untuk baseline/core release. · `P1`: important, setelah P0 stabil.
- `P2`: enhancement. · `P3`: optional/future.

### Task format
```markdown
### TASK-ID — Title
- Status / Priority / Depends On / SRS / Files / Acceptance / Verification / Evidence / Notes
```

## Rules

1. **Task-ID immutability** — ID yang sudah dipublikasikan TIDAK didaur ulang; pindah dokumen
   tetap memakai ID sama; superseded ditandai `SUPERSEDED BY <ID>`; rebaseline penomoran dicatat
   di `docs/roadmap/rebaseline-2026-08.md`.
2. **Dependency rules** — task dieksekusi hanya bila dependency DONE; gate fase menunggu semua
   task fase; detail per fase di roadmap file masing-masing.
3. **Evidence policy** — label DONE tanpa evidence (test/browser/benchmark/drill) tidak berlaku.
4. **Frozen evidence** — `docs/audit/*` (dated snapshots) dan accepted ADRs tidak ditulis ulang;
   supersession ditandai eksplisit.
5. **Documentation authority** — lihat indeks otoritas: `docs/README.md`.

## Roadmap map

| Lokasi | Isi |
|---|---|
| `docs/roadmap/KINEVO_MASTER_EXECUTION_PROGRAM.md` | canonical master execution program (R0 → P39) |
| `docs/roadmap/roadmap.md` | ringkasan baseline + P29–P39 |
| `docs/roadmap/active/` | fase aktif (P28) |
| `docs/roadmap/planned/` | fase terencana (P29–P39) |
| `docs/roadmap/archive/` | riwayat fase, master prompt lama, planning spec, task legacy |
| `docs/roadmap/rebaseline-2026-08.md` | mapping old→new + matriks migrasi dokumen |
| `docs/audit/` | dated audit snapshots (FROZEN) |
| `docs/README.md` | indeks otoritas dokumentasi |

## Latest checkpoint

- `cc603b1` — Master Execution Prompt V3 adoption + P28 authorization record (2026-08-31).
- R0 documentation & roadmap rebaseline — epic ini (lihat final report di `docs/roadmap/rebaseline-2026-08.md`).
