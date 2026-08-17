# LIFESYNC OS

> A personal operating system for planning, scheduling, execution, knowledge
> capture, progress tracking, and optional local AI assistance. Single-user,
> self-hostable, offline-capable, deterministic-core, modular monolith.

## Status

Early development. A proven repository skeleton, CI pipeline, Docker development
environment, environment/secrets baseline, and an Identity/profile baseline are
complete through **TASK-010**. See [`docs/implementation-status.md`](docs/implementation-status.md)
and [`TASK.md`](TASK.md) for the current execution board.

## What it is

LIFESYNC OS helps one person plan goals and milestones, schedule tasks against a
deterministic engine, capture knowledge, track progress, and optionally use a
local AI model for assistance — without surrendering schedule authority to a
black box. Scheduling is deterministic; AI is optional, untrusted, and validated.

## Who it is for

- A single owner who wants a self-hosted, offline-capable productivity system.
- Developers and contributors interested in a clean modular-monolith reference
  architecture (Laravel + Vue + PostgreSQL).
- Anyone who values explainable scheduling, explicit commitments, and data
  ownership over opaque cloud productivity suites.

## Architecture summary

```text
Browser / PWA
    │
    ├── Vue 3 + TypeScript + Inertia
    ├── Pinia
    ├── Service Worker
    └── IndexedDB
          │
          ▼
Laravel Modular Monolith
    │
    ├── Planning / Goals / Milestones / Programs
    ├── Tasks / Execution / Recovery
    ├── Scheduling Engine (deterministic)
    ├── Knowledge / Notes / Canvas
    ├── Capacity / Analytics
    ├── Offline Sync
    └── AI Orchestrator (optional, untrusted)
          │
          ├── PostgreSQL        (canonical store)
          ├── Object Storage    (attachments)
          ├── Queue / Scheduler
          └── Optional Ollama / AI provider
```

External engines are always behind bounded adapters: **Excalidraw owns drawing,
Tiptap owns editing, Ollama owns inference — LIFESYNC owns business semantics.**

## Technology stack

- Backend: Laravel (PHP 8.4+) — modular monolith.
- Frontend: Vue 3 + TypeScript + Inertia.js + Vite + Pinia.
- Database: PostgreSQL (canonical store).
- Rich text: Tiptap behind a LIFESYNC editor adapter.
- Canvas: Excalidraw behind a bounded integration adapter.
- Offline: Service Worker + Cache Storage + IndexedDB (cache/queue, never
  authoritative).
- Jobs: Laravel Queue + Laravel Scheduler. Redis is optional optimization, not a
  mandatory dependency.
- AI: provider abstraction; Ollama supported locally; AI is optional.
- Infrastructure: Docker + Linux + Nginx; Oracle Cloud Always Free is one
  supported personal deployment profile, not a domain dependency.
- Storage: S3-compatible object storage or equivalent private object storage.

## Self-hosting

Production and personal deployment guidance lives in
[`docs/deployment.md`](docs/deployment.md). The application is designed to be
portable across Docker-compatible Linux hosts; there is no cloud-lock-in.

## Local development

Docker-based workflow (requires Docker):

```bash
make up          # build + start app (PHP-FPM) and PostgreSQL
make migrate     # run migrations
make logs        # tail service logs
make down        # stop services
```

App is available at http://localhost:8000.

Once the services are up, the common checks run inside the container:

```bash
make test        # PHPUnit suite
make lint        # Pint style check
make analyse     # PHPStan static analysis
make validate    # repository baseline validation
make doctor      # validate + status
```

A local PHP 8.4 + PostgreSQL install is optional; all commands also run through
the `composer:2` image:

```bash
cd server
docker run --rm -v "$PWD/..":/app -w /app/server composer:2 ./vendor/bin/phpunit
```

## First read

For an AI coding session or a new contributor, read in this order:

1. [`AGENTS.md`](AGENTS.md) — operating contract for AI agents and contributors.
2. [`docs/SRS.md`](docs/SRS.md) — normative requirements (single source of truth).
3. [`docs/architecture.md`](docs/architecture.md) — how the system is structured.
4. [`docs/domain-model.md`](docs/domain-model.md) — entities, invariants, state machines.
5. The feature-specific contract (`docs/scheduling-engine.md`,
   `docs/knowledge-layer.md`, `docs/offline-sync.md`, `docs/ai-architecture.md`).
6. Tests related to the feature (`server/tests/`).
7. The source implementation (`server/app/`).

## Documentation map

- `docs/SRS.md` — requirements (normative). Read first.
- `docs/architecture.md` — technical architecture and boundaries.
- `docs/design.md` — UI/UX and interaction design.
- `docs/domain-model.md` — entities, value objects, invariants, state machines.
- `docs/scheduling-engine.md` — deterministic scheduling contract.
- `docs/knowledge-layer.md` — notes, links, documents, canvas relationships.
- `docs/offline-sync.md` — local-first behavior and sync contract.
- `docs/ai-architecture.md` — AI provider, safety, structured outputs.
- `docs/deployment.md` — deployment, operations, backup.
- `docs/environment.md` — environment configuration and secret rules.
- `docs/test-strategy.md` — quality gates and test pyramid.
- `docs/api/openapi.yaml` — versioned API contract.
- `docs/adr/` — architecture decision records (ADR-001..ADR-007).
- `docs/third-party/` — license ledger and attributions.
- `TASK.md` — execution backlog and status; NOT a requirements source.

## Contributing

See [`CONTRIBUTING.md`](CONTRIBUTING.md) for the full contributor guide,
including branch naming, commit conventions, PR rules, SRS/ADR processes,
migration and API rules, and the Definition of Done.

- Bug reports, feature requests, and architecture discussions: use the GitHub
  issue templates.
- Before contributing, review [`CODE_OF_CONDUCT.md`](CODE_OF_CONDUCT.md).

## Security

See [`SECURITY.md`](SECURITY.md) for the vulnerability disclosure policy.
Report security issues privately — never in a public issue.

## Support

See [`SUPPORT.md`](SUPPORT.md) for where to ask questions and get help.

## License

LIFESYNC OS is licensed under the **MIT License** — see [`LICENSE`](LICENSE).
Third-party components retain their own licenses; see
[`docs/third-party/licenses.md`](docs/third-party/licenses.md) for the
provenance ledger.

## Roadmap

The roadmap is tracked as tasks in [`TASK.md`](TASK.md) and mirrored at a
high level in [`docs/implementation-status.md`](docs/implementation-status.md).
Phases: Core domain (goals/milestones/programs/tasks) → Scheduling →
Knowledge → Canvas → Offline/Recovery → Adaptive productivity → AI → Operations.

## Project maturity

This project is pre-1.0. The architecture and requirements baselines are frozen
(SRS v2.0.0); implementation proceeds task by task. The application is not yet a
feature-complete product and should not yet be relied upon as a primary
scheduler.