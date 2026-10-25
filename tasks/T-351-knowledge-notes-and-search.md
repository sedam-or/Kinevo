---
id: T-351
title: Implement the note endpoints and full-text search
milestone: M7
kind: feature
status: todo
depends_on: [T-350, T-019]
requirements: [KNW-FR-02, KNW-FR-03, KNW-FR-08, SEC-AC-35, SEC-AC-37, API-14]
branch: m7-t351-knowledge-notes-and-search
budget_lines: 400
assignee: ""
---

# T-351: Implement the note endpoints and full-text search

## Goal

Users can create, read, edit, delete and search notes safely with optimistic concurrency, and search treats hostile input as plain text.

## Context

> **Endpoints** (`spec/modules/knowledge.md` §2): `GET /notes?workspace_id=&link_type=&link_id=&sort=` (page; sort `updated_at` default or `title`; list items omit `doc`: title, 160-char snippet, `updated_at`, `version`); `POST /notes {workspace_id?, title?, doc?}` (201; a client-supplied `id` is allowed only through the sync executor, T-352; `note_doc_invalid` 422, `note_too_large` 413, `workspace_archived` 409); `GET /notes/{id}` (incl. `doc` and `links[]`); `PATCH /notes/{id}` (If-Match, `{title?, doc?}`; `version_conflict` with `details.current`); `DELETE /notes/{id}` (If-Match, 204, links cascade); `GET /notes/search?q=&workspace_id=` (`{items:[{note_id,title,snippet,rank}]}` ≤ `knowledge.search.max_results`, `query_too_short` < 2 chars, `query_too_long` > 200).
> **KNW-FR-03** search uses PostgreSQL full-text search with the `simple` configuration via `websearch_to_tsquery('simple', q)`; rank by `ts_rank`, ties by `updated_at desc, id`; snippets by `ts_headline` with fixed delimiters `⟦` `⟧` that the client turns into highlights (never raw HTML); the query is a bound parameter (SEC-AC-35: `' | & !` are text). **KNW-FR-08** notes in archived workspaces are read-only (writes ⇒ `workspace_archived`). Workspace defaults to the account's default workspace; the title is `short_text` ≤ 500 and may be empty; `q` is NFC 2–200 chars. **SEC-AC-37** another account's note ⇒ 404. Validation of `doc` and derivation of `plain_text` happen in the use case (T-350 functions), clients cannot set `plain_text`.
> **Events**: `NoteCreated`, `NoteUpdated`, `NoteDeleted` carry ids only (CA-11).

## Scope

**In:**
- Repositories, use cases, handlers, DTOs, search query, OpenAPI regeneration.

**Out:**
- Links, backlinks and reactions: T-352. Web: T-353.

## Files

| Path | Action | Notes |
|---|---|---|
| `crates/kinevo-mod-knowledge/src/{app,infra,http}/notes.rs, search.rs` | create |  |
| `crates/kinevo-mod-knowledge/tests/{notes_api,search_api}.rs` | create | AC-1…AC-10 |
| `contracts/openapi.json, web/src/api/schema.d.ts` | modify | regenerated |

## Interfaces

```rust
pub struct SearchHit { pub note_id: NoteId, pub title: String, pub snippet: String, pub rank: i32 }   // rank is an integer scaled from ts_rank
```

## Business rules

1. Money-free: rank is scaled to an integer for the API (no floats in API types, CQ-08).
2. `PATCH` with a stale `If-Match` returns 409 and the current `version`.
3. A note in an archived workspace refuses writes.

## Security

- Mass assignment of `plain_text`, `account_id`, `version`, `id` → 422 `unknown_field`.
- Content never logged; error details contain paths only.

## Implementation notes

- Snippet generation uses `ts_headline` options `StartSel=⟦, StopSel=⟧, MaxFragments=1, MaxWords=20`.
- Indonesian and English test terms: use a fixture with both.

## Acceptance

| # | Given / When / Then | Test name |
|---|---|---|
| AC-1 | **Given** a valid doc **When** created **Then** 201 and the server-derived `plain_text`, not a client value | `notes_api::knw_fr_02_plain_text_server_derived` |
| AC-2 | **Given** an invalid doc **When** posted **Then** 422 `note_doc_invalid` with paths; an oversize doc 413 | `notes_api::note_doc_invalid_and_too_large` |
| AC-3 | **Given** a stale `If-Match` **When** patching **Then** 409 `version_conflict` with `details.current` | `notes_api::version_conflict_details` |
| AC-4 | **Given** an archived workspace **When** writing **Then** `workspace_archived`; reading works | `notes_api::knw_fr_08_archived_is_read_only` |
| AC-5 | **Given** Indonesian and English notes **When** searched **Then** hits are ranked and snippets use the fixed delimiters | `search_api::knw_fr_03_ranked_snippets_both_languages` |
| AC-6 | **Given** `' | & !` **When** searched **Then** treated as text, no error | `search_api::sec_ac_35_fts_injection` |
| AC-7 | **Given** a 1-char and a 201-char query **When** searched **Then** `query_too_short`/`query_too_long` | `search_api::query_length_limits` |
| AC-8 | **Given** another account's note id **When** accessed **Then** 404 | `notes_api::sec_ac_37_foreign_note_404` |
| AC-9 | **Given** list requests **When** returned **Then** items omit `doc` and carry a 160-char snippet | `notes_api::list_omits_doc` |
| AC-10 | **Given** `PATCH` with `plain_text` **When** sent **Then** 422 `unknown_field` | `notes_api::mass_assignment_rejected` |

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
