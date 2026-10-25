---
id: T-101
title: Implement the workspaces endpoints, events and the entitlement port
milestone: M2
kind: feature
status: todo
depends_on: [T-100, T-017, T-019, T-014, T-012]
requirements: [WS-FR-02, WS-FR-03, WS-FR-04, WS-FR-05, WS-FR-06, SEC-AC-12, API-14, DOM-25]
branch: m2-t101-workspaces-endpoints
budget_lines: 400
assignee: ""
---

# T-101: Implement the workspaces endpoints, events and the entitlement port

## Goal

Users can list, create, rename, reorder, archive, unarchive and delete workspaces with the rules of the spec; plan limits are consulted through a port that is unlimited when billing is off.

## Context

> **Endpoints** (`spec/modules/workspaces.md` §2): `GET /workspaces?include_archived=` (page ordered by `position, id`); `POST /workspaces {name, color?, position?}` (201; `workspace_name_taken`, `workspace_limit_reached`, validation); `GET /workspaces/{id}`; `PATCH /workspaces/{id}` (If-Match; `version_conflict`, `workspace_name_taken`); `POST /workspaces/{id}/archive {base_version}` (`workspace_is_default`, `version_conflict`); `POST /workspaces/{id}/unarchive {base_version}` (`workspace_name_taken`); `DELETE /workspaces/{id}` (If-Match; `workspace_not_empty` with `details.counts`, `workspace_is_default`).
> **WS-FR-02**: names unique among non-archived workspaces per account, case-insensitive. **WS-FR-03**: archiving hides the workspace; its goals and tasks remain; archived workspaces are read-only: creating goals/tasks/notes in them → 409 `workspace_archived` (consumer modules check through `WorkspaceAccess`). **WS-FR-04**: delete only when empty (counts from consumer modules through `WorkspaceUsagePort`). **WS-FR-05**: unlimited on self-hosted; with billing on, the plan's `max_workspaces` applies to non-archived workspaces via `EntitlementPort` (`unlimited` when billing is off). **WS-FR-06**: workspace-scoped list endpoints take optional `workspace_id`.
> **Validation**: `name` short text 1–80, NFC, no leading/trailing space; `color` enum of nine values; `position` int 0–10000.
> **Events**: `WorkspaceCreated`, `WorkspaceArchived`, `WorkspaceDeleted` (ids only), written through the outbox in the same transaction; audit not required.
> **Concurrency**: `If-Match`/`base_version` optimistic locking; `version` increments on every change (contracts).
> **SEC-AC-12**: a foreign workspace id in any create call → 404 (RLS plus FK).
> **Ports to add in `kinevo-ports`** (this task): `WorkspaceAccess` (`require_writable(tx, workspace_id) -> Result<WorkspaceInfo, AppError>` → `not_found` or `workspace_archived`; `default_workspace(tx)`), `WorkspaceUsagePort` (`counts(tx, workspace_id) -> UsageCounts { goals, tasks, notes, canvases }`; consumer modules implement it; the workspaces module calls every registered implementation), `EntitlementPort` (`value(account, key) -> Entitlement::{Unlimited, Limit(i64)}`; default implementation `AlwaysUnlimited`). Fakes behind `testing`.

## Scope

**In:**
- Repositories, use cases, handlers and route specs for the seven endpoints; ordering and reorder through `position` updates.
- The three ports with fakes, plus the real `WorkspaceAccess` implementation exported by this crate for consumers.
- `workspace.limit` check in create/unarchive through `EntitlementPort`.
- Outbox events and DTOs with OpenAPI derives (regenerate `contracts/openapi.json` and `web/src/api/schema.d.ts`).

**Out:**
- Consumer-side `WorkspaceUsagePort` implementations: goals (T-103), tasks (T-107); notes/canvases in their milestones.
- UI: T-109.

## Files

