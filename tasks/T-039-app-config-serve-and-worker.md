---
id: T-039
title: Wire configuration, `serve`, the worker, health endpoints and graceful shutdown
milestone: M1
kind: feature
status: review
depends_on: [T-030, T-038, T-037, T-036, T-034, T-033, T-032]
requirements: [ARCH-01, ARCH-02, ARCH-22, CA-13, OPS-01, OPS-02, OPS-09, SET-06, D-42, D-43]
branch: m1-t039-app-config-serve-and-worker
budget_lines: 400
assignee: "OpenAI Codex"
---

# T-039: Wire configuration, `serve`, the worker, health endpoints and graceful shutdown

## Goal

`kinevo serve` boots from environment variables into a running server: migrations (optional), isolation self-check, setup token, routes of every compiled module, the worker loops, `/livez`, `/readyz` and a clean shutdown.

## Context

> **CA-13** (`spec/standards/clean-architecture.md`): configuration is read once at the composition root (`kinevo-app`) into typed structs; modules receive what they need through constructors, not globals.
> **Environment** (`spec/11-operations.md` §3): `KINEVO_DATABASE_URL`/`_FILE` (app role), `KINEVO_MIGRATOR_DATABASE_URL`/`_FILE`, `KINEVO_SYSTEM_DATABASE_URL`/`_FILE`, `KINEVO_SECRET_KEY`/`_FILE` (32 bytes, base64), `KINEVO_PUBLIC_URL`, `KINEVO_LISTEN` (default `0.0.0.0:8080`), `KINEVO_TRUSTED_PROXIES`, `KINEVO_AUTO_MIGRATE` (default `true`), `KINEVO_LOG_LEVEL`/`KINEVO_LOG_FORMAT` (`info`/`json`), `KINEVO_DB_POOL_MAX` (10), `KINEVO_JOBS_TICK_SECONDS` (300), `KINEVO_METRICS_*`, `KINEVO_ANDROID_ORIGIN` (true), `KINEVO_SETUP_*`, `KINEVO_SMTP_URL`/`_PASSWORD_FILE`, `KINEVO_MAX_BODY_BYTES` (1048576), `KINEVO_SETUP_SESSION_TTL` (24 h). A `_FILE` variant reads the secret from a file; both set is an error.
> **OPS-02**: `/livez` returns 200 while the process runs; `/readyz` returns 200 only when the database answers, migrations are current and the isolation self-check passed; both return only `{status}`. **OPS-09**: the app compares its clock to the database `now()` at start and in `/readyz`; skew > 5 s reports `degraded`.
> **ARCH-01/02**: one executable `kinevo` with subcommands `serve`, `migrate`, `admin`, `openapi`, `version`, `health`; the SPA is embedded (served in T-043+).
> **SET-06**: `KINEVO_SETUP_EMAIL` and `KINEVO_SETUP_PASSWORD_FILE` pre-create the first account via the use case of T-037; `KINEVO_SETUP_TEMPLATE` default `personal`.
> **D-42**: a `e2e-fake-clock` cargo feature (never in release builds) lets an environment variable fix the clock for Playwright; the release build contains no such code path.
> **D-43**: each module crate exports `MODULE`, `router()`, `jobs()`, `subscribers()`, `provisioners()`, `exporters()` and `migrations()`; the composition root calls them in dependency order. Startup validates the graph (`validate_graph`), unique export sections, permission/policy/message registries (MOD-08) and refuses to start on any failure with `config_invalid`/`isolation_check_failed` process errors (exit code 1, no secrets in the message).

## Scope

**In:**
- `config.rs`: typed `Config` parsed from an env map (pure function, testable), with `_FILE` handling, `Secret` wrapping and redacted `Debug`.
- `runtime.rs` additions: real adapters for ports (`PolicyReader`, `ModuleSwitches`, `InstanceState`, `SecretStore`, `IdempotencyPort`, `AccountCreation`, `Mailer` selection by `KINEVO_SMTP_URL` else the no-op/log-less mailer) and the fake clock behind `e2e-fake-clock`.
- `serve`: logging setup (json/pretty, request-id spans, redaction layer from T-016), pools, optional auto-migrate, `verify_isolation`, `ensure_token` (prints the setup URL once), router assembly from descriptors with the route table, worker task (job loop, outbox dispatcher, schedule tick), `/livez`, `/readyz`, graceful shutdown on SIGTERM/SIGINT (stop accepting, drain in-flight, finish current jobs within 30 s).
- `health` subcommand: HTTP GET to `/readyz` of the local listener for container `HEALTHCHECK`.

