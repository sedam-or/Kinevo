# Kinevo Documentation — Authority Index

> One discoverable entry point. Future agents: start here, do not guess.
> Hierarchy (highest wins): legal/security constraints → Product Constitution (P29, future) →
> SRS/requirements → domain model/architecture → UX/IA/design → commercial/FinOps → ADRs →
> roadmap/TASK → implementation evidence.

## Where do I find…

| Truth type | Canonical location | Notes |
|---|---|---|
| **Execution authority / roadmap** | `roadmap/KINEVO_MASTER_EXECUTION_PROGRAM.md` | master program (R0 → P39); STOP-at-boundary policy |
| **Current tasks / control plane** | root `TASK.md` | slim control plane ONLY — detail in roadmap/ |
| **Active phase detail** | `roadmap/active/P28-product-experience-closure.md` | the ONE detailed P28 authority |
| **Planned phases** | `roadmap/planned/P29…P39-*.md` | microtasks authored at activation |
| **Roadmap history / rebaseline** | `roadmap/rebaseline-2026-08.md` | old→new mapping + doc migration matrix |
| **Historical tasks/prompts** | `roadmap/archive/**`, `archive/**` | verbatim evidence, never rewritten |
| **Requirements** | `SRS.md` | full SRS v3 reconstruction → P29 (narrow truth patches applied R0) |
| **Architecture** | `architecture.md` | full convergence → P29 (CURRENT/TARGET split then) |
| **Domain model** | `domain-model.md` | |
| **Scheduling engine** | `scheduling-engine.md` + `adr/ADR-015`, `adr/ADR-016` | deterministic authority |
| **Offline sync** | `offline-sync.md` + `adr/ADR-017` + `convergence/OFFLINE_CAPABILITY_MATRIX_2026-08-31.md` | ADR-017 is the contract |
| **Knowledge layer** | `knowledge-layer.md` + `adr/ADR-009` | |
| **Canvas** | `adr/ADR-010`, `adr/ADR-005` | Excalidraw owns drawing only |
| **AI architecture** | `ai-architecture.md` + `adr/ADR-011` | AI proposes, user decides |
| **API contract** | `api/openapi.yaml` | contract of record; `bash scripts/check-openapi.sh` |
| **UX/design** | `design.md` (authority incl. §74–103), `design-tokens.md`, `state-machine-ui.md` | full design convergence → P29 (future docs/ux/); P28-011 state matrix + RET-013 failure matrix live in `state-machine-ui.md` |
| **UX audits (living)** | `ui-audit.md`, `browser-e2e.md` | gap + run baselines |
| **Retention semantics** | `retention-events.md` | RET-007 v1 event taxonomy (semantic contract only; P32 instruments it) |
| **Brand** | `brand.md` | |
| **Commercial/billing** | `billing.md`, `adr/ADR-013`, `ai-economics/` | prices are launch hypotheses; quotas NOT locked (→ P33) |
| **Third-party/licenses** | `third-party/licenses.md`, `third-party/adoption-matrix.md`, `third-party/attributions.md` | check BEFORE copying external source |
| **ADRs** | `adr/` | accepted decisions are historical authority; supersession marked explicitly |
| **Frozen audits** | `audit/` (dated snapshots) | immutable; new audits get new dated files |
| **Convergence (active bridge)** | `convergence/TARGET_DECISION_REGISTER.md` | migrates into canonical docs in P29 |
| **Testing strategy** | `test-strategy.md` | never weaken tests for green |
| **Ops** | `environment.md`, `deployment.md`, `release-management.md`, `compatibility.md` | secrets classification, SemVer/changelog gates |
| **Mobile** | `mobile-architecture.md` | durable mobile offline deferred to P36 (ADR-017 §2.19) |
| **Implementation status** | `implementation-status.md` | living status |

## Governance (repository root)

`AGENTS.md` (agent contract) · `CONTRIBUTING.md` · `SECURITY.md` (disclosure, never public issues)
· `CODE_OF_CONDUCT.md` · `SUPPORT.md` · `COMMIT-PROTOCOL.md` · `CHANGELOG.md` (Keep a Changelog)
· `LICENSE` (MIT).

## Historical archives

- `roadmap/archive/master-prompts/` — superseded execution prompts (verbatim).
- `roadmap/archive/planning-specs/` — owner planning inputs (third-party spec, finance revision).
- `roadmap/archive/convergence/` — completed convergence registers.
- `roadmap/archive/task-legacy/` — legacy task detail (Phases 0–39 old numbering).
- `archive/` — pre-R0 archived docs (e.g. 2026-08-26 documentation inventory).
