# ADR-0002 — AGPL-3.0-only with DCO; hosted cloud as the business

- Status: **Accepted** (2026-10-25) — D-04; owner chose "AGPL-3.0 + hosted cloud".

## Context
The product should be open-source, easy to adopt, and fund itself through a hosted service. Legacy code was MIT. The business model is a managed cloud for convenience (D-25/D-26); every feature stays available to self-hosters.

## Decision
- License the repository (from the commit after tag `legacy-v0`) under **AGPL-3.0-only**. `legacy-v0` stays MIT.
- Contributions use the **Developer Certificate of Origin** (`git commit -s`), no CLA.
- No dual/commercial license is offered now. If requested, introducing a CLA is a new decision that applies to future contributions only.
- Relicensing the maintainer's own legacy code is possible because every non-bot commit in the legacy history has a single author identity (checked on 2026-10-25 from `git log`; the maintainer must confirm they hold all rights, including any institutional/capstone obligations — **maintainer action M-RIGHTS**).

## Alternatives
| Option | For | Against |
|---|---|---|
| AGPL + hosted cloud | network-copyleft stops closed hosted forks; aligns with "own your data" | some companies avoid AGPL; SaaS competitors must publish changes (acceptable) |
| MIT core + closed modules | easiest adoption | closed modules fragment the community; hosted forks possible |
| MIT only | simplest | no protection for the hosted business |

## Consequences
- README states the licence and what it means for self-hosters (free to use, modify; if you offer it as a network service you must offer your modifications).
- Dependencies must be AGPL-compatible (CQ-16, `cargo-deny`).
- Brand protection via `TRADEMARK.md` (name/logo not licensed).
- Risk RM-R9. **Revisit if** adoption data shows the licence is the main blocker.
