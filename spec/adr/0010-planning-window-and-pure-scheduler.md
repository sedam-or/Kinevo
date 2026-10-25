# ADR-0010 — Pure scheduler crate with a daily planning window and server authority

- Status: **Accepted** (2026-10-25) — D-22, D-33.

## Context
Legacy scheduling was deterministic and explainable, but (a) could place work at any hour (even 03:00) and in the past, (b) mixed time-zone logic into the algorithm layer, (c) trusted a client-submitted draft at apply time, and (d) could not be reused on clients.

## Decision
- `kinevo-scheduler` is a pure crate (integers, no clock/tz/I/O) usable natively and as WASM; the application layer precomputes day windows from the account timezone and policies, so time zones never enter the crate.
- Planning happens only inside configurable daily windows (default 08:00–22:00 local) and never before "now".
- The server is the only authority: apply re-validates the stored draft against current constraints inside the transaction; clients may only preview.
- Same input ⇒ byte-identical output (JCS golden tests, native vs WASM).

## Alternatives
| Option | For | Against |
|---|---|---|
| Port the PHP algorithm verbatim | fastest parity | keeps the defects above |
| Client-side authority for offline | works offline end to end | violates "server is source of truth"; conflict risk |
| Heuristic/AI scheduler | flexible | non-deterministic, unexplainable (rejected by product principle 3) |

## Consequences
- Parity fixtures from legacy tests need the window set to the full day; differences are listed (`modules/scheduler.md` §12).
- Offline schedule preview is possible but never persisted.
- **Revisit if** users need multiple windows per day or per-weekday windows (extend `days[]` generation in the application layer; the crate API already takes arbitrary windows).
