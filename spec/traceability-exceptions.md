# Traceability exceptions

Every requirement id must be covered by at least one acceptance criterion in a task and one named test (`12-testing.md`, CQ-17). This file lists the **only** ids or id families allowed to have no automated test, each with the reason and the evidence that replaces the test. `scripts/spec_tool.py check` treats an id listed here as covered; any other uncovered id is an error.

An entry is added only in the same commit as the spec change that needs it, and is removed when a test becomes possible.

| Id / family | Why no automated test | Replacement evidence |
|---|---|---|
| `PRD-*` (product principles, personas, scope) | Statements of intent, not system behaviour | Realised through module requirements that are tested; validated by V1–V4 (`13-roadmap.md` §2) |
| `RM-*` (milestones, risks, validation) | Planning artefacts | Integration task of each milestone; recorded results in `docs/research/` |
| `DS-A11Y-16` (manual accessibility audit) | Requires human assistive-technology testing | Audit report recorded before 1.0 (`13-roadmap.md` §3) |
| `CLD-*` legal and commercial items (marked "maintainer action") | Depend on counsel and owner decisions (OWNER-01…06, M-LEGAL) | Signed-off documents linked from `15-cloud.md` |
| `DST-*` store-listing and template-marketplace steps | Performed in third-party consoles | Release checklist in `RELEASING.md` with links to the published listing |
| `OPS-*` runbook prose (restore drill steps excepted, which are tested) | Human procedures | Runbook reviewed by a maintainer each minor release |

Status (2026-10-25): `spec_tool.py check` is not yet implemented, so this list has **not** been reconciled against an automated coverage report. The first run in the Phase 6 gate will either confirm it or add/remove rows.
