# Kinevo — Workspace Model

> STATUS: AUTHORITATIVE (P29, 2026-08-31). Canonical workspace semantics under
> `product-constitution.md`. Migrates TARGET_DECISION_REGISTER decisions #2–#9
> verbatim as the locked model (register archived as evidence). Implementation
> authority for scoping rules: `docs/SRS.md` (Workspace FRs) and domain model.

## 1. What a Workspace is

A Workspace is the **contextual organization boundary** of a personal operating
system: a named area of life (Research, Work, Personal) that scopes intention and
context while reality and execution stay honest across all of them. Every owner
has exactly one default workspace (auto-provisioned at registration, unarchivable,
`EnsureDefaultWorkspaceUseCase`). Workspaces are filter/context — never silos
that fragment execution.

## 2. Locked scope semantics

| Concept | Scope | Status |
|---|---|---|
| Goals | workspace-scoped | CURRENT (migration 2026_08_26_000001) |
| Programs | workspace-scoped | CURRENT |
| Tasks | workspace-scoped | CURRENT (P19-013) |
| Notes | workspace-scoped | CURRENT (P19-014) |
| Canvases | workspace-scoped | CURRENT (P19-017) |
| Hard Landscape | **GLOBAL personal reality** — no workspace_id, ever | CURRENT (locked; register #7) |
| Today | cross-workspace by default | CURRENT (register #3) |
| Week | cross-workspace by default | CURRENT (register #4) |
| Month | cross-workspace by default | CURRENT (register #5) |
| Schedule drafts / Sync Now | cross-workspace (placement is personal reality) | CURRENT |
| Offline operation ledger | operation-scoped (target entity carries workspace) | CURRENT |
| Notifications | global with workspace context where relevant | CURRENT |
| AI contextual retrieval | workspace-isolated where workspace-owned context is used | CURRENT (providers receive only the working set's context) |

## 3. Workspace as filter/context

- **Why:** life areas stay separable for focus, while Today/Week/Month remain the
  honest cross-workspace execution surface (register #6).
- **CURRENT:** reads of scoped entities (goals/programs/tasks/notes/canvases) are
  filtered by the active workspace (`activeWorkspaceId` precedence: explicit
  deep-link > stored convenience > server default; "All workspaces" global view
  survives hydration, P19-028).
- **TARGET (MIGRATION_REQUIRED):** workspace filter layer for Today/Week/Month
  and Progress/Review surfaces. Server already accepts `?workspace_id` on
  analytics (AnalyticsController:71); web UI wiring is the remaining gap.
- Progress and Review support **global + workspace-filtered** perspectives
  (register #8/#9): global is current; the filter is TARGET.

## 4. Active-workspace authority (web/mobile)

- **CURRENT (web):** the SPA owns active-workspace selection client-side
  (`kinevo.active-workspace` localStorage + deep links), validated against the
  server list; the server resolves a fallback default for unscoped list requests
  (`ResolveWorkspaceContext`).
- **CURRENT (mobile):** same selection semantics over the OpenAPI envelope; the
  server contract (not the client) decides fallback when no context is sent.
- **TARGET:** one explicit server-side "active workspace" preference per session
  owner so web/mobile/mobile-truth drift cannot occur. Migration is
  MIGRATION_REQUIRED only if P30+ identity work touches session state; until
  then the current precedence rules are canonical and documented in SRS.
  (Register #2/#6 basis; no second source of truth is introduced.)

## 5. Invariants

1. Workspace deletion does not exist — workspaces archive; default never archives.
2. Hard Landscape, schedule placements, and effective landscape are never
   workspace-owned; adding workspace_id to them is forbidden (consistency rule).
3. Workspace-scoped entities carry `workspace_id` from creation; unassigned legacy
   rows are adopted into the default workspace once (`adoptUnassigned`).
4. AI proposals inherit the workspace of their target entity; proposals never
   cross workspaces.
5. Cross-workspace surfaces label scope explicitly when a filter is active
   (content rule: `docs/ux/content-design.md`).
