# Nivaras specification — index

This directory is the **source of rules** for the rebuild. Tasks (`tasks/`) say *what* to build; this spec says *which rules* it must obey; `CLAUDE.md` says *how* an agent works. Process rules live at the repository root: `AGENTS.md` (binding rules, precedence, repository map, commands), `WORKFLOW.md` (task steps, blocking, status lifecycle, report), `COMMIT_CONVENTION.md` and `BRANCHING.md`. A change to behaviour is a change to a spec file first (rule M: the same commit also updates this index, `AGENTS.md`, `CODEOWNERS`, `13-roadmap.md` and affected tasks, with grep/script evidence).

Legacy behaviour is cited as `legacy-v0:<path>` and read with `git show legacy-v0:<path>`. The archived ZIP in `legacy/` is never opened.

## 1. Reading order

Each file depends only on files above it (its header says `Depends on:`).

| # | File | Prefix | What it fixes |
|---|---|---|---|
| — | [`00-verdict.md`](00-verdict.md) | — | Phase 0 analysis of the legacy repo; measured baseline; keep/rewrite/drop |
| — | [`decisions.md`](decisions.md) | `D` | Decision log D-01…D-95 and owner-only open items OWNER-01…07 |
| — | [`adr/`](adr/) | `ADR` | Architecture decision records (see §4) |
| 01 | [`01-product.md`](01-product.md) | `PRD` | Problem, personas, principles, scope, non-goals (PROVISIONAL until V1) |
| 02 | [`02-architecture.md`](02-architecture.md) | `ARCH` | Modular monolith, crates, ports, events, tenancy, runtime |
| 03 | [`03-module-system.md`](03-module-system.md) | `MOD` | Module descriptor, toggles, boundaries ("drag and drop") |
| 04 | [`04-policies.md`](04-policies.md) | `POL` | Typed, versioned, effective-dated policy keys |
| 05 | [`05-templates-and-setup.md`](05-templates-and-setup.md) | `TPL`, `SET` | Install templates and the 3-step setup wizard |
| 06 | [`06-domain-model.md`](06-domain-model.md) | `DOM` | Schema of record (DDL, constraints, RLS, indexes) |
| 07 | [`07-api-conventions.md`](07-api-conventions.md) | `API` | HTTP rules every endpoint obeys |
| — | [`modules/`](modules/) | see §2 | One file per module (behaviour, endpoints, permissions, events, tests) |
| 08 | [`08-frontend.md`](08-frontend.md) | `FE` | Web PWA architecture (also the Android WebView content) |
| 09 | [`09-design-system.md`](09-design-system.md) | `DS` | Tokens (OKLCH, computed contrast), type, grid, components |
| 10 | [`10-security-privacy.md`](10-security-privacy.md) | `PRV`, `THR` | Privacy rules, threat model |
| 11 | [`11-operations.md`](11-operations.md) | `OPS` | Running, backups, upgrades, observability |
| 12 | [`12-testing.md`](12-testing.md) | `TST` | Test layers and quality gates |
| 13 | [`13-roadmap.md`](13-roadmap.md) | `RM` | Milestones M0–M11, validation V1–V5, 1.0 gates, risks |
| 14 | [`14-distribution.md`](14-distribution.md) | `DST` | Channels: Docker one-liner, templates, cloud, PWA, Android |
| 15 | [`15-cloud.md`](15-cloud.md) | `CLD` | Nivaras Cloud business layer |
| — | [`standards/`](standards/) | see §3 | Cross-cutting standards bound by every file above |
| — | [`traceability-exceptions.md`](traceability-exceptions.md) | — | Requirements deliberately without a direct test, each justified |

## 2. Modules

