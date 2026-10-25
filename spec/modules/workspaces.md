# Module `workspaces`

Kind: core. Depends on: `identity`. Tables: `workspaces`. Prefix: `WS`.
A workspace is a project/area container (e.g. "Thesis", "Day job", "Side project"). It groups goals,
tasks, notes and canvases. **Hard Landscape and the schedule are account-wide**: fixed commitments
and the single schedule span all workspaces (the "many projects, one life" job, PRD-02).

## 1. Permissions

`workspaces.workspace.read`, `.create`, `.update`, `.archive`, `.delete` — all held by `account_owner` and `instance_admin` (for their own data).

## 2. Endpoints

| Method / path | Success | Errors |
|---|---|---|
| `GET /workspaces?include_archived=` | 200 page, ordered by `position, id` | — |
| `POST /workspaces` `{ name, color?, position? }` | 201 | `workspace_name_taken` 409, `workspace_limit_reached` 409 (billing policy), validation 422 |
| `GET /workspaces/{id}` | 200 | `not_found` |
| `PATCH /workspaces/{id}` (If-Match) `{ name?, color?, position? }` | 200 | `version_conflict`, `workspace_name_taken` |
| `POST /workspaces/{id}/archive` `{ base_version }` | 200 | `workspace_is_default` 409, `version_conflict` |
| `POST /workspaces/{id}/unarchive` `{ base_version }` | 200 | `workspace_name_taken` |
| `DELETE /workspaces/{id}` (If-Match) | 204 | `workspace_not_empty` 409 (`details.counts`), `workspace_is_default` 409 |

## 3. Functional requirements

| ID | Requirement | Acceptance |
|---|---|---|
| WS-FR-01 | Every account has exactly one default workspace created with the account (name from message key `workspace.default_name`, locale of the account). It cannot be archived or deleted; it may be renamed. | Test; DOM-25. |
| WS-FR-02 | Names are unique among non-archived workspaces of an account, case-insensitively. | Duplicate → 409 `workspace_name_taken`. |
| WS-FR-03 | Archiving hides the workspace from default lists and from pickers; its goals/tasks remain, and scheduled tasks **keep their assignments** (the schedule is account-wide). Archived workspaces are read-only: creating goals/tasks/notes in them → 409 `workspace_archived`. | Test. |
| WS-FR-04 | Delete is allowed only for an empty workspace (no goals, tasks, notes, canvases). | Non-empty → 409 `workspace_not_empty` with counts. |
| WS-FR-05 | The number of workspaces is unlimited on self-hosted instances. When `billing` is enabled, the plan's `max_workspaces` (from `billing.plans`) applies to **non-archived** workspaces; reaching it returns 409 `workspace_limit_reached`. Entitlement lookup uses the `EntitlementPort` (returns "unlimited" when billing is off). | Tests with billing off/on. |
| WS-FR-06 | Every workspace-scoped list endpoint accepts `workspace_id` (optional; absent = all non-archived workspaces). The Today/Schedule surfaces are never workspace-filtered by default (account-wide). | Contract test. |

## 4. Validation

`name`: `short_text` 1–80, no leading/trailing space, NFC. `color`: enum from DDL. `position`: int 0–10000.

## 5. Events

Emits `WorkspaceCreated`, `WorkspaceArchived`, `WorkspaceDeleted`. Consumes none.

## 6. Abuse cases

SEC-AC-12: referencing another account's workspace id in any create (task, goal, note) → 404 `not_found` (RLS plus FK both prevent it); test per consuming module.
