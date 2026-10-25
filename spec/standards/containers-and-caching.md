# Standard — Containers and caching

Decision: D-14 (small Alpine runtime image with a shell for health/debugging, trade-off recorded in ADR-0005), D-13 (no Redis).

## 1. Image

| Aspect | Specification |
|---|---|
| Build | multi-stage `Dockerfile`: (1) `node:22-alpine` builds `web/` (Vite) → static files; (2) `rust:1.99-alpine` (musl, static) builds the `kinevo` binary with the web assets embedded (`rust-embed`) and SQLx offline metadata; (3) runtime stage |
| Runtime base | `alpine:3.22` `[version pinned by digest at build; update monthly by Dependabot]` with `ca-certificates` and busybox `sh`; **no** package manager at runtime |
| Contents | `/usr/local/bin/kinevo` (static binary), `/etc/kinevo/templates/*.toml` only if not embedded (they are embedded; none needed), CA bundle |
| User | `kinevo` UID/GID 10001, no login shell for the account, `WORKDIR /` read-only |
| Filesystem | read-only root; `tmpfs` at `/tmp` (64 MiB); no volumes required (state is in PostgreSQL) |
| Process | `ENTRYPOINT ["/usr/local/bin/kinevo"]`, `CMD ["serve"]`; PID 1 handles SIGTERM (OPS-03); entrypoint wrapper runs `kinevo migrate` when `KINEVO_AUTO_MIGRATE=true` |
| Health | `HEALTHCHECK --interval=30s --timeout=3s --start-period=20s CMD ["kinevo","health"]` |
| Labels | OCI annotations: source, revision, version, licenses (`AGPL-3.0-only`), created |
| Capabilities | `cap_drop: [ALL]`, `no-new-privileges`, `read_only: true`, seccomp default |
| Size target | `[ASSUMED]` ≤ 40 MB compressed; measured and reported per release |
| Platforms | `linux/amd64`, `linux/arm64` |
| Why Alpine, not distroless | A shell lets operators run `kinevo health`/debug in minimal setups and helps third-party platform templates; the cost is a slightly larger attack surface (busybox). Distroless remains a candidate if Trivy findings or size make it worthwhile (revisit trigger in ADR-0005). |

Compose (reference): services `kinevo` (depends_on `postgres` healthy), `postgres` (`pg_isready`), secrets via files, network `internal` for Postgres (no published port), published `127.0.0.1:8080` by default (reverse proxy fronts it).

## 2. Server-side caches (all in-process, `moka`, bounded; D-13)

| Cache | Key | Value | TTL | Max entries | Invalidation | Failure mode |
|---|---|---|---|---|---|---|
| Instance + module switches | `"instance"` | settings, switch map | `modules.cache_ttl_seconds` (10 s) | 1 | `ModuleSwitched`, `InstanceSetupCompleted`, `PolicyChanged` events (same node: immediate; other nodes n/a in 1.0) | miss ⇒ read DB |
| Policy resolution | `(scope, account_id?, key)` | resolved value + `version_id` | 30 s | 20 000 | `PolicyChanged` for the key/scope | miss ⇒ read DB; **decisions that snapshot (drafts, invoices) always read through, bypassing the cache** |
| Permission set | `account_id` | roles/flags (`is_instance_admin`, `status`) | 60 s | 10 000 | `AccountDisabled`, role change, logout-all | miss ⇒ read DB; a disabled account is cut off within ≤ 60 s for access-token requests (refresh is immediate) |
| Refresh grace | `sha256(old token)` | new token pair | `auth.refresh.grace` | 10 000 (LRU) | TTL only | restart ⇒ replay inside window revokes the family (documented) |
| TOTP replay | `(account, step)` | marker | 90 s | 10 000 | TTL | restart ⇒ replay possible within the same step (accepted, low risk) |
| Rate limiter | key per `ratelimit.*` | counters | window | 100 000 | window expiry | restart resets counters (accepted) |
| Embedded data | — | tz database, common-password list, templates | process lifetime | — | none | — |

Rules (CAC-1…4): caches never contain S3 data except the grace cache (RAM only, ≤ 10 s); every cache has the bounds above and a metric (`cache_hits_total{cache}`, `cache_misses_total{cache}`); caches are an optimisation — correctness must not depend on them (tests run with caches disabled and enabled); cache keys include the account id wherever data is account-specific.

## 3. HTTP caching

| Resource | `Cache-Control` |
|---|---|
| Hashed static assets (`/assets/*`) | `public, max-age=31536000, immutable` |
| `index.html`, `sw.js`, `manifest.webmanifest` | `no-cache` (always revalidate) |
| `GET /api/v1/*` (authenticated) | `no-store` |
| `GET /api/v1/openapi.json`, `/api/v1/instance` | `public, max-age=60` |
| Canvas files | `private, max-age=0, must-revalidate` + `ETag` (sha256) |
| ICS exports | `no-store` |

Compression: `br`/`gzip` for text assets and JSON ≥ 1 KiB via `tower-http`; precompressed static assets embedded at build time.

## 4. Client caches (web/Android)

| Cache | Content | Staleness | Persisted | Cleared |
|---|---|---|---|---|
| Service worker precache | app shell, hashed assets | by release | Cache Storage | on new version activation |
| TanStack Query | allow-listed read queries (`today`, `tasks`, `goals`, `schedule input`, `notes list`, `modules`) | `staleTime` per query: `today` 30 s, `modules` 5 min, lists 60 s | IndexedDB (7 days max, S2 — device-level protection) | logout of a different account; manual "clear offline data" in Settings |
| Mutation queue | pending sync operations | n/a | IndexedDB | after final result (SYN-C1…C7) |
| WASM module | scheduler | by release | Cache Storage | with app shell |

Each cached screen shows "Last updated hh:mm" when offline or stale (`standards/ux-states.md` `stale.*`).