| File | Key | Kind | Prefix | Milestone |
|---|---|---|---|---|
| [`modules/identity.md`](modules/identity.md) | `identity` | core | `ID` | M1 |
| [`modules/admin.md`](modules/admin.md) | `admin` | core | `ADM` | M1 |
| [`modules/workspaces.md`](modules/workspaces.md) | `workspaces` | core | `WS` | M2 |
| [`modules/goals.md`](modules/goals.md) | `goals` | core | `GOAL` | M2 |
| [`modules/tasks.md`](modules/tasks.md) | `tasks` | core | `TASK` | M2 |
| [`modules/landscape.md`](modules/landscape.md) | `landscape` | core | `LAND` | M3 |
| [`modules/scheduler.md`](modules/scheduler.md) | `scheduler` | core | `SCH` | M3 |
| [`modules/execution.md`](modules/execution.md) | `execution` | core | `EXE` | M4 |
| [`modules/progress.md`](modules/progress.md) | `progress` | core | `PRG` | M4 |
| [`modules/sync.md`](modules/sync.md) | `sync` | toggleable (on) | `SYN` | M5 |
| [`modules/ai.md`](modules/ai.md) | `ai` | toggleable (off) | `AI` | M6 |
| [`modules/knowledge.md`](modules/knowledge.md) | `knowledge` | toggleable (on) | `KNW` | M7 |
| [`modules/canvas.md`](modules/canvas.md) | `canvas` | toggleable (template-dependent) | `CNV` | M8 |
| [`modules/billing.md`](modules/billing.md) | `billing` | toggleable (off; cloud only) | `BIL` | M9 |
| [`modules/android.md`](modules/android.md) | — (client shell, not a module) | — | `AND` | M10 |
| [`modules/_template.md`](modules/_template.md) | — | template every module file follows | — | — |

## 3. Standards

| File | Prefix | Scope |
|---|---|---|
| [`standards/code-quality.md`](standards/code-quality.md) | `CQ` | Code quality rules CQ-01…22 |
| [`standards/clean-architecture.md`](standards/clean-architecture.md) | `CA` | Layering and boundary rules CA-01…18 |
| [`standards/validation.md`](standards/validation.md) | `VAL` | Validation and sanitisation of every input |
| [`standards/security-by-design.md`](standards/security-by-design.md) | `SEC`, `SEC-AC` | Security controls SEC-01…23; abuse-case registry SEC-AC-01…48 |
| [`standards/auth.md`](standards/auth.md) | `AUTH` | Access/refresh tokens, rotation, reuse detection, MFA mechanics |
| [`standards/contracts.md`](standards/contracts.md) | `CON` | Canonical forms: money, time, ids, JSON documents |
| [`standards/containers-and-caching.md`](standards/containers-and-caching.md) | — | Image, runtime hardening, in-process caches |
| [`standards/ux-states.md`](standards/ux-states.md) | `UXS` | UI states and feedback catalogue (en, id) |
| [`standards/rbac.md`](standards/rbac.md) | `RBAC` | Roles and the permission registry |
| [`standards/error-handling.md`](standards/error-handling.md) | `ERR` | Problem response and the error-code registry |
| [`standards/user-journeys.md`](standards/user-journeys.md) | `JRN` (J0–J9) | Persona journeys = milestone acceptance = E2E tests |
| [`standards/state-machines.md`](standards/state-machines.md) | `SM` | State machines SM-01…24 |
| [`standards/data-flows.md`](standards/data-flows.md) | `FLW` | Multi-step flows FLW-01…24 with transaction boundaries |
| [`standards/open-source-stack.md`](standards/open-source-stack.md) | — | Every dependency with reason, version, licence and embedded-data provenance; additions are listed with the task that adopts them |

## 4. ADRs

| ADR | Title | Decision |
|---|---|---|
| [0001](adr/0001-rust-backend-rebuild.md) | Rebuild the backend in Rust from a new specification | D-02 |
| [0002](adr/0002-agpl-license-and-dco.md) | AGPL-3.0-only with DCO; hosted cloud as the business | D-04 |
| [0003](adr/0003-react-spa-frontend.md) | React + Vite SPA embedded in the server binary | D-07 |
| [0004](adr/0004-single-schema-rls-tenancy.md) | One schema, `account_id` on every row, RLS forced | D-08 |
| [0005](adr/0005-alpine-runtime-image.md) | Small Alpine runtime image with a shell | D-14 |
| [0006](adr/0006-android-capacitor-shell.md) | Android as a Capacitor shell; token transport for native | D-21, D-37 |
| [0007](adr/0007-access-token-hs256.md) | HS256 access tokens; opaque rotating refresh tokens | D-11 |
| [0008](adr/0008-module-toggles-not-plugins.md) | "Drag and drop" = module toggles, not a plugin API | D-01 |
| [0009](adr/0009-postgres-queue-and-outbox.md) | PostgreSQL as job queue and outbox; no Redis | D-13 |
| [0010](adr/0010-planning-window-and-pure-scheduler.md) | Pure scheduler crate, daily planning window, server authority | D-22, D-33 |
| [0011](adr/0011-database-contexts-and-account-provisioning.md) | Database contexts, role-scoped RLS policies, account provisioning | D-39 |

