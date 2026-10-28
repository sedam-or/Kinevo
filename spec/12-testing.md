# 12 — Testing and quality gates

Depends on: all specs. Prefix: `TST`. Principles: tests prove requirements (CQ-17); a test is never weakened to pass; time and ids are injected (CQ-07); security controls have named abuse-case tests; unexecuted checks are never reported as passed.

## 1. Test pyramid

| Level | Scope | Tools | Database | Speed target |
|---|---|---|---|---|
| **Unit (domain)** | pure functions, state machines, value objects, `kinevo-scheduler`, `landscape` resolver, validation kinds | `cargo nextest`, `proptest`, `insta` (snapshots) | none | whole suite < 30 s |
| **Component (module)** | use cases with real repositories | `nextest` + one shared PostgreSQL 17 server from `scripts/dev-postgres.sh` (CI may use its PostgreSQL service, 17/18 matrix) | real, unique database per test, connected **as `kinevo_app`** | < 3 min |
| **API / contract** | HTTP routes end-to-end in-process, OpenAPI conformance, auth, RLS, rate limits | `axum` test server + generated client; `schemathesis`-style property checks against the OpenAPI document (nightly) | real | < 5 min |
| **Architecture** | dependency graph, forbidden imports, tables-per-module, route descriptors (CA-05) | custom Rust test + `cargo metadata` | none | < 20 s |
| **Web unit/component** | components, hooks, forms, i18n, offline queue | Vitest, Testing Library, MSW (from OpenAPI), `fake-indexeddb` | none | < 2 min |
| **E2E journeys** | user journeys (`standards/user-journeys.md`) on Chromium, Firefox, WebKit | Playwright + `@axe-core/playwright` | real stack in Docker | < 15 min |
| **Android** | shell plugins, secure store, deep link, CORS, offline | JUnit + Espresso/accessibility checks on an emulator | real server in Docker | nightly / release |
| **Non-functional** | benchmarks (scheduler, API), fuzzing (ICS, note/scene validators), mutation testing, restore drill | `criterion`, `cargo-fuzz`, `cargo-mutants`, scripts | real | nightly |

## 2. Requirements

| ID | Requirement | Acceptance |
|---|---|---|
| TST-01 | **Traceability.** Every requirement ID in `spec/` (patterns `[A-Z]+-FR-\d+`, `SEC-AC-\d+`, `[A-Z]{2,5}-R\d+`, `ARCH-\d+`, etc.) is referenced by at least one test name/comment (`// SPEC: ID`), or is marked in `spec/traceability-exceptions.md` as `manual` or `environment-limited` with a reason. `scripts/spec_tool.py check` reports orphan IDs. | CI `spec-check` |
| TST-02 | **Determinism.** No test depends on wall-clock time, network, or random seeds not printed; tests use the injected `Clock`/`IdGen`; flaky tests are quarantined with an issue and a deadline. | CI flake report |
| TST-03 | **Shared fixtures.** `spec/fixtures/validation/*.json` is consumed by Rust and TypeScript tests (VAL §5). | Both suites green |
| TST-04 | **Contract tests.** The generated OpenAPI document is committed (`contracts/openapi.json`); CI regenerates and fails on drift; the TS client types are regenerated and must match; schema files under `contracts/` pass the compatibility check against the previous release (CON-C2). | CI `openapi-drift` |
| TST-05 | **Scheduler golden + parity.** `kinevo-scheduler` has golden JCS fixtures (`scheduler_input_v1` → `scheduler_output_v1`); the same fixtures run natively and in the WASM build (node/browser) and must be byte-identical; permutation property test (shuffling inputs does not change output). Legacy-derived fixtures follow SCH §12.4 (differences listed, never silently adopted). | CI `scheduler-parity` |
| TST-06 | **Isolation tests** run as `kinevo_app`: for every tenant table, a generated test inserts rows for two accounts and asserts account A can neither see nor write B's rows, even with a deliberately unfiltered `SELECT`/`UPDATE`/`DELETE`; and asserts RLS is enabled+forced (SEC-AC-01/02). | CI `isolation` |
| TST-07 | **Module matrix.** CI runs the core journey J1 with every toggleable module off, and each toggleable module's journey with only its requirements on (MOD-07). | CI `module-matrix` |
| TST-08 | **Upgrade test.** A database dump produced by release N−1 (kept as a fixture from 1.1 on) is restored, the new image migrates it, and smoke journeys pass. Before the first release, a self-upgrade test applies migrations one by one and checks idempotent checksums (CA-18). | CI `upgrade` |
| TST-09 | **No business literals.** `scripts/check-no-literals` (created in M1) scans Rust/TS for numeric/string literals in a deny-list of contexts (durations, percentages, currency codes, time-of-day strings, URLs) outside allow-listed files; a seeded violation fixture proves it fails. | CI |
| TST-10 | **Contrast gate.** `python3 scripts/contrast.py` exits 0 (all pairs pass, all colours in gamut); from M1 it reads `tokens.json`. | CI `contrast` |
| TST-11 | **Accessibility gate.** axe with tags `wcag2a, wcag2aa, wcag21aa, wcag22aa` on every route and every state in the UX catalogue; zero serious/critical violations; keyboard walk tests for dialogs and the command palette. | CI `a11y` |
| TST-12 | **Abuse-case tests** are named `sec_ac_<nn>_<slug>` (Rust) / `SEC-AC-<nn> …` (Playwright), listed in `security-by-design.md` §4; CI fails if a registry ID has no test. | CI `spec-check` |
| TST-13 | **Log hygiene (SEC-AC-45).** The E2E run uses fixture secrets/titles/emails with a recognisable marker; after the run, log files and audit `details` are scanned for the markers; any hit fails the build. | CI |
| TST-14 | **Coverage** `[ASSUMED targets]`: domain crates ≥ 90 % line coverage, overall Rust ≥ 75 %, web ≥ 70 %; reported by `cargo-llvm-cov`/Vitest; a drop > 2 points without justification fails the PR check (advisory first, blocking from 1.0). | CI report |
| TST-15 | **Mutation testing** (advisory, nightly) on `kinevo-scheduler`, landscape resolver, task state machine and policy resolution; surviving mutants open issues. | nightly |
| TST-16 | **Fuzzing** (nightly, short; weekly long): ICS parser, note/canvas validators, JCS canonicaliser, rrule expansion; crashes become regression fixtures. | nightly |
| TST-17 | **Performance benchmarks** (not gates): scheduler 500 tasks × 28 days; API p95 for Today with 5 000 tasks; reports stored per release; claims in docs cite them (SCH-FR-17, OPS-08). | bench job |
| TST-18 | **Migration tests**: each migration applies on an empty DB and on the previous schema with seed data; destructive migrations require a backup note in the PR. | CI |
| TST-19 | **Fault injection** for multi-step transactions (setup finish, apply draft, complete session, accept proposal, reconcile): the test kills the transaction after each statement and asserts no partial state (SET-02, SCH-FR-09, EXE-FR-05, AI-FR-06, SYN-FR-02). | CI |
| TST-20 | **Concurrency tests** use real parallel requests against PostgreSQL (not mocks) for: duplicate sync ops, two applies, two starts, concurrent refresh, double setup finish. | CI |

