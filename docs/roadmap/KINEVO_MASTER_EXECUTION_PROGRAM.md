# Kinevo — Master Execution Program (Canonical)

> STATUS: AUTHORITATIVE execution authority (owner-issued V3, adopted 2026-08-31; R0 rebaseline
> applied). Supersedes `archive/master-prompts/KINEVO_POST_P27_MASTER_EXECUTION_PROMPT.md` and
> `archive/master-prompts/KINEVO_MASTER_EXECUTION_PROMPT_V3.md` (both archived verbatim as
> historical evidence). This document is the ONE canonical master program.

## 0. Execution contract

- Philosophy: QUALITY OVER SPEED — correctness, coherence, recoverability, security,
  accessibility, observability, maintainability, user trust before release speed.
- AUTO_CONTINUE_PHASES = FALSE. Execute the current authorized phase; at phase end: run the
  complete verification gate → final report (format §108 of archived V3) → update roadmap truth
  → coherent checkpoint → STOP. Next phase only on explicit owner authorization.
- Within one phase, bounded tasks may run sequentially while every internal gate stays green.
- Global policies that bind every phase: production definition (functional/tested/accessible/
  observable/secure/recoverable/documented/failure-tested/license-correct), test policy (never
  weaken tests for green), evidence policy (label ≠ evidence), additive migrations,
  third-party failure degradation, documentation truth at every phase end, git policy (clean
  checkpoint, no mixed concerns, never discard owner WIP).

## 1. Locked direction (do not change silently)

- **Identity**: workspace-scoped personal operating system reconciling intention, reality, and
  context into executable action. Tagline: "Kinevo — Turn intentions into execution."
- **Workspace semantics**: Goals/Programs/Tasks/Notes/Canvas workspace-scoped; Hard Landscape
  GLOBAL; Today/Week/Month cross-workspace by default; Progress/Review global + filtered;
  Notifications global with context. Never add workspace_id to Hard Landscape for consistency.
- **AI authority**: AI PROPOSES, USER DECIDES. Never authoritative scheduler logic; never silent
  mutation. Pattern: context → proposal → review → accept/edit/reject → authoritative mutation.
- **Commercial**: Free/Pro/Power. Launch-price hypotheses: Rp0 / Rp49.900 / Rp89.900 (not eternal
  truth; beta-validated). No annual plan; no default trial locked. Power = capacity/depth/history/
  intelligence/convenience — NOT team features. AI quotas NOT locked (FinOps simulation required —
  P33). BYOK Pro/Power per final policy; BYOK never consumes hosted allowance.
- **Open source/cloud**: Core (public MIT, genuinely self-hostable) / Cloud (private SaaS layer) /
  Site (public). NO repository split before P34.
- **Licensing**: Core stays MIT; third-party integration classified EMBED/HARVEST/REIMPLEMENT/
  ADAPTER+SERVICE/REFERENCE ONLY/REJECT; license uncertainty = production gate.
- **Design**: product = "Kinevo Tactile Editorial" (calm); marketing = "Kinevo Editorial
  Constructivism" (high-expression). No gradient-SaaS/glassmorphism/card-soup/confetti/3D gimmicks.
- **Runtime target (P30)**: Laravel Octane + FrankenPHP — benchmarked, isolation-tested, with
  rollback; benefit is NOT assumed proven.

## 2. R0 — Documentation & Roadmap Rebaseline (DONE 2026-08-31)

Documentation reconstruction BEFORE any further P28 feature execution. Completed: TASK.md → slim
control plane; docs/roadmap/ structure (active/planned/archive); P28 detail migrated to
`active/P28-product-experience-closure.md`; legacy task detail archived verbatim; old master
prompts + planning specs archived; rebaseline mapping recorded; ADR-009/010/011 reconstructed;
SRS/architecture narrow truth patches; docs/README.md authority index; README/AGENTS updated.
NO product feature implementation occurred during R0.

## 3. Phase roadmap

