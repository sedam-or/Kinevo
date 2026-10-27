---
id: T-400
title: Create the canvas module: migration, descriptor, scene validation and image sniffing
milestone: M8
kind: feature
status: todo
depends_on: [T-354]
requirements: [CNV-FR-01, CNV-FR-02, CNV-FR-09, DOM-20, D-44, SEC-AC-01, SEC-AC-38]
branch: m8-t400-canvas-schema-and-scene-validation
budget_lines: 400
assignee: ""
---

# T-400: Create the canvas module: migration, descriptor, scene validation and image sniffing

## Goal

The canvas crate owns its three tables, a pure scene validator and a strict image sniffer that rejects SVG and polyglot files.

## Context

> **Module** (`spec/modules/canvas.md`): kind **toggleable**, default off in `personal` and on in `student`/`cloud` (the templates already say so, T-034); depends on `workspaces`; permissions `canvas.canvas.read|create|update|delete`, `canvas.file.manage`, `canvas.link.manage`. Boundary: Excalidraw owns drawing behaviour; Nivaras owns identity, ownership, persistence, versioning, links and attachments; the scene is opaque, validated JSON.
> **DDL** (`06-domain-model.md` §9): `canvases(id, account_id, workspace_id → workspaces, title ≤ 500, scene jsonb, scene_bytes int, created_at, updated_at, version)`; `canvas_files(id, account_id, canvas_id → canvases CASCADE, file_key, mime in (image/png, image/jpeg, image/webp, image/gif), size_bytes, sha256 bytea, data bytea, created_at, UNIQUE (canvas_id, file_key))`; `canvas_links(id, account_id, canvas_id → canvases CASCADE, target_type in (goal, milestone, task, note), target_id, created_at, UNIQUE (canvas_id, target_type, target_id))`. Standard RLS. Migration `crates/kinevo-mod-canvas/migrations/20270601000000_canvas_canvas.sql`. Storage is PostgreSQL `jsonb`/`bytea` (CNV-FR-09).
> **`canvas_scene_v1`** (§3): `{schema, elements[], app_state{view_background_color "#rrggbb", grid_size int|null}}`; `elements` ≤ 5 000, each with `id` ≤ 64 chars, `type` in {rectangle, diamond, ellipse, arrow, line, freedraw, text, image, frame}, numeric geometry within ±1e7, other Excalidraw attributes pass through as bounded JSON (strings ≤ 10 000 chars, arrays ≤ 10 000 points); `image` elements may reference a `file_id` (a missing file yields the warning `canvas_file_missing`, not an error); `app_state` keeps only the two keys (everything else discarded); total bytes ≤ `canvas.scene.max_bytes` (5 MiB).
> **CNV-FR-01** the server rejects unknown element types with `errors[].path` and drops disallowed `app_state` keys. **CNV-FR-02** allowed MIME `image/png`, `image/jpeg`, `image/webp`, `image/gif`; **SVG is rejected**; content is sniffed by magic number and must match the declared type; size ≤ `canvas.file.max_bytes` (2 MiB); `file_key` matches `^[A-Za-z0-9_-]{8,64}$`; `sha256` stored.

## Scope

**In:**
- Crate skeleton, descriptor (toggleable; emits `CanvasCreated`, `CanvasUpdated`, `CanvasDeleted`; consumes `GoalDeleted`, `MilestoneDeleted`, `TaskDeleted`, `NoteDeleted`, `WorkspaceDeleted`), migration, grants, `domain/{scene,image}.rs`, wiring: Register the crate in the workspace `Cargo.toml`, as a dependency of `kinevo-app`, and in the composition root list (`kinevo-app/src/modules.rs`), in dependency order; the architecture test of T-042 must stay green.

**Out:**
- Endpoints: T-401.

## Files

| Path | Action | Notes |
|---|---|---|
| `crates/kinevo-mod-canvas/**` | create | skeleton |
| `crates/kinevo-mod-canvas/migrations/20270601000000_canvas_canvas.sql` | create |  |
| `crates/kinevo-mod-canvas/tests/{isolation,descriptor,scene,image}.rs` | create | AC-1…AC-9 |
| `Cargo.toml, crates/kinevo-app/**` | modify | wiring |

## Interfaces

```rust
pub fn validate_scene(v: &serde_json::Value, max_bytes: usize) -> Result<Scene, Vec<SceneError>>;   // Scene.warnings may hold canvas_file_missing
pub fn sniff_image(bytes: &[u8], declared: &str) -> Result<ImageMime, ImageError>;
```

## Business rules

1. Sniffing decides the type; the declared type must match.
2. The scene is stored with disallowed `app_state` keys removed, other fields untouched.
3. Pure and total functions.

## Security

- SEC-AC-38: an SVG with `<script>` sent as `image/png` is rejected by sniffing; polyglots (valid image header plus trailing markup) are accepted only when the magic number and declared type agree and the bytes are stored verbatim, never executed or rendered by the server.
- `run_isolation` covers the three tables.
- Scene and file bytes are S2: never logged.

## Implementation notes

- The `Cargo.toml` stays free of image-decoding crates: magic-number checks are a small hand-written table (PNG, JPEG, WEBP RIFF header, GIF87a/89a).

## Acceptance

| # | Given / When / Then | Test name |
|---|---|---|
| AC-1 | **Given** a migrated database **When** compared with the DDL **Then** equal | `isolation::dom_canvas_tables_match_ddl` |
| AC-2 | **Given** two accounts **When** `run_isolation` runs **Then** clean for the three tables | `isolation::sec_ac_01_canvas_isolated` |
| AC-3 | **Given** an unknown element type **When** validated **Then** an error with its path | `scene::cnv_fr_01_unknown_element_type` |
| AC-4 | **Given** extra `app_state` keys **When** validated **Then** only the two allowed keys remain | `scene::cnv_fr_01_app_state_filtered` |
| AC-5 | **Given** an image element with a missing file **When** validated **Then** accepted with `canvas_file_missing` | `scene::missing_file_is_warning` |
| AC-6 | **Given** limits at and over their boundaries **When** validated **Then** exact errors (5 000 elements, string and array bounds, bytes) | `scene::scene_limits_boundaries` |
| AC-7 | **Given** each allowed type with matching bytes **When** sniffed **Then** accepted; SVG and mismatches rejected | `image::cnv_fr_02_sniffing_table` |
| AC-8 | **Given** an SVG renamed `.png` with `image/png` declared **When** sniffed **Then** rejected | `image::sec_ac_38_svg_as_png_rejected` |
| AC-9 | **Given** the descriptor **When** validated **Then** toggleable with the right dependencies | `descriptor::mod_08_canvas_descriptor_valid` |

## Tests

```
cargo nextest run -p kinevo-mod-canvas --features kinevo-db/testing   # DB tests environment-limited without Docker/PostgreSQL
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
