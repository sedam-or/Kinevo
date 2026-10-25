# 02 — Architecture

Depends on: `01-product.md`, `decisions.md`. Requirement prefix: `ARCH`. Standards that bind this
file: `standards/clean-architecture.md` (CA), `standards/code-quality.md` (CQ),
`standards/open-source-stack.md` (versions and reasons).

## 1. Shape: a modular monolith in one binary

```
                         ┌────────────────────────── kinevo (single binary) ───────────────────────────┐
 Browser PWA ──HTTPS──▶  │ axum router ─▶ module HTTP layer ─▶ application use cases ─▶ domain (pure)    │
 Android shell ─────────▶│        ▲                                   │                                 │
                         │  embedded SPA (rust-embed)                  ▼                                 │
                         │                               ports (traits) ─▶ repositories (sqlx)           │
                         │  job worker (same process) ◀── outbox/queue tables ◀───────────┐              │
                         └──────────────────────────────────────────────────────────────┬─┴──────────────┘
                                                                                        ▼
                                                                                   PostgreSQL
```

| ID | Requirement |
|---|---|
| ARCH-01 | One executable `kinevo` with subcommands: `serve` (HTTP + worker), `migrate`, `admin` (CLI admin tasks), `openapi` (print the OpenAPI document), `version`, `health` (exit code only, for container health checks). |
| ARCH-02 | The web app is built to static files and embedded into the binary at compile time. `serve` needs no other files. |
| ARCH-03 | PostgreSQL (version policy in `standards/open-source-stack.md`) is the only required external service. SMTP, AI providers and payment gateways are optional outbound integrations. |
| ARCH-04 | The worker runs in the `serve` process by default. `serve --no-worker` and `kinevo worker` allow splitting later without code changes. |
| ARCH-05 | All time-dependent logic uses an injected `Clock`. No domain or application code calls a wall-clock function directly (CQ-07). |
| ARCH-06 | The scheduler is a separate pure crate usable from native code and WASM (D-22). |

## 2. Workspace layout (D-29)

```
Cargo.toml                    # workspace
crates/
  kinevo-kernel/              # shared kernel: ids, Money, TimeRange, Clock, errors registry, secret wrappers, pagination, i18n keys
  kinevo-ports/               # cross-module ports (traits) + DTOs + domain event types
  kinevo-scheduler/           # PURE: no I/O, no clock, no rand, no async; native + wasm32 targets
  kinevo-mod-identity/        # one crate per module; see §3
  kinevo-mod-admin/
  kinevo-mod-workspaces/
  kinevo-mod-goals/
  kinevo-mod-tasks/
  kinevo-mod-landscape/
  kinevo-mod-scheduler/       # application + infra around kinevo-scheduler (drafts, runs, locks)
  kinevo-mod-execution/
  kinevo-mod-progress/
  kinevo-mod-sync/
  kinevo-mod-ai/
  kinevo-mod-knowledge/
  kinevo-mod-canvas/
  kinevo-mod-billing/
  kinevo-db/                  # pool, transaction helper (sets RLS context), migration runner, test support
  kinevo-http/                # shared HTTP middleware: auth, request id, error mapping, rate limit, OpenAPI assembly
  kinevo-app/                 # composition root + binary `kinevo`
web/                          # React SPA (08-frontend.md)
android/                      # Capacitor shell + Kotlin plugins (modules/android.md)
spec/  tasks/  scripts/  legacy/
```

Dependency rule (enforced by `cargo-deny` bans + a CI script, CA-05):

```
kinevo-kernel ◀── kinevo-ports ◀── kinevo-mod-*  ◀── kinevo-app
kinevo-kernel ◀── kinevo-scheduler ◀── kinevo-mod-scheduler
kinevo-kernel ◀── kinevo-db ◀── kinevo-mod-*        kinevo-http ◀── kinevo-mod-*
```

- `kinevo-mod-*` crates **never depend on each other** (CA-03).
- `kinevo-scheduler` depends only on `kinevo-kernel`'s pure types (`TimeRange`, ids); `kinevo-kernel` has no async, no sqlx, no axum (CA-06).
- Only `kinevo-app` depends on all modules.

## 3. Inside a module crate (CA-02)

