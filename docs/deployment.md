# Kinevo — Deployment & Operations

### Deployment target
Primary reference: Linux Docker-compatible host. Oracle Cloud Always Free is a supported personal deployment profile but not a software architecture dependency.

### Production containers
Recommended:
```text
reverse-proxy
app
queue-worker
scheduler
postgres
ollama (optional)
```

Redis is optional.

### Network exposure
Public:
- 80/443 only through reverse proxy.

Private:
- PostgreSQL.
- Redis.
- Ollama.
- internal application ports.

### Cloudflare
Recommended for:
- DNS;
- TLS edge;
- basic protection;
- origin shielding where appropriate.

### Environment separation
- local;
- staging;
- production.

Secrets MUST be environment-managed, never committed.

### Database
- PostgreSQL timezone-aware timestamps;
- migrations are versioned;
- backups are automated;
- restore must be tested.

### Backup strategy
At minimum:
- daily database backup;
- object storage backup/versioning where supported;
- periodic full JSON/CSV export;
- documented restore procedure.

---

### Backup & restore automation (TASK-082, SRS §16.4, NFR-05)

**Scripts** (`scripts/backup.sh`, `scripts/restore.sh`):
- `backup.sh` dumps the canonical PostgreSQL store to a timestamped gzipped
  file, prunes local backups to `BACKUP_KEEP`, and optionally copies to a
  remote S3-compatible bucket (`--remote-bucket s3://bucket/prefix`,
  `AWS_*`/`mc`).
- `restore.sh` terminates active connections, drops and recreates the DB, and
  applies the chosen backup. It is **destructive** and guarded: it aborts
  unless `CONFIRM_RESTORE=yes`, and validates the DB identifier.

**Compose** (`docker-compose.prod.yml`): a `backup` service (postgres image,
which ships `pg_dump`/`psql`) runs `backup.sh` on a daily loop into the
`kinevo_backups` volume; optional remote copy via env.

**Usage**
```bash
make prod-backup           # on-demand backup (remote copy via REMOTE_BUCKET)
make prod-backup-list      # list local backups
make prod-restore CONFIRM_RESTORE=yes [BACKUP_FILE=...]   # DESTRUCTIVE
```

**Restore test**: periodically run the restore flow against a scratch copy to
prove recoverability (SRS §16.4 "restore test periodically"). The manual
JSON/CSV export remains available via the `GET /export` activity-log endpoint.

**RPO/RTO**: the suggested personal baseline is RPO ≤24h (daily backup) and
RTO ≤4h; a remote backup copy is required for the remote-copy part of §16.4.

---

### Disaster recovery
Exact RPO/RTO MUST be finalized from operational needs. Suggested personal deployment baseline may target RPO <= 24h and RTO <= 4h.

### Monitoring
Monitor:
- app health;
- DB health;
- scheduler runs;
- queue backlog;
- storage failures;
- offline sync failure rates;
- disk utilization;
- memory utilization;
- CPU utilization;
- backup success.

### Oracle profile
For Oracle Always Free personal deployment, size services conservatively around the 2 OCPU / 12 GB RAM baseline. Ollama should use a small quantized model and be loaded on demand where possible. Large coding models belong on the development workstation rather than the free production VPS.

### Upgrade path
When resource pressure appears:
1. profile;
2. reduce unnecessary resident processes;
3. separate workers;
4. add Redis if justified;
5. move object storage off-node;
6. upgrade VPS only after evidence.

### Deployment rules
- immutable container image where practical;
- migrations run as explicit release step;
- health checks before traffic switch;
- rollback plan documented;
- never deploy untested schema changes.

---

### Ollama development adapter (SRS §13.6, ADR-011)

Ollama is an optional local inference engine for development and small
self-hosted deployments. The application MUST remain fully functional when it
is unavailable (SRS FR-60); enabling it is purely additive.

Start it (opt-in compose profile, internal network only — no host port):
```bash
docker compose --profile ai up -d
```

Configure the app in `server/.env`:
```dotenv
AI_PROVIDER=ollama
OLLAMA_BASE_URL=http://ollama:11434
OLLAMA_MODEL=llama3.1
```
(`OLLAMA_BASE_URL` points at the compose service name; a local desktop install
would use `http://localhost:11434`.)

