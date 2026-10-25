# Module `landscape` (Hard Landscape)

Kind: core. Depends on: `identity`. Tables: `landscape_events`, `schedule_overrides`, `import_batches`. Prefix: `LAND`.
The **Hard Landscape** is the set of fixed commitments (classes, meetings, shifts) that automation must
never overwrite. It is account-wide. Behaviour is ported from legacy ADR-015
(`legacy-v0:server/app/Domain/Scheduling/Resolution/EffectiveLandscapeResolver.php`,
`.../Recurrence/*`); deviations are listed in §9.

## 1. Permissions

`landscape.event.read|create|update|delete`, `landscape.override.read|create|delete`, `landscape.import.create|delete`, `landscape.export.read` — `account_owner`, `instance_admin` (own data).

## 2. Endpoints

| Method / path | Success | Errors |
|---|---|---|
| `GET /landscape/events?from=&to=` | 200 page of **source** events overlapping the window (recurring series included if any occurrence overlaps) | — |
| `POST /landscape/events` `{ title, start_at, end_at, timezone?, rrule? }` | 201 (`kind` derived: `recurring` iff `rrule`) | `rrule_invalid` 422, validation |
| `GET /landscape/events/{id}` | 200 incl. `overrides[]` | `not_found` |
| `PATCH /landscape/events/{id}` (If-Match) | 200 | `version_conflict`, `rrule_invalid` |
| `DELETE /landscape/events/{id}` (If-Match) | 204; overrides cascade | — |
| `POST /landscape/events/{id}/overrides` | 201 | `override_requires_recurring` 422, `override_range_invalid` 422 |
| `GET /landscape/events/{id}/overrides` | 200 | — |
| `DELETE /landscape/overrides/{id}` | 204 | — |
| `GET /landscape/effective?from=&to=` | 200 `{ occurrences[], cancelled[], warnings[] }` (§4) | `window_too_large` 422 (> 366 days) |
| `POST /landscape/imports/preview` (multipart `file`) | 200 normalized preview (§6) | `ics_too_large` 413, `ics_invalid` 422, `ics_too_many_events` 422 |
| `POST /landscape/imports` `{ filename?, items[] }` + `Idempotency-Key` | 201 `{ batch_id, created, replaced }` | validation 422 |
| `DELETE /landscape/imports/{batch_id}` | 204 (removes events created by the batch) | `not_found` |
| `GET /landscape/export.ics?from=&to=` | 200 `text/calendar` | — |

## 3. Time and recurrence rules

| ID | Rule |
|---|---|
| LAND-R1 | A source event has `start_at`/`end_at` (instants) and an IANA `timezone` (default: account timezone). The *wall-clock* time in that zone is what recurs. |
| LAND-R2 | `rrule` = `{ freq: "DAILY"|"WEEKLY", interval ≥ 1 (≤ 52), by_day?: ["MO".."SU"] (WEEKLY only), count? (1–1000), until? (date or instant), until_date_only: bool }`. `count` and `until` are mutually exclusive. `until` must not precede `start_at`. Missing `by_day` on WEEKLY = the weekday of `start_at` in the event zone. |
| LAND-R3 | Occurrence generation is deterministic: for `DAILY`, every `interval` days from the start date; for `WEEKLY`, every `interval` weeks (weeks begin Monday for stepping) on each `by_day`; `count` counts occurrences from the first one. Expansion works on local dates in the event zone, then converts each local start to an instant. |
| LAND-R4 | Daylight-saving: a local time that does not exist (spring-forward gap) moves forward by the gap length; an ambiguous local time (fall-back) takes the earlier instant. The occurrence's duration is `end_at − start_at` of the source (absolute duration). |
| LAND-R5 | `until_date_only=true` includes every occurrence whose local start date ≤ `until` date (inclusive of the whole last day); otherwise occurrences with start instant ≤ `until`. |
| LAND-R6 | A window `[from, to)` includes an occurrence iff `occ.start < to AND occ.end > from` (half-open overlap). |
| LAND-R7 | An unparseable or unsupported stored recurrence **degrades** to the single base occurrence and adds warning `rrule_degraded` with the source event id; it is never silently dropped. |

## 4. Effective landscape resolution (pure function in `kinevo-mod-landscape::domain`)

`resolve(sources, overrides, from, to) → { occurrences, cancelled, warnings }`

For each source event and each generated occurrence `O` (original start date `d` = local date of `O.start` in the event zone):

1. **Exception**: among overrides of kind `one_time` with `effective_from == d`, choose the one with greatest `(created_at, id)`. If found: `cancels_occurrence=true` → move `O` to `cancelled` (provenance `cancelled`); else `O` becomes `[override_start_at, override_end_at)` (provenance `excepted`).
2. Else **Permanent shift**: among overrides of kind `permanent` with `effective_from ≤ d ≤ effective_to`, choose the greatest `(effective_from, created_at, id)`. Let `delta` = whole calendar days from `shift.effective_from` to `d`. The occurrence becomes `[shift.override_start_at + delta days, shift.override_end_at + delta days)`, adding **calendar days in the event zone** (wall-clock preserved), duration = shift duration (provenance `shifted`).
3. Else keep `O` unchanged (provenance `base`).
4. After overrides, an occurrence is returned only if it overlaps the window (LAND-R6) *after* shifting; cancelled occurrences are returned in `cancelled` for UI display.

Output order is total and deterministic: `(start_at, end_at, event_id, original_start_at)`. Precedence labels used by the scheduler and UI, lowest → highest: `ordinary < recurring < explicit_override < locked_task < hard_landscape`.

