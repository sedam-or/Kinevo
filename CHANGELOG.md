# Changelog

All notable changes to Kinevo are documented here.

This project follows Semantic Versioning. Format based on
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/). Version numbers below
refer to the **application**. The **SRS version** (currently 2.0.0) and **API
version** (v1) are tracked separately in `docs/SRS.md` and
`docs/api/openapi.yaml` respectively.

Release governance: see `docs/release-management.md`.

## [Unreleased]
### Changed
- Documentation & roadmap rebaseline (R0): `TASK.md` reconstructed into a slim execution control
  plane (detailed task history moved verbatim to `docs/roadmap/archive/task-legacy/`); new
  canonical master execution program (`docs/roadmap/KINEVO_MASTER_EXECUTION_PROGRAM.md`, R0 → P39)
  superseding both prior root execution prompts (archived); P28 detail migrated to
  `docs/roadmap/active/P28-product-experience-closure.md`; planned phase docs created for P29–P39;
  old→new phase mapping + document migration matrix recorded in
  `docs/roadmap/rebaseline-2026-08.md`; documentation authority index added (`docs/README.md`);
  ADR-009/010/011 reconstructed from implementation evidence (resolving the dangling-reference
  blocker); SRS/architecture/README/AGENTS received narrow current-truth corrections (SPA
  frontend without Inertia, ADR-017 offline semantics instead of last-write-wins, truthful mobile
  offline status); convergence registers archived after completion. No product behavior changed.
### Added
- Offline mutation reconciliation & operation ledger (ADR-017): a server-authoritative
  idempotency ledger (`offline_operations`) now backs replay of offline mutations for a
  bounded allowlist (task create/update/status, subtask create, note create/update).
  `POST /sync/reconcile` replays queued operations through the same application use cases
  with per-operation transactions; the same operation_id + identical payload replays safely
  (response-loss protection), a different payload is rejected, and version conflicts
  (task/note `base_version`) never overwrite newer server state — they surface for review.
  Online mutations accept an optional `X-Operation-Id` for the same convergence. The web
  IndexedDB MutationQueue is now real: offline mutations enqueue durably, drain on reconnect
  and reload, clear only after server acknowledgement, and show an aggregate
  offline/queued/syncing/conflict state with a "Discard local change" review action. Offline
  reload no longer logs the user out. Ledger retention (90 days) with a daily prune command.
  Schedule mutations and quick capture stay online-only by design (the schedule remains the
  deterministic authority). BLOCKER-OFFLINE-01 resolved (web/server); mobile durable offline
  is explicitly deferred to Android hardening with the same server protocol as the contract.
- Scheduler trigger, Sync Now & draft approval lifecycle (ADR-016): a weekly
  planning trigger (`schedule:prepare-weekly`) now prepares a persisted,
  review-ready draft for the user's local week — it never auto-applies. Manual
  Sync Now (`POST /schedule/sync`) returns a deterministic read-only diff
  (no_changes / proposal / run_in_progress) that is applied only through the
  explicit apply endpoint. Reality changes (Hard Landscape, KRS/ICS imports,
  schedule overrides) mark the accepted schedule as "needs review" with an
  in-app attention notice, bounded to the planning window. Weekly drafts have a
  lifecycle (pending/applied/discarded/superseded) with derived staleness, and
  per-user run locks prevent duplicate calculations. Tasks can be marked as the
  user's single Sacred Anchor (placed first by the scheduler). FR-27/FR-29
  trigger semantics are now implemented; BLOCKER-SCHED-01 resolved.
- Effective Schedule resolution (ADR-015): recurring Hard Landscape (e.g. KRS-confirmed weekly
  courses) now appears on every occurrence date in Today/Week/Calendar, the deterministic
  scheduler draft, the rescheduler, and ICS export. Permanent Shift and One-Time Exception
  overrides now change the effective schedule (exception > latest shift > base), including
  occurrence cancellation (`cancels_occurrence`). Superseded placements are archived into
  `schedule_assignment_history` in the same transaction as every schedule mutation, with new
  schedule activity events. Users can lock/unlock a scheduled placement
  (`POST /tasks/{id}/assignment/lock|unlock`) — locked placements are never moved by the
  scheduler or rescheduler. The recurrence engine now enforces UNTIL (inclusive; UTC `Z`
  normalized) and counts COUNT occurrences from the series start, not the query window.
### Changed
- The Android shell now lives in this repo (Phase 27, TASK-P27-001..010): the NativePHP mobile
  surface was ported in as `routes/native.php` + ten native screens (Today/Tasks/Capture/Goals/
  Review/Notifications/Canvas/Notes/Workspaces/More), all built against the existing OpenAPI —
  including task execution (mark done), AI goal breakdown trigger, review summary, notification
  read app, and a read-only canvas companion that hands off to the web editor. Server-side contract
  locked by tests. The bundled APK is now reproducibly rebuilt from this repo on headless Linux
  (`infrastructure/nativephp/linux-build/build-android-apk.sh`) and boots on the emulator; screen
  content rendering on device is tracked as an open finding (ui-audit UI-021).
