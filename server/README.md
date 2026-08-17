# LIFESYNC OS — Server

This directory contains the Laravel modular monolith backend for LIFESYNC OS.

## Layout

- `app/Domain/` — domain entities, value objects, contracts.
- `app/Application/` — application use cases (commands/queries).
- `app/Infrastructure/` — infrastructure implementations (Eloquent repositories, adapters).
- `app/Http/` — HTTP controllers and presentation.
- `app/Models/` — Eloquent models (persistence layer).
- `routes/` — route definitions (web, api).
- `tests/` — PHPUnit unit and feature tests.
- `database/` — factories, seeders (migrations live at the repo root `../database/migrations`).

## Development

Run through the Docker stack from the repository root:

```bash
make up        # build + start app and PostgreSQL
make migrate   # run migrations
make test      # PHPUnit suite
make lint      # Pint style check
make analyse   # PHPStan static analysis
```

Or run Composer scripts directly:

```bash
composer ci
```

## Contracts

- Backend behavior MUST trace to `docs/SRS.md` (FR-xx / NFR-xx).
- API changes MUST update `docs/api/openapi.yaml`.
- Schema changes MUST add a migration under the repo-root `database/migrations/`.

See the repository root `README.md`, `AGENTS.md`, and `CONTRIBUTING.md`.