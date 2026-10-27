---
id: T-001
title: Create the Cargo workspace, toolchain pins and the just entry point
milestone: M1
kind: feature
status: review
depends_on: []
requirements: [D-29, D-38, ARCH-01, CQ-01, CQ-02, CQ-03, CQ-04, CQ-07, CQ-08, CQ-16, SEC-13]
branch: m1-t001-cargo-workspace
budget_lines: 350
assignee: "codex"
---

# T-001: Create the Cargo workspace, toolchain pins and the just entry point

## Goal

`just check` runs formatting, lints, tests, dependency policy and spec checks over an empty but compiling Rust workspace whose crate layout and lint configuration match the architecture. `kinevo version` prints the version.

## Context

This is the root task: every later task builds on this layout and these lint rules.

> **D-29** (`spec/decisions.md`): Repository layout: Cargo workspace under `crates/`, web app under `web/`, Android shell under `android/`, specs under `spec/`, tasks under `tasks/`, scripts under `scripts/`.

> **D-38**: A root `justfile` defines `setup`, `dev`, `fmt`, `lint`, `test`, `check` (everything CI runs) and `spec` (spec/task checks). CI calls the same recipes, so a green `just check` locally predicts `ci-ok`.

> **ARCH-01** (`spec/02-architecture.md` §1): One executable `kinevo` with subcommands: `serve`, `migrate`, `admin`, `openapi`, `version`, `health`. *(This task implements only `version`; later tasks add the others.)*

> **Dependency rule** (`spec/02-architecture.md` §2): `kinevo-kernel ◀── kinevo-ports ◀── kinevo-mod-* ◀── kinevo-app`; `kinevo-kernel ◀── kinevo-db ◀── kinevo-mod-*`; `kinevo-http ◀── kinevo-mod-*`. `kinevo-mod-*` crates never depend on each other (CA-03).

> **CQ-02**: Rust `clippy::all` and `clippy::pedantic` as warnings promoted to errors in CI, with a short allow-list at workspace level (`module_name_repetitions`, `missing_errors_doc`, …) justified in `Cargo.toml`.
> **CQ-03**: `#![forbid(unsafe_code)]` in every crate.
> **CQ-04**: `unwrap`, `expect`, indexing that can panic, `unreachable!` and `todo!` are denied by clippy (`unwrap_used`, `expect_used`, `indexing_slicing`, `panic`, `todo`, `unimplemented`) outside tests and `main` startup validation.
> **CQ-07**: `SystemTime::now`, `Instant::now` (for business time), `Uuid::now_v7`, `rand::*` are forbidden outside `kinevo-app`'s wiring and `kinevo-db`'s test support (clippy `disallowed_methods` list in `clippy.toml`).
> **CQ-08**: No `f32`/`f64` in domain, persistence or API types (clippy `disallowed_types`).
> **CQ-16**: `cargo-deny` allow-lists licences (AGPL-compatible: MIT, Apache-2.0, BSD, ISC, MPL-2.0, Unicode, Zlib, CC0, 0BSD) and bans yanked/unmaintained crates.

> **Stack** (`spec/standards/open-source-stack.md` §1): Rust stable, edition 2024, `rust-toolchain.toml` pinning (1.99.x present locally).

## Scope

**In:**
- Workspace `Cargo.toml` with members for the M1 crates only: `kinevo-kernel`, `kinevo-ports`, `kinevo-db`, `kinevo-http`, `kinevo-app` (binary `kinevo`), `kinevo-mod-identity`, `kinevo-mod-admin`. Each is an empty library (the app has `main.rs`) with `#![forbid(unsafe_code)]` and the allowed dependency edges only (no code dependencies yet beyond what compiles).
- `[workspace.lints]` implementing CQ-02/03/04 and `float_arithmetic = "deny"`; each crate opts in with `[lints] workspace = true`.
- `clippy.toml` with `disallowed-methods` (CQ-07) and `disallowed-types` (CQ-08) where clippy supports them.
- `rustfmt.toml` (edition 2024, defaults otherwise), `rust-toolchain.toml` (stable channel pinned to the local 1.99 release, components `rustfmt`, `clippy`).
- `deny.toml` (licences per CQ-16, sources = crates.io only, advisories deny, bans: `multiple-versions = "warn"`).
- `justfile` with recipes `setup`, `fmt`, `lint`, `test`, `spec`, `check`, `dev` (prints "not available until T-039" and exits 0).
- `kinevo version` subcommand using `clap` derive, printing `kinevo <CARGO_PKG_VERSION>`.

