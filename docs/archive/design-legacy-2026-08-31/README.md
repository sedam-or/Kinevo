# Design Legacy Archive (2026-08-31 — P29 convergence)

Frozen verbatim copies of the pre-P29 UX/audit authorities. They are HISTORICAL
EVIDENCE — never canonical. The living authorities live in `docs/ux/`:

| Archived file | Was | Canonical successor |
|---|---|---|
| design.md (105 sections) | UI/UX authority incl. §74–103, §104 P17, §105 retention/third-party | `docs/ux/design-system.md` + `interaction-states.md` + `motion.md` + `accessibility.md` + `content-design.md` + `docs/marketing/site-specification.md` |
| design-tokens.md | token contract | `docs/ux/design-system.md` §2–§5 (two factual claims corrected: CSS prefix is `--color-*`/`--radius-*`; primary is `#F53003`) |
| brand.md | brand usage inventory | `docs/ux/design-system.md` (font corrected to Instrument Sans) + `docs/marketing/site-specification.md` |
| state-machine-ui.md | state-machine UI matrix + P28-011 global state matrix + RET-013 failure matrix | `docs/ux/interaction-states.md` |
| ui-audit.md | living UX gap audit (dated §10 inventory) | audit evidence only; P28-012/P28-013 evidence lives in `docs/browser-e2e.md` |

Section-number references to old design.md (e.g. "§99", "§104") in code comments
and older docs are historical pointers into the archived file.
