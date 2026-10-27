# 01 — Product

Depends on: `decisions.md`, `00-verdict.md`. Requirement prefix: `PRD`.
Validation status: **PROVISIONAL.** No user research exists (`00-verdict.md` §7). Items marked
`[ASSUMED]` must be confirmed by the validation exercise in `13-roadmap.md` §Validation before 1.0 scope is frozen.

## 1. What Nivaras is

Nivaras turns long-horizon goals into a schedule you can follow, around the commitments you cannot
move. One sentence: **"Plans that survive contact with your life."**

Loop: `GOAL → MILESTONE → TASK → SCHEDULE (around fixed commitments) → TODAY → FOCUS → PROGRESS → ADAPT`.

Nivaras is a **single-user planner** (D-30): one person, one account, many workspaces (projects/areas).
It is not a team tool, not a calendar replacement, not a note-taking suite.

## 2. Users

| Persona | Description | Primary jobs |
|---|---|---|
| **Maya — ambitious student** | Fixed class/lab hours, thesis and side projects, tight deadlines `[ASSUMED]` | Import fixed hours from ICS; break a goal into milestones; see "what now"; know if a deadline is at risk |
| **Dion — professional with many projects** | Meetings, day job, 3–5 personal projects `[ASSUMED]` | Keep projects separate (workspaces) yet scheduled together; re-plan when a meeting moves |
| **Sari — self-hoster** | Runs the instance for herself (homelab/VPS) | Install in minutes; choose modules; back up; update |
| **Instance admin** | Same person as Sari, or the Nivaras Cloud operator | Open/close registration, enable modules, manage accounts, view audit log |

Roles in the system are `instance_admin` and `account_owner` only (D-12).

## 3. Jobs to be done (JTBD)

| ID | Job |
|---|---|
| PRD-01 | When I set a meaningful goal, help me break it into executable milestones and tasks, and let me stay in control of the plan. |
| PRD-02 | When my fixed commitments change, re-plan honestly around the time I actually have. |
| PRD-03 | When I open the app, show me the one thing to do now and what is next. |
| PRD-04 | When I work, let me start, pause and finish a task with a timer, and record what happened. |
| PRD-05 | When I look back, show me what moved and what is at risk. |
| PRD-06 | When I lose connectivity, let me keep working and reconcile safely later. |
| PRD-07 | When I think, keep notes and sketches attached to the goal or task they serve. |
| PRD-08 | When I install Nivaras, let me be productive after a 3-step setup and own my data. |

## 4. Product principles

1. **Intention over busywork.** Every task can trace to a goal (tasks without a goal are allowed but flagged "unanchored").
2. **Reality is respected.** Fixed commitments (Hard Landscape) are never overwritten by automation.
3. **The scheduler decides placement; the user decides acceptance.** Nothing the scheduler or AI proposes changes the plan until the user approves it.
4. **Same input, same plan.** Deterministic, explainable placement (D-22).
5. **You own your data.** Self-host is complete; export is always available; no feature is withheld from self-host except operating a payment gateway (D-25, D-26).
6. **Boring and fast.** One binary, one database, no extra services (D-13, D-05).

## 5. Scope

### 5.1 In scope for 1.0 (D-20)

| Area | Module (key) | Toggleable | Milestone |
|---|---|---|---|
| Sign-up/login, sessions, TOTP | `identity` | no (core) | M1 |
| Instance setup wizard, settings, audit, module switches | `admin` | no (core) | M1 |
| Workspaces | `workspaces` | no (core) | M2 |
| Goals + milestones | `goals` | no (core) | M2 |
| Tasks + subtasks + status machine | `tasks` | no (core) | M2 |
| Hard Landscape, overrides, ICS import/export | `landscape` | no (core) | M3 |
| Deterministic scheduler, drafts, Sync Now | `scheduler` | no (core) | M3 |
| Today, execution timer, focus sessions | `execution` | no (core) | M4 |
| Progress | `progress` | no (core) | M4 |
| Offline sync + PWA queue | `sync` | **yes** | M5 |
| AI breakdown | `ai` | **yes** (off by default) | M6 |
| Knowledge notes | `knowledge` | **yes** | M7 |
| Canvas boards | `canvas` | **yes** | M8 |
| Billing (cloud only) | `billing` | **yes** (off by default) | M9 |
| Android app (Capacitor shell) | — | — | M10 |