- Analytics "Goal progress" now summarizes (2026-08-25): the chart lists the
  first 8 goals with an explicit "+N more goals" line instead of rendering
  every goal, keeping the surface readable and fast as goal counts grow.
- Clearer words everywhere (Phase 17, TASK-P17-030): the schedule draft
  explains itself in plain language instead of developer terms, and note and
  canvas lists no longer show internal revision counters.
### Added
- Bring your own key finally means it (Phase 25, TASK-P25-008): you can connect an AI provider with
  your own credential in Settings → AI. Your requests then skip Kinevo's credit meter entirely (your
  spend, your provider), while the request gets a `byok` ledger flag and is still protected by the
  runtime safeguards. Your API key is encrypted at rest and never shown again once saved.
- Hard AI runtime safeguards keep costs sane (Phase 25, TASK-P25-007): per-minute, per-day and
  daily-estimated-cost limits are config-driven and enforced before any provider call — separate
  from credits, so abuse protection still applies even when you bring your own key.
- Estimated provider cost now rides along with each AI run (Phase 25, TASK-P25-001): a versioned
  price catalog computes an estimated cost from token usage against owner-configured rates, recorded
  on the AI-runs history with pricing provenance — still clearly separate from the real provider
  invoice, and empty until the owner populates real prices.
- AI usage is now metered and honest (Phase 25, TASK-P25-001..005): every AI action is refused up front
  (403) once your monthly credits run out, and only successful generations spend a credit — failed
  provider calls burn nothing. Each run records a stable request id, tokens and credits spent, so the
  AI-runs history shows exactly what each request consumed.
- Billing is live against the real Midtrans sandbox (Phase 24, TASK-P24-035/036): a user can
  start a paid checkout whose pending subscription is created by the provider, and a
  signature-verified settlement webhook activates the subscription, records the payment
  transaction, and grants the plan to every device on the account (idempotent, replay-safe,
  out-of-order-safe). Billing operations documentation shipped in `docs/billing.md`.
- Billing refunds and chargebacks no longer surprise you (Phase 24, TASK-P24-022/023): a settled
  charge can be refunded against the Midtrans Core API, and webhook-driven refund or chargeback
  notifications mark the charged transaction `refunded`, while a chargeback also flags the
  subscription as uncertain instead of silently revoking or keeping paid access. All states,
  transitions, and signatures are covered by tests.
- Grace on a missed payment, then a clean recovery (Phase 24, TASK-P24-019): a failed attempt moves
  the subscription to `past_due` with entitlement preserved, and the next successful settlement
  restores full active status automatically (Midtrans handles the retry dunning).
- One paid subscription per account, enforced (Phase 24, TASK-P24-043): starting a second checkout
  while an active/past-due subscription exists is rejected with a clear error; cancel first, then
  switch plans.
- AI now lives where you work (Phase 17, TASK-P17-029): notes offer
  "Summarize with AI" and "Extract tasks with AI" in the editor, the canvas
  index suggests a board structure from a plain-language description, and
  every task detail page can "Clarify task" — each proposal stays pending
  until you accept, and unconfigured AI routes to Settings instead of failing.
- AI actions now guide instead of failing (Phase 17, TASK-P17-028): when AI
  is off or not configured, "Generate with AI" / "Break Down with AI" show
  "AI is not configured." with a one-tap [Configure AI] shortcut to
  Settings → AI & Providers.
- AI breakdowns are now explainable (Phase 17, TASK-P17-027): each proposal
  shows a concise decision summary plus the assumptions, the inputs it used
  (deadline, capacity, commitments), and the constraints it honoured — while
  keeping internal reasoning private.

- Breakdown proposals can now be reviewed and accepted right where the goal
  was created (Phase 17, TASK-P17-026): after "Generate with AI" the proposal
  opens in place — edit milestones, accept, and stay put instead of being
  sent to another page.

- Every object now suggests its next step (Phase 17, TASK-P17-016): an
  empty goal asks for its first milestone, a backlog task asks to be
  scheduled, a missed task asks to be recovered, and an offline drawing board
  points at its queued sync — one clear suggestion instead of guessing.

- Task cards can now explain themselves (Phase 17, TASK-P17-015): a quiet
  "Why this task now?" link on the current task and week-planner entries
  expands to show the priority tier, how close the deadline is, why this
  slot fits — and what your last energy check-in contributed.

- Today reads top-down like a control center (Phase 17, TASK-P17-014):
  what's happening now leads, then what's next, then your day's timeline —
  with a new day-progress bar, the energy check-in and quick capture grouped
  underneath as support instead of competing for attention.

- Dark mode is finally dependable (Phase 17, TASK-P17-013): your light/dark/
  system choice now survives reloads without a white flash, dark mode reaches
  the login screen and native controls like date pickers, and the drawing
  board follows the app theme instead of ignoring it.

- The scheduler earned its buttons back (Phase 17, TASK-P17-012): draft
  generation, reschedule proposals and their apply actions are now bold,
  tactile primaries with the app's offset-shadow press feedback, replacing
  unstyled placeholders.

