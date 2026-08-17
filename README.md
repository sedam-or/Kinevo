# LIFESYNC OS

### Purpose
LIFESYNC OS adalah personal operating system untuk planning, scheduling, execution, knowledge capture, progress tracking, adaptive capacity, dan optional local AI assistance. Sistem ditargetkan sebagai single-user, self-hostable, offline-capable, explainable, dan maintainable modular monolith.

### Current architecture at a glance
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
    ├── Scheduling Engine
    ├── Knowledge / Notes / Canvas
    ├── Capacity / Analytics
    ├── Offline Sync
    └── AI Orchestrator
          │
          ├── PostgreSQL
          ├── Object Storage
          ├── Queue / Scheduler
          └── Optional Ollama / AI provider
```

### Primary technology stack
- Backend: Laravel + PHP.
- Frontend: Vue 3 + TypeScript + Inertia + Vite + Pinia.
- Database: PostgreSQL.
- Rich document editor: Tiptap via a LIFESYNC editor adapter.
- Canvas: Excalidraw behind a bounded integration adapter.
- Offline: Service Worker + Cache Storage + IndexedDB.
- Jobs: Laravel Queue + Laravel Scheduler; Redis is optional optimization, not a first-class dependency.
- Deployment profile: Dockerized Linux host; Oracle Cloud Always Free is a supported personal deployment profile, not a domain requirement.
- AI: provider abstraction; Ollama is supported for local inference.

### First read
For an AI coding session, read in this order:
1. `AGENTS.md`
2. `docs/SRS.md`
3. `docs/architecture.md`
4. `docs/domain-model.md`
5. feature-specific contract
6. tests related to the feature
7. source implementation

### Quick start placeholder
The final implementation repository MUST replace this section with exact tested commands. Until then, the authoritative development workflow SHALL be defined in `docs/deployment.md` and `AGENTS.md`.

Containerized workflow (TASK-003):
```bash
docker compose -f infrastructure/docker-compose.yml up -d --build
docker compose -f infrastructure/docker-compose.yml exec app php artisan migrate
# app available at http://localhost:8000
```

Makefile shortcuts:
```bash
make up        # build + start app and PostgreSQL
make migrate   # run migrations
make logs      # tail service logs
make down      # stop services
```

Local workflow (from `server/`, optional when PHP is installed):
```bash
cp .env.example .env
php artisan key:generate
php artisan migrate
./vendor/bin/phpunit
```

Local PHP is optional: the same commands run through the `composer:2` Docker image, e.g.
```bash
docker run --rm -v "$PWD/..":/app -w /app/server composer:2 ./vendor/bin/phpunit
```

### Repository navigation
- `docs/SRS.md`: what must exist.
- `docs/architecture.md`: how the system is structured.
- `docs/design.md`: UI/UX behavior and visual interaction system.
- `docs/domain-model.md`: entities, invariants, state machines, value objects.
- `docs/scheduling-engine.md`: deterministic scheduling contract.
- `docs/knowledge-layer.md`: notes, links, documents, canvas relationships.
- `docs/offline-sync.md`: local-first behavior and synchronization contract.
- `docs/ai-architecture.md`: AI provider, safety, context, structured outputs.
- `docs/deployment.md`: environment, Docker, Oracle/VPS, Cloudflare, backup.
- `docs/test-strategy.md`: quality gates and test pyramid.
- `TASK.md`: execution backlog and status tracking; NOT a requirements source.

### Non-negotiable principles
- Never let AI silently mutate authoritative state.
- Never replace deterministic scheduling with LLM output.
- Never delete tasks automatically because scheduling failed.
- Never bypass domain invariants through direct UI-side mutation.
- Never expose PostgreSQL/Redis as public network services.
- Never make a feature “done” solely because the code compiles.

### Project status
Status MUST be maintained in `docs/implementation-status.md` and `TASK.md`; do not duplicate volatile status in this README except for a manually updated one-line summary.

---

