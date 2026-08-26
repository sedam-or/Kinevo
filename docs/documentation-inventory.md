# Documentation Inventory & Classification (TASK-P21-001/002)

> Audit date: 2026-08-26 · Method: full-tree inspection of `docs/`, root `*.md`,
> governance files. Authority hierarchy follows AGENTS.md.

## Classification legend
PRODUCT · ENGINEERING · CONTRIBUTOR · AGENT · OPERATIONS · HISTORICAL · TEMPORARY

## Inventory

| File | Class | Authority | Notes |
|---|---|---|---|
| docs/SRS.md | PRODUCT | **CURRENT authoritative requirements** | Disposition (P21-004): current; not historical. Referenced by ADRs/tests. |
| docs/architecture.md | ENGINEERING | current authoritative | + Workspaces §(P19) |
| docs/domain-model.md | ENGINEERING | current authoritative | |
| docs/scheduling-engine.md | ENGINEERING | current authoritative | |
| docs/knowledge-layer.md | ENGINEERING | current | |
| docs/offline-sync.md | ENGINEERING | current | |
| docs/ai-architecture.md | ENGINEERING | current (+ P18 control plane §) | |
| docs/billing.md | ENGINEERING | current (P24-038) | payment/billing ops contract |
| docs/design.md | PRODUCT | current (UX authority incl. §104) | |
| docs/design-tokens.md | ENGINEERING | current | |
| docs/state-machine-ui.md | ENGINEERING | current | R3 deliverable |
| docs/ui-audit.md | ENGINEERING | living baseline | |
| docs/browser-e2e.md | ENGINEERING | living baseline | run records |
| docs/test-strategy.md | CONTRIBUTOR | current | |
| docs/api/openapi.yaml | ENGINEERING | **contract of record** | 124 paths |
| docs/environment.md | OPERATIONS | current | secret classification |
| docs/deployment.md | OPERATIONS | current | Docker/TLS/backup/observability |
| docs/release-management.md | OPERATIONS | current | SemVer/changelog policy |
| docs/compatibility.md | OPERATIONS | current matrix | |
| docs/brand.md | PRODUCT | current (P20) | brand architecture |
| docs/implementation-status.md | ENGINEERING | living status | |
| docs/adr/* | ENGINEERING | decision records | immutable |
| docs/third-party/* | OPERATIONS | license ledger | |
| docs/archive/KINEVO_MASTER_P18-20 prompt | HISTORICAL | execution-prompt provenance | archived P21-007 |
| docs/archive/mapping-system-analysis-2026-08-25.md | HISTORICAL | analysis snapshot | archived P21-007 |
| docs/google*.html, robots.txt, sitemap.xml | OPERATIONS | site verification (Pages landing) | versioned intentionally |
| TASK.md | AGENT+CONTRIBUTOR | execution control (current) | P21-005: retained while development active |
| AGENTS.md | AGENT | current agent contract | P21-006 verified: boundaries/security/test protocol/workflow/constraints only |
| README.md | CONTRIBUTOR | entry point | accuracy re-verified in P21 gate run |
| CHANGELOG.md | PRODUCT | Keep-a-Changelog | gated by CI |
| CONTRIBUTING.md / CODE_OF_CONDUCT.md / SECURITY.md / SUPPORT.md | CONTRIBUTOR | governance | current |
| COMMIT-PROTOCOL.md | CONTRIBUTOR | commit rules | current |

## Result
No TEMPORARY files remain tracked in the tree. Historical material lives only
under `docs/archive/`. No `misc` bucket used.