(Milestone numbers are defined in `13-roadmap.md`. M0 = specification and repository setup.)

### 5.2 Explicit exclusions (through 1.0)

| ID | Excluded | Reason |
|---|---|---|
| PRD-X1 | Teams, sharing, comments, RBAC beyond D-12 | Single-user product (D-30) |
| PRD-X2 | Third-party plugin API, marketplace, visual/no-code builder | Not justified by evidence (D-01); RFC after 1.0 |
| PRD-X3 | Autonomous AI that changes the schedule | Violates principle 3 |
| PRD-X4 | Calendar sync with Google/Microsoft (OAuth) | Needs verified apps and ongoing upkeep; ICS covers the need `[ASSUMED]` |
| PRD-X5 | Boosts, Recharge, Breaks, Pauses, Adaptive, Recovery | D-24 |
| PRD-X6 | KRS PDF import | D-16 |
| PRD-X7 | Native Kotlin/Compose screens, iOS app | D-21 (iOS users use the PWA) |
| PRD-X8 | Email notifications, push notifications, Android local notifications | D-27; in-app only in 1.0 |
| PRD-X9 | Payment gateways other than the one chosen for billing | `modules/billing.md` |
| PRD-X10 | Real-time collaboration, shared canvases | PRD-X1 |

## 6. Success measures (all `[ASSUMED]` until instrumented)

| ID | Measure | Target | How measured |
|---|---|---|---|
| PRD-M1 | Time from `docker run` to first task completed | ≤ 10 minutes for a new self-hoster | Usability trial T2 (`13-roadmap.md`) |
| PRD-M2 | Setup wizard completion with defaults only | 100% of trial users | Trial T2 |
| PRD-M3 | Weekly active planners: users who complete ≥ 1 task linked to a goal in a week | Tracked from M4; no target until baseline exists | Aggregate counter, no personal data (`standards/security-by-design.md` SEC-12) |
| PRD-M4 | Scheduler reproducibility | 100% of golden fixtures produce byte-identical drafts | CI (`12-testing.md`) |
| PRD-M5 | Accessibility | 0 serious/critical axe violations on all shipped screens; WCAG 2.2 AA manual checklist passed | CI + manual |

## 7. Core product requirements

| ID | Requirement | Acceptance (observable) |
|---|---|---|
| PRD-R1 | A new instance reaches a usable state through the 3-step wizard with default values only. | Given an empty database, when the admin completes the wizard pressing only "Next" and "Finish", then a first account exists, the default template's modules are enabled, and `GET /api/v1/instance` returns `setup_completed=true`. |
| PRD-R2 | Every module in §5.1 marked toggleable can be switched off without breaking core flows. | Given `ai`, `knowledge`, `canvas`, `sync`, `billing` all disabled, the core-loop journey J1 (`standards/user-journeys.md`) passes. |
| PRD-R3 | Disabled modules expose no routes, no navigation entries and no background jobs. | Given `canvas` disabled, `GET /api/v1/canvases` returns 404 `module_disabled` and the web nav has no Canvas entry. |
| PRD-R4 | No business value (price, currency, time, duration, threshold, locale) is a code literal. | CI check `check-no-literals` (`12-testing.md` TST-09) passes; policy keys are listed in `04-policies.md`. |
| PRD-R5 | All data of one account is invisible to every other account, including through SQL bugs. | Abuse-case test SEC-AC-01 (`standards/security-by-design.md`). |
| PRD-R6 | A user can export all their data and delete their account. | Journey J7: export JSON+ICS, then delete; afterwards no rows with that `account_id` exist. |

Per-module functional requirements live in `modules/*.md` with IDs `<AREA>-FR-nn`.
