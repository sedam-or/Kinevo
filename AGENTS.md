# Kinevo — AI Coding Agent Operating Contract

### Mission
Build Kinevo as a maintainable, testable, explainable, offline-capable,
open-source modular monolith while preserving the invariants defined by
`docs/SRS.md`, the architecture, and the test contracts. Never silently redefine
product requirements; never overwrite completed implementation merely to match
documentation.

### Normative source hierarchy
When documents disagree, use this order unless an approved ADR explicitly states
otherwise:
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
Lower-level documents and existing code cannot redefine higher-level
requirements. If a conflict exists between implementation and requirements,
report it and determine the smallest safe correction — do not silently choose.

### Documentation map (R0 rebaseline, 2026-08-31)
- **Documentation authority index:** `docs/README.md` — start there; do not guess.
- **Execution authority:** `docs/roadmap/KINEVO_MASTER_EXECUTION_PROGRAM.md` (R0 → P39).
- **`TASK.md` is a slim control plane ONLY** (current phase, gate, next phase, conventions,
  links). Detailed task bodies live in `docs/roadmap/active/` and `docs/roadmap/planned/`.
- **Task IDs are immutable.** Never recycle; supersession is explicit; renumbering only via the
  mapping in `docs/roadmap/rebaseline-2026-08.md`.
- **`docs/audit/*` dated snapshots are FROZEN** — never rewritten; new audits get new dated files.
- **Accepted ADRs are historical authority** — never deleted or silently edited; supersession is
  marked explicitly in the file.
- **Archived material** (`docs/roadmap/archive/**`, `docs/archive/**`) is evidence, not authority.
- Transitional bridge until P29: accepted ADRs + verified implementation + frozen audits +
  `docs/convergence/TARGET_DECISION_REGISTER.md` close gaps between old canonical docs and current
  reality.

### Repository governance documents
Contributors and agents MUST respect the open-source governance files:
- `CONTRIBUTING.md` — contributor guide (branch/commit/PR rules, SRS/ADR/migration/API processes).
- `SECURITY.md` — vulnerability disclosure policy. Security issues are never
  reported in public issues.
- `docs/third-party/licenses.md` — license/provenance ledger. Check before
  copying external source.
- `.github/` — issue/PR templates, CI, Dependabot, CODEOWNERS.

### Mandatory agent behavior — before modifying code
The agent MUST:
- identify the relevant SRS requirement IDs (FR-xx / NFR-xx);
- inspect the affected sections of `docs/SRS.md`, `docs/domain-model.md`, and
  `docs/api/openapi.yaml`;
- locate the current domain/application/infrastructure implementation;
- inspect related tests (`server/tests/`);
- inspect relevant local `AGENTS.md` files if present;
- determine whether a migration or API/schema change is required;
- determine whether documentation must change;
- explain the intended impact internally and keep the patch bounded;
- avoid unrelated refactoring unless required for correctness.

### Mandatory agent behavior — after modifying code
The agent MUST:
- run the relevant tests (`composer test` in `server/`, or `make test`);
- run static analysis (`composer analyse`) and formatting/lint (`composer lint`);
- update API/schema contracts when they changed (`docs/api/openapi.yaml`,
  `database/migrations/`);
- update documentation when required;
- update `TASK.md` only when a task is actually completed or materially
  progressed;
- preserve migration safety;
- report unresolved assumptions or failures honestly.

### Pre-Commit Mandatory Verification Protocol
Before executing `git commit`, the agent MUST run and ensure ALL of the
following commands pass 100% without error:

1. `npm audit` — dependency security check.
2. `npm run typecheck` and `npm run build` — frontend typecheck and build.
3. `./vendor/bin/phpstan analyse` and `composer test` (or `make test`) — PHP
   static analysis and test suite.

It is FORBIDDEN to commit if any of the steps above results in a failing
status. If a step fails, fix the underlying issue first and re-run the full
protocol until everything is green before committing.

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
Requires explicit impact analysis and an ADR (`docs/adr/`) unless already
covered by an existing approved decision.

### Forbidden shortcuts
- Do not create business logic in Vue components.
- Do not make controllers contain scheduling algorithms.
- Do not let Eloquent models become the entire domain model.
- Do not let the browser become authoritative for schedule state.
- Do not allow LLM output to bypass domain validation.
- Do not introduce a new dependency when an existing repository abstraction
  already solves the problem.
- Do not copy external application source code merely because it is convenient;
  check `docs/third-party/licenses.md` and `CONTRIBUTING.md` first.
- Do not expose secrets, note contents, AI prompts, or private document content
  in logs.
- Do not silently introduce a second source of truth.
- Do not silently drift the schema; any schema change requires a migration.
- Do not make undocumented API or architecture changes.
- Do not commit secrets, tokens, or private data.
- Do not change tests merely to make them pass.
- Do not declare a task done without evidence (test output, CI, commit).

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
Scheduling MUST be deterministic for the same inputs. AI may propose semantic
decomposition or explanations, but it MUST NOT become the authoritative schedule
generator.

