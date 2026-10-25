---
id: T-402
title: Build the Canvas UI: lazy Excalidraw board, autosave, images, links and offline read-only
milestone: M8
kind: feature
status: todo
depends_on: [T-401, T-353]
requirements: [CNV-FR-03, CNV-FR-04, CNV-FR-05, CNV-FR-06, SEC-AC-38, FE-04, FE-09, FE-14, UXS-02, DS-A11Y-13, J7b]
branch: m8-t402-web-canvas
budget_lines: 420
assignee: ""
---

# T-402: Build the Canvas UI: lazy Excalidraw board, autosave, images, links and offline read-only

## Goal

Users can draw, attach images, link boards to their plan and recover from conflicts or flaky networks without losing work; offline they can still view the last loaded board.

## Context

> **Routes** (`spec/08-frontend.md` §3): `/think/canvas` (list), `/think/canvas/:id` (board). Nav contributed by the `canvas` descriptor (permission `canvas.canvas.read`). Excalidraw (`@excalidraw/excalidraw`) loads lazily on the board route only (FE-09; React 19 compatibility is verified in this task and recorded in the Report); bundle report shows the chunk.
> **Autosave (CNV-FR-05)**: debounce 3 000 ms after the last change and on blur/visibility hidden; at most one in-flight save; failures keep the local scene and show `canvas.save_failed` with retry. **Conflict (CNV-FR-04)**: a stale version shows the conflict state with **Keep mine as a copy** (creates a new canvas with the local scene) and **Use current**; no merging. **Images**: pasted/added images upload via `PUT /canvases/{id}/files/{file_key}` (key generated client-side, matching `^[A-Za-z0-9_-]{8,64}$`) before the scene referencing them is saved when possible; SVG files are refused in the UI with `canvas_file_invalid` and the server enforces the same; files are fetched with the bearer token and shown through object URLs (CNV-FR-03). **Links**: link a board to goals, milestones, tasks and notes (picker as in T-353). **Rename**: `PATCH title`.
> **Offline (CNV-FR-06)**: online-only; offline the board opens read-only from the cache if previously loaded, with banner `canvas.offline_readonly`; edits are disabled, nothing is queued. States: loading, empty list, error, `canvas.save_failed`, conflict, `canvas.offline_readonly`.
> **Journey J7b**: draw, add a PNG, reload, link to a goal, rename; offline read-only; SVG upload refused. The scene sent is Excalidraw's `elements` and the two `app_state` keys; the browser never trusts the server's `warnings` for correctness.

## Scope

**In:**
- Feature folder `web/src/features/canvas/**`, route files, i18n (en, id), MSW handlers, Vitest tests with Excalidraw mocked at the module boundary, Playwright `j7b.spec.ts` (real Excalidraw), cache persistence of the last loaded board (allow-list entry), a11y gate routes (list route; the board canvas itself is excluded from axe with a documented reason and keyboard access for list actions tested).

**Out:**
- Server changes.

## Files

| Path | Action | Notes |
|---|---|---|
| `web/src/features/canvas/**` | create |  |
| `web/src/i18n/{en,id}/canvas.json` | create |  |
| `web/src/offline/persister.ts` | modify | allow-list the canvas board key (read-only cache) |
| `web/tests/{canvas.test.tsx,e2e/journeys/j7b.spec.ts}` | create | AC-1…AC-8 |
| `web/tests/e2e/gates/a11y.spec.ts` | modify | add list route |

## Interfaces

```ts
useCanvases(filters); useCanvas(id); useSaveScene(id); useUploadCanvasFile(id); useSetCanvasLinks(id); useCreateCanvasCopy();
```

## Business rules

1. At most one save in flight; the latest local scene is saved next.
2. A failed or conflicting save never replaces the local scene.
3. Nothing is queued offline.

## Security

- Image URLs are object URLs created from authenticated fetches.
- The UI rejects SVG before upload and the server rejects it again.

## Implementation notes

- Record the Excalidraw 0.18.1 + React 19 result in the Report; if incompatible, raise a blocking question instead of pinning an older React.

## Acceptance

| # | Given / When / Then | Test name |
|---|---|---|
| AC-1 | **Given** changes **When** 3 s pass **Then** one `PUT scene` with the version is sent; no overlap of saves | `canvas::cnv_fr_05_autosave_single_inflight` |
| AC-2 | **Given** a save failure **When** it happens **Then** the local scene stays and `canvas.save_failed` offers retry | `canvas::cnv_fr_05_failure_keeps_local_scene` |
| AC-3 | **Given** a stale version **When** saving **Then** the conflict state offers a copy or the current version | `canvas::cnv_fr_04_conflict_choices` |
| AC-4 | **Given** an SVG file **When** added **Then** refused with the file-invalid message and no request | `canvas::sec_ac_38_svg_refused_client_side` |
| AC-5 | **Given** offline with a cached board **When** opened **Then** read-only with `canvas.offline_readonly` and no queue entries | `canvas::cnv_fr_06_offline_readonly` |
| AC-6 | **Given** link targets **When** saved **Then** `PUT links` is called | `canvas::links_picker` |
| AC-7 | **Given** the list route **When** axe runs **Then** no serious violations | `e2e::a11y_canvas_list_route` |
| AC-8 | **Given** J7b **When** run with real Excalidraw **Then** draw, PNG, reload, link, rename, offline read-only and SVG refusal pass | `e2e::j7b_canvas_journey` |

## Tests

```
pnpm --dir web test
pnpm --dir web check-i18n
just e2e   # environment-limited without browsers
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
