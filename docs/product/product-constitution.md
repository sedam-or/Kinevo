# Kinevo — Product Constitution

> STATUS: AUTHORITATIVE (P29, 2026-08-31). This is the highest canonical product
> authority. Hierarchy: legal/security hard constraints → **this document** →
> SRS → domain/architecture → UX/IA/design → commercial policy → ADRs →
> roadmap/TASK → implementation evidence. Nothing below this document may redefine
> what Kinevo IS. Supersedes: scattered identity/vision statements in the SRS v2
> preamble, master-program §1 prose (content migrated; the program remains the
> execution authority), and `docs/convergence/TARGET_DECISION_REGISTER.md`
> decisions 1, 10, 11, 12, 13, 14 (migrated here; register archived as evidence).
> Companion canonical docs: `vision-mission.md`, `product-model.md`,
> `workspace-model.md`, `commercial-model.md` (same directory).

## 1. What Kinevo is

**Kinevo is a workspace-scoped personal operating system that reconciles
intention, reality, and context into executable action.**

- **Intention** — what a person wants to achieve (Goals, Milestones, Programs).
- **Reality** — what time actually allows (Hard Landscape: fixed commitments,
  imported schedules, effective scheduling with overrides).
- **Context** — what a person knows and thinks (Knowledge: Notes, Canvas, links).

Tagline: **"Kinevo — Turn intentions into execution."**

Product category: personal productivity operating system (single-user,
workspace-scoped), not a team tool, not a calendar replacement, not a todo list.

## 2. The user problem

Serious individuals carry meaningful intentions (research, study, building,
practice) while their real life imposes fixed commitments — class schedules,
meetings, shifts — that generic tools ignore. Todo lists record wishes; calendars
record events; neither connects the two, and neither protects context. The result:
plans that shatter on contact with reality, and thinking scattered across apps.

Kinevo exists to close that gap: capture intention, respect reality, keep context
connected to execution, and make the next move obvious.

## 3. Target user

**Primary: serious individual users** — knowledge workers, builders, researchers,
students, creators, professionals — who manage meaningful intentions alongside
non-negotiable real-world commitments. Indonesia-first commercial assumptions are
retained without limiting architecture. Gen-Z influence shapes brand expression
only; it does not redefine the market. The single-user model is canonical through
v1 (owner decision, migrated from register #1); multi-user is not a roadmap item
until the owner explicitly changes it.

## 4. Core product loop

```
INTENTION → STRUCTURE → SCHEDULE → TODAY → EXECUTION → PROGRESS → REVIEW → ADAPT
   (Goal)    (milestones,  (effective   (now/    (timers,     (events,   (review,  (reschedule,
             programs)     landscape,   next)    completion)  analytics) recovery) import, shift)
                           drafts)
```

The first-love experience (design-language baseline, browser-proven in P28):
LOGIN → TODAY → NOW TASK → START → COMPLETE → PROGRESS → NEXT TASK.

Conceptual model:

```
                        KINEVO
                      WORKSPACE
                          │
          ┌───────────────┼───────────────┐
          │               │               │
     INTENTION         REALITY         CONTEXT
          │               │               │
        Goals       Hard Landscape      Knowledge
          │           (import & sync)   Notes / Canvas
          └───────────────┼───────────────┘
                          ↓
                      STRUCTURE
                          ↓
                       SCHEDULE
                          ↓
                         TODAY
                          ↓
                      EXECUTION
                          ↓
                       PROGRESS
                          ↓
                        REVIEW
                          ↓
                        ADAPT
```

## 5. User authority

1. **AI proposes. User decides.** AI never holds schedule authority, never
   mutates silently, and every material AI output passes structured validation →
   domain validation → explicit human approval before anything is applied.
2. **The deterministic scheduler owns placement.** ADR-015/016 semantics:
   effective landscape → capacity → deterministic generator → draft → explicit
   user approval. Weekly automation may calculate; it must never auto-apply.
   Sync Now re-proposes; it must never silently mutate accepted work.
3. **Reality is respected, not overwritten.** Hard Landscape is global personal
   reality; overrides (Permanent Shift, One-Time Exception) change the effective
   schedule through explicit, previewed, auditable actions.

## 6. Data ownership

- **Kinevo Core is genuinely open source (MIT) and self-hostable.** The user can
  own the software and their data.
- **Kinevo Cloud** is the optional managed convenience layer (hosting, managed
  AI allowance, sync/storage operations, backups).
- The user's data is not the product. No behavioral sale of personal context.
- Offline work is never lost: ADR-017 makes the server the reconciliation
  authority with an idempotent operation ledger; the client queue is a cache,
  never the canonical source.

## 7. AI role (bounded)

AI assists three bounded ways: (1) semantic decomposition proposals (goal →
milestones), (2) structure suggestions (canvas/note), (3) explanations of
scheduler decisions. AI output is untrusted input until validated and approved.
AI availability degrades gracefully (hosted allowance / BYOK / local provider /
unavailable states) — the product works without AI.

## 8. Core vs Cloud

| | Kinevo Core | Kinevo Cloud |
|---|---|---|
| License | MIT, public | Private SaaS layer |
| Hosting | Self-hosted | Managed |
| AI | Bring-your-own provider (BYOK) or self-hosted | Hosted allowance (bounded) |
| Data | User-owned storage | Managed storage + backups |
| Boundary | No repository split before P34 | Separate private repo (P34) |

"Own the software. Or let Kinevo host it for you." Core is never hollowed out to
manufacture Cloud value.

## 9. Product non-goals (through v1)

Kinevo is NOT:
- a team collaboration suite, RBAC system, or enterprise PM platform
- an autonomous AI agent that controls the user's schedule
- a generic document workspace or chat application
- a calendar replacement only, or a todo-list clone
- a social productivity network

Single-user personal-productivity architecture remains canonical through v1.
Power tier must never secretly become teams/enterprise administration.

## 10. Product principles

1. **Intention over busywork** — everything traces back to a meaningful goal.
2. **Reality-aware planning** — fixed commitments constrain; capacity is honest.
3. **Context continuity** — Goal ↔ Task ↔ Note ↔ Canvas ↔ Progress stay linked.
4. **User authority** — AI proposes; the user decides; nothing mutates silently.
5. **Deterministic execution** — same inputs, same schedule; explainable placement.
6. **Visible consequences** — every action shows what changed and what is next.
7. **Recoverability** — offline ledger, optimistic conflicts, history preserved;
   failure states explain, preserve data, and offer recovery.
8. **Data ownership** — MIT Core, self-hostable, export-friendly.
9. **Quality over speed** — gates over dates; never weaken tests for green.

## 11. North Star

**WGPU — Weekly Goal Progressing Users**: unique users in a seven-day window who
perform at least one meaningful progress action on one or more active goals.
Supporting activation/retention metric definitions: `docs/retention-events.md`
(RET-007 taxonomy v1). Instrumentation lands in P32.

## 12. Decision log (migrated from TARGET_DECISION_REGISTER)

The following register decisions are now canonically housed here (register
archived as evidence): #1 single-user through v1 · #10 deterministic scheduler
remains authoritative · #11 AI proposes/user decides · #12 Effective recurrence
implemented, not descoped (ADR-015) · #13 offline reconciliation implemented,
not downgraded (ADR-017) · #14 Core remains MIT. Workspace decisions #2–#8 live
in `workspace-model.md`; runtime/email/price decisions live in
`architecture.md` and `commercial-model.md`.