- Actions now answer back (Phase 17, TASK-P17-011): completing a task
  snaps the button, refreshes your progress, pops a confirmation and briefly
  spotlights the next task; energy check-ins flash "Saved ✓"; goal AI
  breakdowns narrate their stages instead of a bare spinner.

- Every page now has exactly one obvious primary action (Phase 17,
  TASK-P17-010): on the goals list the "Create" and "Generate with AI"
  actions no longer compete — creating a goal hands the spotlight to the AI
  breakdown offer; program forms and task editing were demoted to supporting
  actions. A full page-by-page action-hierarchy audit is recorded in
  docs/ui-audit.md.

- Empty screens now teach (Phase 17, TASK-P17-009): when Today has nothing
  in progress, or Goals/Tasks/Analytics are still empty, a short in-place
  explanation shows what belongs there and what to do next. Dismissing any
  explanation ("Got it") keeps it away on that device.
- Features now explain themselves in place (Phase 17, TASK-P17-008): a small
  "What's this?" info control on Hard Landscape, Capacity, Context check-in,
  the Activity heatmap, the Dynamic Rescheduler, and AI breakdown proposals
  opens a short explanation. Dismissing it ("Got it") is remembered locally,
  so each callout appears once per device.
- AI status is now a single source of truth (Phase 17, TASK-P17-007): the
  status and config endpoints expose one canonical state (`disabled`,
  `not_configured`, `configured`, `testing`, `connected`, `degraded`,
  `unavailable`), and AI settings renders it live — distinguishing a saved
  configuration from an actually reachable provider.
- Breakdown is now invokable where the goal lives (Phase 17, TASK-P17-005):
  the goal detail header and the empty-milestone state both offer an explicit
  [Break Down with AI] action — no trip to Settings or another AI page. While
  a proposal awaits review the entry points hide so duplicates can't stack,
  and generation failures surface inline without leaving the goal.
- Completed the AI goal-breakdown workflow (Phase 17, TASK-P17-004): the goal
  detail now shows a review card for pending AI breakdown proposals with
  rationale, risks, and milestone target dates/effort. Milestones can be
  edited inline before accepting; edits are revalidated against the same
  schema as AI output and the proposal is marked `edited`. Acceptance still
  runs through the validated proposal contract — nothing is applied to the
  goal until Accept, and already-decided proposals can no longer be changed.
- Added the AI & Providers settings surface (Phase 17, TASK-P17-006): a new
  SYSTEM nav item opens Settings → AI & Providers where the AI provider
  (Disabled / local Ollama / OpenAI-compatible), model, base URL, and API key
  can be configured, tested, and persisted. The key is encrypted server-side,
  never stored in browser storage, never returned raw (masked `…last4` hint
  only), and is replace/remove-only; Ollama explicitly shows "API key not
  required". Test-connection pings candidate settings before saving. A saved,
  enabled configuration takes precedence over environment defaults.
