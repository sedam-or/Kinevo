---
id: T-109
title: Build the workspace picker and workspace manager
milestone: M2
kind: feature
status: todo
depends_on: [T-101, T-050, T-046, T-047]
requirements: [WS-FR-02, WS-FR-03, WS-FR-05, WS-FR-06, FE-01, UXS-02, UXS-04]
branch: m2-t109-web-workspaces-ui
budget_lines: 330
assignee: ""
---

# T-109: Build the workspace picker and workspace manager

## Goal

Users can filter Plan screens by workspace and create, rename, recolour, reorder, archive, unarchive and delete workspaces from one manager dialog.

## Context

> **Endpoints** (`spec/modules/workspaces.md` §2) as in T-101. **WS-FR-03** archived workspaces are read-only; create in them → 409 `workspace_archived`. **WS-FR-05** plan limit: `workspace_limit_reached` with `details.max`. **WS-FR-06** list endpoints take an optional `workspace_id`; absent means all non-archived workspaces; Today/Schedule are never workspace-filtered by default.
> **UX keys** (`ux-states.md` §5): `workspace.limit` (modal, Manage plan), `workspace.archived.readonly` (banner, Unarchive), `conflict.task`-style conflict card for `version_conflict`. Delete uses an alertdialog naming the workspace; `workspace_not_empty` shows the counts per kind; the default workspace shows an explanation instead of archive/delete.
> **Routes**: no new route; the picker sits in the Plan screens' header (T-110/T-111) and the manager opens as a Dialog (no drag and drop: reorder with Move up/Move down buttons, DS §7 and DS-A11Y-04).
> **Descriptors**: the `workspaces` descriptor gains no nav item; `GET /modules` lists it for `useModules()` only.
> **Colour**: nine colour names from the DDL map to tokens (`workspace-slate` … `workspace-pink`) added to `tokens.json` with contrast checks against `surface` (decorative dot plus text label, never colour alone, DS-A11Y-13).

## Scope

**In:**
- `features/workspaces/`: `useWorkspaces(includeArchived)`, mutations, `WorkspacePicker` (combobox with All workspaces option and archived toggle), `WorkspaceManager` dialog, URL search-param `workspace` shared by Plan screens via a `useWorkspaceFilter()` hook.
- Workspace colour tokens in `tokens.json` (regenerate CSS, run the contrast gate) and a small `WorkspaceDot` component with a text label.

**Out:**
- Goals and tasks screens: T-110/T-111.

## Files

| Path | Action | Notes |
|---|---|---|
| `web/src/features/workspaces/**` | create | hooks, picker, manager, tests |
| `web/src/styles/tokens.json, tokens.css, tokens.d.ts` | modify | nine colour tokens |
| `web/src/i18n/en.json, id.json, web/scripts/i18n.allow` | modify | labels |

## Interfaces

```ts
useWorkspaceFilter(): { workspaceId: string | undefined; set(id?: string): void }   // URL search param
<WorkspacePicker value onChange includeArchived /> ; <WorkspaceManager open onClose />
```

## Business rules

1. The picker lists non-archived workspaces by default; the archived toggle shows the rest with a read-only badge.
2. Archive/unarchive/delete errors map to the inline messages of the registry; `workspace_is_default` hides the destructive actions for the default workspace.
3. Reordering updates `position` through `PATCH` for the moved pair only.

## Security

- Names render as text; no HTML.
- All permission checks are server-side.

## Implementation notes

- Add the new label keys to the catalogues in both languages in the same commit (check-i18n).

## Acceptance

| # | Given / When / Then | Test name |
|---|---|---|
| AC-1 | **Given** workspaces **When** the picker renders **Then** All, then active ones in `position` order; the archived toggle adds archived ones | `workspaces_ui::ws_fr_06_picker_lists_and_filters` |
| AC-2 | **Given** a duplicate name **When** creating **Then** the field shows the `workspace_name_taken` message | `workspaces_ui::ws_fr_02_duplicate_name_inline` |
| AC-3 | **Given** the default workspace **When** the manager opens **Then** archive and delete are absent with an explanation | `workspaces_ui::default_workspace_protected` |
| AC-4 | **Given** a non-empty workspace **When** deleting **Then** the dialog shows the counts from `workspace_not_empty` | `workspaces_ui::ws_fr_04_not_empty_counts` |
| AC-5 | **Given** `workspace_limit_reached` **When** creating **Then** the `workspace.limit` modal shows with the max | `workspaces_ui::ws_fr_05_limit_modal` |
| AC-6 | **Given** an archived workspace **When** viewed **Then** the read-only banner offers Unarchive | `workspaces_ui::ws_fr_03_archived_banner` |
| AC-7 | **Given** Move up/down **When** used **Then** positions update without drag and drop and the focus stays on the moved row | `workspaces_ui::reorder_by_buttons_keeps_focus` |
| AC-8 | **Given** the colour tokens **When** the contrast gate runs **Then** exit 0 | `workspaces_ui::tst_10_workspace_tokens_pass_contrast` |

## Tests

```
pnpm --dir web typecheck
pnpm --dir web lint
pnpm --dir web test
cargo nextest run -p kinevo-mod-workspaces -p kinevo-mod-goals -p kinevo-mod-tasks --test descriptor
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
