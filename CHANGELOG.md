# Changelog

All notable changes to Kinevo are documented here.

Format based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
semantic-version-compatible. Version numbers below refer to the **application**.
The **SRS version** (currently 2.0.0) and **API version** (v1) are tracked
separately in `docs/SRS.md` and `docs/api/openapi.yaml` respectively.

## [0.4.0] — 2026-08-17

### Added

- Identity/profile baseline:
  - Sanctum bearer-token authentication (register, login, logout, me).
  - Owner `profiles` table with settings (display_name, locale, timezone,
    week_start_day).
  - Domain layer: `Profile` entity, `ProfileSettings` value object,
    `ProfileRepository` contract, Eloquent implementation.
  - Application use cases: RegisterUser, LoginUser, LogoutUser, GetProfile,
    UpdateProfile.
  - API endpoints under `api/v1`: `/auth/register`, `/auth/login`,
    `/auth/me`, `/auth/logout`, `/profile`.
- OpenAPI `Identity` tag and schema additions.
- Feature tests for auth and profile ownership (17 tests total).

## [0.3.0] — 2026-08-17

### Added

- Environment/config/secrets baseline:
  - `docs/environment.md` contract.
  - `server/.env.example` annotated with secret vs non-secret defaults.
  - `scripts/check-secrets.sh` enforced in CI.

## [0.2.0] — 2026-08-17

### Added

- Docker development environment:
  - `infrastructure/docker/` (PHP 8.4-FPM Alpine image, entrypoint).
  - `infrastructure/docker-compose.yml` (app + PostgreSQL 17).
  - Makefile targets: `up`, `down`, `logs`, `migrate`, `shell`.

## [0.1.0] — 2026-08-17

### Added

- Repository skeleton:
  - Laravel 13 modular monolith under `server/` (PHP 8.4+, PostgreSQL).
  - Migrations canonicalized under `database/migrations/`.
  - CI pipeline (Pint, PHPStan, PHPUnit, repository validation).
- Architecture baseline: SRS v2.0.0, design, architecture, domain model,
  scheduling, knowledge, offline, AI, deployment, test strategy docs.
- ADR baseline (ADR-001..ADR-007).
- Repository bootstrap tooling (`scripts/`, `Makefile`).

[0.4.0]: https://github.com/sedam-or/Kinevo/compare/v0.3.0...v0.4.0
[0.3.0]: https://github.com/sedam-or/Kinevo/compare/v0.2.0...v0.3.0
[0.2.0]: https://github.com/sedam-or/Kinevo/compare/v0.1.0...v0.2.0
[0.1.0]: https://github.com/sedam-or/Kinevo/releases/tag/v0.1.0