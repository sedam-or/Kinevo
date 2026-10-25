---
id: T-154
title: Implement ICS preview, commit and undo
milestone: M3
kind: feature
status: todo
depends_on: [T-153]
requirements: [LAND-FR-10, LAND-FR-11, LAND-FR-12, LAND-FR-13, LAND-FR-14, LAND-FR-15, LAND-FR-16, LAND-FR-17, SEC-AC-16, TST-19]
branch: m3-t154-landscape-ics-import
budget_lines: 400
assignee: ""
---

# T-154: Implement ICS preview, commit and undo

## Goal

Users can import a calendar file safely: preview with warnings, atomic idempotent commit, and undo; the parser is bounded and cannot be abused.

## Context

> **Import** (`spec/modules/landscape.md` §5–§6): `parse_ics(bytes, account_tz) → Preview` is a pure function. **LAND-FR-10** accepts `text/calendar` or `.ics` ≤ 1 MiB (413 `ics_too_large`), ≤ 2 000 VEVENTs (`ics_too_many_events`), unfolds lines, honours `\n \, \; \\` escapes, UTF-8 only (`ics_invalid` otherwise), no network access, ignores unknown properties; fuzzed. **LAND-FR-11** mapping: `SUMMARY`→title (empty → message key `landscape.untitled`), `DTSTART`/`DTEND`/`DURATION`, `TZID`→zone (UTC `Z` → UTC, floating → account tz with warning `floating_time_assumed_account_timezone`), `UID`→`external_uid`, `RRULE` (FREQ DAILY/WEEKLY with INTERVAL, BYDAY without ordinals, COUNT, UNTIL), `EXDATE`→one-time cancel overrides, `RECURRENCE-ID` components→one-time exception overrides. **LAND-FR-12** unsupported RRULE (MONTHLY, YEARLY, BYSETPOS, ordinal BYDAY…) → single occurrence with warning `rrule_unsupported`. **LAND-FR-13** all-day (`VALUE=DATE`) → `all_day=true`, `include=false`, warning `all_day_skipped_by_default`; included ones span 00:00→24:00 local of each day. **LAND-FR-14** existing `external_uid` → `status="exists"`, unchecked; selected at commit → title/times/rrule replaced (version +1), overrides kept. **LAND-FR-15** commit accepts the normalized items array (not the file), re-validates every item as a manual create, atomic and idempotent (`Idempotency-Key`). **LAND-FR-16** undo removes events created by the batch that still have `import_batch_id`; replaced events are not reverted.
> **Endpoints** (§2): `POST /landscape/imports/preview` (multipart `file`; 200 preview), `POST /landscape/imports {filename?, items[]}` + `Idempotency-Key` (201 `{batch_id, created, replaced}`), `DELETE /landscape/imports/{batch_id}` (204; `not_found`), body limit for the multipart route set by the route spec (1 MiB + overhead).
> **Preview item** (§6): `{ key, title, kind, start_at, end_at, timezone, rrule?, exdates[], exceptions[], all_day, include, status (new|exists), warnings[] }`; items with neither `DTEND` nor `DURATION` are rejected (`ics_item_no_end`) and listed in `rejected[]` with the source line number.
> **SEC-AC-16**: a 1 MiB file of nested/repeating properties or an RRULE with `COUNT=100000` is bounded by the limits and the 5 000-occurrence cap; response < 1 s. **TST-19**: fault injection for the commit transaction. **Events**: `LandscapeImported`, `LandscapeChanged`.

## Scope

**In:**
- `domain/ics_parse.rs` (own bounded line parser; `icalendar` may be used only where it keeps the limits), preview mapping, `import.rs` use cases (preview, commit, undo), handlers, multipart extraction with the size limit, OpenAPI regeneration.
- Fixture `.ics` files per mapping row, a fuzz target config for `cargo-fuzz` kept in `fuzz/` (not run in `just check`) plus a bounded in-test randomised run (proptest) proving no panic and bounded memory.
- Round-trip property test: export (T-153) → import preview → commit equals the original landscape.

