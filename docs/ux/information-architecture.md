# Kinevo — Information Architecture

> STATUS: AUTHORITATIVE (P29, 2026-08-31). Canonical IA under the Product
> Constitution and SRS. Decided from product truth + implementation audit —
> Stitch was not consulted for this document (timing rule). Implementation
> contract for navigation: `server/resources/js/shell/navigation.ts`.

## 1. Canonical model

```
KINEVO                                    [Workspace switcher]

NOW          Today · Week · Month            (cross-workspace execution surfaces)
BUILD        Goals · Tasks · Schedule        (intention → structure → week plan)
THINK        Knowledge · Canvas              (context that serves execution)
REFLECT      Progress · Review               (what moved; what to adjust)
──────────────────────────────────────
SYSTEM       Import & Sync · Notifications · Settings (+ Plan, AI & Providers, Workspace Home)
+ CAPTURE    Quick Capture (on Today; primary global action)
```

Conceptual mapping to the core loop: NOW = execution present · BUILD = intention
→ structure → schedule · THINK = context · REFLECT = progress/review · SYSTEM =
operations. The current implemented group labels (Execute / Plan / Knowledge /
Review / System) map onto NOW / BUILD / THINK / REFLECT / SYSTEM respectively.

## 2. Screen inventory & classification

| Surface (nav key) | Current state | Classification | Canonical destination |
|---|---|---|---|
| Today (`today`) | TodayView: NOW/NEXT/Timeline/progress/capture/first-session guide | **CANONICAL** | NOW |
| Week (`week`) | WeekView (7-day plan, FR-11) | **CANONICAL** | NOW |
| Calendar (`calendar`) | CalendarView = full-month grid (FR-15, `shiftMonth`) | **RENAME → "Month"** | NOW |
| Goals (`goals`) | GoalListView + GoalDetailView (roadmap, breakdown, NextAction) | **CANONICAL** | BUILD |
| Tasks (`tasks`) | TaskListView + TaskDetailView | **CANONICAL** | BUILD |
| Schedule (`schedule`) | ScheduleDraftView: weekly draft banner, generate/reschedule, **KRS + ICS import sections, Sync Now** | **CANONICAL** | BUILD |
| Knowledge (`knowledge`) | NotesListView + NoteEditView (Tiptap) + LinkManager | **CANONICAL** | THINK |
| Canvas (`canvas`) | CanvasListView + CanvasWorkspaceView (Excalidraw island) | **CANONICAL** | THINK |
| Analytics (`analytics`) | AnalyticsView: overview/heatmap/pillars/interpretations | **RENAME → "Progress"** | REFLECT |
| Review | no dedicated surface; reflection exists as flows: EOD reconciliation (FR-47), Morning Recovery (FR-48), weekly review concepts | **MISSING → TARGET** (dedicated Review surface; flows already canonical) | REFLECT |
| Settings (`settings`) | profile/preferences | **CANONICAL** | SYSTEM |
| Plan (`plan-settings`) | PlanSettingsView (billing/plan) | **CANONICAL** | SYSTEM |
| AI & Providers (`ai-settings`) | AiSettingsView + usage summary | **CANONICAL** | SYSTEM |
| Workspace Home (`workspace-home`) | identity/current-goal/doorways | **CANONICAL** | SYSTEM (workspace context surface) |
| Import & Sync | integrated INSIDE Schedule surface (KRS/ICS/Sync Now) | **CANONICAL as Schedule sections** — not a separate nav item; section headers must say "Import & Sync" (discoverability rule) | SYSTEM conceptually, BUILD physically |
| Notifications | topbar bell (NotificationCenter), global with context | **CANONICAL** (topbar, not nav group) | SYSTEM |
| Capture | Quick Capture on Today + `nav` free access | **CANONICAL** (Today-primary; never a separate page) | global action |

## 3. Settled terminology decisions (supersedes older labels)

1. **Week** = the 7-day planning surface (FR-11). The word "Calendar" is
   DEPRECATED as a navigation label — the month grid (FR-15) is named **Month**.
   Rename `Calendar → Month` is a decided change: TARGET (MIGRATION_REQUIRED,
   label-only code change; recorded as a post-convergence task).
2. **Month** = canonical name of the full-month grid (cross-workspace, FR-15).
3. **Progress** = canonical name of the analytics/reflection data surface
   (rename `Analytics → Progress`: TARGET, label-only). "Review" = the
   reflective WORKFLOW (weekly review, EOD, recovery) — its dedicated surface is
   TARGET; until then Review flows live where they act (Today/Schedule).
4. **Schedule** = the weekly draft / Sync Now / Import & Sync surface (draft
   lifecycle, ADR-016). It is the single "import & sync" destination.
5. Programs/Milestones are not nav items — they live inside Goals (detail
   surfaces). Navigation model: Goal detail owns milestones/programs
   (EntityLinks chips provide task/schedule/progress continuity).
6. Workspace Home = the workspace identity/context surface in SYSTEM (not a
   first-love surface; Today is the landing view).
7. Plan + AI & Providers are peer SYSTEM surfaces under Settings-class authority
   (commercial + AI configuration).
8. Mobile-web differences: mobile bottom bar keeps Today · Tasks · Goals ·
   Knowledge permanent (`MOBILE_PRIMARY_KEYS`); everything else behind "More".
   The locked mobile app IA (Today · Tasks · Capture · Workspace · More) is
   governed by `docs/mobile-architecture.md`.

## 4. Navigation rules

- One primary surface per intent; no duplicate entrances to the same data.
- Capture is always one gesture away (Today primary + mobile quick capture).
- Cross-workspace surfaces (Today/Week/Month) never silently filter by workspace;
  when the workspace filter layer lands (TARGET FR-73/74) an explicit, labeled
  scope control is required (`docs/ux/content-design.md`).
- Every nav destination must render its purpose header + empty state per
  `docs/ux/interaction-states.md` (browser-proven in P28 p28-ux-audit 13/13).
