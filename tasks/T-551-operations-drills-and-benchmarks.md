---
id: T-551
title: Add the restore drill, upgrade test, readiness and shutdown checks and the benchmark harness
milestone: M11
kind: feature
status: todo
depends_on: [T-550]
requirements: [OPS-02, OPS-03, OPS-04, OPS-06, OPS-08, OPS-09, OPS-13, TST-08, TST-17, SEC-AC-46, SCH-FR-17]
branch: m11-t551-operations-drills-and-benchmarks
budget_lines: 400
assignee: ""
---

# T-551: Add the restore drill, upgrade test, readiness and shutdown checks and the benchmark harness

## Goal

Operators have scripted proof that backups restore, upgrades work, the process shuts down cleanly and the numbers quoted in docs come from a recorded benchmark.

## Context

> **OPS-06** restore drill scripted for CI nightly: dump a populated test instance (`pg_dump -Fc`), restore into a fresh database, start the app, run smoke journeys; a second drill restores **without** the master key and asserts content intact with secrets needing re-entry (`secrets_unreadable` state). `scripts/restore-drill.sh` uses the Compose stack from T-056 or `KINEVO_TEST_DATABASE_URL` when Docker is unavailable (the dev-postgres script, T-058 preflight). **OPS-04** upgrade test: start the app at the previous reference (`--from-ref <git ref>`, default the previous milestone tag/branch), create data through the API, then run the current binary against the same database and assert forward-only migration success, `/readyz`, and data presence; two versions never serve concurrently. **OPS-03** graceful shutdown on SIGTERM: stop accepting, finish in-flight requests (≤ 30 s), let jobs finish or release leases, exit 0 — test with a slow request. **OPS-02/09** `/readyz` degrades when the clock skews more than 5 s from the database: assert (these exist from T-039; add tests only for gaps and record which existed). **OPS-13** the daily retention jobs of all modules appear in `GET /admin/jobs/summary` and run with an injected clock — one test enumerating the descriptors.
> **TST-17 / SCH-FR-17 / OPS-08 benchmarks (not gates)**: `crates/kinevo-app/benches` or a script `scripts/bench.sh` records: scheduler 500 tasks × 28 days (from T-164), API p95 for `GET /today` with 5 000 tasks, idle RSS of the app and of Postgres, and writes `bench/results/<date>-<git-sha>.json` plus a short Markdown summary. Docs may cite only recorded results. Hardware and commit are part of the result.
> **Environment**: needs Docker or a local PostgreSQL ≥ 17. Without both, the drills are **environment-limited**; the scripts exit 3 with that message and the Report states it.

## Scope

**In:**
- `scripts/{restore-drill.sh,upgrade-test.sh,bench.sh}`, `crates/kinevo-app/tests/{shutdown,jobs_summary,readyz_skew}.rs` for missing gaps, bench harness code, `bench/README.md` and the results directory ignored except the summary schema, `just restore-drill`, `just upgrade-test`, `just bench`.

**Out:**
- Nightly workflows (Phase 5).

## Files

| Path | Action | Notes |
|---|---|---|
| `scripts/{restore-drill.sh,upgrade-test.sh,bench.sh}` | create |  |
| `crates/kinevo-app/tests/{shutdown,jobs_summary,readyz_skew}.rs` | create |  |
| `crates/kinevo-app/benches/today_p95.rs` | create | bench |
| `bench/README.md` | create |  |
| `scripts/tests/test_ops_scripts.py` | create | AC-1…AC-7 |
| `justfile` | modify | recipes |

## Interfaces

```text
$ scripts/restore-drill.sh [--without-master-key]    # exit 0 | 3 environment-limited
$ scripts/upgrade-test.sh --from-ref <ref>
$ scripts/bench.sh                                    # writes bench/results/<date>-<sha>.json
```

## Business rules

1. Drills use synthetic data only (`example.test`).
2. Benchmarks are reports, never gates.
3. Scripts exit 3 with a clear message when Docker/PostgreSQL are unavailable.

## Security

- The master key used in drills is a throwaway value generated per run.
- Dumps are written to a temp directory with mode 700 and deleted afterwards.

## Implementation notes

- If the previous reference cannot build (no network), the upgrade test falls back to applying the previous migration set against seeded data (the TST-18 path) and says so.

## Acceptance

| # | Given / When / Then | Test name |
|---|---|---|
| AC-1 | **Given** a populated instance **When** dumped and restored **Then** smoke journeys pass on the restored database | `test_ops_scripts::ops_06_restore_drill_smoke (needs PostgreSQL)` |
| AC-2 | **Given** a restore without the master key **When** started **Then** content is intact and secrets report unreadable | `test_ops_scripts::ops_06_restore_without_master_key (needs PostgreSQL)` |
| AC-3 | **Given** data created at the previous reference **When** the current binary migrates **Then** readiness and data hold | `test_ops_scripts::ops_04_upgrade_keeps_data (needs PostgreSQL)` |
| AC-4 | **Given** a slow in-flight request **When** SIGTERM arrives **Then** it completes and the process exits 0 within 30 s | `shutdown::ops_03_graceful_shutdown` |
| AC-5 | **Given** a clock skew over 5 s **When** `/readyz` is read **Then** degraded | `readyz_skew::ops_09_skew_degrades_readiness` |
| AC-6 | **Given** all module descriptors **When** retention jobs are listed **Then** each appears in the jobs summary | `jobs_summary::ops_13_retention_jobs_listed` |
| AC-7 | **Given** the bench harness **When** run **Then** a result file with hardware, commit and the four measurements is written | `test_ops_scripts::tst_17_bench_result_shape` |

## Tests

```
python3 -m unittest scripts.tests.test_ops_scripts -v   # DB-dependent cases environment-limited without Docker/PostgreSQL
cargo nextest run -p kinevo-app --features kinevo-db/testing
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