| Path | Action | Notes |
|---|---|---|
| `crates/kinevo-ports/src/{workspace,entitlement}.rs` | create | ports and fakes |
| `crates/kinevo-mod-workspaces/src/{domain,app,infra,http}/**` | create | feature code |
| `crates/kinevo-mod-workspaces/tests/{workspaces_api,rules}.rs` | create | AC-1…AC-9 |
| `contracts/openapi.json, web/src/api/schema.d.ts` | modify | regenerated |

## Interfaces

```rust
#[async_trait] pub trait WorkspaceAccess<T: Send>: Send + Sync { async fn require_writable(&self, tx: &mut T, id: WorkspaceId) -> Result<WorkspaceInfo, AppError>; async fn default_workspace(&self, tx: &mut T) -> Result<WorkspaceId, AppError>; }
#[async_trait] pub trait WorkspaceUsagePort<T: Send>: Send + Sync { async fn counts(&self, tx: &mut T, id: WorkspaceId) -> Result<UsageCounts, AppError>; }
#[async_trait] pub trait EntitlementPort: Send + Sync { async fn value(&self, account: AccountId, key: EntitlementKey) -> Result<Entitlement, AppError>; }
```

## Business rules

1. The default workspace cannot be archived or deleted but can be renamed.
2. Unarchive re-checks name uniqueness among active workspaces and the plan limit.
3. Delete returns the per-module counts that block it.
4. Reordering is by `position` values the client sends via `PATCH`; the server does not renumber others.

## Security

- All SQL runs in `Tx::begin(account)`; foreign ids give 404.
- Mass assignment (`account_id`, `is_default`, `version`, `id`, `archived_at` in `PATCH`) → 422 `unknown_field` (`deny_unknown_fields`).
- Names are plain text; rendered escaped by clients.

## Implementation notes

- `EntitlementPort` real implementation arrives with `billing` (M9); `kinevo-app` wires `AlwaysUnlimited` now.
- Use the shared `Page<T>` and cursor helpers from M1.

## Acceptance

| # | Given / When / Then | Test name |
|---|---|---|
| AC-1 | **Given** the default workspace **When** archived or deleted **Then** 409 `workspace_is_default`; renaming works | `workspaces_api::ws_fr_01_default_cannot_archive_or_delete` |
| AC-2 | **Given** names `Thesis` and `thesis` **When** creating the second **Then** 409 `workspace_name_taken` | `workspaces_api::ws_fr_02_case_insensitive_unique` |
| AC-3 | **Given** an archived workspace **When** a consumer calls `require_writable` **Then** `workspace_archived` | `rules::ws_fr_03_archived_is_read_only` |
| AC-4 | **Given** a workspace with fake usage counts **When** deleting **Then** 409 `workspace_not_empty` with `details.counts`; empty deletes | `workspaces_api::ws_fr_04_delete_only_empty` |
| AC-5 | **Given** an entitlement limit of 2 **When** creating a third active workspace **Then** `workspace_limit_reached`; with `Unlimited` it succeeds | `rules::ws_fr_05_limit_via_entitlement_port` |
| AC-6 | **Given** a stale `If-Match` **When** patching **Then** 409 `version_conflict` | `workspaces_api::patch_version_conflict` |
| AC-7 | **Given** another account's workspace id **When** read or changed **Then** 404 | `workspaces_api::sec_ac_12_foreign_id_not_found` |
| AC-8 | **Given** `PATCH` with `is_default` **When** sent **Then** 422 `unknown_field` | `workspaces_api::mass_assignment_rejected` |
| AC-9 | **Given** create, archive and delete **When** done **Then** the three events are in the outbox with ids only | `workspaces_api::events_emitted_with_ids_only` |

## Tests

```
cargo nextest run -p kinevo-mod-workspaces --features kinevo-db/testing   # DB tests environment-limited without Docker/PostgreSQL
just check
```

## Definition of done

- [ ] Every acceptance criterion has its named test, and it passes (or is marked environment-limited with the exact command not run).
- [ ] `just check` passes locally.
- [ ] Only the files listed under *Files* changed; diff within budget.
- [ ] Report below is filled in.

## Report

_Filled in by the implementer (WORKFLOW.md §4)._

## Questions

_None yet._