```
src/
  lib.rs            # exposes: Module descriptor, router(), migrations(), jobs(), subscribers()
  domain/           # entities, value objects, domain services, policies' typed structs. PURE (no sqlx, no axum, no clock)
  app/              # use cases ("recipes"): validate → load → call domain → persist → emit event
  ports.rs          # traits this module needs from others, re-exported from kinevo-ports
  infra/            # repositories (sqlx) on the module's OWN tables only; outbound adapters
  http/             # axum handlers + request/response DTOs + OpenAPI annotations; no business logic
  migrations/       # SQL files owned by the module
```

- Business decisions live **only** in `domain/` (CA-01). Handlers translate; use cases orchestrate; repositories persist.
- A use case takes `&Ctx` (account id, clock, request id), loads through repository traits, and returns a typed result. It runs inside exactly one `Tx` when it changes more than one row (CA-08).
- SQL strings appear only in `infra/` and only reference the module's own tables (CA-04). Cross-module reads go through a trait in `kinevo-ports` implemented by the owning module.

## 4. Module descriptor and wiring (details in `03-module-system.md`)

Each module exports one `const MODULE: ModuleDescriptor` (key, display-name message key, core/toggleable, dependencies, permissions, policies, migrations, routes, jobs, event subscriptions). `kinevo-app` collects descriptors, validates the dependency graph at startup, and registers only enabled modules. The descriptor is pure data (D-43); routes, jobs, subscribers, provisioners, exporters and migrations are exported as plain functions of the module crate and wired by `kinevo-app`.

## 5. Cross-module communication

| Need | Mechanism | Example |
|---|---|---|
| Synchronous read of another module's data | Port trait in `kinevo-ports`, implemented by the owner, injected by `kinevo-app` | `scheduler` reads open tasks through `TaskReadPort` |
| Synchronous command to another module | Port trait (explicit, rare) | `ai` accepting a proposal calls `CreateMilestonesPort` + `CreateTasksPort` |
| Reaction to something that happened | Domain event through the **outbox** | `execution` emits `TaskCompleted`; `progress` and `scheduler` subscribe |

**Platform ports (D-41).** `PolicyReader`, `ModuleSwitches`, `InstanceState` and `SecretStore` live in `kinevo-ports` and are implemented by `admin`; `AccountProvisioner` (MOD-10) and `AccountExporter` (MOD-11) are implemented by any module that owns per-account data. The platform tables `audit_log`, `jobs`, `outbox_*` and `idempotency_keys` are reached only through `kinevo-db` APIs.

**Outbox (ARCH-10).** A use case inserts event rows into `outbox_events` in the same transaction as the state change. A dispatcher job reads undelivered rows `FOR UPDATE SKIP LOCKED` in id order per account, calls each subscriber, and marks delivery per `(event_id, subscriber)` in `outbox_deliveries`. Delivery is **at-least-once**; every subscriber **must be idempotent** keyed by `event_id`. Failed deliveries retry with the backoff policy `jobs.retry.*` (`04-policies.md`) and then park as `dead` with an audit entry; an admin can retry them.

**Disabled module behaviour (ARCH-11).** A disabled module registers no routes, jobs or subscribers. Its events are still recorded in the outbox with no subscriber from that module. Its tables and data are **retained** (not dropped) and become visible again when the module is re-enabled. Migrations for every compiled module always run, regardless of the switch.

## 6. Data and isolation (D-08)

| ID | Requirement |
|---|---|
| ARCH-20 | Every tenant-owned table has `account_id uuid not null` and RLS **enabled and forced** with policy `USING (account_id = current_setting('app.account_id', true)::uuid) WITH CHECK (same)`. |
| ARCH-21 | The application connects with role `kinevo_app` (no `SUPERUSER`, no `BYPASSRLS`, not the table owner). Migrations run with role `kinevo_migrator` (owner). Roles are created by `kinevo migrate` or by the documented bootstrap SQL. |
| ARCH-22 | `kinevo-db::Tx::begin(account_id)` executes `SET LOCAL app.account_id = $1` (via `set_config(..., true)`) as the first statement. Repositories take `&mut Tx` and cannot be constructed without one. Code outside `kinevo-db` cannot obtain a raw connection to tenant tables (CA-09). |
| ARCH-23 | Instance-level tables (`instance_settings`, `instance_modules`, `_sqlx_migrations`, `jobs` bookkeeping that is not per-account) have no `account_id` and are accessed only through repositories that require the `instance_admin` permission or the system context. |
| ARCH-24 | Three database contexts exist, all created only by `kinevo-db` (ADR-0011): `Tx::begin(account)` (`kinevo_app`, every authenticated request and per-account job), `Tx::begin_admin(account)` (`kinevo_app` + `app.instance_admin`, for instance-level writes by an `instance_admin` and setup Finish) and `SystemTx` (`kinevo_system`, used only for login lookup/counters, refresh rotation, worker claim/dispatch and instance maintenance). The system role has narrowly granted privileges and reaches tenant data only through the role-scoped `system_auth` policies on the auth tables (SEC-06). |
| ARCH-25 | Ids are UUIDv7 generated by the application (`IdGen` port). Timestamps are `timestamptz` UTC. Mutable aggregates have `version bigint not null default 1`, incremented on every write (optimistic concurrency, `07-api-conventions.md` §7). |