**Out (do not do):**
- Any other subcommand, configuration, logging or HTTP code.
- Web, Docker or CI workflow files.
- `kinevo-scheduler` (M3) and the other module crates (their milestones).

## Files

| Path | Action | Notes |
|---|---|---|
| `Cargo.toml` | create | workspace, `resolver = "3"`, shared `[workspace.package]` (edition 2024, licence `AGPL-3.0-only`, repository URL), `[workspace.dependencies]` for `clap`, `[workspace.lints]` |
| `Cargo.lock` | create | committed (SEC-13) |
| `rust-toolchain.toml` | create | |
| `rustfmt.toml` | create | |
| `clippy.toml` | create | |
| `deny.toml` | create | |
| `justfile` | create | |
| `crates/kinevo-kernel/Cargo.toml`, `src/lib.rs` | create | empty lib |
| `crates/kinevo-ports/Cargo.toml`, `src/lib.rs` | create | depends on kernel |
| `crates/kinevo-db/Cargo.toml`, `src/lib.rs` | create | depends on kernel |
| `crates/kinevo-http/Cargo.toml`, `src/lib.rs` | create | depends on kernel, ports |
| `crates/kinevo-mod-identity/Cargo.toml`, `src/lib.rs` | create | depends on kernel, ports, db, http |
| `crates/kinevo-mod-admin/Cargo.toml`, `src/lib.rs` | create | same |
| `crates/kinevo-app/Cargo.toml`, `src/main.rs`, `src/cli.rs` | create | binary `kinevo`; depends on every crate |
| `crates/kinevo-app/tests/lint_config.rs` | create | AC-3 |

## Interfaces

```text
$ kinevo version
kinevo 0.0.0
$ kinevo --help        # lists `version` only for now
```

```rust
// crates/kinevo-app/src/cli.rs (shape)
#[derive(clap::Parser)] #[command(name = "kinevo")]
pub struct Cli { #[command(subcommand)] pub command: Command }
#[derive(clap::Subcommand)] pub enum Command { Version }
pub fn version_line() -> String  // "kinevo <version>"
```

`justfile` recipes (exact names, D-38):

| Recipe | Runs |
|---|---|
| `setup` | `rustup show` when `rustup` exists (installs the pinned toolchain; otherwise print a notice and use the system toolchain), `cargo install --locked cargo-deny cargo-nextest` if missing; mention `scripts/setup-dev.sh --all` in the notice (D-53) |
| `fmt` | `cargo fmt --all` |
| `lint` | `cargo fmt --all -- --check` and `cargo clippy --workspace --all-targets -- -D warnings` |
| `test` | `cargo nextest run --workspace` (fall back to `cargo test --workspace` if nextest is missing, with a printed notice) |
| `spec` | `python3 scripts/spec_tool.py check`, `python3 scripts/contrast.py`, `python3 scripts/tasks_index.py --check` |
| `check` | `lint`, `test`, `cargo deny check`, `spec` |

## Business rules

None. This task contains no business logic.

## Security

- `forbid(unsafe_code)` everywhere (CQ-03).
- `deny.toml` enforces licence and advisory policy (CQ-16, SEC-13).
- The lockfile is committed (SEC-13).

## Implementation notes

- Use `clippy::pedantic` at `warn` level and promote it with `-D warnings` in `just lint`. Set the restriction lints from CQ-04 to `deny` in `[workspace.lints.clippy]`. Allow them in tests with `#[cfg_attr(test, allow(...))]` at crate roots, giving a reason (CQ-20).
- `disallowed-methods` entries: `std::time::SystemTime::now`, `std::time::Instant::now`, `uuid::Uuid::now_v7`, `uuid::Uuid::new_v4`, `jiff::Timestamp::now`, `jiff::Zoned::now`, `rand::thread_rng`, `rand::random`. Clippy accepts paths to crates that are not dependencies yet.
- If the pinned clippy cannot ban the primitive types `f32`/`f64` through `disallowed-types`, keep `float_arithmetic = "deny"` and record this in the Report (*Deviations*). Do not invent a workaround.
- Keep crate `lib.rs` files to the crate doc comment plus `#![forbid(unsafe_code)]`.

## Acceptance

