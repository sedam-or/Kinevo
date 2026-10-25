---
id: T-353
title: Build the Notes UI: Tiptap editor, autosave, conflicts, search and links
milestone: M7
kind: feature
status: todo
depends_on: [T-352, T-254]
requirements: [KNW-FR-03, KNW-FR-04, KNW-FR-06, KNW-FR-08, SEC-AC-36, FE-04, FE-09, FE-14, UXS-02, DS-A11Y-13, J7a]
branch: m7-t353-web-knowledge
budget_lines: 420
assignee: ""
---

# T-353: Build the Notes UI: Tiptap editor, autosave, conflicts, search and links

## Goal

Users can write notes with a constrained rich-text editor, never lose text to a conflict or a flaky network, find notes by search, and link them to their plan.

## Context

> **Routes** (`spec/08-frontend.md` §3): `/think/notes` (list with search and workspace filter), `/think/notes/:id` (editor with links panel and backlinks). Nav contributed by the `knowledge` descriptor (permission `knowledge.note.read`).
> **Editor**: `@tiptap/react` with starter-kit, link, task-list, task-item configured to the exact `note_doc_v1` allow-list (no images, tables, embeds or HTML; link schemes `http`, `https`, `mailto`; code block language restricted); loaded lazily on the notes routes only (FE-09). Output is Tiptap JSON sent as `doc`; the server validates and the browser shows `note_doc_invalid` paths as an inline banner, never altering the user's content.
> **Autosave (KNW-FR-06)**: debounce 1 500 ms after the last keystroke and on blur; each save is `PATCH` with `If-Match`; states `note.saving`, `note.saved`, `note.save_failed` (retry); on `version_conflict` keep the local text and show `note.conflict` with **Keep mine as a new note** (creates a new note) and **Use current**; never overwrite. With `sync` enabled, creates and updates go through the offline queue (T-253/T-254: `note:create`, `note:update`) and a concurrent offline edit surfaces as the conflict card. Notes in archived workspaces open read-only with `note.readonly.archived`.
> **Search**: debounced input; `search.too_short` under 2 characters; results show snippets where `⟦ ⟧` become `<mark>` **elements built by the client** (never `innerHTML`); `search.empty`. **Links**: a picker for goals, milestones, tasks (and canvases when enabled) calling `PUT /notes/{id}/links`; backlinks section on goal/task detail pages shows linking notes through `GET /links/backlinks`.
> **Journey J7a**: write with headings, lists and links; link to a goal and a task; search Indonesian and English terms; edit in two tabs → `note.conflict` → keep mine as a new note. **SEC-AC-36**: render with React only, no `dangerouslySetInnerHTML`; a stored `javascript:` href cannot appear (server rejects) and the client also strips it defensively.

## Scope

**In:**
- Feature folder `web/src/features/notes/**`, route files, i18n (en, id), MSW handlers, Vitest tests, Playwright `j7a.spec.ts`, backlinks section on goal and task detail, a11y gate routes.

**Out:**
- Canvas UI: T-402.

## Files

| Path | Action | Notes |
|---|---|---|
| `web/src/features/notes/**` | create |  |
| `web/src/i18n/{en,id}/notes.json` | create |  |
| `web/src/features/goals/GoalDetail.tsx, web/src/features/tasks/TaskEditor.tsx` | modify | backlinks slot only |
| `web/tests/{notes.test.tsx,e2e/journeys/j7a.spec.ts}` | create | AC-1…AC-9 |
| `web/tests/e2e/gates/a11y.spec.ts` | modify | add routes |

## Interfaces

```ts
useNotes(filters); useNote(id); useSaveNote(id); useSearchNotes(q, workspaceId?); useSetNoteLinks(id); useBacklinks(type, id);
```

## Business rules

1. The editor extensions list equals the allow-list; a test fails if an extra node type becomes available.
2. Highlights are React elements, not HTML strings.
3. A conflict never discards local text.

## Security

- No `dangerouslySetInnerHTML` anywhere in the feature (lint rule).
- Note content is not sent to analytics or logs.

## Implementation notes

- Heavy dependencies must not appear in the initial bundle; the bundle report must show the notes chunk separately.

## Acceptance

| # | Given / When / Then | Test name |
|---|---|---|
| AC-1 | **Given** the editor **When** the extension list is inspected **Then** it equals the `note_doc_v1` allow-list | `notes::knw_note_doc_allow_list_matches` |
| AC-2 | **Given** typing **When** 1.5 s pass **Then** one `PATCH` with `If-Match` is sent; blur saves at once | `notes::knw_fr_06_autosave_debounce` |
| AC-3 | **Given** a `version_conflict` **When** received **Then** local text is kept with Keep mine as a new note / Use current | `notes::knw_fr_06_conflict_card_keeps_text` |
| AC-4 | **Given** an archived workspace **When** the note opens **Then** read-only with the banner | `notes::knw_fr_08_readonly_archived` |
| AC-5 | **Given** search hits with delimiters **When** rendered **Then** `<mark>` elements are built without innerHTML | `notes::knw_fr_03_highlights_are_elements` |
| AC-6 | **Given** a query under 2 characters **When** typed **Then** `search.too_short` and no request | `notes::search_too_short_state` |
| AC-7 | **Given** targets selected **When** saved **Then** `PUT links` is called and backlinks appear on the goal | `notes::knw_fr_04_link_picker_and_backlinks` |
| AC-8 | **Given** a `javascript:` href **When** rendered **Then** it is not clickable | `notes::sec_ac_36_href_sanitised` |
| AC-9 | **Given** J7a **When** run in the browser **Then** write, link, search and two-tab conflict pass | `e2e::j7a_notes_journey` |

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
