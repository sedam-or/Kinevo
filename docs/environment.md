# LIFESYNC OS — Environment Configuration Baseline

### Scope
This document is the normative baseline for environment configuration. It governs
`server/.env.example`, secret handling, and non-secret defaults. It is a configuration
contract, not a requirements source; requirements live in `docs/SRS.md`.

### Normative hierarchy reminder
For configuration contracts, `docs/environment.md` documents the intended defaults and
rules. `server/.env.example` is the machine-readable mirror. If they disagree, the
decision recorded here governs until an ADR overrides it.

---

## 1. Environment separation

Three environments are recognized:

| Environment | Purpose                     | APP_ENV  | APP_DEBUG | Notes                          |
|-------------|-----------------------------|----------|-----------|--------------------------------|
| local       | developer workstation / Docker dev | local    | true      | default, safe placeholder secrets |
| staging     | pre-production validation    | staging  | true      | isolated from production data   |
| production  | user-facing single-user deploy | production | false   | secrets from platform secret store |

`APP_ENV` drives Laravel behavior. `APP_DEBUG=true` in production MUST never be set.

## 2. Secret classification

A variable is a **secret** if its value must be kept private and varies per deployment.
Secrets MUST NEVER be committed to the repository, including in `.env.example`,
example files, tests, fixtures, or documentation.

LIFESYNC secrets:

- `APP_KEY` (application encryption key)
- `DB_PASSWORD` (PostgreSQL credentials)
- `DB_URL` if used (full DSN)
- `REDIS_PASSWORD`
- `MAIL_PASSWORD`
- `AWS_ACCESS_KEY_ID` / `AWS_SECRET_ACCESS_KEY`
- any future provider API keys / tokens (AI provider, object storage, notification tokens)

### Secret rules (from SRS NFR-02)
- Secrets SHALL reside in platform secret storage, never in source code.
- `.env` (real environment) is never committed; `.env.example` only ships placeholders.
- Placeholder values in `.env.example` MUST be obviously non-functional
  (`change-me`, empty, or dev-only `secret`).
- Never log secrets, AI prompts/context, or private document content.
- Production secrets are injected via the deployment platform (Docker secrets,
  environment manager, Cloudflare/OKE secret store), never baked into images.

## 3. Non-secret defaults

The following are safe defaults that MAY be committed in `.env.example` and SHOULD work
in local development without modification:

| Variable           | Default           | Purpose                                        |
|--------------------|-------------------|------------------------------------------------|
| `APP_NAME`         | `LIFESYNC OS`     | product identity                              |
| `APP_ENV`          | `local`           | environment mode                              |
| `APP_DEBUG`        | `true`            | local debugging; false in production          |
| `APP_URL`          | `http://localhost:8000` | base URL for links                        |
| `APP_LOCALE`       | `en`              | UI locale                                     |
| `DB_CONNECTION`    | `pgsql`           | canonical store (architecture decision)       |
| `DB_HOST`          | `127.0.0.1`       | local default; `postgres` in Docker compose   |
| `DB_PORT`          | `5432`            | PostgreSQL port                               |
| `DB_DATABASE`      | `lifesync`        | database name                                 |
| `DB_USERNAME`      | `lifesync`        | database user                                 |
| `DB_SSLMODE`       | `prefer`          | TLS posture for local; `require` in prod      |
| `SESSION_DRIVER`   | `database`        | sessions in canonical store                   |
| `SESSION_LIFETIME` | `120`             | minutes                                       |
| `BROADCAST_CONNECTION` | `log`         | no external broadcaster in MVP                |
| `FILESYSTEM_DISK`  | `local`           | object storage adapter default                |
| `QUEUE_CONNECTION` | `database`        | queue on canonical store (no Redis requirement) |
| `CACHE_STORE`      | `database`        | cache on canonical store                      |
| `MAIL_MAILER`      | `log`             | dev mail sink                                 |
| `LOG_CHANNEL`      | `stack`           | standard stack channel                        |
| `LOG_LEVEL`        | `debug`           | debug in local, warning in production         |
| `SANCTUM_STATEFUL_DOMAINS` | `localhost:8000` | SPA-origin allowlist for stateful Sanctum auth; set to the frontend origin in production |

## 4. `.env.example` rules

- Every variable present in config files MUST have a documented default or explicit
  "secret — set per environment" marker.
- Secrets appear with placeholder values only (`change-me`, empty, or dev `secret`).
- Comments explain non-obvious choices (e.g. why sqlite is never a canonical default).
- The example MUST NOT reference real credentials, hostnames, or tokens.

## 5. Secret hygiene in CI

CI MUST fail if:

- any `.env` (non-example) file is committed;
- any obvious credential pattern appears in the diff (e.g. `APP_KEY=base64:`,
  real-looking tokens/keys);
- `.env.example` contains a non-placeholder secret.

## 6. Local development defaults

`server/.env.example` SHALL be usable as a starting point for local development after:

```bash
cp .env.example .env
php artisan key:generate
```

Docker compose (`infrastructure/docker-compose.yml`) injects container-aware values
(`DB_HOST=postgres`) at boot; the entrypoint applies them over the checked-out `.env`.

## 7. Versioning

Changes to secret classification or required defaults MUST be recorded in this document
and reflected in `server/.env.example` in the same change. Removing a required variable
is a breaking configuration change and requires a documented migration note.

---