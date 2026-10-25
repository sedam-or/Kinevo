---
id: T-056
title: Build the container image, compose reference and quick-start script
milestone: M1
kind: feature
status: todo
depends_on: [T-043, T-039, T-040]
requirements: [OPS-01, OPS-02, DST-01, DST-02, DST-03, DST-06, SEC-13, D-49]
branch: m1-t056-ops-docker-compose-quickstart
budget_lines: 330
assignee: ""
---

# T-056: Build the container image, compose reference and quick-start script

## Goal

`docker compose up` from the repository starts PostgreSQL and Kinevo, prints the setup URL once, becomes healthy, and reaches the wizard; the quick-start script generates secrets safely.

## Context

> **Image** (`spec/standards/containers-and-caching.md` §1): multi-stage Dockerfile: (1) `node:22-alpine` builds `web/` with Vite, (2) `rust:1.99-alpine` (musl, static) builds `kinevo` with the assets embedded, no database needed at build time (D-49), (3) runtime `alpine:3.22` pinned by digest with `ca-certificates` and busybox `sh`, no package manager at runtime. User `kinevo` UID/GID 10001; read-only root, tmpfs `/tmp` (64 MiB); `ENTRYPOINT ["/usr/local/bin/kinevo"]`, `CMD ["serve"]`; `HEALTHCHECK --interval=30s --timeout=3s --start-period=20s CMD ["kinevo","health"]`; OCI labels (source, revision, version, licenses `AGPL-3.0-only`, created); platforms amd64 and arm64 (multi-arch publishing is Phase 5 CI; this task makes the Dockerfile arch-agnostic).
> **Compose reference**: services `kinevo` (depends_on `postgres` healthy) and `postgres` (`pg_isready`), secrets via files, an internal network for Postgres (no published port), `127.0.0.1:8080` published by default, `cap_drop: [ALL]`, `no-new-privileges`, `read_only: true`.
> **OPS-01**: `docker run`/compose from the image reaches the wizard with only the database URLs, `KINEVO_SECRET_KEY` and `KINEVO_PUBLIC_URL` set; nothing is generated silently (the quick-start script prints where the key was stored). **DST-02**: the quick-start never prints secrets to shared logs; generated secrets are written once to a local `.env` (mode 600) and the script tells the user to back up `KINEVO_SECRET_KEY`. **DST-03**: the setup URL with token is printed in the container log after first start. **DST-06**: the README quick start is executed by CI (Phase 5 `quickstart` job).
> **Database roles** (D-50, T-040): compose runs a one-shot service `bootstrap` (`kinevo migrate --bootstrap`) after Postgres is healthy; `kinevo` depends on it completing successfully. The Postgres superuser URL is passed only to that service.
> **SEC-13**: pin base images by digest (digests resolved at implementation time and recorded; Dependabot updates later).

## Scope

**In:**
- `Dockerfile`, `.dockerignore`, `compose.yaml`, `.env.example` (placeholders only), `scripts/quickstart.sh` (bash, `set -euo pipefail`, uses `openssl rand`, writes `.env` mode 600, refuses to overwrite, prints next steps without secrets), 
- A smoke script `scripts/smoke-compose.sh` that runs `docker compose up -d --wait`, calls `/readyz`, asserts the setup URL line exists in the logs, then tears down; used by T-057 and later CI.
- Docs fragment `docs/quick-start.md` (short; the README is Phase 5).

**Out:**
- Publishing, signing, SBOM and provenance: Phase 5 CI. Reverse-proxy examples: Phase 5 docs.

## Files

| Path | Action | Notes |
|---|---|---|
| `Dockerfile, .dockerignore, compose.yaml, .env.example` | create | image and reference stack |
| `scripts/quickstart.sh, scripts/smoke-compose.sh` | create | bash scripts |
| `docs/quick-start.md` | create | operator steps |

## Interfaces

```text
./scripts/quickstart.sh            # writes .env (0600) with generated KINEVO_SECRET_KEY and DB passwords; prints next steps
docker compose up -d --wait         # healthy within 60 s
docker compose logs kinevo | grep setup   # shows the one-time setup URL
```

## Business rules

1. `quickstart.sh` refuses to run if `.env` exists.
2. The compose file contains no real secret; `.env.example` has only placeholders and comments.
3. Postgres data uses a named volume; the application container has no volumes.
4. The image runs as UID 10001 with a read-only root file system and passes the health check.

## Security

- `.env` is in `.gitignore` (add if missing); the script sets `umask 077`.
- The script prints the location of the secret key and the backup warning, never its value.
- No `latest` tags; digests pinned.

## Implementation notes

- If Docker is unavailable in the implementer's environment, mark the runtime ACs environment-limited and run the static checks (`hadolint` if present, `docker compose config`, `shellcheck` if present).
- Build once with `docker build .` and record the compressed image size in the Report (target in the spec is an `[ASSUMED]` ≤ 40 MB).

## Acceptance

| # | Given / When / Then | Test name |
|---|---|---|
| AC-1 | **Given** a clean checkout **When** `docker build .` runs **Then** it succeeds with no database | `ops_docker::dst_01_image_builds_without_database` |
| AC-2 | **Given** the compose stack **When** started **Then** `kinevo` becomes healthy and `/readyz` returns 200 | `ops_docker::ops_01_compose_reaches_ready` |
| AC-3 | **Given** first start **When** logs are read **Then** the setup URL with token appears once | `ops_docker::dst_03_setup_url_in_log_once` |
| AC-4 | **Given** `quickstart.sh` **When** run twice **Then** the second run refuses; the first writes `.env` with mode 600 and no secret on stdout | `ops_docker::dst_02_quickstart_safe_secrets` |
| AC-5 | **Given** the running container **When** inspected **Then** user is 10001, root fs read-only, capabilities dropped | `ops_docker::container_hardening_flags` |
| AC-6 | **Given** the compose file **When** linted **Then** no image uses a floating tag and no secret literal exists | `ops_docker::sec_13_pinned_digests_no_secret_literals` |

## Tests

```
docker build -t kinevo:dev .
scripts/smoke-compose.sh   # environment-limited without Docker
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
