---
id: T-152
title: Implement the landscape event and override endpoints
milestone: M3
kind: feature
status: todo
depends_on: [T-151, T-017, T-019, T-014]
requirements: [LAND-FR-02, LAND-FR-03, SEC-AC-14, SEC-AC-15, API-14]
branch: m3-t152-landscape-events-and-overrides-api
budget_lines: 380
assignee: ""
---

# T-152: Implement the landscape event and override endpoints

## Goal

Users can create, list, edit and delete fixed commitments and their overrides, and every change tells the scheduler which dates were affected.

## Context

> **Endpoints** (`spec/modules/landscape.md` §2): `GET /landscape/events?from=&to=` (source events overlapping the window; recurring series included if any occurrence overlaps); `POST /landscape/events {title, start_at, end_at, timezone?, rrule?}` (201; `kind` derived: recurring iff rrule; `rrule_invalid`); `GET /landscape/events/{id}` with `overrides[]`; `PATCH /landscape/events/{id}` (If-Match; `version_conflict`, `rrule_invalid`); `DELETE /landscape/events/{id}` (If-Match; overrides cascade); `POST /landscape/events/{id}/overrides` (201; `override_requires_recurring`, `override_range_invalid`); `GET /landscape/events/{id}/overrides`; `DELETE /landscape/overrides/{id}`.
> **Validation** (§8): `title` short text 1–200; `timezone` IANA (default: account timezone); `start_at`/`end_at` instants, `end > start`, duration ≤ 7 days; `rrule.by_day` unique from the seven codes; override `reason` short text ≤ 200. **LAND-FR-02** override rules (T-151 functions). **LAND-FR-03**: creating, changing or deleting events or overrides emits `LandscapeChanged { from, to }` with the min/max affected dates capped to the next 366 days; the scheduler reacts (SCH-FR-12).
> **List by window**: an event is returned when any occurrence overlaps `[from, to)` (use the T-151 expansion with a small cap, or a cheap pre-filter on `start_at`/`until` followed by expansion); the window is limited to 366 days (`window_too_large` 422).
> **Abuse/hygiene**: mass assignment (`account_id`, `kind`, `source`, `external_uid`, `import_batch_id`, `version`) → 422 `unknown_field`; foreign ids → 404.

## Scope

**In:**
- Repositories, use cases, handlers and DTOs for the eight endpoints; affected-date computation for `LandscapeChanged`; OpenAPI regeneration.

**Out:**
- Effective view and export: T-153. Import: T-154.

## Files

| Path | Action | Notes |
|---|---|---|
| `crates/kinevo-mod-landscape/src/{app,infra,http}/events.rs, overrides.rs` | create |  |
| `crates/kinevo-mod-landscape/tests/{events_api,overrides_api}.rs` | create | AC-1…AC-8 |
| `contracts/openapi.json, web/src/api/schema.d.ts` | modify | regenerated |

## Interfaces

```rust
pub fn affected_range(before: Option<&SourceEvent>, after: Option<&SourceEvent>, today: Date) -> (Date, Date);   // capped to today+366
```

## Business rules

1. An event with `rrule` becomes `recurring`; removing the rrule on PATCH turns it `one_time` (the DB check requires the pair to match).
2. Deleting an event deletes its overrides in the same transaction and emits one `LandscapeChanged`.
3. The default timezone is the account's; an invalid zone is a validation error.
4. Permanent overrides only for recurring events.

## Security

- Strict DTOs (`deny_unknown_fields`); all SQL in the account `Tx`; foreign ids 404.

## Implementation notes

- Events never carry a workspace (LAND-FR-05).
- The affected range for a recurring change spans from the first changed occurrence to the end of the 366-day cap.

## Acceptance

| # | Given / When / Then | Test name |
|---|---|---|
| AC-1 | **Given** an rrule **When** creating **Then** `kind=recurring`; without rrule `one_time` | `events_api::create_derives_kind` |
| AC-2 | **Given** an invalid rrule (both `count` and `until`) **When** creating **Then** 422 `rrule_invalid` | `events_api::land_r2_rrule_validation_errors` |
| AC-3 | **Given** a window **When** listing **Then** series with any overlapping occurrence are included and others not | `events_api::list_by_window_includes_series` |
| AC-4 | **Given** a one-time override with `effective_from != effective_to` **When** posting **Then** 422 `override_range_invalid`; on a one-time event 422 `override_requires_recurring` | `overrides_api::land_fr_02_override_validation` |
| AC-5 | **Given** any create/update/delete **When** committed **Then** one `LandscapeChanged` event with the affected dates capped to 366 days | `events_api::land_fr_03_landscape_changed_event` |
| AC-6 | **Given** a stale `If-Match` **When** patching **Then** 409 `version_conflict` | `events_api::patch_version_conflict` |
| AC-7 | **Given** `PATCH` with `source` or `external_uid` **When** sent **Then** 422 `unknown_field` | `events_api::mass_assignment_rejected` |
| AC-8 | **Given** another account's event or override id **When** accessed **Then** 404 | `events_api::foreign_ids_not_found` |

## Tests

```
cargo nextest run -p kinevo-mod-landscape --features kinevo-db/testing   # DB tests environment-limited without Docker/PostgreSQL
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