| Phase | Name | Status |
|---|---|---|
| P28 | Product Experience Closure | **ACTIVE** — `active/P28-product-experience-closure.md` |
| P29 | Product & Architecture Convergence | planned |
| P30 | Runtime, Identity & Communication Foundation | planned |
| P31 | Assets & Content Infrastructure | planned |
| P32 | Analytics, AI Observability & Retention Instrumentation | planned |
| P33 | Commercial Runtime & FinOps | planned |
| P34 | Repository Boundary & Distribution | planned |
| P35 | Production Operations & Reliability | planned |
| P36 | Android Production | planned |
| P37 | Production Security & Privacy Gate | planned |
| P38 | Production Performance & Capacity Gate | planned |
| P39 | Release Candidate | planned |

Detailed per-phase objective/scope/workstreams/dependencies/non-goals/gate:
`docs/roadmap/planned/P29…P39-*.md` (created at rebaseline; detailed microtasks are authored at
phase activation — never fabricated in advance).

Gate highlights (full criteria in each phase doc):
- **P28-014** Product Experience Baseline Gate (not a launch gate).
- **P29**: one authority per truth type; slim TASK.md; coherent hierarchy; no unresolved HIGH
  contradiction. STOP after checkpoint.
- **P30**: FrankenPHP parity + isolation + memory soak + benchmark + rollback drill; identity
  baseline; EmailProvider abstraction (Resend first; tracked delivered/bounced/complained).
- **P31**: production asset pipeline (Uppy → validation → Pic Smaller → AssetStorage → object
  storage); no large binaries embedded in Note/Canvas JSON.
- **P32**: canonical event taxonomy (from P28 semantics); OpenPanel/Langfuse via adapters; AI
  Ledger remains billing truth; redaction + degradation tests.
- **P33**: Midtrans production behavior; entitlements authoritative (Kinevo backend); AI FinOps
  simulator (P50–P99 + abuse) BEFORE quota lock; BYOK boundaries.
- **P34**: repository split (Core/Cloud/Site) with never-delete-first process + pre-split tag.
- **P35**: health/metrics/alerts/error tracking; backup RESTORE DRILL (unrestored backup ≠
  evidence); runbooks; Filament = operator control plane only.
- **P36**: Android production — secure storage (no plaintext tokens), durable mobile offline via
  ADR-017 protocol (NO second protocol), CI signed release pipeline.
- **P37**: security & privacy gate — cross-user/workspace/IDOR/replay/webhook/upload/secret tests;
  account deletion + full export working; privacy docs match implementation. No P0/P1 open.
- **P38**: benchmarked capacity envelope (launch/100/1k/10k profiles); P50/P95/P99 user-perceived
  latency; FrankenPHP soak; no unacceptable leak.
- **P39**: RC1 dogfood → bug burn-down (P0/P1 classification) → RC2 rehearsal from zero (fresh
  deploy, migration, backup/restore, rollback, email/payment/AI/storage providers) → final golden
  journeys → GO/NO-GO. No calendar-forced launch; no shipping because a date was written.

## 4. Stitch MCP policy (governance only — MCP NOT connected)

- P28: Stitch DISABLED as implementation authority.
- P29 Product Constitution/SRS/domain: DISABLED as decision authority.
- P29 Information Architecture: may be connected READ-ONLY for comparison.
- P29 Design Convergence: actively reviewed if available (workflow: canonical truth first →
  inventory frames → classify APPROVED / APPROVED_WITH_REFINEMENT / OUTDATED /
  CONFLICTS_WITH_PRODUCT / REJECT → extract visual grammar → write canonical design docs).
- Repository canonical design documentation ALWAYS wins over Stitch on conflict.
- Provenance record to create at first connection: `docs/ux/stitch-reference.md`
  (project ID placeholder only — `<INSERT_STITCH_PROJECT_ID_HERE>`; never commit credentials).

## 5. Documentation reconstruction policy

R0/P29 are explicitly authorized to rewrite/split/merge/move/rename/archive/delete documentation,
update all references (README/AGENTS/CI checks), and remove superseded requirements — leaving
exactly one canonical location per truth type. DELETE requires: content migrated + not legally
required + no live references + replacement authority exists + doc-link check green. Dated audit
snapshots and accepted ADRs are never rewritten; supersession is marked explicitly. Historical
roadmap numbering changes require an explicit old→new mapping in
`docs/roadmap/rebaseline-2026-08.md`.
