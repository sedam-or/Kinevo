# LIFESYNC OS — AI Coding Agent Operating Contract

### Mission
Build LIFESYNC OS as a maintainable, testable, explainable, offline-capable modular monolith while preserving the invariants defined by `docs/SRS.md`.

### Normative source hierarchy
When documents disagree, use this order unless an approved ADR explicitly states otherwise:
1. `docs/SRS.md`
2. `docs/architecture.md`
3. `docs/domain-model.md`
4. `docs/scheduling-engine.md`
5. `docs/knowledge-layer.md`
6. `docs/offline-sync.md`
7. `docs/ai-architecture.md`
8. `docs/api/openapi.yaml`
9. `database/migrations/`
10. `docs/design.md`
11. `docs/test-strategy.md`
12. implementation source
13. comments / temporary notes

`TASK.md` controls execution order/status only. It never overrides requirements.

### Mandatory agent behavior
Before modifying code, the agent MUST:
- identify the relevant SRS requirement IDs;
- locate the current domain/API/schema implementation;
- inspect related tests;
- inspect relevant local `AGENTS.md` files if present;
- explain the intended impact internally and keep the patch bounded;
- avoid unrelated refactoring unless required for correctness.

After modifying code, the agent MUST:
- run relevant tests;
- update API/schema/docs when contracts changed;
- update `TASK.md` only when a task is actually completed or materially progressed;
- preserve migration safety;
- report unresolved assumptions or failures honestly.

### Agent modes
#### Exploration mode
Read, search, map dependencies, identify invariants. No broad edits.

#### Implementation mode
One bounded vertical slice. Code + tests + required contracts.

#### Verification mode
Prove behavior. Never weaken a test merely to get green.

#### Refactoring mode
No intentional behavior change. Establish passing baseline first.

#### Architecture-change mode
Requires explicit impact analysis and an ADR unless already covered by an existing approved decision.

### Forbidden shortcuts
- Do not create business logic in Vue components.
- Do not make controllers contain scheduling algorithms.
- Do not let Eloquent models become the entire domain model.
- Do not let the browser become authoritative for schedule state.
- Do not allow LLM output to bypass domain validation.
- Do not introduce a new dependency when an existing repository abstraction already solves the problem.
- Do not copy external application source code merely because it is convenient; check `docs/third-party/licenses.md` first.
- Do not expose secrets, note contents, AI prompts, or private document content in logs.
- Do not silently introduce a second source of truth.

### Domain implementation rule
```text
HTTP / UI
   ↓
Controller / Inertia endpoint
   ↓
Application use case
   ↓
Domain service / domain entity
   ↓
Repository / infrastructure
   ↓
PostgreSQL / storage
```

### Scheduling rule
Scheduling MUST be deterministic for the same inputs. AI may propose semantic decomposition or explanations, but it MUST NOT become the authoritative schedule generator.

### Transaction rule
Any mutation that must leave several entities consistent MUST use an explicit transaction boundary or a documented compensating action.

### Concurrency rule
Use optimistic versioning for mutable aggregates where concurrent edits are possible. Return stable `409` conflicts rather than silently overwriting newer state.

### API rule
Business API mutations MUST validate authorization, ownership, payload shape, state transition, and idempotency semantics server-side.

### Offline rule
IndexedDB is cache/queue, never canonical source of truth. Offline operations must carry an operation UUID and reconcile through the server contract.

### AI rule
All AI output is untrusted input. Required path:
```text
User intent
  ↓
AI provider
  ↓
Structured response
  ↓
Schema validation
  ↓
Domain validation
  ↓
Human approval where mutation is material
  ↓
Transaction
```

### Definition of done
A non-trivial feature is DONE only when:
- requirement linkage exists;
- domain behavior exists;
- persistence/API impact is handled;
- relevant unit/integration/E2E tests pass;
- accessibility/UX is handled where user-facing;
- offline behavior is handled where in scope;
- docs/contracts are synchronized;
- no unresolved compile/test errors remain unless explicitly documented.

### Local developer tooling
The preferred development coding model MAY include Qwythos 9B Q6_K GGUF, DeepCoder, Qwen Coder, or equivalent local models. These are development tools, not runtime product dependencies.

### External open-source components
Use adapters. Do not make Excalidraw, Tiptap, or any third-party editor the owner of LIFESYNC business semantics.

---