| ID | Requirement | Acceptance |
|---|---|---|
| LAND-FR-01 | `resolve` is pure (no I/O, no clock) and total: same inputs → identical output; golden fixtures come from legacy tests (`legacy-v0:server/tests/Unit/Scheduling/*Resolver*`, `*Recurrence*`). | Golden + property tests (idempotent, sorted). |
| LAND-FR-02 | One-time exceptions require `effective_from == effective_to`; permanent shifts require `effective_to ≥ effective_from`; both require `override_end_at > override_start_at`. Permanent shifts apply only to `recurring` events. | Validation tests. |
| LAND-FR-03 | Creating, changing or deleting events/overrides emits `LandscapeChanged { from, to }` (the min/max affected dates, capped to the next 366 days); the scheduler reacts (SCH-FR-12). | Event test. |
| LAND-FR-04 | `GET /landscape/effective` window ≤ 366 days; results for large series are generated lazily and capped at 5 000 occurrences (`warnings: ["occurrence_cap_reached"]`). | Test. |
| LAND-FR-05 | Landscape events never carry a workspace. | Schema test. |

## 5. ICS import (preview → commit → undo)

Server-side parsing is a pure function `parse_ics(bytes, account_tz) → Preview`.

| ID | Requirement | Acceptance |
|---|---|---|
| LAND-FR-10 | Accepts `text/calendar` or `.ics` ≤ 1 MiB (413 `ics_too_large` otherwise), ≤ 2 000 VEVENTs (`ics_too_many_events`). Unfolds lines, honours `\n`/`\,`/`\;`/`\\` text escapes, UTF-8 only. No network access, no `ATTACH`/`URL` fetching, no external entity concept exists in the format; unknown properties are ignored. | Fuzz test (no panic, bounded memory); table tests. |
| LAND-FR-11 | Mapping: `SUMMARY`→title (empty → `message landscape.untitled`), `DTSTART`/`DTEND`/`DURATION`, `TZID`→zone (UTC `Z` → zone UTC; floating → account tz), `UID`→`external_uid`, `RRULE` (FREQ DAILY/WEEKLY with INTERVAL, BYDAY without ordinals, COUNT, UNTIL), `EXDATE`→ one-time **cancel** overrides, `RECURRENCE-ID` components → one-time **exception** overrides. | Fixtures per mapping. |
| LAND-FR-12 | Unsupported `RRULE` (MONTHLY, YEARLY, BYSETPOS, ordinal BYDAY, etc.) → the event is previewed as a **single** occurrence with warning `rrule_unsupported` (mirrors LAND-R7). | Test. |
| LAND-FR-13 | All-day events (`VALUE=DATE`) are previewed with `all_day=true`, **unchecked by default** (`include=false`), warning `all_day_skipped_by_default`; if included, they span `00:00 → 24:00` local of each day. | Test. |
| LAND-FR-14 | Existing `external_uid` → item `status="exists"`, unchecked by default. If selected at commit, the event's title/times/rrule are replaced (version +1); its overrides are kept. | Test. |
| LAND-FR-15 | The commit endpoint accepts the **normalized items array** (not the file); the server re-validates every item exactly as a manual create. Commit is atomic and idempotent (`Idempotency-Key`). | Fault-injection + replay tests. |
| LAND-FR-16 | Undo (`DELETE /landscape/imports/{batch_id}`) removes events created by that batch that still have `import_batch_id` set; replaced events are not reverted. | Test. |
| LAND-FR-17 | Export produces RFC 5545 output: CRLF line ends, 75-octet folding, `UID` = `<event id>@<instance host>`, `DTSTAMP` from the injected clock, RRULE per LAND-R2, overrides as `EXDATE`/`RECURRENCE-ID` components. A re-import of an export yields an identical landscape (round-trip test). | Round-trip property test. |

## 6. Preview item shape

`{ key (stable within the preview), title, kind, start_at, end_at, timezone, rrule?, exdates[], exceptions[], all_day, include (default), status ("new"|"exists"), warnings[] (codes) }`. Warning codes: `rrule_unsupported`, `all_day_skipped_by_default`, `floating_time_assumed_account_timezone`. An item with neither `DTEND` nor `DURATION` is rejected (`ics_item_no_end`) and listed under `rejected[]` with its source line number.

## 7. State machines

Landscape objects have no lifecycle states beyond existence. Import batch: `previewed (client only) → committed → undone`. Failure at commit: nothing persisted; the client keeps the preview and retries with the same idempotency key.

## 8. Validation

`title`: `short_text` 1–200; `timezone`: `iana_tz`; `start_at`/`end_at`: instants, `end > start`, duration ≤ 7 days; `rrule.by_day`: unique, from the 7 codes; `reason`: `short_text` ≤ 200.

## 9. Differences from legacy (intentional)

1. `permanent` as an *event* type is removed (it had no distinct behaviour, only a label); `permanent` remains an *override* kind.
2. Recurrence is expanded in the event's own IANA zone with explicit DST rules (legacy delegated to a generic engine; behaviour in the gap/overlap was unspecified).
3. Exception matching uses the **local date in the event zone** (legacy compared the date string of a UTC-parsed instant).
4. The resolver docblock/behaviour contradiction in legacy (overrides "must not alter output") is resolved in favour of the behaviour: overrides apply.

## 10. Events and abuse cases

Emits `LandscapeChanged`, `LandscapeImported`. Consumes none. Abuse: SEC-AC-16 ICS bomb (1 MiB of nested/repeating properties, 100 000-instance RRULE COUNT) → bounded by LAND-FR-10 and the 5 000 occurrence cap, response < 1 s; SEC-AC-17 CRLF/`BEGIN:VEVENT` injection through a task title in export → text escaping prevents property injection (test with `\r\nATTENDEE:` payload).
