# Kinevo — Technical Architecture

### Architectural style
Kinevo uses a **modular monolith**. It is one deployable Laravel application with explicit domain/application/infrastructure boundaries.

### Why modular monolith
- single-user product;
- high domain coupling between scheduling, tasks, goals, capacity, and recovery;
- low operational overhead;
- strong database transactions;
- easy local development;
- simple deployment;
- future extraction remains possible because module boundaries are explicit.

### High-level architecture
```text
Browser
├── Vue 3 / TypeScript / Inertia
├── Service Worker
└── IndexedDB
        │ HTTPS
        ▼
Laravel
├── Presentation
├── Application
├── Domain
└── Infrastructure
        │
        ├── PostgreSQL
        ├── Object Storage
        ├── Queue
        └── AI Provider
                │
                └── Ollama / external provider optional
```

### Layering rules
#### Presentation
Controllers, Inertia pages, API resources, request validation, UI-facing orchestration. No complex business algorithms.

#### Application
Use cases/commands/queries, transaction boundaries, orchestration of domain services.

#### Domain
Entities, value objects, domain services, policies, state machines, scheduling algorithms.

#### Infrastructure
Eloquent repositories, storage adapters, queue adapters, HTTP clients, AI providers, third-party engines.

### Domain modules
- Identity
- Goals
- Milestones
- Programs
- Tasks
- Scheduling
- Capacity
- Execution
- Recovery
- Knowledge
- Canvas
- Analytics
- Notifications
- Offline Sync
- AI
- Import/Export
- Audit

### Dependency rule
Domain MUST NOT import Vue, Inertia, HTTP controllers, Laravel presentation concerns, Excalidraw, Tiptap, or Ollama directly.

Infrastructure adapters implement interfaces consumed by application/domain layers.

### Canvas boundary
```text
Vue
 ↓
CanvasHost / CanvasAdapter
 ↓
React Island
 ↓
Excalidraw
```

Excalidraw owns visual editing. Kinevo owns canvas entity, ownership, links, persistence, versioning, and offline synchronization.

### Knowledge boundary
Tiptap owns document editing mechanics. Kinevo owns note identity, links, persistence, access, attachments, and domain semantics.

### AI boundary
LLMs are untrusted external-like providers. All outputs pass schema validation and domain validation.

### Mobile boundary
NativePHP (Phase 26/27) is a **presentation front, not a second backend**. It reuses the Domain +
Application + Infrastructure layers; its Blade/EDGE native UI sits above the same dependency rule
(architecture.md top). On-device SQLite is the local canonical store and reconciles to PostgreSQL
via the offline-sync envelope. See `docs/mobile-architecture.md` and `docs/adr/ADR-008-mobile.md`.

### Scheduler boundary
Scheduler is a domain subsystem, not a controller or job implementation. Jobs trigger scheduler runs but do not implement scheduling decisions.

### Scheduler pipeline
```text
Snapshot
 ↓
Normalize temporal state
 ↓
Build candidate slots
 ↓
Apply hard constraints
 ↓
Rank tasks
 ↓
Score soft signals
 ↓
Generate candidate schedule
 ↓
Validate
 ↓
Explain decisions
 ↓
Persist draft/version
```

### Hard constraints
- hard landscape;
- locked task immobility under automation;
- temporal validity;
- valid durations;
- no illegal overlap;
- deadline feasibility;
- required Sacred Anchor behavior;
- capacity safety rules;
- system-specific invariants.

### Soft optimization signals
- priority tier;
- goal urgency;
- milestone urgency;
- effective capacity;
- difficulty;
- familiarity;
- energy/stress;
- context switching;
- progress value;
- estimated duration confidence.

### Job model
Laravel Queue handles background work. Job implementations MUST be idempotent. Scheduler runs MUST have stable run IDs and persisted status.

### Storage model
PostgreSQL stores canonical structured state. Object storage stores large binary assets. Browser IndexedDB stores temporary offline state/queue.

### Network trust boundaries
- Browser untrusted.
- Public HTTP enters through Nginx/Cloudflare.
- PostgreSQL private.
- Redis private.
- Ollama private/internal.
- Object storage access through signed or scoped access mechanisms.

### Portability
Architecture MUST remain portable across:
- Oracle VPS;
- other VPS;
- Docker-compatible managed hosting;
- local development.

No domain layer may depend on Oracle-specific APIs.

### Deployment shape
```text
Cloudflare
 ↓
Nginx
 ↓
Laravel app
 ├── PHP-FPM
 ├── Queue worker
 └── Scheduler
 ↓
PostgreSQL
```

Ollama is optional and may run alongside the app in development or as a private service in larger deployment profiles.

### Architecture invariants
- no microservices requirement for MVP;
- no Kubernetes;
- no Kafka;
- no GraphQL unless future requirement justifies it;
- no public database;
- no LLM direct DB mutation;
- no third-party editor as business-domain owner.

---


## Workspaces & Context System (Phase 19)

Workspaces are top-level context containers owned by a single user. Domain:
`App\Domain\Workspaces` (aggregate + type/status VOs + repository contract);
persistence: `workspaces` table plus nullable `workspace_id` on goals,
programs, tasks, notes and canvases (parent-inherited entities such as
milestones, subtasks, schedule assignments and canvas files follow their
parent and are not directly scoped; Hard Landscape and notifications remain
global by explicit decision).

Precedence (server-enforced): explicit context > declared active workspace >
owner's default ("Personal", provisioned at registration and lazily adopted
for pre-existing rows). Lists accept `workspace_id` (validated owned) or an
explicit global view; writes always land in exactly one workspace. Task↔Goal
consistency is validated server-side (inheritance, conflict → 422).

Client contract: one authoritative active state (server default wins unless a
validated stored choice or `?workspace=` deep link exists); switching reloads
the app so every surface rehydrates consistently. Knowledge links remain the
relationship authority — workspaces add context only.
