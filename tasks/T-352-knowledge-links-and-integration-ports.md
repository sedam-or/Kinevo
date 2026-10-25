---
id: T-352
title: Implement note links, backlinks, deletion reactions, the sync executors and the exporter
milestone: M7
kind: feature
status: todo
depends_on: [T-351, T-251, T-029]
requirements: [KNW-FR-04, KNW-FR-05, KNW-FR-07, MOD-06, MOD-11, SEC-AC-37, CA-10, CA-03]
branch: m7-t352-knowledge-links-and-integration-ports
budget_lines: 420
assignee: ""
---

# T-352: Implement note links, backlinks, deletion reactions, the sync executors and the exporter

## Goal

Notes can be linked to goals, milestones, tasks and canvases without cross-module SQL, links never dangle, notes work offline, and notes appear in account exports.

## Context

> **Links** (`spec/modules/knowledge.md` §2, KNW-FR-04): `PUT /notes/{id}/links {targets:[{type,id}]}` (If-Match on the note; replaces the set atomically; 200 full link set; `link_target_not_found` 404 with `details.index`, `link_limit_reached` 409 above 50, `version_conflict`); `GET /links/backlinks?target_type=&target_id=` (notes linking to the target: title, id, `updated_at`; `not_found`). Types `goal`, `milestone`, `task`, `canvas` (canvas only if the `canvas` module is enabled). Target existence is checked through the new **`LinkTargetPort::exists(tx, kind, id) -> bool`** in `kinevo-ports`, with implementations by `goals` (goal, milestone), `tasks` (task) and — in M8 — `canvas`; the composition root builds a registry by target kind and skips kinds whose module is disabled.
> **KNW-FR-05** deleting a goal, milestone, task or canvas removes links pointing at it (subscribers to `GoalDeleted`, `MilestoneDeleted`, `TaskDeleted`, `CanvasDeleted`, `WorkspaceDeleted`); if this module was disabled at the time, job `knowledge.links.reconcile` runs on re-enable and removes dangling links (MOD-06) — it checks every link target through the port.
> **KNW-FR-07 offline**: implement `OpExecutor` (T-251 port) for `note:create` and `note:update` (client `entity_id` allowed on create, same replay rules as tasks: identical payload ⇒ replay, different ⇒ `entity_id_taken`); concurrent offline edits to the same note surface as a conflict (no merge in 1.0). Registered only when `sync` and `knowledge` are both enabled.
> **MOD-11 export**: implement `AccountExporter` (platform port T-008, used by T-029) contributing `notes[]` (with `doc` and links) to `data.json` (`export_v1`, `contracts.md` §8); note content stays out of logs.
> **SEC-AC-37**: a cross-account link target ⇒ 404 `link_target_not_found`.

## Scope

**In:**
- `LinkTargetPort` with fake and the goals/tasks implementations; links use cases and handlers; subscribers; reconcile job; note executors; exporter; wiring; OpenAPI regeneration.

**Out:**
- Canvas `LinkTargetPort` implementation: T-401.

## Files

| Path | Action | Notes |
|---|---|---|
| `crates/kinevo-ports/src/link_targets.rs` | create | port and fake |
| `crates/kinevo-mod-goals/src/infra/link_target.rs, crates/kinevo-mod-tasks/src/infra/link_target.rs` | create |  |
| `crates/kinevo-mod-knowledge/src/{app,infra,http}/links.rs, subscribers.rs, reconcile.rs, op_executor.rs, exporter.rs` | create |  |
| `crates/kinevo-mod-knowledge/tests/{links_api,reactions,note_ops,export}.rs` | create | AC-1…AC-10 |
| `crates/kinevo-app/src/modules.rs` | modify | wire |
| `contracts/openapi.json, web/src/api/schema.d.ts` | modify | regenerated |

## Interfaces

```rust
#[async_trait] pub trait LinkTargetPort<T: Send>: Send + Sync { fn kinds(&self) -> &'static [&'static str]; async fn exists(&self, tx: &mut T, kind: &str, id: Uuid) -> Result<bool, AppError>; }
```

## Business rules

1. `PUT links` replaces the set in one transaction; duplicates collapse.
2. Links to a disabled module's kind are refused with `link_target_not_found`.
3. Reconcile is idempotent.

## Security

- Cross-account targets look identical to missing ones (404).
- The exporter includes only the caller's notes.

## Implementation notes

- The subscribers use their own delivery rows (T-014); handlers are idempotent.

## Acceptance

| # | Given / When / Then | Test name |
|---|---|---|
| AC-1 | **Given** targets of valid and missing ids **When** `PUT links` **Then** missing → 404 with `details.index`; all valid → full set returned | `links_api::knw_fr_04_put_replaces_atomically` |
| AC-2 | **Given** 51 targets **When** put **Then** 409 `link_limit_reached` | `links_api::link_limit_reached` |
| AC-3 | **Given** another account's goal id **When** linked **Then** 404 | `links_api::sec_ac_37_cross_account_target` |
| AC-4 | **Given** backlinks for a goal **When** read **Then** the linking notes | `links_api::backlinks` |
| AC-5 | **Given** a deleted task **When** the event is delivered **Then** its links are removed | `reactions::knw_fr_05_delete_removes_links` |
| AC-6 | **Given** a deletion while the module was off **When** re-enabled **Then** the reconcile job removes dangling links | `reactions::mod_06_reconcile_after_reenable` |
| AC-7 | **Given** `note:create` offline ops **When** replayed or sent with a different payload **Then** replay or `entity_id_taken` | `note_ops::knw_fr_07_note_create_replay_semantics` |
| AC-8 | **Given** two offline `note:update` ops on one version **When** reconciled **Then** the second is a conflict | `note_ops::knw_fr_07_concurrent_edit_conflict` |
| AC-9 | **Given** an export **When** built **Then** notes and links appear and nothing from other accounts | `export::mod_11_exporter_contributes_notes` |
| AC-10 | **Given** `sync` or `knowledge` off **When** the registry builds **Then** the note executors are absent | `note_ops::executors_absent_when_module_off` |

## Tests

```
cargo nextest run -p kinevo-mod-knowledge --features kinevo-db/testing   # DB tests environment-limited without Docker/PostgreSQL
just check
cargo nextest run -p kinevo-mod-goals -p kinevo-mod-tasks --features kinevo-db/testing
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
