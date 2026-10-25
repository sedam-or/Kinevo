---
id: T-350
title: Create the knowledge module: migration, descriptor and the note_doc_v1 validator
milestone: M7
kind: feature
status: todo
depends_on: [T-304]
requirements: [KNW-FR-01, KNW-FR-02, KNW-FR-09, DOM-20, D-44, SEC-AC-01, SEC-AC-36]
branch: m7-t350-knowledge-schema-and-doc-validation
budget_lines: 400
assignee: ""
---

# T-350: Create the knowledge module: migration, descriptor and the note_doc_v1 validator

## Goal

The knowledge crate owns `notes` and `note_links` and a pure validator that accepts only the allow-listed Tiptap document shape and derives plain text.

## Context

> **Module** (`spec/modules/knowledge.md`): kind **toggleable**, default on; depends on `workspaces`; permissions `knowledge.note.read|create|update|delete`, `knowledge.link.manage`, `knowledge.search` for `account_owner` and `instance_admin`. Boundary: Tiptap owns editing; Kinevo owns identity, ownership, persistence, versioning, links and search; the server never executes or renders the document as HTML.
> **DDL** (`06-domain-model.md` §9): `notes(id, account_id, workspace_id → workspaces, title ≤ 500, doc jsonb, plain_text, search tsvector GENERATED ALWAYS AS (to_tsvector('simple', title || ' ' || plain_text)) STORED, created_at, updated_at, version)` with GIN index `notes_search_idx`; `note_links(id, account_id, note_id → notes CASCADE, target_type in (goal, milestone, task, canvas), target_id, created_at, UNIQUE (note_id, target_type, target_id))`. Standard RLS. Migration `crates/kinevo-mod-knowledge/migrations/20270501000000_knowledge_knowledge.sql`.
> **`note_doc_v1`** (§3): nodes `doc`, `paragraph`, `heading` (levels 1–3), `bulletList`, `orderedList`, `listItem`, `taskList`, `taskItem` (`checked`), `blockquote`, `codeBlock` (`language` ≤ 30 chars `[a-z0-9+#-]`), `horizontalRule`, `hardBreak`, `text`; marks `bold`, `italic`, `strike`, `code`, `link` (`href` scheme `http`, `https`, `mailto` only, ≤ 2048 chars); limits: nesting depth ≤ 12, total bytes ≤ `knowledge.note.max_bytes` (262144), text node ≤ 100 000 chars. Not in 1.0: images, files, tables, embeds, raw HTML, custom nodes.
> **KNW-FR-01** unknown nodes/marks/attributes ⇒ 422 `note_doc_invalid` with `errors[].path`; the validator never "fixes" content silently. **KNW-FR-02** `plain_text` is derived by the server from `doc` on every write (block separators = newline); clients cannot set it. **KNW-FR-09** note content is S2: never in logs, metrics, audit, AI prompts or error messages.

## Scope

**In:**
- Crate skeleton, descriptor (toggleable default on, `requires: ["workspaces"]`; emits `NoteCreated`, `NoteUpdated`, `NoteDeleted`; consumes `GoalDeleted`, `MilestoneDeleted`, `TaskDeleted`, `CanvasDeleted`, `WorkspaceDeleted`), migration, grants, `domain/doc.rs` (`validate_doc`, `plain_text`), wiring: Register the crate in the workspace `Cargo.toml`, as a dependency of `kinevo-app`, and in the composition root list (`kinevo-app/src/modules.rs`), in dependency order; the architecture test of T-042 must stay green.

**Out:**
- Endpoints: T-351. Links: T-352.

## Files

| Path | Action | Notes |
|---|---|---|
| `crates/kinevo-mod-knowledge/**` | create | skeleton |
| `crates/kinevo-mod-knowledge/migrations/20270501000000_knowledge_knowledge.sql` | create |  |
| `crates/kinevo-mod-knowledge/tests/{isolation,descriptor,doc,plain_text}.rs` | create | AC-1…AC-9 |
| `Cargo.toml, crates/kinevo-app/**` | modify | wiring |

## Interfaces

```rust
pub fn validate_doc(v: &serde_json::Value, max_bytes: usize) -> Result<Doc, Vec<DocError>>;   // DocError { path: String, code }
pub fn plain_text(doc: &Doc) -> String;
```

## Business rules

1. The validator is pure and total; every error carries a JSON path.
2. Unknown attributes on known nodes are errors, not dropped.
3. `plain_text` joins block nodes with a single newline and contains no markup.

## Security

- `javascript:`, `data:` and `vbscript:` hrefs are rejected (SEC-AC-36).
- `run_isolation` covers both tables.
- No note text in logs.

## Implementation notes

- Walk the value iteratively or with an explicit depth counter so a 100 000-deep input cannot overflow the stack.

## Acceptance

| # | Given / When / Then | Test name |
|---|---|---|
| AC-1 | **Given** a migrated database **When** compared with the DDL **Then** equal including the generated column and index | `isolation::dom_knowledge_tables_match_ddl` |
| AC-2 | **Given** two accounts **When** `run_isolation` runs **Then** clean | `isolation::sec_ac_01_knowledge_isolated` |
| AC-3 | **Given** a `javascript:` link, nesting 13 and an unknown node **When** validated **Then** errors with paths | `doc::knw_fr_01_table_of_invalid_docs` |
| AC-4 | **Given** each allowed node and mark **When** validated **Then** accepted | `doc::note_doc_v1_allow_list_accepts_all` |
| AC-5 | **Given** a code block language with illegal characters **When** validated **Then** rejected | `doc::sec_ac_36_code_language_allow_list` |
| AC-6 | **Given** a 100 000-deep document **When** validated **Then** a depth error without a stack overflow | `doc::depth_bomb_refused` |
| AC-7 | **Given** a document **When** plain text is derived **Then** blocks are newline separated and marks vanish | `plain_text::knw_fr_02_derivation` |
| AC-8 | **Given** a document over `max_bytes` **When** validated **Then** the size error | `doc::note_too_large_boundary` |
| AC-9 | **Given** the descriptor **When** validated **Then** toggleable, default on, right dependencies | `descriptor::mod_08_knowledge_descriptor_valid` |

## Tests

```
cargo nextest run -p kinevo-mod-knowledge --features kinevo-db/testing   # DB tests environment-limited without Docker/PostgreSQL
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
