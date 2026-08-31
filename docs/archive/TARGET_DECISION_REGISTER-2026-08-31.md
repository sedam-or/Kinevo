# KINEVO TARGET DECISION REGISTER — 2026-08-30

Status: ACTIVE · Owner-approved decisions for the convergence phase.
Labels: **CURRENT** = verified implemented today (frozen audit) · **TARGET_LOCKED** = decided,
binds future work · **TARGET_DECISION_REQUIRED** = explicitly open, evidence-driven.
Nothing in this register may be presented as current implementation unless also labeled CURRENT.

| # | Decision | Label | Basis / notes |
|---|---|---|---|
| 1 | Kinevo remains a single-user personal operating system through v1 | TARGET_LOCKED | matches CURRENT (no membership/RBAC; single-owner workspaces) |
| 2 | Workspace is the contextual organization boundary | TARGET_LOCKED | CURRENT for goals/programs/tasks/notes/canvases (migration 2026_08_26_000001) |
| 3 | Today is cross-workspace by default | TARGET_LOCKED | CURRENT (user-scoped dayView; no workspace filter) |
| 4 | Week is cross-workspace by default | TARGET_LOCKED | CURRENT (weekView user-scoped) |
| 5 | Month is cross-workspace by default | TARGET_LOCKED | CURRENT (monthView user-scoped) |
| 6 | Workspace acts as filter/context for these views | TARGET_LOCKED | filter layer is the TARGET extension of 3-5 (not yet built — not CURRENT) |
| 7 | Hard Landscape is global personal reality, not workspace-owned | TARGET_LOCKED | CURRENT (`hard_landscape_events` has no workspace_id) |
| 8 | Progress supports global and workspace-filtered perspectives | TARGET_LOCKED | server accepts `?workspace_id` (AnalyticsController:71); web UI currently global-only — filter wiring is TARGET |
| 9 | Review supports global and workspace-filtered perspectives | TARGET_LOCKED | review reflection surface itself is still PARTIAL (frozen audit) — both scopes are TARGET |
| 10 | Deterministic scheduler remains authoritative | CURRENT + TARGET_LOCKED | ScheduleDraftGenerator determinism tested; AI may never become schedule authority |
| 11 | AI proposes; user decides | CURRENT + TARGET_LOCKED | pending-only proposals; accept endpoints only; no auto-accept path (frozen audit §AI-6) |
| 12 | Effective recurrence and override behavior will be IMPLEMENTED, not descoped | TARGET_LOCKED | resolves BLOCKER-ES-01/02/03; ADR required before implementation |
| 13 | Offline server reconciliation will be IMPLEMENTED, not downgraded to cache-only | TARGET_LOCKED | resolves BLOCKER-OFFLINE-01; ADR required before implementation |
| 14 | Kinevo Core remains MIT | CURRENT + TARGET_LOCKED | LICENSE + composer.json |
| 15 | Third-party provenance/license governance is mandatory | CURRENT + TARGET_LOCKED | docs/third-party/licenses.md + attributions.md + ADR-014 |
| 16 | Adopted third-party capabilities will be integrated and production-hardened before public production | TARGET_LOCKED | adoption-matrix modes bind at adoption; PRODUCTION_HARDENED bar per PRODUCTION_READINESS_GAP_2026-08-30 |
| 17 | Release principle: quality over speed | TARGET_LOCKED | governance principle for all convergence gates |
| 18 | FrankenPHP + Laravel Octane is the target runtime migration | TARGET_LOCKED (migration) | NOT CURRENT (nginx+php-fpm / artisan serve are current); migration happens during convergence, never silently |
| 19 | FrankenPHP performance claims remain BENCHMARK_REQUIRED | TARGET_DECISION_REQUIRED | no benchmark evidence exists in repo; claims unproven until measured |
| 20 | Resend is the initial Kinevo Cloud transactional email provider | TARGET_LOCKED | provider choice only; access MUST go through decision 21 |
| 21 | Email access must use a Kinevo-owned provider abstraction | TARGET_LOCKED | aligns P29-004 (no provider-specific calls hardcoded) |
| 22 | Free / Pro / Power remain the commercial plan model | CURRENT + TARGET_LOCKED | config/saas.php tiers; `personal` retired via effectivePlanCode |
| 23 | Launch price hypothesis: Free Rp0 · Pro Rp49.900/month · Power Rp89.900/month | TARGET_LOCKED (hypothesis values) | matches config/billing.php:36-37 (whole Rupiah); `launch_hypothesis: true` flag retained |
| 24 | Hosted AI allowance numbers remain NOT PRODUCTION LOCKED | TARGET_DECISION_REQUIRED | config 20/300/1000 = deprecated-baseline placeholders; docs 20/150/500 = DECISION_REQUIRED; FinOps simulation gates the final numbers |
| 25 | Power exact entitlement parameters remain evidence-driven | TARGET_DECISION_REQUIRED | reserved keys (advanced_analytics/wrapped/mobile_access) unenforced; no storage entitlement exists |

## Dependency notes

- Decisions 12+13 (implement recurrence/override + offline reconciliation) override any earlier
  "descope" readings of the SRS; they are recorded here as TARGET, not CURRENT, until the
  stabilization plan executes.
- Decision 18 must never be executed as an incidental refactor: it requires the stabilization
  plan's runtime step + benchmarks (decision 19) before any production cut-over.
- Decisions 24+25 block P31/P32 planning finals; they do not block the P28 stabilization steps
  1-12.
