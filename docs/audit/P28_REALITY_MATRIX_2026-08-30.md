# P28 REALITY MATRIX — 2026-08-30

READ-ONLY audit. TASK.md statuses verified against repository evidence (code, tests, docs artifacts, run records). No statuses were changed.

Totals: 31 P28 items = 19 DONE · 11 TODO · 1 GATED (the "19/30" claim undercounts; 31 items exist).
Legend — Repo status uses the audit taxonomy; "Contradiction" = repository evidence materially disagrees with TASK.md label.

## P28-001..008 (UX audit track)

| Task | TASK status | Repo status | Evidence | Contradictions | Recommended correction |
|---|---|---|---|---|---|
| P28-001 Full UX Inventory | DONE | IMPLEMENTED_TESTED (audit artifact) | docs/ui-audit.md §4/§10.1; tests/e2e/tests/p28-ux-audit.spec.ts (11 tests ×3 engines) | — | keep DONE |
| P28-002 Empty State Audit | DONE | IMPLEMENTED_TESTED | ui-audit.md §10.2; p28-ux-audit empty-state tests (goals/tasks/knowledge/analytics) | — | keep DONE |
| P28-003 Personalization | DONE | IMPLEMENTED_TESTED | ui-audit.md §10.3; shell/Today context tests | — | keep DONE |
| P28-004 Information Architecture | DONE | IMPLEMENTED_TESTED | ui-audit.md §10.4; nav-group contract test; navigation.ts matches design.md §9 (+3 extra SYSTEM items — documented in code, not in design.md §9) | minor: code adds plan-settings/ai-settings/workspace-home beyond §9 | keep DONE; note §9 drift |
| P28-005 CTA Hierarchy | DONE | IMPLEMENTED_TESTED | ui-audit.md §10.5; primary-CTA tests; guest single-primary | — | keep DONE |
| P28-006 Cross-Feature Workflow | DONE | IMPLEMENTED_TESTED | ui-audit.md §10.6; journeys/canonical/continuity specs; TASK cites 2026-08-29 browser runs | browser-e2e.md does NOT contain the 2026-08-29 records (last: 2026-08-26) | keep DONE; backfill run record |
| P28-007 Micro-Interaction System | DONE | IMPLEMENTED_TESTED | design-tokens §motion; app.css reduced-motion collapse; accessibility.spec.ts R5; WCAG contrast fix recorded in TASK | — | keep DONE |
| P28-008 Design System Audit | DONE | IMPLEMENTED_TESTED | ui-audit.md §10.7 (9 token areas) | — | keep DONE |

## P28-009..013 (TODO track — three hold significant hidden implementation)

| Task | TASK status | Repo status | Evidence | Contradiction | Recommended correction |
|---|---|---|---|---|---|
| P28-009 Analytics Meaning | TODO | **PARTIAL→near IMPLEMENTED_TESTED** | server/resources/js/analytics/interpretation.ts + InterpretationStrip.vue ("What changed/Why it matters/What to do", P17-017); ChartMeta.vue period+unit+legend (P17-019); period comparison (AnalyticsView:371); empty-data copy ("No tracked time in this period yet", :318); NextActionBanner + next-action.spec.ts; analytics-hierarchy.spec.ts | Task labeled TODO while acceptance boxes 1-4 appear already satisfied by P17 legacy | re-audit acceptance boxes against live app; likely reclassify most boxes DONE; remaining gap: recorded browser evidence under P28-009 + action-path completeness |
| P28-010 Feature Explanation Layer | TODO | **PARTIAL** | components/FeatureHelp.vue wired into 7 views (TodayView, TaskListView, GoalListView, RescheduleView, AnalyticsView, AdaptiveContextPanel, ProposalReviewCard); WhyThis.vue; feature-education.spec.ts (P17-008 first-use/dismiss/persist + P17-009 empty-state education) | TODO label vs existing explanation system | re-audit: verify coverage of Goal/Workspace/Knowledge/Canvas/AI-provider-modes; keep open only for uncovered surfaces |
| P28-011 Global State Matrix | TODO | PARTIAL (doc+mobile tests only) | docs/state-machine-ui.md (state table incl. conflict/failed); server/tests/Feature/NativeStateTest.php | no per-entity matrix (8 states × 11 entities) found; web-side state coverage unproven | keep TODO; scope matrix doc as deliverable |
| P28-012 Accessibility Audit | TODO | **PARTIAL→near IMPLEMENTED_TESTED** | tests/e2e/tests/accessibility.spec.ts: axe "no critical or serious violations" (login + surface sweep + canvas shell), keyboard-only login/nav/quick-capture (:73), reduced-motion (:113); P27-013 device TalkBack/font-scale/touch-target evidence in TASK.md; P28-007 AA contrast fix | TODO label vs existing a11y suite + device sweep | re-audit: WCAG 2.2 AA full baseline per core surface + record results; likely small residual |
| P28-013 Browser Golden Journeys | TODO | PARTIAL | tests/e2e: golden-journeys.spec.ts (9 journeys incl. breakdown accept, provider config), journeys.spec.ts, canonical-journey.spec.ts, journey-c-e.spec.ts, journey-i/j | Journeys A-E as specified (A first-login→complete; B returning; C proposal accept; D note-link; E canvas persistence) not captured under this task; 3-engine record for P28-013 absent (config has chromium/firefox/webkit projects) | keep TODO; define missing legs explicitly + record 3-engine evidence |

## P28-TPI-000..010 (third-party foundation — all DONE, verified)