**Out:**
- UI: T-160.

## Files

| Path | Action | Notes |
|---|---|---|
| `crates/kinevo-mod-landscape/src/domain/ics_parse.rs` | create | pure parser |
| `crates/kinevo-mod-landscape/src/{app,infra,http}/imports.rs` | create |  |
| `crates/kinevo-mod-landscape/tests/{ics_parse,imports_api,roundtrip}.rs` | create | AC-1…AC-10 |
| `crates/kinevo-mod-landscape/tests/fixtures/ics/*.ics` | create | fixtures |
| `fuzz/fuzz_targets/ics_parse.rs` | create | fuzz target, not part of `just check` |
| `contracts/openapi.json, web/src/api/schema.d.ts` | modify | regenerated |

## Interfaces

```rust
pub fn parse_ics(bytes: &[u8], account_tz: &TimeZone) -> Result<Preview, IcsError>;   // IcsError → ics_invalid | ics_too_many_events
// POST /landscape/imports/preview → 200 { items:[PreviewItem], rejected:[{ line, code }], warnings }
```

## Business rules

1. Commit re-validates each item with the same functions as manual create; one invalid item fails the whole commit with a field error naming the item key.
2. Commit is idempotent per key; a replay returns the stored result.
3. Items with `include=false` are ignored at commit.
4. `exists` items selected at commit replace the event fields, bump `version` and keep overrides.

## Security

- Size and count limits are checked before parsing structure; the parser never allocates proportional to attacker-controlled counts beyond the limits.
- No network access exists in the parser; `ATTACH` and `URL` are ignored.
- Imported text is stored as plain text and escaped on export.

## Implementation notes

- The multipart handler streams with a hard cap; exceeding it yields 413 before buffering the whole body.
- `landscape.untitled` is stored as the message-key placeholder text in the account locale at import time (same compiled-name approach as `workspace.default_name`).

## Acceptance

| # | Given / When / Then | Test name |
|---|---|---|
| AC-1 | **Given** the mapping fixtures **When** parsed **Then** each row of LAND-FR-11 maps as specified | `ics_parse::land_fr_11_mapping_table` |
| AC-2 | **Given** a MONTHLY rule **When** parsed **Then** one occurrence with `rrule_unsupported` | `ics_parse::land_fr_12_unsupported_rrule` |
| AC-3 | **Given** an all-day event **When** parsed **Then** `include=false` and the warning; included it spans 00:00→24:00 | `ics_parse::land_fr_13_all_day_default_unchecked` |
| AC-4 | **Given** a file over 1 MiB or 2 001 events **When** posted **Then** 413 `ics_too_large` / 422 `ics_too_many_events` | `imports_api::land_fr_10_size_and_count_limits` |
| AC-5 | **Given** random byte soup and nested properties **When** parsed **Then** no panic and bounded time/memory | `ics_parse::sec_ac_16_bounded_on_hostile_input` |
| AC-6 | **Given** `COUNT=100000` **When** previewed and committed **Then** occurrence cap respected and response under 1 s | `imports_api::sec_ac_16_rrule_count_bomb` |
| AC-7 | **Given** an existing `external_uid` **When** previewed and then committed selected **Then** `exists` unchecked by default and replacement bumps version keeping overrides | `imports_api::land_fr_14_exists_replace` |
| AC-8 | **Given** failure injected at each commit statement and a replay with the same key **When** run **Then** nothing partial and replay returns the stored result | `imports_api::land_fr_15_atomic_idempotent_commit` |
| AC-9 | **Given** a batch **When** undone **Then** created events are removed and replaced ones are not reverted | `imports_api::land_fr_16_undo_semantics` |
| AC-10 | **Given** any landscape **When** exported and re-imported **Then** the landscape is identical | `roundtrip::land_fr_17_round_trip_property` |

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
