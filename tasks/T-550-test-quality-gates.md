---
id: T-550
title: Add the coverage, abuse-case registry, log-hygiene, migration and fuzz/mutation gates
milestone: M11
kind: feature
status: todo
depends_on: [T-504]
requirements: [TST-01, TST-12, TST-13, TST-14, TST-15, TST-16, TST-18, TST-20, SEC-AC-45, SEC-13]
branch: m11-t550-test-quality-gates
budget_lines: 400
assignee: ""
---

# T-550: Add the coverage, abuse-case registry, log-hygiene, migration and fuzz/mutation gates

## Goal

The repository enforces, by scripts and tests, that every abuse case has a test, logs stay clean, migrations upgrade existing data, and the riskiest parsers are fuzzed.

## Context

> **TST-01** traceability: `scripts/check_traceability.py` lists every requirement id defined in `spec/` (via `spec_tool` definitions) that is referenced by no test name, `// SPEC: ID` comment or entry of `spec/traceability-exceptions.md`; advisory (prints the list, exit 0) unless `--strict`, which the M11 integration task runs and whose result it reports as-is (many `CLD-*`, `OPS-*`, `DST-*` ids are legitimately listed as exceptions). **TST-12** abuse-case tests are named `sec_ac_<nn>_<slug>` (Rust) / `SEC-AC-<nn> …` (Playwright) and listed in `security-by-design.md` §4; CI fails if a registry ID has no test (`scripts/check_sec_ac.py`, wired into `just spec`; read the registry table and search test sources). **TST-13 / SEC-AC-45** log hygiene: the E2E run uses fixture secrets, titles and emails with a recognisable marker; after the run, log files and audit `details` are scanned for the markers; any hit fails. **TST-14** coverage `[ASSUMED targets]` domain crates ≥ 90 %, overall Rust ≥ 75 %, web ≥ 70 %, via `cargo-llvm-cov` and Vitest coverage; advisory first (report, no failure) — the script prints the numbers and a non-zero exit only behind `--enforce`. **TST-15** mutation testing (advisory, nightly) with `cargo-mutants` on `kinevo-scheduler`, the landscape resolver, the task state machine and policy resolution. **TST-16** fuzzing (nightly short, weekly long) with `cargo-fuzz` for the ICS parser (T-154 target), note and canvas validators, the JCS canonicaliser and rrule expansion; crashes become regression fixtures under `fuzz/regressions/`. **TST-18** migration tests: each migration applies on an empty database and on the previous schema with seed data; destructive migrations need a backup note. **TST-20** concurrency tests use real parallel requests against PostgreSQL: a checker verifies the five named scenarios exist (duplicate sync ops, two applies, two starts, concurrent refresh, double setup finish).
> **Tooling availability**: `cargo-llvm-cov`, `cargo-mutants`, `cargo-fuzz` need installation and, for fuzz, a nightly toolchain; `scripts/dev-setup.sh` (or the existing `just setup`) installs them when network is available; otherwise the gate scripts print `environment-limited` and exit 0 in advisory mode. None of these may be required by `just check` except the registry check, the migration test and the log-hygiene test.

## Scope

**In:**
- `scripts/{check_sec_ac.py,check_traceability.py,coverage.sh,mutants.sh,fuzz.sh,check_concurrency_tests.py}`, `fuzz/fuzz_targets/{note_doc,canvas_scene,jcs,rrule}.rs` (+ the ICS target from T-154), `crates/kinevo-app/tests/{migrations_upgrade,log_hygiene}.rs`, the E2E log-scan step, `just` recipes `coverage`, `mutants`, `fuzz`, and `just spec` including the registry and concurrency checks.

**Out:**
- CI workflows (Phase 5) that call these recipes.

## Files

| Path | Action | Notes |
|---|---|---|
| `scripts/{check_sec_ac.py,check_concurrency_tests.py,coverage.sh,mutants.sh,fuzz.sh}` | create |  |
| `scripts/tests/test_gates.py` | create | AC-1…AC-6 |
| `fuzz/**` | create | targets and regressions dir |
| `crates/kinevo-app/tests/{migrations_upgrade,log_hygiene}.rs` | create | AC-4, AC-5 |
| `justfile` | modify | recipes |

## Interfaces

```text
$ python3 scripts/check_sec_ac.py        # exit 1 listing SEC-AC ids without a test
$ scripts/coverage.sh [--enforce]
```

## Business rules

1. A registry id without a test fails `just spec`.
2. Advisory gates never fail `just check` unless `--enforce` is passed.
3. Fuzz crashes are saved as regression fixtures, not ignored.

## Security

- Marker strings in log-hygiene tests are synthetic (`example.test`, random markers); no real data (PRV-08).
- The scripts never print secrets.

## Implementation notes

- The registry may contain ids whose tests live in tasks not yet merged in a stacked run; the checker must treat a missing test as failure only when `--strict` is passed, and report counts otherwise. `just spec` uses `--strict` only on the integration branch (the M11 integration task runs it with `--strict`).

## Acceptance

| # | Given / When / Then | Test name |
|---|---|---|
| AC-1 | **Given** a registry id with no test **When** the checker runs in strict mode **Then** exit 1 naming the id; with all present exit 0 | `test_gates::tst_12_registry_checker` |
| AC-2 | **Given** a fixture tree **When** the concurrency checker runs **Then** it names any of the five TST-20 scenarios that is missing | `test_gates::tst_20_concurrency_scenarios_present` |
| AC-3 | **Given** coverage tools missing **When** `coverage.sh` runs **Then** it prints `environment-limited` and exits 0 without `--enforce` | `test_gates::tst_14_advisory_when_tools_missing` |
| AC-4 | **Given** every migration **When** applied on an empty database and on the previous schema with seed data **Then** both succeed | `migrations_upgrade::tst_18_each_migration_upgrades_seeded_schema` |
| AC-5 | **Given** the E2E marker fixtures **When** logs and audit details are scanned **Then** no marker appears | `log_hygiene::sec_ac_45_no_markers_in_logs_or_audit` |
| AC-6 | **Given** the fuzz targets **When** built in check mode **Then** they compile (run only with nightly/network) | `test_gates::tst_16_fuzz_targets_exist_and_listed` |

## Tests

```
python3 -m unittest scripts.tests.test_gates -v
cargo nextest run -p kinevo-app --test migrations_upgrade --test log_hygiene --features kinevo-db/testing
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
