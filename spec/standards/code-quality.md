# Standard — Code quality (CQ)

Applies to all Rust, TypeScript and Kotlin code, tests, scripts and CI files. Enforced by CI (`12-testing.md`).
A rule violated for a good reason carries an inline exception **with a reason** (`#[allow(clippy::x)] // reason: …`,
`// eslint-disable-next-line rule -- reason: …`); exceptions without a reason fail CI (CQ-20). Complements `CLAUDE.md`
(think first, simplicity first, surgical changes, goal-driven execution), which binds every change.

| ID | Rule | Check |
|---|---|---|
| CQ-01 | **Formatting is not discussed.** Rust: `rustfmt` (default edition style); TS/JSON/CSS: Biome format; Kotlin: ktlint. CI runs the check mode; no style comments in reviews. | CI `fmt` |
| CQ-02 | **Lints deny by default.** Rust: `clippy::all` and `clippy::pedantic` as warnings promoted to errors in CI, with a short allow-list at workspace level (`module_name_repetitions`, `missing_errors_doc`, …) justified in `Cargo.toml`. TS: Biome recommended + the rules in CQ-12. | CI `lint` |
| CQ-03 | **No `unsafe`.** `#![forbid(unsafe_code)]` in every crate except a single, reviewed, documented exception per RFC. | compile |
| CQ-04 | **No panics in request paths.** `unwrap`, `expect`, indexing that can panic, `unreachable!` and `todo!` are denied by clippy (`unwrap_used`, `expect_used`, `indexing_slicing`, `panic`, `todo`, `unimplemented`) outside tests and `main` startup validation (which uses `expect` with a message naming the config key). | clippy |
| CQ-05 | **No business literals.** Any value that is a price, currency, duration, threshold, percentage, locale, time of day, URL or limit that an operator might want to change is a policy (`04-policies.md`) read through a typed accessor. Technical constants (HTTP header names, protocol versions, SQL column names, algorithm parameters recorded in `open-source-stack.md`) are allowed. | `check-no-literals` script + review |
| CQ-06 | **Newtypes for constrained values.** Ids, emails, titles, durations, basis points, money, instants, ranges, policy keys and permission keys are distinct types with private fields and validating constructors (`TryFrom`/`parse`); raw `String`/`i64` do not cross module boundaries. Invalid values are unrepresentable after construction. | review + architecture tests |
| CQ-07 | **Injected clock and ids.** `Clock` and `IdGen` are traits passed in; `SystemTime::now`, `Instant::now` (for business time), `Uuid::now_v7`, `rand::*` are forbidden outside `kinevo-app`'s wiring and `kinevo-db`'s test support (clippy `disallowed_methods` list in `clippy.toml`). TS: `Date.now`, `new Date()`, `Math.random` forbidden outside `lib/time` and `lib/ids` (Biome `noRestrictedGlobals` style rule via ESLint-compat or custom lint). | clippy / lint |
| CQ-08 | **Money and percent are integers.** No `f32`/`f64` in domain, persistence or API types (clippy `disallowed_types`). Display formatting is a UI concern. | clippy |
| CQ-09 | **Errors are typed.** Library crates use `thiserror` enums mapped to registry codes (`standards/error-handling.md`); `anyhow` only in `kinevo-app` startup and CLI glue. Every error that can reach a client implements `Into<ApiError>` with a registry code. Errors are never stringly typed. | review + compile |
| CQ-10 | **Secrets are not formattable.** Secret-bearing types implement neither `Debug` (custom redacting impl only), `Display`, `Serialize` (except through an explicit `expose()` call site), nor `Clone` unless unavoidable (then `Clone` is documented). | compile-fail tests |
| CQ-11 | **SQL.** Only `sqlx` with compile-time-checked queries (`query!`/`query_as!`) or runtime queries covered by a test that executes them; parameters only (never string interpolation); no `SELECT *`; queries live in `infra/` (CA-04). | review + sqlx offline check |
| CQ-12 | **TypeScript strictness.** `strict`, `noUncheckedIndexedAccess`, `exactOptionalPropertyTypes`, `noImplicitOverride`; no `any` (use `unknown` + narrowing); no non-null assertions outside tests; no `dangerouslySetInnerHTML`; no default exports except routes; no barrel files that re-export entire features. | tsc + lint |
| CQ-13 | **Small units.** Functions ≤ 60 lines and cyclomatic complexity ≤ 12 (clippy `cognitive_complexity` threshold 15 as warning→error); modules ≤ 600 lines; a file that grows beyond is split by responsibility, not by arbitrary size. | clippy + review |
| CQ-14 | **Naming.** Rust per API guidelines; domain terms are the glossary terms (`spec/index.md`): Goal, Milestone, Task, Subtask, Workspace, Hard Landscape, Override, Assignment, Draft, Focus Session, Anchor, Policy, Module. No synonyms ("event" means a domain event; a calendar commitment is a *landscape event*). | review |
| CQ-15 | **Comments explain why.** No comments that restate code. Public items have doc comments stating invariants, units and failure modes. A `// SPEC: <ID>` comment marks the code that implements a spec requirement (greppable traceability). | `check-spec-refs` script |
| CQ-16 | **Dependencies are adopted deliberately.** A new crate/package requires an entry in `standards/open-source-stack.md` (purpose, licence, maintainers, alternatives, security notes) in the same PR; `cargo-deny` allow-lists licences (AGPL-compatible: MIT, Apache-2.0, BSD, ISC, MPL-2.0, Unicode, Zlib, CC0, 0BSD) and bans yanked/unmaintained crates. | CI `deny`, `audit` |
| CQ-17 | **Tests are part of the change.** Every requirement ID touched has a test named after it (`fn id_fr_03_lockout_after_max_attempts`). A test is never weakened to pass; a flaky test is quarantined only with an issue link and a deadline. | `check-spec-refs` |
| CQ-18 | **Determinism in tests.** No sleeps for synchronisation; use the injected clock and explicit synchronisation. Randomised/property tests print their seed and the failing case is added as a fixture. | review |
| CQ-19 | **Logging.** Structured `tracing` events with fixed field names; allowed fields listed in `standards/security-by-design.md` SEC-04; no `println!`/`console.log` in committed code (lint). | lint |
| CQ-20 | **Lint exceptions require a reason** (see top). The count of exceptions is reported in CI and may not grow without a reviewer note. | `check-exceptions` |
| CQ-21 | **Accessibility is code quality.** UI components use the design-system primitives; custom interactive elements need role, name, state, keyboard support and a test (`09-design-system.md` §Accessibility). | axe + review |
| CQ-22 | **One way to do a thing.** Date/time, money, ids, i18n, HTTP, validation and logging each have one module in `kinevo-kernel`/`web/src/lib`; feature code calls it. | review |

## Review checklist (used by every PR; also by the implementing agent in `WORKFLOW.md` step 9)

1. Does each changed line trace to the task? (CLAUDE.md §3)
2. Are requirement IDs and tests linked? (CQ-17)
3. Any literal that should be a policy? (CQ-05)
4. Any new dependency? Entry added? (CQ-16)
5. Any new error path? Registry code, status, translation keys? (CQ-09)
6. Any log line? Fields allowed? (CQ-19, SEC-04)
7. Any new SQL? Tenant-scoped through `Tx`? (ARCH-22, CQ-11)
8. UI: all states in the catalogue implemented and tested? (FE-16)
