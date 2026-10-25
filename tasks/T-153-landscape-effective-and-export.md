---
id: T-153
title: Implement the effective-landscape endpoint, the landscape read port and ICS export
milestone: M3
kind: feature
status: todo
depends_on: [T-152]
requirements: [LAND-FR-01, LAND-FR-04, LAND-FR-17, SEC-AC-17]
branch: m3-t153-landscape-effective-and-export
budget_lines: 380
assignee: ""
---

# T-153: Implement the effective-landscape endpoint, the landscape read port and ICS export

## Goal

Clients and the scheduler can read the resolved landscape for a window, and users can export it as standards-compliant, injection-safe ICS that re-imports identically.

## Context

> **Endpoints** (`spec/modules/landscape.md` §2): `GET /landscape/effective?from=&to=` → `{ occurrences[], cancelled[], warnings[] }`, `window_too_large` 422 above 366 days; `GET /landscape/export.ics?from=&to=` → `text/calendar`.
> **LAND-FR-17**: export is RFC 5545: CRLF line ends, 75-octet folding (not splitting a UTF-8 sequence), `UID = <event id>@<instance host>` (host from `KINEVO_PUBLIC_URL`, injected as configuration), `DTSTAMP` from the injected clock, RRULE per LAND-R2, overrides as `EXDATE` / `RECURRENCE-ID` components; a re-import of an export yields an identical landscape (round-trip property test, with T-154). **SEC-AC-17**: text escaping prevents property injection (a title containing `\r\nATTENDEE:` or `BEGIN:VEVENT` stays inside one escaped `SUMMARY`).
> **Port** (add to `kinevo-ports`, implemented here): `LandscapeReadPort::effective(tx, window) -> EffectiveLandscape` used by the scheduler (T-158) with ranges only (`busy`), not source events; fake behind `testing`.
> **Library**: `icalendar` 0.17.x for building basics (stack map); the escaping and folding functions are covered by their own tests.

## Scope

**In:**
- Use cases and handlers for the two endpoints; `LandscapeReadPort` and its implementation; `export.rs` building ICS from sources and overrides; OpenAPI regeneration.

**Out:**
- Import parsing: T-154. Scheduler export: T-159.

## Files

| Path | Action | Notes |
|---|---|---|
| `crates/kinevo-ports/src/landscape.rs` | create | port and fake |
| `crates/kinevo-mod-landscape/src/{app,infra,http}/effective.rs, export.rs` | create |  |
| `crates/kinevo-mod-landscape/tests/{effective_api,export_ics}.rs` | create | AC-1…AC-7 |
| `contracts/openapi.json, web/src/api/schema.d.ts` | modify | regenerated |

## Interfaces

```rust
#[async_trait] pub trait LandscapeReadPort<T: Send>: Send + Sync { async fn effective(&self, tx: &mut T, window: TimeRange) -> Result<EffectiveLandscape, AppError>; }
pub fn to_ics(events: &[SourceEvent], overrides: &[Override], host: &str, now: Instant) -> String;
```

## Business rules

1. The effective endpoint returns exactly what `resolve` returns; no extra logic in the handler.
2. Lines are folded at 75 octets without splitting characters; text values escape `\\`, `;`, `,` and newlines.
3. Export of an empty window is a valid empty calendar.

## Security

- Text escaping test with CR/LF and `BEGIN:VEVENT` payloads (SEC-AC-17).
- `UID` uses the event id, never user text.

## Implementation notes

- Put escaping and folding in a small, separately tested module reused by the scheduler export (T-159).
- If the `icalendar` crate cannot guarantee the required folding/escaping, generate lines manually in the same module; the tests are the contract.

## Acceptance

| # | Given / When / Then | Test name |
|---|---|---|
| AC-1 | **Given** events and overrides **When** `GET /landscape/effective` **Then** the body equals `resolve` output | `effective_api::land_fr_01_endpoint_matches_resolver` |
| AC-2 | **Given** a window over 366 days **When** requested **Then** 422 `window_too_large` | `effective_api::window_too_large` |
| AC-3 | **Given** more than 5 000 occurrences **When** requested **Then** capped with `occurrence_cap_reached` | `effective_api::land_fr_04_cap_warning` |
| AC-4 | **Given** an export **When** inspected **Then** CRLF endings, folding at 75 octets and `UID` with the host | `export_ics::land_fr_17_format` |
| AC-5 | **Given** a title with `\r\nATTENDEE:` **When** exported **Then** no extra property appears | `export_ics::sec_ac_17_no_property_injection` |
| AC-6 | **Given** the port **When** the scheduler calls it **Then** it returns busy ranges only for the window | `effective_api::landscape_read_port_returns_ranges` |
| AC-7 | **Given** an empty window **When** exported **Then** a valid empty VCALENDAR | `export_ics::empty_calendar_valid` |

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