## 3. CI jobs (aggregate required check `ci-ok`, D-15)

| Job | Contents | Runs |
|---|---|---|
| `fmt` | rustfmt, Biome format, ktlint | every PR |
| `lint` | clippy (deny warnings), Biome lint, `check-no-literals`, `check-exceptions`, typos, actionlint | every PR |
| `rust-test` | nextest unit + component + API on PG 17 (+18 matrix) | every PR |
| `architecture` | CA-05 tests, `cargo-deny` (licences/bans/sources), `cargo-audit` | every PR |
| `scheduler-parity` | native + WASM golden equality | every PR touching scheduler/kernel |
| `web` | typecheck, unit, build, bundle-size report, i18n key check, `npm audit --omit=dev` | every PR |
| `openapi-drift` | regenerate OpenAPI + TS types, schema compat | every PR |
| `spec-check` | `spec_tool.py check`, `tasks-index --check`, traceability, abuse-case registry, link check | every PR |
| `contrast` | `scripts/contrast.py` | every PR touching tokens/spec |
| `e2e` | Playwright journeys on 3 browsers + a11y | every PR (matrix), full on main |
| `module-matrix` | TST-07 | main + PRs touching module descriptors |
| `image` | Docker build, Trivy/Grype scan, container smoke (`health`, non-root, read-only), size report | PRs touching Docker/infra + main |
| `secrets` | gitleaks | every push |
| `release` | on tag: build, sign, SBOM, provenance, attach Android artifacts | tags |
| `nightly` | restore drill, upgrade test, mutation, fuzz, benchmarks, Android emulator, long property tests | scheduled |
| `ci-ok` | needs all required jobs above; the only required status check | always |

**Environment-limited checks** (cannot run in an unattended/offline agent environment): hosted CI itself, Playwright WebKit/Firefox where browsers are not installed, Android emulator, Docker-in-Docker image scan, real payment/AI providers. Tasks mark them `environment-limited: run the local equivalent and state what was not run` (WORKFLOW). They are never reported as passed.

## 4. Test data and naming

| Rule | Detail |
|---|---|
| Fixtures | synthetic only; emails `…@example.test`; no real names, addresses or schedules; fixture content is marked with `[fixture]` where visible |
| Names | Rust `fn <id_lower>_<behaviour>()` e.g. `scheduler_fr_01_permuting_inputs_does_not_change_output`; TS `describe('SCH-FR-01', …)` |
| Builders | `kinevo-db::testing` offers `TestApp` (database-per-test on the shared PostgreSQL server, injected clock, fake gateway/provider, seeded accounts) |
| Time | tests set the clock explicitly, including DST boundary dates for Europe/London, America/New_York, Asia/Jakarta, Australia/Lord_Howe (30-minute DST) |
| Clean-up | each test owns a uniquely named database that is dropped at the end; parallel safe; tests never start per-test containers |

## 5. Local implementation-run resource limits (D-102–D-105)

Database tests use the single server from `scripts/dev-postgres.sh env` and require `KINEVO_TEST_DATABASE_URL`; every test creates and drops its own uniquely named database. Testcontainers are not a database fallback. Local database startup may use only the already available `postgres:17-alpine` image with pulls disabled, or local PostgreSQL. If neither is available, report the exact database command as `environment-limited`.

Before each build, test, or container start, inspect `df -h /`. Below 20 GB free, prune only Kinevo-owned stopped containers and dangling images after checking ownership, and report the exact removals; do not remove volumes, named images, active containers, or resources from another project. Stop if free space remains below 15 GB. Do not build or pull Docker images. Install `cargo-sweep` once and run `CARGO_TARGET_DIR=/home/kepolu/project/open-source/nivaras/Kinevo/target cargo sweep --time 7` at each milestone boundary; do not use `cargo clean` or manually delete `target/`. Development builds use `[profile.dev] debug = 0`, overridable locally with `CARGO_PROFILE_DEV_DEBUG=2`.