## 7. Background jobs and queue (D-13)

- Table `jobs` is the queue: `id, account_id null, kind, payload jsonb, run_at, attempts, max_attempts, state, locked_by, locked_until, last_error_code, created_at`.
- Workers claim with `SELECT ... WHERE state='queued' AND run_at <= now() ORDER BY run_at, id FOR UPDATE SKIP LOCKED LIMIT n`, then `UPDATE state='running', locked_until = now() + lease`.
- A job handler is idempotent. A crashed worker's lease expires (`jobs.lease_seconds`) and the job is re-claimed.
- Recurring jobs (e.g. weekly draft preparation, pruning) are defined by modules as `JobSchedule { kind, cadence_policy_key }` and materialised by a leader-less "tick" job: each tick inserts the next occurrence with a unique key `(kind, account_id, scheduled_for)` using `ON CONFLICT DO NOTHING`. Cadences are policies, never constants.
- `now()` for job scheduling comes from the injected `Clock`.

## 8. Caching (details in `standards/containers-and-caching.md`)

In-process caches only (`moka`): instance settings, module switches, policy snapshots, permission sets, rate-limit counters. Every cache has an explicit key, TTL and invalidation trigger listed in that file. Cache loss never changes correctness.

## 9. Configuration

Precedence (high → low): command-line flag → environment variable `KINEVO_*` → config file (optional) → **instance settings in the database** → compiled default.
- Bootstrap values (database URL, listen address, public base URL, secret key material, log level) come only from flags/env/file.
- Everything the admin may change at runtime lives in the database as typed policies (D-09).
- Secrets are read once into redacting wrapper types and never logged (SEC-03).

## 10. Observability

- `tracing` with JSON logs; fields: `request_id`, `account_id` (UUID only), `module`, `route`, `status`, `latency_ms`. **Never** note content, titles, emails, prompts, tokens (SEC-04).
- Metrics endpoint `/metrics` (Prometheus text) behind `instance_admin` auth or an allow-listed network setting; off by default.
- Health: `GET /livez` (process up), `GET /readyz` (database reachable + migrations current). No authentication, no sensitive data.
- Audit log: append-only table `audit_log` for security-relevant and settings changes (SEC-10).

## 11. Startup sequence (ARCH-30)

1. Parse config; fail fast with a precise error on invalid values (`ERR config_invalid`).
2. Connect as `kinevo_app`; verify roles/RLS flags via `kinevo-db::verify_isolation()`; refuse to start if RLS is not forced on any tenant table (SEC-06).
3. Verify migrations are current (`serve` does **not** migrate unless `--migrate`; the container entrypoint runs `kinevo migrate` first by default, `KINEVO_AUTO_MIGRATE=false` disables).
4. Load instance settings and module switches; validate the module dependency graph (`03-module-system.md` §4).
5. Build router with enabled modules; start the worker; start serving; emit `startup_complete` audit event.

## 12. Failure modes (ARCH-40)

| Failure | Behaviour | Recovery |
|---|---|---|
| Database unreachable at start | Exit non-zero after the configured retries (`db.connect.retries`) | Orchestrator restarts |
| Database lost at runtime | `/readyz` fails; requests return 503 `service_unavailable`; pool reconnects with backoff | Automatic |
| Worker crash mid-job | Lease expires; job re-claimed (at-least-once) | Automatic; handlers idempotent |
| Outbox subscriber keeps failing | Delivery parked `dead` after max attempts; audit entry; admin UI shows count | Admin retries or fixes |
| Module enabled whose dependency is disabled | Switch rejected with `module_dependency_unmet` | Enable dependency first |
| Migration fails | Transaction rolled back (per migration file); process exits | Fix and re-run; never partial |