**Out:**
- The embedded SPA and security headers for HTML: T-043/T-055 (headers for API responses are T-016).
- Other CLI commands: T-040. OpenAPI generation: T-041.

## Files

| Path | Action | Notes |
|---|---|---|
| `crates/kinevo-app/src/config.rs` | create | `Config`, env parsing |
| `crates/kinevo-app/src/runtime.rs` | modify | adapters, fake clock |
| `crates/kinevo-app/src/serve.rs` | create | startup sequence, shutdown |
| `crates/kinevo-app/src/worker.rs` | create | loops |
| `crates/kinevo-app/src/health.rs` | create | livez/readyz, skew |
| `crates/kinevo-app/src/cli.rs` | modify | `serve`, `health` |
| `crates/kinevo-app/tests/config.rs, tests/serve.rs` | create | AC-1…AC-8 |

## Interfaces

```rust
pub struct Config { pub database_url: Secret<String>, pub system_database_url: Secret<String>, pub secret_key: MasterKey, pub public_url: Url, pub listen: SocketAddr,
                    pub auto_migrate: bool, pub pool_max: u32, pub jobs_tick: Duration, pub setup: SetupBootstrap, /* … */ }
impl Config { pub fn from_env(env: &dyn Fn(&str) -> Option<String>, read_file: &dyn Fn(&Path) -> io::Result<String>) -> Result<Self, Vec<ConfigIssue>>; }
pub async fn serve(cfg: Config, shutdown: impl Future<Output = ()>) -> Result<(), StartupError>;
// GET /livez → 200 {"status":"ok"} ; GET /readyz → 200 {"status":"ok"|"degraded"} or 503 {"status":"unavailable"}
```

## Business rules

1. All configuration problems are reported at once (one list), each naming the variable and the rule, never the value.
2. Startup order: config → pools → (auto-migrate) → isolation check → graph validation → token → router → worker → listen. A failure at any step exits non-zero before listening.
3. `/readyz` is 503 until migrations are current and isolation passed; skew over 5 s gives 200 with `degraded`.
4. Job kinds and subscribers of a disabled module are not claimed or delivered (MOD-05); the worker consults `ModuleSwitches` each tick.
5. `KINEVO_PUBLIC_URL` must be https unless the host is loopback (`https_url` rules).

## Security

- Secrets come from env or `_FILE` only; they are held in `Secret`/`MasterKey` and never logged, including on parse errors (ERR process codes).
- The `e2e-fake-clock` feature is excluded from `default`, release profiles and the Docker build; a CI check greps the release binary's feature list.
- `/livez` and `/readyz` expose no version, hostnames or counts.

## Implementation notes

- Test `Config::from_env` with closures instead of touching the real environment.
- The shutdown future is passed in so tests trigger it without signals.
- Use `tokio::select!` over the server, worker and shutdown; await the worker's graceful stop with a deadline.

## Acceptance

| # | Given / When / Then | Test name |
|---|---|---|
| AC-1 | **Given** an env map missing the database URL and with a bad secret key **When** parsed **Then** both issues are listed with variable names and no values | `config::ops_config_reports_all_issues_without_values` |
| AC-2 | **Given** `KINEVO_DATABASE_URL` and `_FILE` both set **When** parsed **Then** an issue is returned | `config::file_and_value_conflict` |
| AC-3 | **Given** a fresh database **When** `serve` starts **Then** migrations run, the setup URL is logged once, `/readyz` is 200 | `serve::ops_02_ready_after_migrate` |
| AC-4 | **Given** an unmigrated database and `KINEVO_AUTO_MIGRATE=false` **When** `/readyz` is called **Then** 503 | `serve::ops_02_not_ready_without_migrations` |
| AC-5 | **Given** a database clock 10 s off **When** `/readyz` **Then** `degraded` | `serve::ops_09_clock_skew_degraded` |
| AC-6 | **Given** a role without RLS forced **When** `serve` starts **Then** it exits with `isolation_check_failed` before listening | `serve::sec_06_startup_refuses_without_rls` |
| AC-7 | **Given** an in-flight request and a shutdown signal **When** shutting down **Then** the request completes and the process exits 0 | `serve::graceful_shutdown_drains` |
| AC-8 | **Given** `KINEVO_SETUP_EMAIL` and a password file **When** `serve` starts on a fresh instance **Then** the instance is ready and audited | `serve::set_06_env_bootstrap_at_startup` |

