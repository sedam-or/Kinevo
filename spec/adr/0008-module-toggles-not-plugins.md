# ADR-0008 — "Drag and drop" means per-install module toggles, not a plugin API or builder

- Status: **Accepted** (2026-10-25) — D-01; owner chose "Both" = easy install + module toggles, with plugin API deferred.

## Context
The owner's vision is that people can "just drag and drop the features". Interpretations: (a) select and configure modules, (b) install third-party extensions, (c) visually compose screens/workflows, (d) easy installation. The evidence (`00-verdict.md` §4, §7) shows no module mechanism today, no user research about extension needs, and a single maintainer.

## Decision
Deliver (a) and (d): first-party modules compiled in, switched on/off per install by the admin (wizard step 2, Admin → Modules) with enforced boundaries (no cross-module imports; ports + events), plus a one-image, three-step setup. Literal drag-and-drop interaction is not required and not built; selection uses accessible switches. Plugin API (b) and visual builder (c) are **deferred** behind RFCs with demand evidence.

## Alternatives
| Option | For | Against |
|---|---|---|
| WASM plugin API now | third-party extensibility | large design/security surface (ABI, sandbox, permissions, schema migrations) with no demand evidence |
| No-code UI builder | strong demo value | enormous scope; unrelated to the core planning problem |
| Fixed product, no toggles | simplest | cannot offer a lean install or cloud/self-host differences |

## Consequences
- The module descriptor (`03-module-system.md` §2) is deliberately the shape a future plugin manifest would take.
- Revisit trigger: ≥ 3 independent RFCs requesting the same extension point, or maintainers wanting to ship a module out-of-tree.
