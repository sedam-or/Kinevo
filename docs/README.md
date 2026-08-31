# Kinevo Documentation — Authority Index

> One discoverable entry point. Future agents: start here, do not guess.
> P29 convergence (2026-08-31) established ONE canonical authority per truth
> type. Hierarchy (highest wins): legal/security constraints → Product
> Constitution → SRS → domain/architecture → UX/IA/design → commercial →
> ADRs → roadmap/TASK → implementation evidence. Pre-P29 documents are archived
> under `docs/archive/` (evidence, not authority).

## Where do I find…

| Truth type | Canonical location | Notes |
|---|---|---|
| **What Kinevo is** | `product/product-constitution.md` | identity, core loop, principles, non-goals, AI authority, ownership |
| **Vision/strategy** | `product/vision-mission.md` | vision, mission, ICP, differentiation, North Star |
| **Product model** | `product/product-model.md` + `product/workspace-model.md` | entities of the loop; locked workspace semantics |
| **Commercial policy** | `product/commercial-model.md` | plans, price hypotheses, BYOK, AI-allowance open decision (P33) |
| **Execution authority / roadmap** | `roadmap/KINEVO_MASTER_EXECUTION_PROGRAM.md` | master program (R0 → P39); STOP-at-boundary policy |
| **Current tasks / control plane** | root `TASK.md` | slim control plane ONLY — detail in roadmap/ |
| **Active phase detail** | `roadmap/active/` | the ONE detailed current-phase authority |
| **Planned phases** | `roadmap/planned/P29…P39-*.md` | microtasks authored at activation |
| **Roadmap history / rebaseline** | `roadmap/rebaseline-2026-08.md` | old→new mapping + doc migration matrix |
| **Historical tasks/prompts** | `roadmap/archive/**`, `archive/**` | verbatim evidence, never rewritten |
| **Requirements** | `SRS.md` | **v3.0.0** canonical; v2.0.0 archived at `archive/srs-v2.0.0-2026-08-31.md` |
| **Requirements traceability** | `requirements/requirements-traceability.md` | 10 core flows: requirement→domain→API→UX→evidence |
| **Architecture** | `architecture.md` | CURRENT/TARGET split; provider boundaries; runtime |
| **Domain model** | `domain-model.md` | aggregates, invariants, workspace/global scope |
| **Scheduling engine** | `scheduling-engine.md` + `adr/ADR-015`, `adr/ADR-016` | deterministic authority |
| **Offline sync** | `offline-sync.md` + `adr/ADR-017` | ledger/reconciliation contract |
| **Knowledge layer** | `knowledge-layer.md` + `adr/ADR-009` | editor boundary, links, conflicts |
| **Canvas** | `adr/ADR-010`, `adr/ADR-005` | Excalidraw owns drawing only |
| **AI architecture** | `ai-architecture.md` + `adr/ADR-011` | providers, metering, safety |
| **API contract** | `api/openapi.yaml` | contract of record; `bash scripts/check-openapi.sh` |
| **UX/IA/design** | `ux/information-architecture.md`, `ux/design-system.md`, `ux/interaction-states.md`, `ux/content-design.md`, `ux/motion.md`, `ux/accessibility.md` | P29 canonical design set |
| **Stitch (design evidence)** | `ux/stitch-reference.md` + `ux/stitch-convergence-matrix.md` | local export = LOCAL_REFERENCE_ONLY; never product authority |
| **Browser evidence** | `browser-e2e.md` | living run record + P28-013 matrix |
| **Retention semantics** | `retention-events.md` | RET-007 v1 event taxonomy (semantic only; P32 instruments) |
| **Marketing** | `marketing/site-specification.md` + `marketing/claims-registry.md` + `marketing/asset-provenance.md` | every external claim cleared here |
| **Billing implementation** | `billing.md` + `billing-capability-matrix.md` + `adr/ADR-012/013` | technical authority under commercial-model.md |
| **Third-party/licenses** | `third-party/licenses.md`, `third-party/adoption-matrix.md`, `third-party/attributions.md` | check BEFORE copying external source |
| **ADRs** | `adr/` | accepted decisions are historical authority; supersession marked explicitly |
| **Frozen audits** | `audit/` (dated snapshots), `archive/` (P29-disposed docs incl. `archive/design-legacy-2026-08-31/`) | immutable; new audits get new dated files |
| **Testing strategy** | `test-strategy.md` | never weaken tests for green |
| **Ops** | `environment.md`, `deployment.md`, `release-management.md`, `compatibility.md` | secrets classification, SemVer/changelog gates |
| **Mobile** | `mobile-architecture.md` | durable mobile offline deferred to P36 (ADR-017 §2.19) |

## Governance (repository root)

`AGENTS.md` (agent contract) · `CONTRIBUTING.md` · `SECURITY.md` (disclosure, never public issues)
· `CODE_OF_CONDUCT.md` · `SUPPORT.md` · `COMMIT-PROTOCOL.md` · `CHANGELOG.md` (Keep a Changelog)
· `LICENSE` (MIT).

## Convergence history

`docs/convergence/P29_CONVERGENCE_MATRIX_2026-08-31.md` records the P29.1
reality inventory and every disposition taken (created/rewritten/merged/archived).
TARGET_DECISION_REGISTER decisions were migrated into `docs/product/*` and
`docs/architecture.md`; the register is archived at `docs/archive/`.