- Upgraded goal creation into a planning workflow (Phase 17, TASK-P17-003):
  the New-goal form now captures Outcome, Description, and Deadline, and after
  creation the product asks how to proceed instead of silently mutating the
  goal: [Generate with AI] creates a *pending* breakdown proposal through the
  validated proposal contract (goals/milestones are never touched without
  approval), [I'll do it myself] opens the goal for manual planning, and
  [Later] dismisses the prompt. Verified in Chromium (`golden-journeys.spec.ts`
  journey G; recorded in `docs/browser-e2e.md` §7 and §8).

- Added a workflow-continuity layer (Phase 17, TASK-P17-002): every major
  entity page now links to its related surfaces so no screen is a dead end.
  A goal detail offers its downstream execution surfaces (Tasks, Schedule,
  Progress→Analytics); a task detail offers upstream context (its Goal, when
  linked) plus Schedule/Notes/Canvas; the Today NOW card links back to the
  serving Goal; and knowledge-desk linked entities are now clickable and open
  on their own surface. Deep-open navigation remembers the target object so a
  link lands directly on the linked item. Verified in Chromium/Firefox/WebKit
  (`tests/e2e/tests/continuity.spec.ts`; recorded in `docs/browser-e2e.md` §11).

- Restructured primary navigation into cognitive groups (Phase 17, TASK-P17-001):
  EXECUTE (Today/Week/Calendar), PLAN (Goals/Tasks/Schedule), KNOWLEDGE
  (Knowledge/Canvas), REVIEW (Analytics), SYSTEM (Settings). Schedule moved out
  of System into Plan (a planning artifact — deciding *when* tasks run happens
  before execution). On mobile the fixed bottom bar now keeps only the primary
  subset (Today/Tasks/Goals/Knowledge) plus a "More" drawer for the rest, so the
  bar never becomes a horizontal scroller. Verified in Chromium/Firefox/WebKit
  (`tests/e2e/tests/navigation.spec.ts`; recorded in `docs/browser-e2e.md` §11).

- Closed the remaining golden-journey browser gaps (rescue R7, TASK-R7):
  Journey C (Recover) is now proven in a real browser against a seeded
  missed-task state, and Journey E (Offline) is proven for canvases — draw
  offline, see the offline badge, reconnect, and find the scene restored from
  the server after reload (`tests/e2e/tests/journey-c-e.spec.ts`, recorded in
  `docs/browser-e2e.md` §8).

- Proven the first-love browser journey end-to-end (rescue R1, TASK-R1): the
  core loop LOGIN → TODAY → NOW task → START → COMPLETE → PROGRESS → NEXT now
  executes through the real browser (`tests/e2e/tests/core-loop.spec.ts`) and
  passes in Chromium, Firefox, and WebKit. Tasks are quick-captured onto a
  future, free day so the scheduler's safety reserve is never exhausted; the
  spec synced the browser clock to the assigned slot for the NOW card, then
  restored real time so elapsed accrues from the server-side start timestamp.
  Full E2E matrix: 57/57 green. Recorded in `docs/browser-e2e.md` §7–§8.
- Added the UI/UX stabilization baseline (rescue R2, TASK-R2): centralized
  design-token modules (`tokens/`: colors, spacing, radius, shadows,
  typography, motion, z-index), CSS hydration into the Tailwind v4 theme with
  light/dark palettes, a shared component library v0 (`KButton` with three
  variants, `KInput`), and a dev-only runtime diagnostics panel (API / auth /
  offline / sync / shell state) that is excluded from production builds. The
  R1 findings were classified into the P0–P3 taxonomy in `docs/ui-audit.md`.
  No user-visible behavior change.
- Added the UI/UX design-system stabilization baseline (rescue Phase R0–R7):
  `docs/design.md` rewritten as the refined Neo-Brutalism product-experience
  specification (Today-first execution surface, explainable automation,
  persistence-state UX, canvas browser-integration canon, golden journeys, and
  the R0–R7 rescue plan), plus `docs/design-tokens.md` (centralized token
  system), `docs/ui-audit.md` (P0–P3 bug-taxonomy audit matrix), and
  `docs/browser-e2e.md` (browser QA matrix + golden-journey records). The
  rescue task board (TASK-R0…R7) is registered in `TASK.md` as Phase 16.
  Documentation/governance only — no product behavior change, SRS not modified.
- Added the golden one-week E2E journey (TASK-150): a single end-to-end test
  walks the full user journey — register/login, goal, milestone, program, task,
  auto-schedule draft + apply over a week, Today view, execution session,
  completion, activity log, progress event, analytics overview with capacity,
  and next-week future scheduling — asserting only on the user-visible API
  payloads the UI renders.
- Completed the import preview/fallback contract (TASK-144): the KRS PDF import
  now reports unreadable schedule-like lines as per-line validation errors and
  flags duplicate rows as warnings in the preview — nothing is silently dropped,
  and rows with invalid time ranges are never staged. The iCal import panel now
  shows the manual-fallback note. Both import features (KRS PDF, iCal) now fully
  satisfy the Preview / Validation Errors / Warnings / Accept / Cancel / Manual
  Fallback contract.
- Added iCalendar export (TASK-143): the Schedule view can now download the
  selected date range as a valid .ics file (`/schedule/export/ics`). Scheduled
  tasks and one-time / permanent Hard Landscape events become calendar events;
  recurring Hard Landscape is expanded within the selected window. The feed is
  RFC-5545-compliant, uses content-derived UIDs (no internal database
  identifiers), and exposes only exportable fields (FR-30, NFR-03).
- Added iCalendar import (TASK-142): the Schedule view can now upload an .ics
  calendar (e.g. public holiday calendar), see a parsed preview with per-event
  errors and warnings and conflict flags, and confirm to add the non-conflicting
  events as Hard Landscape in one transaction — recurring events become weekly
  recurring, and the existing schedule is never overwritten. Timezones are
  resolved to the owner's profile timezone (TZID, UTC, or floating local time).
- Added KRS PDF import (TASK-141): the Schedule view can now upload a KRS PDF,
  see a parsed preview (day/time/course with a confidence score), and confirm to
  add each row as a weekly-recurring Hard Landscape event in one transaction —
  never overwriting the existing schedule. Parsing failures fall back to manual
  entry. Uses the `smalot/pdfparser` library for pure-PHP PDF text extraction
  (recorded in the third-party license ledger).
- Added task evidence attachments (TASK-140): completed tasks can now hold up
  to 3 evidence files (JPG/PNG/PDF, ≤5 MB each) with upload, list, download, and
  delete (`/tasks/{taskId}/attachments`, FR-43). Files are validated by detected
  content type (never the browser MIME alone), stored privately (not
  world-readable), and SHA-256 checksummed. The Task detail view renders the
  attachment list with download/delete and a completed-task upload gate.
- Extended Work-Life analytics (TASK-135): the Analytics view now compares the
  current period's Work/Recharge ratio against the preceding equal-length
  period, shows a weekly ratio trend, and lists notable days (no data, focus
  without recharge, recharge without focus). All of it remains framed as a
  time-balance indicator, never a medical or biological optimum.
- Added the activity heatmap (TASK-134): the Analytics view now renders an
  annual activity heatmap (FR-31) with a per-day intensity grid derived from
  productive time, recharge, task completion, and progress events. It includes a
  color legend, optional pillar filtering (without mutating logs), and an
  accessible list alternative with exact per-day values.
- Added the four-pillar analytics (TASK-133): the Analytics view now renders a
  Life pillars section for exactly Karier, Kesehatan, Bahasa, Branding plus
  Uncategorized (FR-12). Each pillar shows completed task minutes in the period
  versus the mapped program weekly target as a percentage (N/A when there is no
  target). Pillars are derived from program category mapping; tasks without a
  mapping fall back to Uncategorized.
- Added Capacity analytics (TASK-132): the Analytics view now renders a
  capacity section — per-day scheduled load vs available capacity with overload
  highlighting, the realization ratio, a weekly trend, and the effective-capacity
  recommendation/reason from the Capacity feedback loop (reusing
  `CapacityCalculator`). Per-day capacity uses the same primitives as the Today
  view.
- Added Goal Progress analytics (TASK-131): the Analytics view now renders a
  goal progress section from the overview read model — per-goal progress,
  milestone progression, program contribution, deadline health (on track / at
  risk / overdue / completed, a descriptive schedule indicator), and workload
  completion. The metrics are computed server-side; the view only renders them.
- Added the Analytics read-model layer (TASK-130): a new `GET
  /analytics/overview` endpoint composes every read model for a period — task
  completion, goal progress and program contribution, capacity (reusing the
  FR-49 feedback loop), activity, focus, progress events, and the Work-Life
  Ratio — so analytics surfaces consume precomputed read-side services instead
  of recomputing business numbers in the browser.
- Added the Work-Life Ratio analytics (TASK-126): the Analytics view aggregates
  the user's already-recorded productive (focus) and Recharge minutes over a
  period and derives the normative WorkRatio/RechargeRatio (`GET
  /analytics/work-life`, FR-05). The result is presented as a time-balance
  indicator with a per-day series and a disclaimer — it is never framed as a
  health diagnosis. The Recharge timer now derives its day ratio from the same
  domain formula.
- Added the Boost Mode (TASK-125): during a confirmed Break Mode period the
  Today view can set a holiday boost target as a percentage of daily capacity
  (`GET/POST /boost`), capped at the 70% safety limit with an explicit warning
  (FR-37). The recommendation reuses the Capacity feedback loop — Boost Mode is
  offered when recent realization exceeds 90% with no burnout signal, and the
  suggestion is suppressed while a burnout signal is active (FR-49). The target
  is scoped by start/end datetime within the active break and used as a
  temporary capacity ceiling when generating schedule drafts; ending it returns
  the scheduler to the baseline target (FR-38).
- Added the Break Mode (TASK-124): the Today view can now start a break/holiday
  over a date range (`POST /break`) and end it early (`POST /break/end`). The
  covered weeks are tagged exceptional for capacity feedback (FR-49), EOD
  notifications are suppressed during an active break, and a scheduled
  `break:notify-end` job (daily 20:30 local) creates exactly one holiday-end
  notification per break period three days before it ends (FR-39, FR-41). The
  day/week schedule queries expose the recovery state (`break`), and the Today
  view shows a Break Mode banner with an End Break action and a start/end summary
  (FR-36).
- Added the Emergency Pause (TASK-123): the Today view can now tag the current
  week as an exceptional recovery period, keep user-selected tasks in place, and
  shift every other eligible task +1 week to the same weekday
  (`POST /schedule/emergency-pause`). Locked tasks are never auto-moved; tasks
  with no feasible slot next week stay in place and are reported as conflicts;
  tasks are never deleted and ownership is preserved. The week is tagged in a new
  `pause_events` table, EOD notifications are suppressed while audit data is
  preserved, the day/week schedule queries expose the recovery state (`pause`),
  and the Today view shows a recovery banner during the exceptional week (FR-07,
  FR-47, FR-49).
- Added the Mini Pause (TASK-122): the Today view can now move every eligible
  task scheduled today to the first feasible slot on the next day in one action
  (`POST /schedule/mini-pause`). Locked tasks are never auto-moved; tasks with
  no feasible next-day slot stay in place and are reported as conflicts. The
  change is persisted atomically at the next schedule version, logged as a
  `mini_pause` activity event, and explained to the user (FR-07).
- Added the Recharge Timer (TASK-121): after every two completed focus
  sessions, the Today view offers a 15-minute Recharge timer (Start/Pause/
  Resume/Complete/Abandon). Recharge is persisted server-side in a new
  `recharge_sessions` table and counts as Recharge, never Productive Time; the
  recorded duration is the tracked duration, and the day's RechargeMinutes feed
  the WorkRatio/RechargeRatio shown alongside the timer (FR-05). New REST
  endpoints under `/recharge` with a 409 conflict when a timer is already
  running.
- Added the Execution Timer (TASK-120): tasks can now be worked with a persisted
  execution timer (`start`, `pause`, `resume`, `complete`, `abandon`) shown in
  the Today NOW card. Timer state lives server-side in a new `execution_sessions`
  table and elapsed time is always derived from persisted timestamps, never a
  client-only model; completing a timer records a `FocusSession` from the tracked
  duration, advances the task (completed when no subtasks remain, otherwise
  `continued` with a scheduled continuation), and logs `task_started` /
  `task_abandoned` / `task_completed` / `task_continued` activity (FR-05/FR-06/
  FR-18/FR-25). New REST endpoints under `/execution` with a 409 conflict when a
  timer is already running.
- Added the Offline Synchronization UX (TASK-115): the shell now presents eight
  visible sync states (Online, Offline, Queued, Syncing, Saved, Conflict,
  Retrying, Failed) with plain-language explanations of whether a mutation is
  persisted server-side, stored locally, waiting for synchronization, or in
  conflict. A `SyncStatusController` bridges the general offline mutation queue
  into the shell store, a new `HttpMutationApplier` replays queued task/note/
  quick-capture/canvas mutations through the existing API client, and a visible
  `SyncStatusPanel` shows badge + queued count + a "Retry sync" action for
  retrying/failed states; `retrying` joined the color-independent visual states
  (FR-44/FR-57, SRS §9).
- Added the Canvas Context / Linking (TASK-114): canvases can now be attached to
  Goals, Milestones, Programs, Tasks, and Notes through the shared
  `knowledge_links` relation (canvas as link source), surfaced in a Context
  panel in the canvas workspace with create/remove and milestone-aware target
  selection; the backend gains `GET/POST /canvases/{id}/links` and
  `DELETE /canvases/{id}/links/{linkId}`, and `note` is now a valid link target
  (FR-54/FR-55, SRS §10.5).
- Added the Canvas Workspace UI (TASK-113): a `canvas` shell view with canvas
  list and create, an Excalidraw workspace opened through the Vue → CanvasHost →
  CanvasAdapter → React Island boundary (ADR-005) with autosave, Saved/Saving/
  Error/Offline/Conflict save states, read-only mode, light/dark/auto theme, and
  archive-with-confirmation; the backend gains rename (`PATCH /canvases/{id}`)
  and archive (`POST /canvases/{id}/archive`) endpoints and `canvases.archived_at`
  (FR-55/FR-56/FR-57, SRS §7.5, §8.5).
- Added the Knowledge Linking UI (TASK-112): create and remove Note → Goal /
  Milestone / Program / Task / Canvas links from the note editor, with a typed
  link store and a LinkManager that resolves the target context (milestones
  dependent on the selected goal) and surfaces duplicate/validation errors;
  the backend link target set now also supports Canvas (FR-54, SRS §10.5).
- Added the Tiptap Vue binding (TASK-111): an `EditorHost` component that
  connects Vue → EditorAdapter → Tiptap behind the replaceable editor boundary
  (canonical structured JSON, derived markdown/plain text, readOnly/theme), now
  powering note editing with autosave (FR-53, SRS §10).
- Added the Notes UI (TASK-110): note list and full-text search, create and
  edit with debounced autosave and Saved/Saving/Error/Offline/Conflict states
  (optimistic versioning, 409 conflict), and linked-entity display, wired into
  the Knowledge view (FR-53/FR-54).
- Added a consistent Conflict / Lock / Explainability UI (TASK-109): a shared
  visual-state badge system (glyph + text + non-color pattern for locked,
  conflict, overdue, draft, proposed, offline, syncing, queued, failed) applied
  across Today, the task list, and the sync indicator, plus scheduler
  explanation reason codes surfaced in the Schedule Draft view (FR-63).
- Added the Schedule Draft / Dynamic Rescheduler UI (TASK-108): generate and
  preview a deterministic weekly draft (accepted/rejected tasks with reasons),
  apply it atomically at the next schedule version, and propose/apply a dynamic
  reschedule showing per-move BEFORE / AFTER / REASON and conflict flags —
  backed by new `POST /schedule/draft[/apply]` and
  `POST /schedule/reschedule[/apply]` endpoints (FR-27/FR-28).
- Added the global Quick Capture UI (TASK-107): a modal reachable from any
  authenticated view with title, priority, size/duration, program, goal,
  milestone, and due-date fields; when the day has no free slot it presents the
  SRS order Manual Swap / Auto Swap / Schedule Later as actionable strategies
  (FR-03).
- Added the Goals / Milestones / Programs UI (TASK-106): goal list and detail
  (outcome, deadline, progress, status actions), a sequence-ordered milestone
  timeline with status transitions, and a program list with creation, wired to
  the Goal/Milestone/Program APIs (FR-19/20/22/26/50/51).
- Added the Task UI (TASK-105): task list, creation, detail and edit, backend-
  validated status transitions, subtasks (add/toggle/promote), and partial
  completion, wired to the Task APIs (FR-09/FR-45).
- Added the Week / Calendar UI (TASK-104): a 7-day week grid and monthly
  calendar with date navigation, capacity/overload indication, per-day deadline
  markers and task assignments, wired to the real `GET /week`, `GET /calendar`,
  and `GET /schedule` APIs (FR-11/FR-15).
- Added the Today UI (TASK-103): the primary execution surface with a dated
  header, sync state, NOW and NEXT cards, a 06:00–24:00 timeline (scheduled
  tasks, Hard Landscape, empty slots, lock/conflict states), capacity
  indication, and global Quick Capture wired to `GET /today` and
  `POST /quick-capture` (FR-01/FR-02/FR-27).
- Added the global typed API / state client (TASK-102): a shared `fetch`-based
  client with a canonical error taxonomy (401/403/404/409/422/429/503),
  automatic retry with backoff, offline detection, and a global API state store
  for loading/error/connectivity — now shared by the auth client.
- Added the Authentication UI (TASK-101): login and first-owner registration
  forms, session restoration on load, logout, and profile/settings (display
  name, timezone, locale, week start) wired to the existing Sanctum bearer-token
  API, behind a new `/app` SPA host.
- Added the Vue application shell (TASK-100): responsive layout with persistent
  desktop side navigation and mobile bottom navigation (Today/Week/Calendar/
  Goals/Roadmap/Knowledge/Analytics/Settings), global error boundary, global
  loading state, sync and notification indicators, and light/dark/system theme
  handling.
- Added Auto Swap (`POST /tasks/{taskId}/auto-swap`): explicitly move the
  lowest-priority unlocked task on the target day (farthest deadline as
  tie-breaker) to a feasible slot on the following day, then place a task into
  the vacated slot — locked tasks are never moved, Hard Landscape is never
  violated, and the swap commits atomically with a user-visible explanation
  (FR-03/FR-23/FR-28).
- Added Quick Capture placement (`POST /quick-capture`): capture a task and
  attempt immediate placement into the first feasible empty slot today,
  returning a task + assignment on success, or `TASK_NO_CAPACITY` with the
  Manual Swap / Auto Swap / Schedule Later strategies when no slot fits
  (FR-03).
- Added Schedule Overrides (CRUD under `/schedule-overrides`) supporting
  permanent shifts and one-time exceptions on recurring Hard Landscape series,
  with explicit placement precedence (hard landscape > locked task > explicit
  override > recurrence > ordinary) and no silent mutation of source history
  (FR-25).
- Added a deterministic, timezone-aware recurrence engine (RFC-5545 subset:
  daily/weekly, BYDAY, INTERVAL, COUNT/UNTIL) with bounded occurrence
  generation, duplicate prevention, and exception/cancelled-occurrence support
  (FR-46/FR-25).
- Added the Hard Landscape domain and CRUD API (`GET/POST /hard-landscape`,
  `GET/PATCH/DELETE /hard-landscape/{id}`) for defining non-negotiable schedule
  boundaries, with type (permanent/recurring/one-time), recurrence support, and
  overlap conflict detection (FR-27/FR-28). Hard Landscape is now reported in
  the Today schedule view.
- Added the Schedule Query API (`GET /today`, `GET /schedule`, `GET /week`,
  `GET /calendar`) exposing the canonical Today/range/week/month schedule with
  task, program/goal/milestone context, lock and conflict state, capacity
  indicators, and empty (fillable) slots (FR-01/FR-11/FR-15).
- Added release-management lifecycle documentation and release validation tooling.
- Added the production smoke test (TASK-156): `scripts/prod-smoke.sh` (and
  `make prod-smoke`) drives the real production Docker path — build, deploy,
  migrate, health (through the live nginx + TLS proxy), register/login, goal,
  task, schedule draft + apply, Today, backup, and a destructive restore with
  post-restore data verification. Secrets are generated at runtime and never
  persisted.

### Fixed
- Goal → "Generate with AI" now works end to end against your local Ollama
  model (Phase 17, TASK-P17-032): a cold model starting up no longer times
  out at 30 seconds, every proposal is guaranteed to carry the correct goal,
  and providers that reject empty option blocks no longer break generation.
- The note editor no longer pushes content off-screen on phones: its header
  (back button, title, save badge) wraps instead of overflowing at 375–412px
  widths (Phase 17, TASK-P17-034).
- Task lists keep one stable order even when several tasks are created in
  the same moment, instead of occasionally flipping.
- Canvases no longer silently lose edits made while offline: an autosave that
  failed because the device was offline is now retried automatically as soon
  as connectivity returns (offline-sync.md "sync on reconnect"), instead of
  surviving only in memory until the next edit.
- Dark mode now meets WCAG AA contrast on danger buttons: a new
  `--color-danger-contrast` token pairs deep ink text with the lighter dark
  theme's danger fill instead of failing white-on-red (ui-audit UI-011).
- Fixed horizontal page overflow at mobile widths (375px): the topbar and its
  theme toggle, the mobile bottom navigation labels, the canvas workspace
  header, and the sync status explanation prose no longer push content past
  the viewport; the sync explanation is visually clamped while screen readers
  still receive the full state description (ui-audit UI-012).
- Production deployment path fixes surfaced by the TASK-156 smoke test:
  - The production `app`/`queue-worker`/`scheduler` roles now receive
    `APP_KEY`, `APP_URL`, and `DB_PASSWORD` from the deployment environment
    (previously the app could not connect to the database and the entrypoint
    failed fast).
  - The reverse-proxy nginx now uses `$document_root` (not `$realpath_root`) for
    `SCRIPT_FILENAME`, so API/PHP requests route to `app:9000` instead of 404ing
    (the proxy is a pure reverse proxy with no local docroot).
  - `make prod-backup` / `make prod-restore` now mount `../scripts/*.sh` from
    the correct relative path, invoke the scripts via `bash` (installed in the
    `postgres:17-alpine` image), and the scripts are executable — previously the
    wrong relative path caused Docker to mount empty directories and the backup
    restore flow failed.

- Saving AI provider settings on an empty database no longer creates an extra
  orphan record; the single global settings row bootstraps correctly
  (regression found by P17-007 tests).
- Analytics periods with a date-only `to` (YYYY-MM-DD) now include events
  that happen ON the end day. Previously `to=2026-08-23` cut off at
  00:00:00, so same-day activity silently disappeared from the overview —
  a latent bug that surfaced on UTC date rollover (overview + heatmap).
- A real AI Usage surface lands in Settings (Phase 25, TASK-P25-009): a
  summary-first card shows this month's AI credits progress, the estimated
  Kinevo-hosted cost, BYOK usage, a per-feature breakdown of what you asked
  for, and your recent AI runs. No charts yet — the daily chart is explicitly
  deferred.
- AI budget alerts keep surprises away (Phase 25, TASK-P25-010): you get an
  in-app notice when you cross 50/75/90/100% of your monthly AI credits, and
  operators get logged, once-per-day internal alerts on company-wide AI spend
  or a user's anomalous request rate. Alerts never block — they record events
  only, until delivery channels arrive later.

- Mobile architecture is locked in (Phase 26, TASK-P26): we verified NativePHP
  against its official docs and confirmed the native app reuses the same Laravel
  backend — the web UI and the mobile app are two fronts over one codebase. The
  app runs offline-first on device and syncs to the server using the existing
  offline contract; building the actual app is the next phase (P27).

- Mobile feasibility is now proven on real hardware, not just docs (Phase 26,
  TASK-P26-001): the Android toolchain was installed with checksum-verified
  official packages, a real Android 14 emulator (KVM) booted, NativePHP Mobile
  4.2.0 produced a working Android debug APK, and that APK launched on the
  emulator — the running app reached the Kinevo backend (HTTP 200) and verified
  TLS egress. The remaining on-device app features (login, entitlement, billing
  screens) need the Laravel bundle that macOS `native:run` injects, so they are
  tracked as P27.

- Pricing simplified to three tiers with locked Indonesian prices (owner
  decision): Free (Rp0), Pro (Rp34.900/month), Power (Rp49.900/month). The
  intermediate Personal tier is retired — existing subscriptions on it keep
  working but degrade gracefully to plan-catalog defaults. Pro/Power now bring
  your own AI key; Free uses Kinevo-hosted credits only. Annual billing remains
  architecturally supported but unpriced until a future decision.

## [0.4.0] — 2026-08-17

### Added

- Identity/profile baseline:
  - Sanctum bearer-token authentication (register, login, logout, me).
  - Owner `profiles` table with settings (display_name, locale, timezone,
    week_start_day).
  - Domain layer: `Profile` entity, `ProfileSettings` value object,
    `ProfileRepository` contract, Eloquent implementation.
  - Application use cases: RegisterUser, LoginUser, LogoutUser, GetProfile,
    UpdateProfile.
  - API endpoints under `api/v1`: `/auth/register`, `/auth/login`,
    `/auth/me`, `/auth/logout`, `/profile`.
- OpenAPI `Identity` tag and schema additions.
- Feature tests for auth and profile ownership (17 tests total).

## [0.3.0] — 2026-08-17

### Added

- Environment/config/secrets baseline:
  - `docs/environment.md` contract.
  - `server/.env.example` annotated with secret vs non-secret defaults.
  - `scripts/check-secrets.sh` enforced in CI.

## [0.2.0] — 2026-08-17

### Added

- Docker development environment:
  - `infrastructure/docker/` (PHP 8.4-FPM Alpine image, entrypoint).
  - `infrastructure/docker-compose.yml` (app + PostgreSQL 17).
  - Makefile targets: `up`, `down`, `logs`, `migrate`, `shell`.

## [0.1.0] — 2026-08-17

### Added

- Repository skeleton:
  - Laravel 13 modular monolith under `server/` (PHP 8.4+, PostgreSQL).
  - Migrations canonicalized under `database/migrations/`.
  - CI pipeline (Pint, PHPStan, PHPUnit, repository validation).
- Architecture baseline: SRS v2.0.0, design, architecture, domain model,
  scheduling, knowledge, offline, AI, deployment, test strategy docs.
- ADR baseline (ADR-001..ADR-007).
- Repository bootstrap tooling (`scripts/`, `Makefile`).

[0.4.0]: https://github.com/sedam-or/Kinevo/compare/v0.3.0...v0.4.0
[0.3.0]: https://github.com/sedam-or/Kinevo/compare/v0.2.0...v0.3.0
[0.2.0]: https://github.com/sedam-or/Kinevo/compare/v0.1.0...v0.2.0
[0.1.0]: https://github.com/sedam-or/Kinevo/releases/tag/v0.1.0