| # | Given / When / Then | Test name |
|---|---|---|
| AC-1 | **Given** a clean checkout **When** `cargo build --workspace` runs **Then** it succeeds with no warnings | `just lint` |
| AC-2 | **Given** the binary **When** `version_line()` is called **Then** it returns `kinevo ` followed by the package version | `kinevo_app::cli::tests::version_line_has_package_version` |
| AC-3 | **Given** `clippy.toml` **When** the test parses it **Then** every method listed in CQ-07 is in `disallowed-methods` | `lint_config::cq_07_disallowed_methods_listed` |
| AC-4 | **Given** the workspace **When** each crate's manifest is read **Then** `[lints] workspace = true` is set and every `lib.rs`/`main.rs` contains `#![forbid(unsafe_code)]` | `lint_config::cq_03_forbid_unsafe_everywhere` |
| AC-5 | **Given** the workspace **When** `just check` runs **Then** it exits 0 | `just check` |

## Tests

```
just check
cargo run -p kinevo-app -- version
```

## Definition of done

- [x] Every acceptance criterion has its named test, and it passes.
- [x] `just check` passes locally.
- [ ] Only the files listed under *Files* changed; diff within budget.
- [x] `clap` is the only new dependency (it is already in the stack map).
- [x] Report below is filled in.

## Report

### Summary

Created the M1 Cargo workspace, lint and dependency policy, just recipes, and `kinevo version`. The implementation was restored from the local stash after D-55 resolved Q-1. All five acceptance checks now pass.

### Acceptance results

| Criterion | Named test | Result |
|---|---|---|
| AC-1 | `just lint` | pass |
| AC-2 | `kinevo_app::cli::tests::version_line_has_package_version` | pass |
| AC-3 | `lint_config::cq_07_disallowed_methods_listed` | pass |
| AC-4 | `lint_config::cq_03_forbid_unsafe_everywhere` | pass |
| AC-5 | `just check` | pass |

### Commands run

| Command | Exit | Result |
|---|---:|---|
| Pre-implementation TDD red phase (recorded in the first attempt) | expected failures | Version test failed because the function was absent; lint tests failed because the workspace lint configuration was absent. |
| `scripts/preflight.sh` | 0 | Required tools and Docker/PostgreSQL were available. |
| `just check` (after restoring T-001 to `in_progress`) | 1 | Build, lint, tests, dependency checks, and spec checks passed; task-index check reported stale status data. |
| `python3 scripts/tasks_index.py` | 0 | Regenerated the index; 0 problems in 132 tasks. |
| `just lint` | 0 | Formatting and Clippy passed. |
| `just check` | 0 | Nextest: 3 passed, 0 skipped; cargo-deny: no errors and 7 unused-license warnings; spec tool: 0 findings in 60 files; contrast: 0 failures; task index: 0 problems in 132 tasks. |
| `cargo run -p kinevo-app -- version` | 0 | Printed `kinevo 0.0.0`. |

### Files changed

Implementation files listed in this task: `Cargo.lock`, `Cargo.toml`, `clippy.toml`, `deny.toml`, `justfile`, `rust-toolchain.toml`, `rustfmt.toml`; `crates/kinevo-app/Cargo.toml`, `crates/kinevo-app/src/cli.rs`, `crates/kinevo-app/src/main.rs`, `crates/kinevo-app/tests/lint_config.rs`; `crates/kinevo-db/Cargo.toml`, `crates/kinevo-db/src/lib.rs`; `crates/kinevo-http/Cargo.toml`, `crates/kinevo-http/src/lib.rs`; `crates/kinevo-kernel/Cargo.toml`, `crates/kinevo-kernel/src/lib.rs`; `crates/kinevo-mod-admin/Cargo.toml`, `crates/kinevo-mod-admin/src/lib.rs`; `crates/kinevo-mod-identity/Cargo.toml`, `crates/kinevo-mod-identity/src/lib.rs`; `crates/kinevo-ports/Cargo.toml`, `crates/kinevo-ports/src/lib.rs`. Workflow outputs: this task file and generated `tasks/index.md`.

### Deviations

The implementation adds 628 lines including the 238-line generated `Cargo.lock`; excluding the lockfile, the implementation adds 390 lines, 40 over the 350-line guidance. D-55 says budget overages are recorded and shipped, so this is not a blocker. Cargo-deny exits successfully but warns about seven unused license allowances. The first `just check` after restoring `in_progress` found the expected stale task index; the index was regenerated and the final check passed.

### Spec issues noticed

None.

### Environment-limited

None for T-001.

### Not done / not verified

No T-001 acceptance checks remain. The M1 integration task and downstream tasks have not started.

## Questions

- Q-1 (resolved by architect, D-55): Generated lockfile lines are excluded from the budget; exceeding the budget is recorded under Deviations and does not block the task.

Resolved (architect): D-55, Cargo.lock tidak dihitung, budget bukan blocker
