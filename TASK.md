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

**P28 — Product Experience Closure** — DONE 2026-08-31 (detail:
`docs/roadmap/archive/phases/P28-product-experience-closure-DONE-2026-08-31.md`).

Status akhir: **30 DONE · 0 TODO · P28-014 GATE GREEN**. Evidence:
`docs/browser-e2e.md` §P28-013, `docs/ux/interaction-states.md` (P28-011 matrix),
`docs/retention-events.md` §RET-007.

**P29 — Product & Architecture Convergence** — **EXECUTED 2026-08-31** (detail:
`docs/roadmap/active/P29-product-architecture-convergence-EXECUTED.md`).

Satu otoritas kanonik per tipe truth: Product Constitution + product model +
workspace model + commercial model (`docs/product/`), SRS v3.0.0, domain model,
architecture CURRENT/TARGET, traceability 10 flows (`docs/requirements/`), IA +
design system + interaction-states + content + motion + accessibility
(`docs/ux/`), marketing site spec + claims registry + asset provenance
(`docs/marketing/`). Stitch export 131 unit diklasifikasi penuh (design evidence
only; raw export LOCAL_REFERENCE_ONLY). TARGET_DECISION_REGISTER dimigrasi lalu
diarsipkan. Dokumen lawas diarsipkan (`docs/archive/`); ownership: docs index +
README + AGENTS + P30–P39 refined.

## Active gate

**P29 gate — GREEN** (evaluated 2026-08-31): semua deliverable kanonik ada;
CURRENT/TARGET eksplisit; tidak ada kontradiksi HIGH tersisa; doc-link check
green; tidak ada implementasi fitur (nol perubahan kode produk — hanya
doc-pointer/code-comment disclaimers). Tag milestone P28/P29 menunggu keputusan
owner (AGENTS.md melarang agent men-tag tanpa instruksi eksplisit).

## Next phase

**P30 — Runtime, Identity & Communication Foundation**
(`docs/roadmap/planned/P30-runtime-identity-communication.md`) — JANGAN dimasuki
otomatis. Rekomendasi scope awal: FrankenPHP+Octane benchmark & rollback drill,
EmailProvider abstraction (Resend), identitas/verifikasi.

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
- R0 documentation & roadmap rebaseline — lihat `docs/roadmap/rebaseline-2026-08.md`.
- **P28 closure + P28-014 gate GREEN** — `24a608a`/`8776ec8`/`0d44bde` (implementasi, evidence, roadmap).
- **P29 convergence executed** — product/requirements/architecture/UX/marketing
  kanonik + Stitch reconciliation + disposisi dokumen (detail:
  `docs/roadmap/active/P29-product-architecture-convergence-EXECUTED.md`).

## Known blockers

Tidak ada blocker P0/P1. Keputusan terbuka non-blocking: kuota AI produksi +
parameter entitlement Power (P33); tag milestone P28/P29 (owner decision);
rename label IA (Calendar→Month, Analytics→Progress) + Review surface (TARGET —
task implementasi kecil pasca-konvergensi, dicatat di
`docs/ux/information-architecture.md` §2–§3).
