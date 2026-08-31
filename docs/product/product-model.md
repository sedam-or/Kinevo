# Kinevo — Product Model

> STATUS: AUTHORITATIVE (P29, 2026-08-31). What each entity of the core loop is,
> why it exists, what it owns, what it does NOT own, and how it participates in
> the loop (Constitution §4). Domain-level detail: `docs/domain-model.md`; locked
> workspace semantics: `workspace-model.md`; requirements: SRS v3 §2.

| Entity | Why it exists | Owns | Does NOT own | Role in the loop |
|---|---|---|---|---|
| **Workspace** | Life areas stay separable without fragmenting execution | the contextual scope of Goals/Programs/Tasks/Notes/Canvas | reality (Hard Landscape), schedule placement, execution surfaces | the [Workspace] lens over the whole model |
| **Goal** | Captures a meaningful outcome worth structuring | outcome, horizon, deadline, status, its milestones/programs | scheduling, tasks' day-to-day state | INTENTION → STRUCTURE |
| **Milestone** | Makes a goal verifiable stepwise | its own lifecycle + progress | task placement | STRUCTURE (goal → approved steps) |
| **Program** | Organizes repeated work inside a goal | recurring work patterns | reality constraints | STRUCTURE (repeatable intention) |
| **Task** | The unit of executable work | status, priority, estimate, subtasks, evidence | the calendar slot it receives (assignment is scheduling's output) | EXECUTION atom; completion drives PROGRESS |
| **Hard Landscape** | Records reality that must never be scheduled over | fixed commitments (recurring or one-off), imported or manual | flexible work, workspace scope (it is global) | REALITY input to every schedule decision |
| **Effective Landscape** | The single resolved truth of what time actually allows | occurrence resolution (base ← recurrence → overrides) | the user's intentions | REALITY → SCHEDULE bridge (ADR-015) |
| **Schedule Draft** | Lets the user review before anything changes | proposed placements + impact explanation | authority to apply itself (never auto-applies) | SCHEDULE stage gate (ADR-016) |
| **Today** | Where the plan meets the clock | NOW/NEXT/Timeline execution view + capture | week/month planning, goal definitions | EXECUTION stage; the first-love surface |
| **Week / Month** | Mid/long-horizon planning views | 7-day plan; month grid + load | reality definition | SCHEDULE ↔ EXECUTION visibility |
| **Knowledge (Notes/Canvas/Links)** | Keeps thinking connected to execution | notes, boards, typed links | scheduling, task status | CONTEXT, attached to Goals/Tasks |
| **Progress** | The honest record of what moved | progress events, analytics | judgment about the user | PROGRESS stage (events are domain-derived) |
| **Review** | Reflection that closes the loop | review flows (EOD, recovery, weekly audit — dedicated surface TARGET) | work itself | REVIEW → ADAPT |
| **Notification** | Timely awareness without noise | read state, grouping | schedule truth | support surface (global, contextual) |
| **AI Proposal** | Assistance without authority | its own pending/accepted/rejected state | anything until explicitly accepted | feeds STRUCTURE (AI proposes, user decides) |
| **Subscription / Entitlement** | Sustainable operation with honest limits | plan state, access limits | product data, AI truth (ledger is separate) | commercial frame around the loop |

Loop invariants: every stage hands forward through an explicit, user-visible
gate (structure approval, draft review, accept/reject); nothing mutates
silently; every completion produces progress; every review ends in an
executable next action.
