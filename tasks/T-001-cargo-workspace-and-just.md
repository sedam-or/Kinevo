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
| `setup` | `rustup show` (installs the pinned toolchain), `cargo install --locked cargo-deny cargo-nextest` if missing |
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

No application code was written. T-001 is blocked because AC-2's crate-qualified test path cannot be reconciled with the binary-only app files listed in this task without an unlisted library target or nonstandard module nesting.

### Acceptance results

| Criterion | Test name | Result |
|---|---|---|
| AC-1 | `just lint` | not run — blocked before implementation |
| AC-2 | `kinevo_app::cli::tests::version_line_has_package_version` | not run — see Q-1 |
| AC-3 | `lint_config::cq_07_disallowed_methods_listed` | not run — blocked before implementation |
| AC-4 | `lint_config::cq_03_forbid_unsafe_everywhere` | not run — blocked before implementation |
| AC-5 | `just check` | not run — blocked before implementation |

### Commands run

- `scripts/preflight.sh` — exit 127; `/bin/bash: line 1: scripts/preflight.sh: No such file or directory`.
- `gh auth status` — exit 0.
- `cargo --version` — exit 0; Cargo 1.99.1.
- `rustc --version` — exit 0; Rust 1.99.1.
- `PATH=/home/kepolu/.cargo/bin:$PATH rustup show` — exit 0; toolchain 1.99.1 and `wasm32-unknown-unknown` target installed.
- `node --version` — exit 0; v22.17.0.
- `pnpm --version` — exit 0; 9.15.9.
- `just --version` — exit 0; 1.59.0.
- `cargo nextest --version` — exit 0; 0.9.149.
- `cargo deny --version` — exit 0; 0.20.2.
- `cargo install wasm-pack --locked` — exit 0; wasm-pack was already installed under `/home/kepolu/.cargo/bin`.
- `PATH=/home/kepolu/.cargo/bin:$PATH wasm-pack --version` — exit 0; 0.15.0.
- `python3 --version` — exit 0; 3.14.8.
- `docker info --format '{{.ServerVersion}}'` — exit 0; 29.9.2.
- `python3 scripts/spec_tool.py check` — exit 0; 0 findings in 60 spec files.
- `python3 scripts/contrast.py` — exit 0; 0 failures.
- `python3 scripts/tasks_index.py --check` — exit 0; 0 problems in 105 tasks.
- `just lint` — not run; no task workspace or `justfile` was created before the block.
- `just check` — not run; no task workspace or `justfile` was created before the block.

### Files changed

Pending the generated index and final branch diff stat; only this task file and `tasks/index.md` are expected to change.

### Deviations

Implementation stopped under WORKFLOW.md §3 before code or tests. The requested preflight script is absent, so its available tool checks were run manually and recorded above.

### Spec issues noticed

The run instructions refer to D-51 and D-52, but `spec/decisions.md` ends at D-50; `spec/index.md` still describes the decision range as D-01 through D-37. These do not affect T-001's cited decisions.

### Not done / not verified

T-001 implementation and all five acceptance checks remain undone or unverified. No M1 gate was run, so later tasks were not started.

### Follow-up suggestions

Resolve Q-1 by aligning AC-2's test path with the binary target, or explicitly allowing a library target in the Files table.

## Questions

- Q-1 (implementer, 2026-10-25): AC-2 names `kinevo_app::cli::tests::version_line_has_package_version`, which denotes a `kinevo_app` crate containing a `cli` module, but the Files table allows only `crates/kinevo-app/src/main.rs` and `src/cli.rs` and describes the app as a binary named `kinevo`. The listed binary-only layout does not provide that crate-qualified test path; a normal binary unit test is under the binary target, while a library target needs additional wiring not listed here. The required source paths are currently absent, so no implementation was attempted.
  Options: (a) add `crates/kinevo-app/src/lib.rs` and define the library/binary wiring; (b) change the acceptance test path to the binary target path and keep the current Files scope.
  Recommendation: (b), because the task explicitly defines the app as a binary and limits the source files to `main.rs` and `cli.rs`.
