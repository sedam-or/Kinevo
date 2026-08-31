# Rebaseline — 2026-08-31 (R0)

> Records the R0 documentation/roadmap rebaseline: phase mapping, task-ID treatment, and the
> document migration matrix. Historical numbering is never silently rewritten.

## 1. Phase mapping (old → new)

Old numbering = `KINEVO_POST_P27_MASTER_EXECUTION_PROMPT.md` blocks in the legacy TASK.md
(archived: `archive/task-legacy/legacy-phases-29-39-post-p27-plan.md`). New numbering = Master
Execution Program V3 (`../KINEVO_MASTER_EXECUTION_PROGRAM.md`).

| Old phase (post-P27 prompt) | Old purpose | New phase | Rationale | Task-ID treatment |
|---|---|---|---|---|
| PHASE 28 | Product experience audit/UX rescue/personalization/IA | **P28** (unchanged) | active phase, re-audited 21 DONE · 9 TODO · 1 GATED | P28-xxx IDs unchanged; detail moved to `active/P28-product-experience-closure.md` |
| — (not previously a phase) | — | **P29** Product & Architecture Convergence | V3 adds a dedicated documentation-convergence phase before production work | new |
| old PHASE 29 | Identity, email, recovery, security trust | **P30** Runtime, Identity & Communication | merged with runtime foundation | legacy P29-xxx microtasks retired; detail (re)created at P30 activation |
| — (not previously a phase) | — | **P31** Assets & Content Infrastructure | V3 elevates asset pipeline to its own phase | new |
| old PHASE 31 | Product intelligence, analytics, Wrapped | **P32** (event taxonomy/retention semantics; Wrapped feature deferred, evidence-driven) | instrumentation separated from feature build | same |
| old PHASE 32 | Growth, experimentation, commercial analytics | **P32** (retention/analytics) + **P33** (commercial) | split between instrumentation and commercial runtime | same |
| old PHASE 33 | OSS split, Core/Cloud boundary, website | **P34** Repository Boundary & Distribution | renumber only | same |
| old PHASE 34 | SaaS operations, admin, observability, abuse | **P35** Production Operations & Reliability | renumber only | same |
| old PHASE 35 | Android production hardening | **P36** Android Production | renumber only | same |
| old PHASE 36 | Compliance, legal/trust surfaces | **P37** Security & Privacy Gate | folded into the gate phase | same |
| old PHASE 37 | Public beta & PM validation | **P39** RC1 dogfood / burn-down | beta validation absorbed into RC stages | same |
| old PHASE 38 | Scale readiness, cost/capacity, RC | **P38** Performance & Capacity Gate | renumber only | same |
| old PHASE 39 | v1.0 production release | **P39** Release Candidate | GO/NO-GO evidence-driven; operator-approval tag preserved | same |
| Commercial pricing delta (D-001..D-008) | pricing/entitlement/AI-economics patch | completed 2026-08-28; pricing truth in `docs/billing.md` + program §1 | archive: `archive/task-legacy/commercial-pricing-delta-post-p27.md` | D-xxx preserved |

Pre-R1 renumbering (2026-08-29, still valid): old PHASE 28 (Intelligence/Wrapped) → 31 ·
old PHASE 29 (Beta/Growth) → 37 · old PHASE 30 (Release) → 39 — historical record only; V3
mapping above is current.

## 2. Document migration matrix (R0.2)

