# Module `canvas` (Excalidraw boards)

Kind: toggleable (default **off** in `personal`, on in `student`/`cloud`). Depends on: `workspaces`. Tables: `canvases`, `canvas_files`, `canvas_links`. Prefix: `CNV`.
Boundary (legacy ADR-005/010, kept): **Excalidraw owns drawing behaviour; Nivaras owns identity, ownership, persistence, versioning, links and attachments.** The scene is stored as opaque, validated JSON.

## 1. Permissions

`canvas.canvas.read|create|update|delete`, `canvas.file.manage`, `canvas.link.manage` — `account_owner`, `instance_admin` (own data).

## 2. Endpoints

| Method / path | Success | Errors |
|---|---|---|
| `GET /canvases?workspace_id=&link_type=&link_id=` | 200 page (no scene; title, updated_at, version, `scene_bytes`) | — |
| `POST /canvases` `{ workspace_id?, title?, scene? }` | 201 (empty scene if omitted) | `canvas_scene_invalid` 422, `canvas_too_large` 413, `workspace_archived` |
| `GET /canvases/{id}` | 200 incl. `scene`, `files:[{file_key,mime,size_bytes}]`, `links[]` | `not_found` |
| `PATCH /canvases/{id}` (If-Match) `{ title? }` | 200 | `version_conflict` |
| `PUT /canvases/{id}/scene` (If-Match) `{ scene }` | 200 `{ version, scene_bytes }` | `canvas_scene_invalid`, `canvas_too_large`, `version_conflict` (`details.current_version`; current scene via `GET`) |
| `PUT /canvases/{id}/files/{file_key}` (raw image body, `Content-Type`) | 201 | `canvas_file_invalid` 422, `canvas_file_too_large` 413, `canvas_files_limit` 409 (> 100 per canvas) |
| `GET /canvases/{id}/files/{file_key}` | 200 image bytes | `not_found` |
| `DELETE /canvases/{id}/files/{file_key}` | 204 | — |
| `PUT /canvases/{id}/links` `{ targets:[{type,id}] }` | 200 | `link_target_not_found`, `link_limit_reached` (> 50) |
| `DELETE /canvases/{id}` (If-Match) | 204 (files, links cascade) | — |

## 3. Scene schema `canvas_scene_v1`

`{ "schema":"canvas_scene_v1", "elements":[…], "app_state": { "view_background_color": "#rrggbb", "grid_size": int|null } }`.
- `elements`: ≤ 5 000; each has `id` (≤ 64 chars), `type` ∈ {`rectangle`,`diamond`,`ellipse`,`arrow`,`line`,`freedraw`,`text`,`image`,`frame`}, numeric geometry within ±1e7, other Excalidraw attributes pass through as JSON but are bounded (strings ≤ 10 000 chars, arrays ≤ 10 000 points). `image` elements may reference `file_id` that must exist in `canvas_files` (checked at save; a missing file is allowed to be uploaded **before** or in the same sync window — the server accepts the scene and reports `warnings:["canvas_file_missing"]`).
- `app_state`: only the two keys above are kept; everything else (collaborators, selection, zoom) is discarded by the server.
- Total bytes ≤ `canvas.scene.max_bytes`.

## 4. Functional requirements

| ID | Requirement | Acceptance |
|---|---|---|
| CNV-FR-01 | The server validates `canvas_scene_v1`, drops disallowed `app_state` keys, and rejects unknown element types with `errors[].path`. | Table tests. |
| CNV-FR-02 | Files: allowed MIME `image/png`, `image/jpeg`, `image/webp`, `image/gif`; **SVG is rejected** (script risk). Content is sniffed by magic number and must match the declared type; size ≤ `canvas.file.max_bytes`; `sha256` is stored; `file_key` matches `^[A-Za-z0-9_-]{8,64}$`. | Tests incl. SVG renamed `.png`, polyglot file. |
| CNV-FR-03 | Files are served with `Content-Type` from the stored (validated) MIME, `X-Content-Type-Options: nosniff`, `Content-Security-Policy: sandbox`, `Cache-Control: private, max-age=0, must-revalidate`, and require authentication (the web fetches them with the bearer token and uses object URLs). | Header test. |
| CNV-FR-04 | Concurrency: the whole scene is one version; a `PUT scene` with a stale version ⇒ 409; the client offers **Keep mine as a copy** (creates a new canvas) and **Use current**. No merging in 1.0. | E2E with two sessions. |
| CNV-FR-05 | Autosave: debounce 3 000 ms after the last change and on blur/visibility hidden; at most one in-flight save; failures keep the local scene and show `canvas.save_failed` with retry. | E2E. |
| CNV-FR-06 | Canvas is **online-only** in 1.0 (not in `sync`'s op list). Offline, the board opens read-only from the cache if previously loaded, with banner `canvas.offline_readonly`. | E2E offline. |
| CNV-FR-07 | Links as in KNW-FR-04 (targets `goal`, `milestone`, `task`, `note` when `knowledge` enabled); dangling-link cleanup on deletes and on re-enable (job `canvas.links.reconcile`). | Tests. |
| CNV-FR-08 | Canvas content and file bytes are S2 data: never logged or in audit. Account export includes scenes and files (base64 in `data.json`, or as separate ZIP entries when > 1 MiB each). | Scan + export test. |
| CNV-FR-09 | Storage is PostgreSQL (`jsonb`, `bytea`); no object-store dependency in 1.0 (D-13 spirit). Revisit trigger: median instance DB size dominated by canvas files, filed as an RFC. | — |

## 5. Events / jobs

Emits `CanvasCreated`, `CanvasUpdated`, `CanvasDeleted`. Consumes `GoalDeleted`, `MilestoneDeleted`, `TaskDeleted`, `NoteDeleted`, `WorkspaceDeleted`. Job `canvas.links.reconcile`.

## 6. Abuse cases

SEC-AC-38 SVG with `<script>` uploaded as `image/png` ⇒ rejected by sniffing. SEC-AC-39 5 000-element scene of 20 MB ⇒ `canvas_too_large` before parsing (stream limit). SEC-AC-40 file of another account via guessed `file_key` ⇒ 404.
