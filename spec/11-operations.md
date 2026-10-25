# 11 — Operations

Depends on: `02-architecture.md`, `10-security-privacy.md`, `standards/containers-and-caching.md`. Prefix: `OPS`.
Audience: self-hosters and the Kinevo Cloud operator. Everything here must be achievable by one person with Docker and basic Linux skills.

## 1. Supported topologies

| Profile | Components | Notes |
|---|---|---|
| **A. Compose (default)** | `kinevo` image + `postgres:17` (pinned by digest) + volume for Postgres data | one command; documented in README Quick start |
| **B. External Postgres** | `kinevo` image + managed/own PostgreSQL ≥ 17 | operator provides roles (`kinevo_migrator`, `kinevo_app`, `kinevo_system`) via the bootstrap SQL |
| **C. One-click templates** | the same image on a PaaS | defined in `14-distribution.md` |
| **D. Cloud (operator)** | A/B on managed infrastructure with backups, monitoring | `15-cloud.md` |

Single application node is the supported shape in 1.0 (in-process rate limiter, grace cache, caches). Horizontal scaling is out of scope until an RFC replaces those with shared state. PostgreSQL 17 and 18 are the tested versions (CI matrix); older than 17 is unsupported.

## 2. Configuration reference (environment; `_FILE` variants read the value from a file for Docker/Kubernetes secrets)

| Variable | Required | Default | Meaning |
|---|---|---|---|
| `KINEVO_DATABASE_URL` / `_FILE` | yes | — | `postgres://kinevo_app:…@host/db` (application role) |
| `KINEVO_MIGRATOR_DATABASE_URL` / `_FILE` | for `migrate` | — | migrator role |
| `KINEVO_BOOTSTRAP_DATABASE_URL` / `_FILE` | only for `migrate --bootstrap` | — | role-creating login used once to create the three roles (D-50) |
| `KINEVO_SYSTEM_DATABASE_URL` / `_FILE` | yes | — | system role (login lookups, worker bookkeeping, ARCH-24) |
| `KINEVO_SECRET_KEY` / `_FILE` | yes | — | 32 random bytes, base64; **back this up separately** |
| `KINEVO_PUBLIC_URL` | yes | — | external base URL (https in production); drives cookies, HSTS, ICS UIDs, CSP |
| `KINEVO_LISTEN` | no | `0.0.0.0:8080` | bind address |
| `KINEVO_TRUSTED_PROXIES` | no | empty | CIDRs allowed to set `X-Forwarded-For` |
| `KINEVO_AUTO_MIGRATE` | no | `true` | run migrations at container start |
| `KINEVO_LOG_LEVEL` / `KINEVO_LOG_FORMAT` | no | `info` / `json` | |
| `KINEVO_DB_POOL_MAX` | no | `10` | |
| `KINEVO_JOBS_TICK_SECONDS` | no | `300` | recurring-job materialisation cadence |
| `KINEVO_METRICS_ENABLED` / `KINEVO_METRICS_ALLOW` | no | `false` / empty | `/metrics`, allowed CIDRs |
| `KINEVO_ANDROID_ORIGIN` | no | `true` | allow CORS for `https://app.kinevo.localhost` (D-37) |
| `KINEVO_SETUP_*` | no | see `05-templates-and-setup.md` | non-interactive first account |
| `KINEVO_SMTP_URL` / `_PASSWORD_FILE` | no | empty | optional email (D-27) |
| `KINEVO_MAX_BODY_BYTES` | no | `1048576` | global request body ceiling (route limits are stricter) |

Invalid configuration fails startup with an error that names the variable and the rule, never the value of secrets.

## 3. Requirements