| CURRENT PATH | PURPOSE | AUTHORITY | TARGET | ACTION | REASON | REFS UPDATED |
|---|---|---|---|---|---|---|
| TASK.md (10,013 lines) | giant historical task dump | was execution authority | root TASK.md (slim control plane) | REWRITE | control-plane mandate | links to roadmap/ |
| TASK.md Phases 0–10 detail | completed legacy tasks | historical | archive/task-legacy/legacy-phases-00-10.md | MOVE (verbatim) | de-inflate root | — |
| TASK.md P11–P20 execution records | completed legacy records | historical | archive/task-legacy/legacy-execution-records-p11-p20.md | MOVE (verbatim) | de-inflate root | — |
| TASK.md Phases 21–27 detail | completed legacy tasks | historical | archive/task-legacy/legacy-phases-21-27.md | MOVE (verbatim) | de-inflate root | — |
| TASK.md P28 detail | ACTIVE phase | was authority | roadmap/active/P28-product-experience-closure.md | MOVE+CURATE | detailed phase authority | TASK.md links |
| TASK.md Phases 29–39 detail | unstarted old plan | superseded | archive/task-legacy/legacy-phases-29-39-post-p27-plan.md | MOVE (verbatim) + mapping | rebaseline | rebaseline §1 |
| TASK.md commercial delta | completed owner patch | historical | archive/task-legacy/commercial-pricing-delta-post-p27.md | MOVE (verbatim) | pricing truth lives in billing.md/ADR-013 | — |
| KINEVO_MASTER_EXECUTION_PROMPT_V3.md | owner umbrella prompt (verbatim) | superseded by program | archive/master-prompts/ | MOVE + ARCHIVE | ONE canonical program | TASK.md, program |
| KINEVO_POST_P27_MASTER_EXECUTION_PROMPT.md | old umbrella prompt | SUPERSEDED | archive/master-prompts/ | MOVE + ARCHIVE | same | ADR refs, ai-economics, implementation-status |
| KINEVO_THIRD_PARTY_ADOPTION_INTEGRATION_AND_RETENTION_UX_SPEC.md | owner planning spec | historical (decisions → ADR-014 + third-party docs) | archive/planning-specs/ | MOVE + ARCHIVE | root hygiene | ADR-014, third-party/*, design.md |
| revisi-finance.md | owner pricing scratch patch | historical (migrated → billing.md, ADR-013 note, delta archive) | archive/planning-specs/ | MOVE + ARCHIVE | root hygiene | billing.md, ai-economics, implementation-status |
| docs/convergence/PRE_CONVERGENCE_BASELINE.md | convergence blocker register | historical (all blockers RESOLVED) | roadmap/archive/convergence/ | MOVE + ARCHIVE | status artifact complete | ADR-016/017 companion refs |
| docs/convergence/DOCUMENT_DRIFT_TRIAGE.md | drift triage record | historical | roadmap/archive/convergence/ | MOVE + ARCHIVE | triage superseded by R0 | ADR-015 ref |
| docs/convergence/P28_STABILIZATION_PLAN.md | P28 execution order | merged | roadmap/active/P28-*.md §Execution order | MERGE + DELETE | one active-P28 authority | ADR-015 refs |
| docs/convergence/TARGET_DECISION_REGISTER.md | locked target decisions | KEEP_TEMPORARILY (bridge until P29 canonical docs) | stays (docs/convergence/) | KEEP | active decisions | — |
| docs/convergence/OFFLINE_CAPABILITY_MATRIX_2026-08-31.md | offline scope truth | CANONICAL_CURRENT (technical reference) | stays | KEEP | referenced by offline-sync.md/ADR-017 | — |
| docs/documentation-inventory.md | P21-era inventory (2026-08-26) | stale | docs/archive/documentation-inventory-2026-08-26.md | MOVE + ARCHIVE | superseded by docs/README.md | — |
| docs/audit/* (4 dated files) | frozen audit snapshots | FROZEN | stays | KEEP (+README) | immutability rule | docs/audit/README.md |
| docs/SRS.md | requirements | CANONICAL (narrow-patched; full v3 → P29) | stays | PATCH narrow | remove dangerous stale claims | ADR register rows now valid |
| docs/architecture.md | architecture | CANONICAL (narrow-patched; full rewrite → P29) | stays | PATCH narrow | Inertia/mobile claims stale | — |
| docs/design.md, design-tokens.md, ui-audit.md, state-machine-ui.md, brand.md | UX/design | CANONICAL until P29 design convergence (future docs/ux/) | stays | KEEP (classified) | no redesign in R0 | — |
| docs/billing.md + docs/ai-economics/ | commercial/FinOps | CANONICAL current (facts vs open decisions separated) | stays (future docs/commercial/) | KEEP | no invented decisions | path refs fixed |
| README.md | public entry point | CANONICAL | stays | REFINED | point to docs structure, remove stale claims | — |
| AGENTS.md | agent governance | CANONICAL | stays | REFINED | documentation map + control-plane rule | — |

## 3. ADR-009/010/011 dangling references (BLOCKER-DOC-01) — RESOLVED

Reconstructed from credible implementation evidence (decisions verifiably shaped the codebase;
SRS §ADR register, deployment.md, browser-e2e.md, docker-compose.yml):
- `docs/adr/ADR-009-knowledge-editor-boundary.md` (Tiptap boundary; SRS register: Accepted)
- `docs/adr/ADR-010-canvas-excalidraw-adapter.md` (Excalidraw adapter; Accepted pending spike gate → gate satisfied by implemented adapter, noted)
- `docs/adr/ADR-011-ai-provider-abstraction.md` (provider abstraction; Ollama optional; Accepted)
Each carries a provenance note: reconstructed 2026-08-31 (R0), original decision date unrecorded.
NO fabrication of history.

## 4. Verification

- doc-link checker green after moves/repairs.
- grep for old paths returns no live (non-archive) references.
- Full pre-commit protocol (audit/typecheck/build/phpstan/composer test) before commit.
