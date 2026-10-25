# Module `knowledge` (notes)

Kind: toggleable (default on). Depends on: `workspaces`. Optional: `goals`/`tasks` (always present, core), `canvas` (links). Tables: `notes`, `note_links`. Prefix: `KNW`.
Boundary (legacy ADR-004/009, kept): **Tiptap owns editing behaviour; Kinevo owns identity, ownership, persistence, versioning, links and search.** Tiptap's document JSON is stored as data; the server never executes or renders it as HTML.

## 1. Permissions

`knowledge.note.read|create|update|delete`, `knowledge.link.manage`, `knowledge.search` — `account_owner`, `instance_admin` (own data).

## 2. Endpoints

| Method / path | Success | Errors |
|---|---|---|
| `GET /notes?workspace_id=&link_type=&link_id=&sort=` | 200 page (sort: `updated_at` default, `title`) — list items omit `doc` (title, snippet 160 chars, updated_at, version) | — |
| `POST /notes` `{ workspace_id?, title?, doc? }` (+ client `id` allowed for offline create) | 201 | `note_doc_invalid` 422, `note_too_large` 413, `workspace_archived`, `entity_id_taken` |
| `GET /notes/{id}` | 200 incl. `doc`, `links[]` | `not_found` |
| `PATCH /notes/{id}` (If-Match) `{ title?, doc? }` | 200 | `version_conflict` (`details.current`), `note_doc_invalid`, `note_too_large` |
| `DELETE /notes/{id}` (If-Match) | 204 (links cascade) | — |
| `GET /notes/search?q=&workspace_id=` | 200 `{ items:[{note_id,title,snippet,rank}] }` ≤ `knowledge.search.max_results` | `query_too_short` 422 (< 2 chars), `query_too_long` 422 (> 200) |
| `PUT /notes/{id}/links` `{ targets:[{type,id}] }` (If-Match on the note) | 200 full link set | `link_target_not_found` 404 (`details.index`), `link_limit_reached` 409 (> 50), `version_conflict` |
| `GET /links/backlinks?target_type=&target_id=` | 200 notes linking to the target (title, id, updated_at) | `not_found` |

## 3. Document schema `note_doc_v1` (server-side allow-list)

| Aspect | Allowed |
|---|---|
| Nodes | `doc`, `paragraph`, `heading` (levels 1–3), `bulletList`, `orderedList`, `listItem`, `taskList`, `taskItem` (`checked`), `blockquote`, `codeBlock` (`language` ≤ 30 chars, `[a-z0-9+#-]`), `horizontalRule`, `hardBreak`, `text` |
| Marks | `bold`, `italic`, `strike`, `code`, `link` (`href`: scheme `http`, `https`, `mailto` only, ≤ 2048 chars) |
| Limits | nesting depth ≤ 12; total bytes ≤ `knowledge.note.max_bytes`; text node ≤ 100 000 chars |
| Not in 1.0 | images, files, tables, embeds, raw HTML, custom nodes |

## 4. Functional requirements

| ID | Requirement | Acceptance |
|---|---|---|
| KNW-FR-01 | The server validates `doc` against `note_doc_v1`; unknown nodes/marks/attributes ⇒ 422 `note_doc_invalid` with `errors[].path`; it never "fixes" content silently. | Table tests incl. `javascript:` link, nesting 13, unknown node. |
| KNW-FR-02 | `plain_text` is derived by the server from `doc` on every write (block separators = newline); clients cannot set it. | Test. |
| KNW-FR-03 | Search uses PostgreSQL full-text search with the `simple` configuration (language-neutral; legacy hard-coded `english`) via `websearch_to_tsquery('simple', q)`; results are ranked by `ts_rank`, ties by `updated_at desc, id`; snippets by `ts_headline` with fixed delimiters `⟦ ⟧` that the client turns into highlights (never raw HTML). Query is parameterised. | Tests: Indonesian and English terms; injection string (SEC-AC-35). |
| KNW-FR-04 | Links: types `goal`, `milestone`, `task`, `canvas` (canvas only if `canvas` enabled). `PUT` replaces the set atomically; all targets must exist in the caller's account. Resolution goes through `LinkTargetPort` (no cross-module SQL). | Tests incl. cross-account target ⇒ 404. |
| KNW-FR-05 | Deleting a goal/milestone/task/canvas removes the links pointing at it (reaction to `*Deleted` events). If this module was disabled at the time, job `knowledge.links.reconcile` runs on re-enable and removes dangling links (MOD-06). | Test: delete while disabled, re-enable, no dangling rows. |
| KNW-FR-06 | Autosave contract (client): debounce 1 500 ms after the last keystroke and on blur; each save is `PATCH` with `If-Match`. On `version_conflict` the editor keeps the local text, shows the conflict card with **Keep mine as a new note** and **Use current**, and never overwrites. | E2E test with two sessions. |
| KNW-FR-07 | Offline: `note:create` / `note:update` are supported by `sync` (SYN list). Concurrent offline edits to the same note surface as a conflict (no merge in 1.0). | Sync test. |
| KNW-FR-08 | Notes inherit workspace archival: notes in archived workspaces are read-only. | Test. |
| KNW-FR-09 | Note content is S2 data (`10-security-privacy.md`): never in logs, metrics, audit, AI prompts (AI-FR-01) or error messages. | Scan test. |

## 5. Validation

`title`: `short_text` ≤ 500 (may be empty); `workspace_id`: uuid; `q`: 2–200 chars, NFC.

## 6. Events / jobs

Emits `NoteCreated`, `NoteUpdated` (ids only), `NoteDeleted`. Consumes `GoalDeleted`, `MilestoneDeleted`, `TaskDeleted`, `CanvasDeleted`, `WorkspaceDeleted`. Jobs: `knowledge.links.reconcile`.

## 7. UI states

`notes.empty`, `notes.loading`, `note.saving`, `note.saved`, `note.save_failed`, `note.conflict`, `note.readonly.archived`, `search.empty`, `search.too_short` (`standards/ux-states.md`).

## 8. Abuse cases

SEC-AC-35 FTS injection (`' | & !`) ⇒ treated as text via `websearch_to_tsquery`. SEC-AC-36 stored XSS via link `href`/code language ⇒ allow-listed; web renders with React (no `dangerouslySetInnerHTML`). SEC-AC-37 note of another account ⇒ 404.
