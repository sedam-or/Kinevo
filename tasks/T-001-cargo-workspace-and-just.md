---
id: T-001
title: Create the Cargo workspace, toolchain pins and the just entry point
milestone: M1
kind: feature
status: blocked
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

- [ ] Every acceptance criterion has its named test, and it passes.
- [ ] `just check` passes locally.
- [ ] Only the files listed under *Files* changed; diff within budget.
- [ ] `clap` is the only new dependency (it is already in the stack map).
- [ ] Report below is filled in.

## Report

### Summary

The M1 Cargo workspace, lint/dependency policy, `just` entry point, and `kinevo version` command were implemented locally. All five acceptance checks pass, but the implementation diff is 628 added lines across the task's listed implementation files, exceeding the 525-line block threshold (1.5 × the 350-line budget). The implementation is retained locally and is not included in the blocked-task commit pending clarification about whether generated `Cargo.lock` lines count.

### Acceptance results

| Criterion | Test name | Result |
|---|---|---|
| AC-1 | `just lint` | pass |
| AC-2 | `kinevo_app::cli::tests::version_line_has_package_version` | pass |
| AC-3 | `lint_config::cq_07_disallowed_methods_listed` | pass |
| AC-4 | `lint_config::cq_03_forbid_unsafe_everywhere` | pass |
| AC-5 | `just check` | pass; 3 tests passed, cargo-deny reported no errors, spec checks passed |

### Commands run

The TDD red phase failed as expected before implementation: the named CLI test initially failed because `version_line` did not exist; the lint configuration tests failed because the config and workspace lints did not exist; `just lint` and `just check` exited 1 with `error: no justfile found`.

| Command | Exit | Result |
|---|---:|---|
| `export PATH="$HOME/.cargo/bin:$PATH"; cargo test -p kinevo-app --bin kinevo version_line_has_package_version` | 0 | 1 passed |
| `export PATH="$HOME/.cargo/bin:$PATH"; cargo test -p kinevo-app --test lint_config` | 0 | 2 passed |
| `export PATH="$HOME/.cargo/bin:$PATH"; just fmt && just lint` | 101 | First formatting check was corrected; Clippy then required lower priority for `all` and `pedantic` lint groups. |
| `export PATH="$HOME/.cargo/bin:$PATH"; just lint && cargo test -p kinevo-app --bin kinevo version_line_has_package_version && cargo test -p kinevo-app --test lint_config` | 0 | Lint and all three named tests passed. |
| `export PATH="$HOME/.cargo/bin:$PATH"; just check && cargo run -p kinevo-app -- version` | 1 | Initial `cargo deny check` rejected `unmaintained = "deny"`; corrected to the supported `"all"` scope. |
| `export PATH="$HOME/.cargo/bin:$PATH"; cargo deny check` | 0 | All checks passed; seven unused license allowances were reported as warnings. |
| `export PATH="$HOME/.cargo/bin:$PATH"; just check && cargo run -p kinevo-app -- version` | 1 | Gates passed through spec checks; task index was stale while the task remained `in_progress`. |
| `export PATH="$HOME/.cargo/bin:$PATH"; python3 scripts/tasks_index.py && just check && cargo run -p kinevo-app -- version` | 0 | Task index: 0 problems in 132 tasks; nextest: 3 passed; spec tool: 0 findings; contrast: 0 failures; cargo-deny: no errors (7 unused-license warnings); version output: `kinevo 0.0.0`. |

### Files changed

Implementation diff before blocking: 628 insertions, 0 deletions across the 23 implementation paths below. `Cargo.lock` contributes 238 generated lines. The blocked-task commit will contain only this task report/status and the generated task index.

| Path | Added | Removed |
|---|---:|---:|
| `Cargo.lock` | 238 | 0 |
| `Cargo.toml` | 44 | 0 |
| `clippy.toml` | 11 | 0 |
| `crates/kinevo-app/Cargo.toml` | 23 | 0 |
| `crates/kinevo-app/src/cli.rs` | 31 | 0 |
| `crates/kinevo-app/src/main.rs` | 30 | 0 |
| `crates/kinevo-app/tests/lint_config.rs` | 80 | 0 |
| `crates/kinevo-db/Cargo.toml` | 12 | 0 |
| `crates/kinevo-db/src/lib.rs` | 3 | 0 |
| `crates/kinevo-http/Cargo.toml` | 13 | 0 |
| `crates/kinevo-http/src/lib.rs` | 3 | 0 |
| `crates/kinevo-kernel/Cargo.toml` | 9 | 0 |
| `crates/kinevo-kernel/src/lib.rs` | 3 | 0 |
| `crates/kinevo-mod-admin/Cargo.toml` | 15 | 0 |
| `crates/kinevo-mod-admin/src/lib.rs` | 3 | 0 |
| `crates/kinevo-mod-identity/Cargo.toml` | 15 | 0 |
| `crates/kinevo-mod-identity/src/lib.rs` | 3 | 0 |
| `crates/kinevo-ports/Cargo.toml` | 12 | 0 |
| `crates/kinevo-ports/src/lib.rs` | 3 | 0 |
| `deny.toml` | 32 | 0 |
| `justfile` | 41 | 0 |
| `rust-toolchain.toml` | 3 | 0 |
| `rustfmt.toml` | 1 | 0 |

### Deviations

No implementation deviation. The task is blocked solely by the line-budget rule pending the question below.

### Spec issues noticed

None.

### Not done / not verified

No environment-limited checks. The implementation code is not committed or pushed. All downstream tasks are blocked transitively by T-001 and were not started.

### Follow-up suggestions

Clarify in the task or workflow whether generated lockfile lines count toward `budget_lines`; if they do, adjust the task budget or scope before resuming.

## Questions

- Q-1 (implementer, 2026-10-25): Does the 238-line generated `Cargo.lock` count toward T-001's 350-line budget? The implementation diff is 628 added lines including the lockfile, exceeding the workflow's 525-line block threshold; excluding the lockfile leaves 390 implementation lines. The named acceptance checks pass, but the task's diff-budget DoD cannot pass under the literal total.
  Options: (a) count all changed lines and have the architect revise the budget or scope; (b) explicitly exclude generated lockfile lines from this budget check.
  Recommendation: (a), because WORKFLOW.md §2 says to compare the diff size against the task budget and does not exclude generated files.