The adapter is wired and verified with:
```bash
make ai-status   # provider snapshot (SRS §17.8 telemetry)
make ai-smoke    # tiny deterministic generation through the provider
```

Model guidance:
- Prefer a small quantized model for the free-tier VPS profile; load on demand
  where possible. The compose service unloads idle models after 30 minutes
  (`OLLAMA_KEEP_ALIVE=30m`).
- Large coding models belong on the development workstation, not the production
  VPS.
- Ollama SHALL stay on the internal network (SRS §16.4); never expose its port
  publicly.

Failure behavior: connection failures, HTTP errors, and empty responses
surface as `AI_PROVIDER_UNAVAILABLE` (503 on the API); deterministic
scheduling, manual workflows, and all other features keep working.

---

### Production Docker profile (TASK-080)

A dedicated production image and compose overlay implement the deployment.md
container roles (app, queue-worker, scheduler, postgres, optional ollama).

**Image** (`infrastructure/docker/Dockerfile.prod`):
- multi-stage: frontend assets built in a Node stage, runtime is a slim
  php-fpm image with no dev tooling (composer is build-time only);
- production composer dependencies (`--no-dev`), optimized autoload;
- opcache + JIT enabled with `validate_timestamps=0`;
- config/route/event caches baked in;
- `.env.production.example` shipped as the non-secret template (real values
  always injected by the deployment environment).

**Entrypoint** (`kinevo-prod-entrypoint.sh`): applies container env over the
baked `.env` (canonical set), fails fast without `APP_KEY`, and dispatches
roles: `app` → `php-fpm`, `queue-worker` → `php artisan queue:work`,
`scheduler` → `php artisan schedule:work`, plus `artisan`/`migrate` helpers.

**Compose overlay** (`infrastructure/docker-compose.prod.yml`):
- `app` (php-fpm on :9000, internal — reverse proxy routes to it),
  `queue-worker`, `scheduler` each as a separate container role;
- `postgres` with a named volume and healthcheck;
- optional `ollama` behind the `ai` profile (internal network only);
- migrations run explicitly as a release step, not implicitly on boot.

**Usage**
```bash
export APP_KEY="$(openssl rand -base64 32)"   # per deploy
export DB_PASSWORD="change-me-secret"
export APP_URL="https://kinevo.example.com"

make prod-build     # build the image + bake assets
make prod-migrate   # explicit migration release step
make prod-up        # start app, queue-worker, scheduler, postgres
```

Reverse proxy + TLS termination are handled in TASK-081; PostgreSQL/Ollama
remain internal-network services (SRS §16.4).

---

### Reverse proxy & TLS (TASK-081, SRS NFR-02)

Public 80/443 enter only through the nginx reverse proxy; the app (php-fpm),
PostgreSQL, and Ollama stay on the internal network (SRS §16.4).

**Reverse proxy** (`infrastructure/docker/nginx/default.conf`):
- HTTP → HTTPS redirect (except the ACME challenge path);
- TLS termination (TLS 1.2/1.3, security headers) with certificates mounted;
- serves Vite-built assets (`/build/`, long-lived cache) and `sw.js`
  (no-cache), proxies everything else to the app php-fpm on `:9000`;
- forwards `X-Forwarded-Proto https`; the app trusts the proxy
  (`trustProxies('*')` in `bootstrap/app.php`) so HTTPS URLs/schemes are correct.

**Compose** (`docker-compose.prod.yml`):
- `reverse-proxy` publishes `80`/`443` only; `app` publishes no host port;
- shared `certbot_conf` (LetsEncrypt certs) and `certbot_www` (ACME challenge)
  volumes;
- `certbot` companion service behind the `certbot` profile.

**First-time TLS (webroot)** — with the proxy up:
```bash
export SERVER_NAME=kinevo.example.com
make prod-certbot EMAIL=admin@example.com   # issues a cert into certbot_conf
make prod-up                                  # proxy now serves HTTPS
```

**Renewal**: `certbot renew` (webroot) on a schedule; the proxy reloads to pick
up renewed certs. Cloudflare edge is an equally supported TLS profile (SRS
"Reverse Proxy / TLS": Nginx + Cloudflare-compatible edge); with Cloudflare
terminating TLS in front, the nginx `443` server can serve the origin cert.

---