### Transaction rule
Any mutation that must leave several entities consistent MUST use an explicit
transaction boundary or a documented compensating action.

### Concurrency rule
Use optimistic versioning for mutable aggregates where concurrent edits are
possible. Return stable `409` conflicts rather than silently overwriting newer
state.

### API rule
Business API mutations MUST validate authorization, ownership, payload shape,
state transition, and idempotency semantics server-side.

### Offline rule
IndexedDB is cache/queue, never canonical source of truth. Offline operations
must carry an operation UUID and reconcile through the server contract.

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

### External engine boundary
Excalidraw owns drawing behavior. Tiptap owns editing behavior. Ollama owns
inference behavior. Kinevo owns business semantics: identity, ownership,
persistence, versioning, links, attachments, offline state, and domain rules.

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
The preferred development coding model MAY include Qwythos 9B Q6_K GGUF,
DeepCoder, Qwen Coder, or equivalent local models. These are development tools,
not runtime product dependencies.

### External open-source components
Use adapters. Do not make Excalidraw, Tiptap, or any third-party editor the owner
of Kinevo business semantics.

### UI/UX implementation guidance (agent skills, not design authority)
Two third-party skill packs are installed locally (via `npx skills add
Leonxlnx/taste-skill` and the `ui-ux-pro-max-skill/` toolkit) as CONSIDERED
WORKFLOWS for front-end craft and micro-interactions:
- **taste-skill** (`design-taste-frontend` + companions in `.agents/skills/`)
  — anti-slop front-end taste, brief-inference, pre-flight checks.
- **ui-ux-pro-max** (`ui-ux-pro-max-skill/.claude/skills/*` + `search.py`) —
  searchable UI styles, product palettes, font pairings, chart types, and UX
  guidelines.
Rule for every front-end execution: the agent MUST consult the relevant skill
content before writing or reviewing user-facing UI (unless the change is a
pure refactor with no visible behavior change). These skills are GUIDANCE ONLY
and never override the normative hierarchy in the corresponding
“Normative source hierarchy” section above: `docs/design.md`,
`docs/design-tokens.md`, `docs/ui-audit.md`, and `docs/browser-e2e.md` remain
the UI/UX authorities. If a skill suggestion conflicts with a design document,
the design document wins and the conflict is recorded, not silently resolved.
Installed dirs are git-ignored (third-party, not vendored); reinstall with the
commands in the “Local developer tooling” note.

### UI/UX stabilization freeze (rescue R0–R7)
During the rescue phase (TASK-R0…R7, see `TASK.md` Phase 16), the agent MUST NOT
introduce new AI features, new scheduling algorithms, new major domain concepts,
or new dependencies. `docs/design.md` (incl. §74–§103) and `docs/design-tokens.md`
are UI/UX authority; `docs/ui-audit.md` and `docs/browser-e2e.md` are the living
gap/browser baselines. Allowed work: stability, usability, integration, browser
correctness, visual consistency.
- **Exemption path:** P0 fixes only (data loss, auth/save failure, core feature
  blocked, canvas crash, offline mutation lost), each classified per
  `docs/ui-audit.md` §3 and logged as a bug-taxonomy record `docs/ui-audit.md`
  §6 before the fix lands. P1+ work waits for explicit group approval.
- **Prime objective:** the first-love loop LOGIN → TODAY → NOW TASK → START →
  COMPLETE → PROGRESS → NEXT TASK (`docs/design.md` §99) must be made beautiful
  and reliable before any broader redesign.
- The freeze lifts only when TASK-R7 (release readiness) marks the rescue phase
  complete; `DONE` at contract level (tests/typecheck/build) is never sufficient
  evidence to clear a rescue gate without real browser evidence.

### Release & documentation lifecycle
- Versioning follows SemVer; the changelog follows Keep a Changelog; commits
  follow Conventional Commits. Governance: `docs/release-management.md`.
- `TASK.md` (execution state) and `CHANGELOG.md` (user-facing outcomes) are
  separate documents — never merge them, never dump git history into the
  changelog.
- The canonical application version comes from the latest `v*` git tag; there is
  no committed `VERSION` file. The SRS version and API major version are
  separate contracts.
- Before a release candidate, run `make changelog-check`, `make version-check`,
  and `make release-dry-run`; never weaken a gate to get green. Publishing a tag
  / GitHub Release is a deliberate manual action — the agent never tags or pushes
  a release without explicit instruction.
- Keep every document in one lifecycle state (ACTIVE / AUTHORITATIVE / REFERENCE
  / TEMPORARY / DEPRECATED / ARCHIVED). Promote durable decisions to ADRs /
  `docs/architecture.md` / `docs/SRS.md`; archive or remove spikes, prompts, and
  scratch. Never let a temporary document silently become architecture
  authority, and never edit published release tags.

---