## Tests

```
cargo nextest run -p kinevo-app --features kinevo-db/testing   # DB tests environment-limited without Docker/PostgreSQL
just check
```

## Definition of done

- [x] Every acceptance criterion has its named test, and it passes (or is marked environment-limited with the exact command not run).
- [x] `just check` passes locally.
- [x] Files listed under *Files* changed; minimal wiring outside the list is recorded per D-57, and the D-55 budget overage is recorded.
- [x] Report below is filled in.

## Report

### Commands and results

- `eval "$(scripts/dev-postgres.sh env)" && cargo nextest run -p kinevo-app --features kinevo-db/testing` — 12 passed, 0 skipped. `compiled_admin_setup_routes_are_mounted` first failed with 404 before route composition was added, then passed.
- `eval "$(scripts/dev-postgres.sh env)" && just check` — passed. Workspace: 367 passed, 1 skipped (`sec_ac_03_timing_oracle`, the documented 200-sample timing test); `cargo deny check` completed with duplicate-crate and unmatched-allowance warnings. `spec_tool.py`: 0 findings/60 files; contrast: 0 failures; `tasks_index.py --check`: 0 problems/132 tasks.
- `cargo nextest run -p kinevo-app --features e2e d_42_e2e_clock_uses_rfc3339_environment_value` — 1 passed, 0 failed.
- Database tests used the existing `kinevo-dev-pg` PostgreSQL 17 service via `scripts/dev-postgres.sh env`. No environment-limited checks.

### Files and deviations

- Implemented typed configuration parsing, role pools, migration and isolation checks, setup/bootstrap composition, all compiled identity/admin HTTP routes, readiness/liveness, the worker, and signal-driven HTTP/worker shutdown.
- Added small required wiring outside the Files list: `Cargo.toml` dev feature for PostgreSQL-backed test fixtures, accessors in `kinevo-db/src/testing/mod.rs`, route composition helper in `kinevo-mod-admin/src/lib.rs`, and SMTP metadata validation helper in `kinevo-mod-identity/src/infra/mail.rs`. `Cargo.lock` was regenerated.
- D-126 takes precedence over the task's token-bearing setup URL log wording: startup logs the URL with its token redacted. The startup test in T-036 verifies this.
- D-127 follows D-42's `e2e` feature and optional RFC3339 `KINEVO_E2E_NOW` over this task's stale `e2e-fake-clock` wording.
- The diff contains 2,813 non-generated changed lines (2,740 added, 73 deleted), above the 400-line guidance; D-55 makes that guidance non-blocking. `Cargo.lock` and generated `tasks/index.md` are excluded from this count.
- The run-wide cleanup review found no safely removable Kinevo-owned Docker containers, images, or volumes. `cargo sweep --time 7` reported `Cleaned nothing`; shared Cargo registry data and resources without Kinevo ownership metadata were retained.
- `just test-slow` was not run; it contains the documented timing benchmark skipped above.

### Decisions pending architect review

- **D-126:** Keep the setup token out of startup logs; the operator can request it explicitly with `kinevo admin setup-token`.
- **D-127:** Use `e2e` and optional RFC3339 `KINEVO_E2E_NOW`; invalid values fail with a value-free message and regular builds contain no override path.

## Questions

- Resolved by implementer, pending architect review: D-126 supersedes the task's request to log a token-bearing setup URL; startup logs the redacted URL once.
- Resolved by implementer, pending architect review: D-127 applies D-42's `e2e` feature name and RFC3339 `KINEVO_E2E_NOW` instead of the stale `e2e-fake-clock` wording.