| ID | Requirement | Acceptance |
|---|---|---|
| OPS-01 | `docker run`/compose from the published image reaches the setup wizard with only `KINEVO_DATABASE_URL`-family, `KINEVO_SECRET_KEY` and `KINEVO_PUBLIC_URL` set; compose file generates nothing silently (the quick-start script prints where the secret key was stored). | Journey J0 on a clean VM (trial T2). |
| OPS-02 | `/livez` returns 200 when the process runs; `/readyz` returns 200 only when the database answers, migrations are current and isolation checks passed; both return no data beyond `{status}`. The image `HEALTHCHECK` runs `kinevo health` (no curl needed). | Container tests. |
| OPS-03 | Graceful shutdown on SIGTERM: stop accepting connections, finish in-flight requests (≤ 30 s), let running jobs finish or release their lease, exit 0. | Test. |
| OPS-04 | **Upgrade**: `pull` → backup → start new image (auto-migrate) → verify `/readyz`. Migrations are forward-only; two different versions never serve concurrently against one database. The release notes state schema changes and minimum upgrade-from version. Downgrade = restore the pre-upgrade backup with the previous image. | Upgrade test from N−1 fixture dump (TST-08). |
| OPS-05 | **Backups** (documented, not built in): `pg_dump -Fc` of the database on a schedule, stored encrypted, plus `KINEVO_SECRET_KEY` stored separately; the guide includes cron/systemd timer and a restore command. `kinevo admin check-backup <dump>` is **not** provided in 1.0. | Docs + restore drill (OPS-06). |
| OPS-06 | **Restore drill** is scripted in CI nightly: dump a populated test instance, restore into a fresh database, start the app, run smoke journeys; a second drill restores without the master key and asserts content is readable and secrets show as unset (SEC-AC-46). | CI nightly job. |
| OPS-07 | **Observability**: JSON logs per SEC-04; `/metrics` (optional) exposes S1 counters/histograms only: `http_requests_total{route,status}`, `http_request_duration_seconds`, `jobs_total{kind,state}`, `jobs_queue_age_seconds`, `outbox_pending`, `outbox_dead`, `db_pool_in_use`, `scheduler_runs_total{trigger,status}`, `ai_runs_total{status}`, `sync_ops_total{status}`. No account ids as labels. | Metrics contract test. |
| OPS-08 | **Resource baseline** `[ASSUMED, verify in benchmark]`: single-user instance idles at < 100 MB RSS for the app and < 256 MB for Postgres; a 1 vCPU / 1 GB host runs the stack comfortably for dozens of accounts. The README states only measured numbers once measured. | Benchmark task. |
| OPS-09 | **Time**: host clock must be NTP-synchronised; the app compares its clock to the database `now()` at start and in `/readyz` and reports skew > 5 s as degraded. | Test. |
| OPS-10 | **TLS guidance**: production exposes HTTPS via a reverse proxy (Caddy/nginx/Traefik examples) or the optional built-in rustls listener (`KINEVO_TLS_CERT_FILE`, `KINEVO_TLS_KEY_FILE`); HTTP is for localhost/dev only. | Docs + test of header behaviour. |
| OPS-11 | **Image hygiene**: published tags are `X.Y.Z`, `X.Y`, `X`, `latest` (stable only); every image is signed (cosign keyless) with SBOM and provenance attestations; Compose files pin base images by digest. | Release dry-run (RELEASING.md). |
| OPS-12 | **Runbooks** (in `docs/operations/` created in Phase 5): locked-out admin (`kinevo admin reset-password`), lost `KINEVO_SECRET_KEY` (secrets must be re-entered; content intact), dead outbox deliveries, stuck jobs/leases, database full, slow argon2 (tune concurrency), clock skew, upgrade failure/rollback, suspected token theft (revoke sessions, rotate key). | Docs review. |
| OPS-13 | **Retention jobs** run daily (audit, idempotency, jobs, outbox, ledger, drafts, proposals, exports); their cadence and bounds come from policies; failures are visible in `GET /admin/jobs/summary`. | Job tests. |
| OPS-14 | **Cloud SLO draft** `[ASSUMED]`: availability 99.5 % monthly, RPO 24 h (daily backups) improving to ≤ 1 h with WAL archiving, RTO 4 h; published only after measured. | `15-cloud.md` |

## 4. Data migration from the legacy implementation

The legacy schema (`legacy-v0:database/migrations/*`) differs (integer ids, per-user ownership, boosts etc.). A converter is **optional** and only built if the owner needs to carry real data over (OWNER-07). If requested it is a one-off CLI (`kinevo admin import-legacy <dump>`) specified in its own task with a mapping table; no compatibility promise is made for third-party legacy installs.
