---
id: T-553
title: Generate the licence ledger and enforce asset and font provenance
milestone: M11
kind: feature
status: todo
depends_on: [T-550]
requirements: [SEC-13, CQ-16, D-23, D-48]
branch: m11-t553-licence-and-provenance-audit
budget_lines: 360
assignee: ""
---

# T-553: Generate the licence ledger and enforce asset and font provenance

## Goal

Every dependency licence and every shipped asset is listed with its provenance by a generated, checked ledger, and the open questions about third-party text are surfaced instead of assumed.

## Context

> **1.0 gate "Legal/provenance"** (`spec/13-roadmap.md` §3): licence ledger complete; assets have provenance rows; fonts licences recorded; the Karpathy `CLAUDE.md` permission resolved (D-23). **CQ-16** `cargo-deny` licence allow-list (AGPL-compatible: MIT, Apache-2.0, BSD, ISC, MPL-2.0, Unicode, Zlib, CC0, 0BSD). Fonts: Fraunces, Inter, JetBrains Mono under SIL OFL-1.1 (stack map), icons from lucide (ISC) per T-044 provenance entries.
> **Ledger generation**: `scripts/licence_ledger.py` merges `cargo deny list --format json` (or `cargo metadata` + `deny.toml` as fallback), `pnpm --dir web licenses list --json --prod` and the Gradle dependency list from `android/` into `legal/LICENCE-LEDGER.md` (name, version, licence, source URL) and `legal/THIRD-PARTY-NOTICES.md`; the check mode fails when a dependency has no licence, an unknown licence, or one outside the allow-list, or when the committed ledger is stale versus the lockfiles. **Asset provenance**: `legal/ASSET-PROVENANCE.md` has one row per file under `web/public/`, `web/src/assets/`, `android/app/src/main/res/` and `docs/assets/` (source, author, licence, date, modification); `scripts/check_provenance.py` fails on any shipped asset file without a row and on rows without files; font files must carry the OFL text file next to them. **Karpathy `CLAUDE.md`** (D-23): the check reports its status as `OPEN — maintainer must confirm the licence/permission` and never marks it resolved; same for any other file whose provenance is unknown.
> **No guessing**: unknown licences are reported as `UNKNOWN` and fail the check; the script never infers a licence from a name.

## Scope

**In:**
- `scripts/{licence_ledger.py,check_provenance.py}`, generated `legal/*.md`, initial provenance rows from existing assets (fonts, icons, favicons, generated brand placeholders from T-254 noting they are placeholders), `just legal` and inclusion of the check in `just spec`.

**Out:**
- Deciding any licence question (maintainer).

## Files

| Path | Action | Notes |
|---|---|---|
| `scripts/{licence_ledger.py,check_provenance.py}` | create |  |
| `legal/{LICENCE-LEDGER.md,THIRD-PARTY-NOTICES.md,ASSET-PROVENANCE.md}` | create | generated and maintained |
| `scripts/tests/test_legal.py` | create | AC-1…AC-6 |
| `justfile` | modify | `legal`, spec inclusion |

## Interfaces

```text
$ python3 scripts/licence_ledger.py --check    # exit 1 on unknown/disallowed/stale
$ python3 scripts/check_provenance.py
```

## Business rules

1. Unknown ⇒ failure, never a guess.
2. The ledger is generated; hand edits are overwritten and the check detects drift.
3. D-23 stays `OPEN` until a maintainer edits the decision file.

## Security

- The scripts read lockfiles and metadata only; no network access is required for the check (the generation may use already-installed tools).

## Implementation notes

- When `cargo-deny` or `pnpm` is unavailable the script exits 3 `environment-limited` without rewriting the ledger.

## Acceptance

| # | Given / When / Then | Test name |
|---|---|---|
| AC-1 | **Given** a dependency with a disallowed licence **When** the check runs **Then** exit 1 naming it | `test_legal::cq_16_disallowed_licence_fails` |
| AC-2 | **Given** a dependency with no licence data **When** the check runs **Then** `UNKNOWN` and exit 1 | `test_legal::unknown_licence_fails` |
| AC-3 | **Given** lockfile changes without regeneration **When** the check runs **Then** stale ledger failure | `test_legal::stale_ledger_detected` |
| AC-4 | **Given** a shipped asset with no provenance row **When** checked **Then** failure naming the file | `test_legal::asset_without_provenance_fails` |
| AC-5 | **Given** font files without the OFL text **When** checked **Then** failure | `test_legal::fonts_require_licence_text` |
| AC-6 | **Given** the Karpathy `CLAUDE.md` question **When** the report is printed **Then** it says `OPEN` and exits 0 only because it is a maintainer item | `test_legal::d_23_reported_open` |

## Tests

```
python3 -m unittest scripts.tests.test_legal -v
python3 scripts/licence_ledger.py --check   # environment-limited without cargo-deny/pnpm
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