| Task | TASK status | Repo status | Evidence | Contradictions | Recommended |
|---|---|---|---|---|---|
| TPI-000 Decomposition & Reality Audit | DONE | IMPLEMENTED (doc artifact) | docs/third-party/adoption-matrix.md §TPI-000 (inspection + capability inventory + duplicate risks + task mapping) | Known limitation recorded: Pic Smaller/Uppy/Gotify/Langfuse/GlitchTip sub-tasks not yet created in TASK.md | keep DONE |
| TPI-001 Adoption Matrix | DONE | IMPLEMENTED | adoption-matrix.md (11 rows, modes/licenses/contracts/exit) | — | keep DONE |
| TPI-002 License/Provenance | DONE | IMPLEMENTED | licenses.md + attributions.md; AGPL service-boundary rule | — | keep DONE |
| TPI-003 Integration Modes | DONE | IMPLEMENTED | adoption-matrix Decision column (11 rows, one mode each) | — | keep DONE |
| TPI-004 Capability Ownership | DONE | IMPLEMENTED | architecture.md §ownership + ADR-014 | — | keep DONE |
| TPI-005 Adapter Contract Inventory | DONE | IMPLEMENTED | architecture.md §7 ports | — | keep DONE |
| TPI-006 Resource Budget | DONE | IMPLEMENTED (planning values) | deployment.md §budget (9 services) + matrix fields | estimates flagged verify-at-adoption | keep DONE |
| TPI-007 Development Profiles | DONE | IMPLEMENTED (partial wiring) | docker-compose.yml default core + ollama `profiles:["ai"]`; 6 external profile NAMES declared as contract; services not yet added (by design, P34/P24/P31) | — | keep DONE |
| TPI-009 Third-Party UX Consistency | DONE | IMPLEMENTED_TESTED | ui-audit.md §10.8; CanvasHost/EditorHost tokenized chrome; no engine naming on user surfaces | RET-012 mapping row still says TODO | fix RET-012 row |
| TPI-010 Retention Workflow Continuity | DONE | IMPLEMENTED (audit) | ui-audit.md §10.9; 10 §55 conditions checked (6 PASS, 4 PARTIAL→P31/P33) | — | keep DONE |

## P28-RET track + gate

| Task | TASK status | Repo status | Evidence | Contradictions | Recommended |
|---|---|---|---|---|---|
| RET mapping table | (table) | STALE | TASK.md:8209-8217 | RET-004 row TODO while owner P28-006 is DONE; RET-011 TODO while P28-007 DONE; RET-012 TODO while TPI-009 DONE; RET-009 TODO consistent with P28-009 TODO (but owner itself under-labeled, see P28-009) | sync 3 rows to DONE |
| RET-002 Contextual Empty States | TODO | PARTIAL | p28-ux-audit empty-state tests pass 4-question bar; §16 canonical enrichment (context example line) not found | — | keep TODO (small) |
| RET-005 AI Breakdown Aha Moment | TODO | PARTIAL | ProposalReviewCard + goal breakdown accept flow exist (golden-journeys :144); continuation CTA after accept not found in code search | — | keep TODO |
| RET-006 First Session Journey | TODO | NOT_STARTED | no first-session walkthrough record; FeatureHelp declared "NOT onboarding slides" (FeatureHelp.vue:10) | — | keep TODO (depends RET-005) |
| RET-007 First Week Retention Events | TODO | BLOCKED | depends on P32-001 (Product Event Taxonomy, TASK.md:8696 — future phase); Notes line says "P31 event taxonomy" — ambiguous | forward dependency creates phase-cycle risk | keep TODO/BLOCKED; fix P31/P32 reference |
| RET-008 Progress Feedback System | TODO | PARTIAL | NextActionBanner + completion cascade base (P17-011) exist; progress-delta layer not found | — | keep TODO |
| RET-013 Retention Failure E2E | TODO | NOT_STARTED | tests/e2e/tests/retention-failures.spec.ts does NOT exist | — | keep TODO (accurate) |
| P28-014 UX RELEASE GATE | GATED | GATED (correct) | gate boxes unchecked; blockers: analytics actionable record, explanations coverage, journeys A-E record, RET-013 E2E, no unresolved P0/P1 blocker | gate cannot be evaluated while P28-009/010/012 statuses are stale | keep GATED until status corrections + remaining legs done |

## P28 verified completion count
- TASK.md label truth: 19 DONE / 11 TODO / 1 GATED (31 items).
- Evidence-truth: the 19 DONE are evidence-backed. Of the 11 TODO, three (P28-009, P28-010, P28-012) already hold substantial implementation+test evidence and need re-audit → likely reclassification rather than new implementation. Remaining genuinely-open work concentrates in: P28-011 matrix, P28-013 journey legs A-E + 3-engine record, RET-002 canonical copy, RET-005 continuation CTA, RET-006, RET-008 delta layer, RET-013 E2E, then gate.

## Route map note (backend audit companion)
Full 39-controller route map (public: auth register/login, billing webhook, health; authed: profile, goals, milestones, programs, workspaces+default, saas/plan, billing checkout/cancel/resume/subscription, tasks+subtasks+quick-capture+auto-swap, attachments, imports krs/ics, logs/export, notifications, recovery, recharge, adaptive, focus-sessions, execution, progress, ai/* (25 routes), metrics/observability, notes, knowledge links/search, canvases+files, today/schedule/week/calendar, schedule/export/ics, hard-landscape, schedule drafts/reschedule, mini-pause/emergency-pause/break/boost, analytics, schedule-overrides) was captured during this audit and is reflected in the baseline file §B.