A new ADR is numbered next in sequence, starts as `Proposed`, and becomes `Accepted` only through the decision log. Accepted ADRs are never edited except to mark them `Superseded by ADR-NNNN`.

## 5. ID scheme

- Requirement ids are `PREFIX-NN` (e.g. `SCH-FR-07`, `CA-09`, `SEC-AC-12`). Module files may add a kind segment (`-FR-` functional, `-NFR-` non-functional). Ids are **stable forever**: never renumbered or reused; a removed requirement keeps its id with `(withdrawn, D-nn)`.
- Decisions `D-nn` (`decisions.md`), owner questions `OWNER-nn`, maintainer actions `M-<NAME>` (e.g. `M-LEGAL`, `M-RIGHTS`).
- Tasks are `T-nnn` (`tasks/`); commit subjects reference them as `[T-nnn]`.
- Error codes are `snake_case` strings in the `ERR` registry; permission keys are `<module>.<resource>.<action>` in the `RBAC` registry; policy keys are dotted (`auth.refresh.grace`) in `04-policies.md`.
- Consistency is checked by `scripts/spec_tool.py` (`codes`, `perms`, `check`).

## 6. Glossary

| Term | Meaning |
|---|---|
| Account | One person's tenant. Every tenant row carries `account_id`; RLS isolates accounts. |
| Instance | One installation (self-hosted or cloud), administered by an `admin` account. |
| Module | A first-party feature bundle (crate `kinevo-mod-<key>`) with a descriptor; toggleable modules can be switched off per instance. |
| Template | A preset of modules and policies chosen in setup step 1 (`personal`, `student`, `cloud`, …). |
| Policy | A typed, versioned, effective-dated setting an operator may change (`04-policies.md`); never a hard-coded business value. |
| Workspace | A project/area container grouping goals, tasks, notes and boards. |
| Goal / Milestone / Task / Subtask | Outcome → ordered checkpoints → schedulable work items → checklist steps. |
| Hard Landscape | Fixed commitments (classes, meetings, shifts) the scheduler must never overlap. |
| Planning window | The daily local time range in which the scheduler may place work (ADR-0010). |
| Draft / Apply | A proposed schedule, and the server-side re-validated commit of that proposal. |
| Sync Now | Re-plan after reality changed while keeping every still-feasible assignment. |
| Today / Now task | The execution view and the single task the user should do next. |
| Focus session | A started-to-finished work interval on one task. |
| Operation ledger | The server record of offline operations (applied / conflict / rejected), keyed by operation UUID. |
| Proposal (AI) | Untrusted AI output, schema- and domain-validated, accepted or rejected by the user. |
| Outbox | Events written in the same transaction as the state change and delivered at-least-once to other modules. |
| Nivaras Cloud | The optional managed hosting of the same AGPL image. |

## 7. Spec → milestone → task map

Milestone scope comes from `13-roadmap.md` §1. The task-level map (which `T-nnn` implements which requirement id) is generated into `tasks/index.md` by `scripts/tasks_index.py` and verified with `--check`; it is not maintained by hand here.

| Milestone | Specs implemented |
|---|---|
| M0 | this `spec/`, agent rules, `tasks/`, governance, CI skeleton, brand |
| M1 | 02, 03, 04, 05, 06 (platform + identity/admin tables), 07, `modules/identity`, `modules/admin`, 08 (shell), 09 (tokens, primitives), 11 (image), 12 (isolation/architecture/contrast checks) |
| M2 | `modules/workspaces`, `modules/goals`, `modules/tasks` |
| M3 | `modules/landscape`, `modules/scheduler` |
| M4 | `modules/execution`, `modules/progress`, 08 (PWA) |
| M5 | `modules/sync`, 08 (offline) |
| M6 | `modules/ai` |
| M7 | `modules/knowledge` |
| M8 | `modules/canvas` |
| M9 | `modules/billing`, 15 |
| M10 | `modules/android`, 14 (Android channel) |
| M11 | 10, 12, 13 §3 (1.0 gates) |
