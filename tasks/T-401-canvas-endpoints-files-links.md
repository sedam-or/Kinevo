---
id: T-401
title: Implement the canvas endpoints, image files, links, reactions and the exporter
milestone: M8
kind: feature
status: todo
depends_on: [T-400, T-352]
requirements: [CNV-FR-02, CNV-FR-03, CNV-FR-04, CNV-FR-07, CNV-FR-08, MOD-06, MOD-11, SEC-AC-39, SEC-AC-40, CA-03]
branch: m8-t401-canvas-endpoints-files-links
budget_lines: 420
assignee: ""
---

# T-401: Implement the canvas endpoints, image files, links, reactions and the exporter

## Goal

Users can store, version, link and export boards and their images safely; oversized or hostile uploads are refused before they cost memory.

## Context

> **Endpoints** (`spec/modules/canvas.md` §2): `GET /canvases?workspace_id=&link_type=&link_id=` (page without scene: title, `updated_at`, `version`, `scene_bytes`); `POST /canvases {workspace_id?, title?, scene?}` (201, empty scene if omitted; `canvas_scene_invalid` 422, `canvas_too_large` 413, `workspace_archived`); `GET /canvases/{id}` (scene, `files[{file_key,mime,size_bytes}]`, `links[]`); `PATCH /canvases/{id}` (If-Match, `{title?}`); `PUT /canvases/{id}/scene` (If-Match, `{version, scene_bytes}`; `version_conflict` with `details.current_version`); `PUT /canvases/{id}/files/{file_key}` (raw image body with `Content-Type`; 201; `canvas_file_invalid` 422, `canvas_file_too_large` 413, `canvas_files_limit` 409 above 100); `GET /canvases/{id}/files/{file_key}`; `DELETE /canvases/{id}/files/{file_key}` (204); `PUT /canvases/{id}/links {targets}` (`link_target_not_found`, `link_limit_reached` above 50); `DELETE /canvases/{id}` (If-Match, 204, files and links cascade).
> **CNV-FR-03** files are served with `Content-Type` from the stored validated MIME, `X-Content-Type-Options: nosniff`, `Content-Security-Policy: sandbox`, `Cache-Control: private, max-age=0, must-revalidate`, and require authentication (the web fetches with the bearer token and uses object URLs). **CNV-FR-04** the whole scene is one version; a stale `PUT scene` ⇒ 409 (no merging in 1.0). **SEC-AC-39** a 20 MB scene is refused with `canvas_too_large` before parsing via the stream size limit (the scene route's body limit is `canvas.scene.max_bytes` + overhead; the file route's `canvas.file.max_bytes`). **SEC-AC-40** a guessed `file_key` of another account ⇒ 404. Canvas is **online-only** (CNV-FR-06: it is not in the sync op list).
> **CNV-FR-07** links to `goal`, `milestone`, `task`, `note` (note only when `knowledge` is enabled) through `LinkTargetPort` (T-352; this task adds the canvas implementation for kind `canvas` and uses the registry for the rest); dangling-link cleanup on `GoalDeleted`, `MilestoneDeleted`, `TaskDeleted`, `NoteDeleted`, `WorkspaceDeleted` and on re-enable (job `canvas.links.reconcile`, MOD-06). **CNV-FR-08/MOD-11** an `AccountExporter` adds scenes and files (base64 in `data.json`, or ZIP entries under `canvas-files/<canvas-id>/<file_key>` when larger than 1 MiB each, `contracts.md` §8).

## Scope

**In:**
- Repositories, use cases, handlers, DTOs, streaming size limits on the two upload routes, file serving headers, `LinkTargetPort` implementation for `canvas`, subscribers, reconcile job, exporter, wiring, OpenAPI regeneration.

**Out:**
- Web: T-402.

## Files

| Path | Action | Notes |
|---|---|---|
| `crates/kinevo-mod-canvas/src/{app,infra,http}/{canvases,scene,files,links}.rs` | create |  |
| `crates/kinevo-mod-canvas/src/infra/{link_target,subscribers,reconcile,exporter}.rs` | create |  |
| `crates/kinevo-mod-canvas/tests/{canvases_api,files_api,links_api,export}.rs` | create | AC-1…AC-11 |
| `crates/kinevo-app/src/modules.rs` | modify | wire |
| `contracts/openapi.json, web/src/api/schema.d.ts` | modify | regenerated |

## Interfaces

```rust
// PUT /canvases/{id}/scene → 200 { version, scene_bytes, warnings: ["canvas_file_missing"]? }
```

## Business rules

1. Whole-scene versioning only; no merge.
2. The file body limit is enforced on the stream before buffering.
3. Deleting a canvas deletes files and links in the same transaction and emits `CanvasDeleted`.

## Security

- Files require authentication and are never public.
- Foreign canvas/file ids → 404.
- No scene or file bytes in logs.

## Implementation notes

- `scene_bytes` is the byte length of the stored JSON text.

## Acceptance

| # | Given / When / Then | Test name |
|---|---|---|
| AC-1 | **Given** a valid scene **When** created and updated **Then** versions bump and `scene_bytes` is recorded | `canvases_api::scene_versioning` |
| AC-2 | **Given** a stale version **When** putting a scene **Then** 409 `version_conflict` with `details.current_version` | `canvases_api::cnv_fr_04_stale_scene_conflict` |
| AC-3 | **Given** a 20 MB scene **When** posted **Then** 413 `canvas_too_large` without buffering the whole body | `canvases_api::sec_ac_39_stream_limit` |
| AC-4 | **Given** an allowed image **When** uploaded and fetched **Then** the stored MIME and the required headers are returned | `files_api::cnv_fr_03_serving_headers` |
| AC-5 | **Given** an SVG, a mismatched type and an oversize file **When** uploaded **Then** `canvas_file_invalid`/`canvas_file_too_large` | `files_api::cnv_fr_02_upload_rejections` |
| AC-6 | **Given** 100 files **When** a 101st is uploaded **Then** 409 `canvas_files_limit` | `files_api::files_limit` |
| AC-7 | **Given** another account's `file_key` **When** fetched **Then** 404 | `files_api::sec_ac_40_foreign_file_404` |
| AC-8 | **Given** link targets **When** put **Then** atomic replacement, `link_target_not_found` and the 50 limit | `links_api::cnv_fr_07_links_rules` |
| AC-9 | **Given** a deleted goal or note **When** events are delivered or the module re-enabled **Then** dangling links vanish | `links_api::mod_06_reaction_and_reconcile` |
| AC-10 | **Given** an export **When** built **Then** scenes and files appear inline or as ZIP entries by size | `export::cnv_fr_08_export_scenes_and_files` |
| AC-11 | **Given** a canary in scene text **When** logs and audit are scanned **Then** it never appears | `export::cnv_fr_08_canary_scan` |

## Tests

```
cargo nextest run -p kinevo-mod-canvas --features kinevo-db/testing   # DB tests environment-limited without Docker/PostgreSQL
just check
cargo nextest run -p kinevo-mod-knowledge --features kinevo-db/testing
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
