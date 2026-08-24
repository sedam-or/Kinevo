# Kinevo — UI/UX Design System & Product Experience Specification

> **Document role:** Product UI/UX authority and implementation contract.
>
> **Status:** Proposed stabilization baseline (lifecycle: ACTIVE)
>
> **Primary objective:** Replace the current visually weak / inconsistent UI with a coherent, highly usable, accessible, responsive, self-hosted productivity operating system experience while preserving the deterministic domain architecture.
>
> **Design direction:** **Neo-Brutalism, refined for long-duration productivity use.**
>
> **Important:** This document does not redefine business requirements in `docs/SRS.md`. It defines how approved requirements (SRS §14 UI/UX REQUIREMENTS and its FR/NFR ancestors) are experienced through the interface.

---

## 1. Purpose

Kinevo is intended to behave as a personal operating system rather than a conventional todo application.

The UI must therefore make five things obvious at all times:

1. What matters.
2. What is happening now.
3. What can be done next.
4. Why Kinevo made a scheduling recommendation.
5. Whether the user's work is safely persisted.

The interface must not require the user to understand:

- Laravel;
- PostgreSQL;
- scheduling algorithms;
- IndexedDB;
- service workers;
- Excalidraw;
- Tiptap;
- Ollama;
- API versioning;
- optimistic concurrency;
- queue internals.

Those are implementation details.

---

## 2. Product Experience Principles

### 2.1 Execution before administration

The primary interface is not a dashboard.

The primary interface is **execution**.

The default authenticated landing screen SHALL be Today.

Today answers:

> "What should I do now?"

Not:

> "How many metrics can I inspect?"

### 2.2 Progressive disclosure

Do not show every piece of information at once.

Information hierarchy:

```text
Level 1
Immediate action

Level 2
Context needed to make the action

Level 3
Reasoning / details

Level 4
Historical analytics / advanced controls
```

Example:

```text
NOW
Research methodology
45 min
09:00–09:45
[Start]

More
Goal: Finish Research
Milestone: Methodology
Reason: deadline pressure
```

Do not show the explanation, full activity history, capacity model, and analytics in the initial card.

### 2.3 Reduce decision load

The system must reduce cognitive overhead.

Bad:

```text
27 visible actions
14 badges
12 metric cards
8 competing calls to action
```

Good:

```text
ONE PRIMARY ACTION
ONE SECONDARY ACTION
OPTIONAL DETAILS
```

### 2.4 Explainable automation

Automation must never feel arbitrary.

Whenever Kinevo moves, recommends, rejects, or conflicts with something, the interface MUST provide a concise reason.

Example:

> Moved to 14:00 because the 10:00 slot conflicts with a locked task and the task has a nearer deadline.

Do not show:

> "Optimized."

### 2.5 Safety before cleverness

A visually exciting UI is useless if users cannot tell whether their data is saved.

Persistence state is a first-class UI state.

Users MUST be able to distinguish:

```text
Saved
Saving
Offline
Queued
Syncing
Conflict
Retrying
Failed
```

### 2.6 One mental model

The user should understand:

```text
Goal
  ↓
Milestone
  ↓
Program
  ↓
Task
  ↓
Schedule
  ↓
Execution
  ↓
Progress
```

The UI must reinforce this model consistently.

---

## 3. Design Direction — Refined Neo-Brutalism

### 3.1 Why Neo-Brutalism

Kinevo should feel:

- decisive;
- physical;
- tangible;
- energetic;
- memorable;
- transparent;
- less corporate;
- less "generic SaaS";
- visually differentiated.

Neo-Brutalism is appropriate because:

- hard borders communicate boundaries;
- strong typography creates hierarchy;
- shadows communicate physical interaction;
- simple surfaces reduce visual ambiguity;
- deliberate imperfection makes the product feel less sterile.

However:

> Kinevo MUST NOT become a novelty design.

This is a productivity system used for hours.

Therefore the style is:

> **Neo-Brutalism for structure, refined minimalism for cognition.**

---

## 4. Visual Language

### 4.1 Core characteristics

Use:

- thick borders;
- offset shadows;
- strong typography;
- flat or lightly textured surfaces;
- high-contrast controls;
- deliberate geometric layouts;
- rounded corners sparingly;
- visible focus indicators;
- large click targets;
- asymmetrical accents;
- simple iconography.

Avoid:

- excessive gradients;
- glassmorphism;
- blur-heavy panels;
- floating cards everywhere;
- tiny typography;
- overly rounded "pill soup";
- decorative animation;
- gratuitous 3D.

### 4.2 Border language

Default:

```text
1px:
secondary containers

2px:
normal interactive component

3px:
primary cards / strong emphasis

4px:
hero / modal / important state
```

Do not make every element 4px thick.

The border hierarchy is part of information hierarchy.

### 4.3 Shadow language

Default Neo-Brutalist elevation:

```text
Resting:
4px 4px 0 currentColor

Hover:
6px 6px 0 currentColor

Pressed:
2px 2px 0 currentColor
```

The exact CSS implementation MAY use theme tokens.

The important rule is:

> Shadow offset communicates physical interaction, not generic material elevation.

### 4.4 Corners

Use a restrained radius system:

```text
0px:
tables, timeline segments, canvas overlays

4px:
inputs, buttons, badges

8px:
cards, drawers

12px:
large modal / workspace surfaces
```

Avoid fully rounded controls except where semantics require it.

---

## 5. Color System

The palette must be high-contrast but not visually exhausting.

### 5.1 Semantic colors

Required semantic roles:

```text
--color-bg
--color-surface
--color-surface-raised
--color-text
--color-text-muted
--color-border
--color-primary
--color-primary-contrast
--color-success
--color-warning
--color-danger
--color-info
--color-focus
```

Semantic colors MUST communicate meaning consistently.

### 5.2 Color must never be the only state signal

All state indicators MUST include at least two of:

```text
icon/glyph
text
pattern
border treatment
position
shape
```

Example:

```text
CONFLICT
⚠ Conflict
[dashed red border]
```

not only a red border.

This is consistent with the existing visual-state contract (`VisualStateBadge`, SRS §14.3 "avoid color-only communication").

### 5.3 Light theme

Light theme should feel:

```text
warm neutral canvas
deep ink text
high-contrast dark borders
one strong accent
secondary semantic accents
```

Avoid pure white everywhere.

Recommended visual hierarchy:

```text
Page background
  ↓
surface
  ↓
raised surface
  ↓
active surface
```

### 5.4 Dark theme

Dark theme is not simply light theme with inverted colors.

Use:

```text
deep neutral background
dark-raised surfaces
bright text
muted secondary text
high-contrast borders
controlled accent saturation
```

Avoid excessive neon.

### 5.5 System theme

System mode follows operating-system preference.

User choice:

```text
Light
Dark
System
```

MUST persist locally and MUST be restored on load.

---

## 6. Typography System

### 6.1 Primary font

Use a highly legible sans-serif.

Preferred properties:

- strong x-height;
- readable numerals;
- good UI punctuation;
- excellent weight range.

Do not choose a novelty font for body text.

### 6.2 Hierarchy

Recommended scale:

```text
Display:
40–56px

H1:
32–40px

H2:
24–32px

H3:
20–24px

Body:
15–17px

Small:
13–14px

Micro:
11–12px
```

Do not use micro text for essential information.

### 6.3 Typography weight

```text
Heading:
700–900

Primary body:
450–550

Secondary:
400–500

Labels:
600–750

Numeric emphasis:
700–900
```

---

## 7. Spacing System

Use a consistent spacing scale.

Base:

```text
4
8
12
16
20
24
32
40
48
64
80
```

No arbitrary spacing values unless justified by a component-specific geometry requirement.

---

## 8. Layout System

### 8.1 Desktop

Primary layout:

```text
┌────────────────────────────────────────────────────────┐
│ Topbar                                                 │
├──────────────┬─────────────────────────────────────────┤
│              │                                         │
│ Sidebar      │ Main content                            │
│              │                                         │
│              │                                         │
│              │                                         │
└──────────────┴─────────────────────────────────────────┘
```

Recommended:

```text
Sidebar:
240–280px

Content:
max 1440px

Main horizontal gutter:
24–40px
```

### 8.2 Tablet

Collapse sidebar into:

```text
top navigation
or
compact side rail
```

Do not preserve desktop density at all costs.

### 8.3 Mobile

Use:

```text
top bar
main content
bottom navigation
floating Quick Capture
```

Bottom navigation MUST prioritize:

```text
Today
Tasks
Goals
Knowledge
More
```

Canvas gets a dedicated route, not necessarily a permanent bottom-nav item.

---

## 9. Navigation Architecture

Primary navigation:

```text
Today
Week
Calendar
Goals
Tasks
Schedule
Knowledge
Canvas
Analytics
Settings
```

The navigation should be organized so the mental purpose is obvious:

```text
EXECUTE
  Today
  Week
  Calendar

PLAN
  Goals
  Tasks
  Schedule

KNOWLEDGE
  Notes
  Canvas

REVIEW
  Analytics

SYSTEM
  Settings
```

This grouping better communicates mental purpose than one flat menu.
Schedule is a planning artifact (deciding *when* tasks run happens before
execution), so it lives in PLAN — never under SYSTEM next to Settings
(design.md §104).

---

## 10. Global Shell

### 10.1 Topbar

Topbar MUST contain:

Left:

```text
Kinevo wordmark
current section
```

Center or context area:

```text
date / workspace context
```

Right:

```text
Sync status
Notifications
Quick Capture
Theme
Profile
```

### 10.2 Quick Capture button

On desktop:

```text
+ Capture
```

On mobile:

```text
floating + button
```

Shortcut:

```text
Cmd/Ctrl + K
```

Optional alternative:

```text
N
```

Do not conflict with browser-native shortcuts.

---

## 11. Global UI State System

Every network-driven surface MUST represent:

```text
idle
loading
loaded
empty
error
offline
saving
saved
conflict
retrying
```

Do not hide all states behind a spinner.

### 11.1 Loading

Prefer skeletons that preserve layout.

Avoid:

```text
Loading...
```

for entire screens.

### 11.2 Empty states

Every empty state should contain:

```text
what is empty
why it matters
one clear next action
```

Example:

```text
No milestones yet.

Break this goal into checkpoints
so Kinevo can schedule the work.

[Create milestone]
```

### 11.3 Error states

Error UX MUST contain:

```text
What happened
What is safe
What can be done now
```

Example:

```text
Couldn't save this note.

Your latest edits are still on this device.
The server rejected the save because the note changed elsewhere.

[Compare]
[Keep local]
[Reload server]
```

---

## 12. Today — Primary Product Surface

Today is the heart of Kinevo.

Today structure is a single vertical flow:

```text
Header
Date
Sync
Recovery
NOW
NEXT
Timeline
Quick Capture
```

and the timeline must show Hard Landscape, tasks, Recharge, buffers, empty slots, conflicts, and locks.

### 12.1 Today structure

```text
TODAY
────────────────────────────────────────

Good morning, Juan.

Tue · 21 Aug
[Sync status]

┌──────────────────────────────────────┐
│ NOW                                  │
│                                      │
│ Research methodology                 │
│ 09:00 — 09:45                        │
│                                      │
│ Research › Methodology               │
│                                      │
│ [ Start ]                            │
└──────────────────────────────────────┘

NEXT
09:45 Break
10:00 Read paper #12

TIMELINE
06  ─────────────────────────
08  Hard Landscape
09  Research
10  Empty Slot
11  Meeting
...
24
```

### 12.2 NOW card visual behavior

The NOW card is the highest-priority visual surface.

Use:

```text
thick border
strong offset shadow
large title
large time range
one primary button
small context labels
```

Hover:

```text
shadow increases
```

Pressed:

```text
shadow compresses
```

### 12.3 NOW card must not feel like a modal

It remains embedded in the page.

The user should be able to:

```text
Start
Complete
Partial complete
Reschedule
Open task
Open note
Open canvas
Lock/unlock
```

without losing context.

---

## 13. Timeline Design

### 13.1 Axis

Time labels should use:

```text
06:00
07:00
08:00
...
24:00
```

If the user zooms, use finer granularity.

### 13.2 Timeline blocks

Each event type has a unique geometry.

### Hard Landscape

```text
heavy hatch / stripe
strong border
LOCK glyph
```

### Scheduled task

```text
solid surface
task title
duration
context marker
```

### Sacred Anchor

```text
distinct icon
special accent
locked indicator
```

### Recharge

```text
soft visual surface
rest icon
```

### Empty Slot

```text
outlined dashed region
subtle plus icon on hover
```

### Conflict

```text
diagonal pattern
warning glyph
red/orange semantic color
```

---

## 14. Drag & Drop

Where supported:

```text
drag task
 ↓
preview target slot
 ↓
show valid/invalid state
 ↓
drop
 ↓
validate server
```

Never silently place the task.

Invalid drop should show:

```text
cannot place here
reason
```

Example:

```text
Locked event occupies this interval.
```

---

## 15. Week View

The Week screen answers:

> "How does my workload distribute across the week?"

Layout:

```text
Mon | Tue | Wed | Thu | Fri | Sat | Sun
```

Each day shows:

```text
date
scheduled minutes
capacity
load ratio
key deadlines
task count
```

Use a physical paper-like neo-brutalist grid.

Do not turn every day into a card.

---

## 16. Calendar View

Monthly calendar is a planning surface, not a task list.

Each day may show:

```text
task count
deadline marker
hard landscape marker
capacity condition
progress marker
```

Clicking a date opens:

```text
day detail drawer
```

rather than navigating immediately when possible.

---

## 17. Goals Workspace

Goal is the second most important planning surface.

### Goal card

```text
GOAL
Complete Research
────────────────
Deadline
Dec 15

██████████░░ 72%

4 / 6 milestones
```

Use one dominant progress visualization.

Avoid 5 separate progress meters for the same concept.

---

## 18. Goal detail

Structure:

```text
Outcome
Deadline
Progress

Milestones
──────────
01 Literature Review
02 Methodology
03 Experiment
04 Analysis
05 Writing

Programs

Knowledge

Next actions

History
```

### 18.1 Goal decomposition

When AI suggests a breakdown:

```text
AI PROPOSAL
```

must be visually distinct from committed data.

Example:

```text
┌─────────────────────────────────┐
│ AI PROPOSAL                     │
│                                 │
│ 5 suggested milestones          │
│ 180 estimated minutes/week      │
│                                 │
│ [Review] [Edit] [Reject]        │
└─────────────────────────────────┘
```

Never visually present an AI proposal as already committed.

---

## 19. Task Experience

Task detail must feel like:

> a small execution workspace.

Structure:

```text
Task title
Status
Priority
Schedule
Goal/Milestone/Program
Subtasks
Notes
Attachments
Activity
AI
```

Primary action changes according to task state:

```text
Backlog:
Schedule

Scheduled:
Start

In Progress:
Complete

Partial:
Continue

Missed:
Recover
```

One main action.

---

## 20. Subtasks

Subtasks are checklist items.

Use strong completion feedback:

```text
☐ Analyze dataset
☑ Clean dataset
```

Completion animation:

```text
checkbox scale 0.9 → 1.0
text strike-through
small progress update
```

Avoid excessive celebratory confetti.

Kinevo is serious productivity software.

---

## 21. Quick Capture UX

Quick Capture must be the fastest interaction in Kinevo.

Desktop:

```text
Ctrl/Cmd + K
```

Modal:

```text
What needs to be done?

[________________________]

Priority   P2
Duration   45m
Goal       Research
Milestone  Methodology

[Capture]
```

Progressive disclosure:

```text
initial:
title

optional:
priority
duration
goal
milestone
program
due date
```

Do not require a 10-field form to capture a thought.

---

## 22. Capacity Feedback

Capacity is not an analytics-only concept.

Use a simple signal:

```text
Today's load
████████░░ 78%

Moderate
```

or:

```text
This week
███████████░ 92%

High load
```

Clicking reveals:

```text
available capacity
scheduled load
effective capacity
recommendation
reason
```

---

## 23. Adaptive Context

Context check-in must be lightweight.

Do not create a clinical-looking questionnaire.

Use:

```text
Energy
1 2 3 4 5 6 7 8 9 10

Stress
1 2 3 4 5 6 7 8 9 10
```

Optional:

```text
Difficulty
Familiarity
```

Do not force all fields.

The interface should say:

> "Help Kinevo choose a better task for this moment."

Not:

> "Measure your neurological state."

---

## 24. Focus Session UX

Focus mode should remove distractions.

Full-screen or distraction-reduced:

```text
Task
Research methodology

45:00

[Pause]
[Complete]
```

Optional context:

```text
Goal
Milestone
```

Do not show analytics while the timer is running.

After completion:

```text
Session completed
45 min

[Done]
[Add reflection]
```

---

## 25. Recharge UX

Recharge is deliberately designed as a productive state.

Use:

```text
RECHARGE
15:00
```

Visual tone should be calmer than deep-work mode but still consistent with Kinevo.

Do not display:

> "You are wasting time."

Display:

> "Protect your capacity."

---

## 26. Emergency Pause UX

Emergency Pause should be respectful and low-friction.

Example:

```text
PAUSE MODE

Your capacity is interrupted.
Kinevo will protect today's remaining commitments.

Affected:
5 tasks
2 deadlines

[Start Recovery Mode]
[Cancel]
```

After activation:

```text
Recovery mode active
Notifications reduced
Schedule protected
```

---

## 27. Break Mode

Break Mode should not feel like a failure state.

Visual treatment:

```text
BREAK MODE
```

with:

```text
remaining duration
next commitment
```

Avoid aggressive productivity language.

---

## 28. Notifications

Use a layered model:

```text
Toast
↓
Notification center
↓
Contextual prompt
```

Do not spam.

Important notifications:

```text
Recovery needed
Schedule conflict
Sync conflict
AI proposal ready
Import failed
Backup/deployment problem
```

---

## 29. Notification center

Should expose:

```text
Unread
Today
Earlier
```

Each notification should have:

```text
icon
title
timestamp
one-line explanation
action
```

---

## 30. Knowledge Workspace

Knowledge should feel like a unified research desk.

Layout:

```text
┌────────────┬─────────────────────────────┐
│ Notes      │ Editor                      │
│            │                             │
│ Search     │ Title                       │
│            │ Content                     │
│ Note list  │                             │
│            │                             │
│            │ Linked entities              │
└────────────┴─────────────────────────────┘
```

Desktop:

```text
left:
notes

center:
editor

right:
context/links
```

Mobile:

```text
list
→ editor
→ context
```

---

## 31. Editor Design

Tiptap editor should feel minimal.

Toolbar:

```text
Heading
Bold
Italic
Link
List
Task list
```

Do not show 40 formatting actions.

Use slash commands only if they can be made reliable.

---

## 32. Note autosave

Top right:

```text
Saved
Saving...
Offline
Conflict
```

Use text + icon.

Do not place autosave state at the bottom of a 1500-line document.

---

## 33. Linked Knowledge

Use a context sidebar:

```text
LINKED TO

Goal
Research

Milestone
Methodology

Task
Run ViT baseline

Canvas
Model Architecture
```

Each item is clickable.

---

## 34. Canvas — Critical Redesign

Canvas is currently the highest-risk UX/technical surface. The adapter and unit orchestration are proven in the repository, but the adapter tests intentionally mock the React/Excalidraw island because the test environment cannot mount WebGL/canvas. Real browser behavior is therefore not yet proven by automated tests.

Therefore the new design MUST treat Canvas as a **browser-integration feature**, not merely an adapter.

### 34.1 Canvas architecture

```text
Vue Canvas Workspace
        ↓
CanvasHost
        ↓
CanvasAdapter
        ↓
React Island
        ↓
Excalidraw
```

The domain remains:

```text
Kinevo-owned
```

The editor remains:

```text
Excalidraw-owned
```

### 34.2 Canvas entry state

When opening Canvas:

```text
Loading Canvas...
```

Then:

```text
Loading editor...
```

Then:

```text
Ready
```

If the editor fails:

```text
Canvas editor failed to initialize.

Your saved canvas data is still safe.

[Retry]
[Open read-only data]
[Report issue]
```

Never leave the page blank.

### 34.3 Canvas toolbar

Kinevo should wrap the engine with a product shell.

Topbar:

```text
← Back
Canvas title

Saved
[Undo]
[Redo]

Context
Goal / Milestone / Program / Task

[More]
```

Do not fight Excalidraw's native tools.

Kinevo controls the surrounding workspace.

### 34.4 Canvas save state

Always visible:

```text
● Saved
◐ Saving
○ Offline
⚠ Conflict
✕ Failed
```

No hidden autosave.

### 34.5 Canvas conflict

When 409 occurs:

```text
CANVAS CONFLICT

This canvas changed on another session.

Your local version:
4 changes

Server version:
7 changes

[Review server]
[Keep local]
[Copy local to new canvas]
```

Never overwrite silently.

### 34.6 Canvas offline

Offline banner:

```text
OFFLINE
Your changes are saved locally.
They will sync when connection returns.
```

If queued:

```text
QUEUED
1 canvas change waiting to sync.
```

If sync succeeds:

```text
SYNCED
```

---

## 35. Canvas failure rescue strategy

Before considering Canvas complete, browser verification MUST include:

```text
desktop Chrome
desktop Firefox
mobile Chrome/Safari equivalent where supported
fresh session
authenticated session
existing canvas
new canvas
empty canvas
large canvas
image/file
offline
reconnect
version conflict
theme switch
resize
route navigation
browser back/forward
refresh
```

The current unit tests alone are insufficient because the Excalidraw island is intentionally mocked in the adapter tests.

---

## 36. Canvas browser diagnostic mode

Implement a development-only diagnostic route:

```text
/dev/canvas-diagnostics
```

It should show:

```text
React mounted: YES/NO
Excalidraw mounted: YES/NO
Initial data loaded: YES/NO
Scene changes received: YES/NO
Autosave connected: YES/NO
API connected: YES/NO
IndexedDB available: YES/NO
Service Worker active: YES/NO
Last save:
Server version:
Adapter state:
```

This route MUST be disabled or protected in production.

This will drastically reduce debugging time.

---

## 37. Analytics Design

Analytics should be visually dense but cognitively organized.

Primary sections:

```text
Progress
Capacity
Execution
Focus
Recovery
Work-Life
```

Do not present 20 graphs immediately.

---

## 38. Analytics cards

Each metric needs:

```text
value
period
trend
meaning
```

Example:

```text
Effective capacity
23h

↓ 8% vs previous 4 weeks

Reason:
completion ratio decreased
```

---

## 39. Goal progress visualization

Prefer:

```text
milestone roadmap
```

over:

```text
12 circular progress rings
```

Example:

```text
Literature ✓
      │
Methodology ●
      │
Experiment ○
      │
Analysis ○
      │
Writing ○
```

This tells a story.

---

## 40. Heatmap

Use GitHub-like familiarity but Kinevo visual language.

The heatmap may encode:

```text
productive time
or
meaningful progress
```

but must clearly label which metric is being shown.

Never assume darker = better unless explained.

---

## 41. Settings

Settings should be organized:

```text
Profile
Appearance
Scheduling
Notifications
Offline
AI
Data
Security
About
```

Do not expose raw environment variables.

---

## 42. AI UI

AI must visually communicate uncertainty and non-authority.

Every AI-generated object MUST show:

```text
AI-generated
```

and, where applicable:

```text
Provider
Model
Generated at
```

---

## 43. AI proposal card

Example:

```text
AI PROPOSAL

Suggested breakdown
for "Complete Research"

5 milestones
~180 min/week

[Preview]
[Edit]
[Accept]
[Reject]
```

The Accept action should be visually strong but never destructive.

---

## 44. AI unavailable state

If Ollama/provider is offline:

```text
AI unavailable

Kinevo's core scheduling and execution features
continue to work normally.

[Retry]
```

This behavior is explicitly part of the AI architecture.

---

## 45. Accessibility

Target:

```text
WCAG 2.2 AA
```

Required:

- keyboard navigation;
- visible focus;
- semantic landmarks;
- accessible labels;
- accessible dialogs;
- screen reader status;
- reduced motion;
- sufficient contrast;
- touch target minimum ~44px where practical;
- no color-only meaning;
- logical heading hierarchy.

---

## 46. Keyboard System

Global:

```text
Cmd/Ctrl + K
Quick Capture

G then T
Today

G then W
Week

G then C
Calendar

G then G
Goals

G then K
Knowledge
```

Only implement shortcuts that do not conflict with normal text input.

---

## 47. Reduced Motion

Respect:

```text
prefers-reduced-motion
```

Animations should become:

```text
opacity
small transform
minimal movement
```

or be removed.

---

## 48. Interaction Motion Language

Default:

```text
micro interactions:
100–160ms

panel transitions:
160–220ms

modal:
180–260ms
```

No animation should block a primary interaction.

---

## 49. Neo-Brutalist Interaction Effects

### Button hover

```text
rest:
shadow 4px 4px

hover:
shadow 6px 6px

active:
translate 2px 2px
shadow 2px 2px
```

### Card hover

```text
border becomes stronger
shadow offset increases
```

### Pressed

The UI should physically appear to depress.

---

## 50. Component Library

Create shared components.

Minimum:

```text
Button
IconButton
Input
Textarea
Select
Combobox
Checkbox
Switch
Badge
VisualStateBadge
Card
Panel
Modal
Drawer
Tabs
Tooltip
Toast
Dropdown
ProgressBar
Timeline
CalendarGrid
EmptyState
ErrorState
Skeleton
CommandPalette
ConfirmDialog
```

Do not build five visually different buttons.

---

## 51. Button hierarchy

Only three main variants:

```text
Primary
Secondary
Danger
```

Optional:

```text
Ghost
```

Primary action must be visually obvious.

---

## 52. Modal rules

Use modals for:

```text
short decisions
confirmation
focused creation
```

Do not put entire pages inside modals.

If a task form is complex:

```text
route/drawer
```

rather than giant modal.

---

## 53. Drawer rules

Use drawers for:

```text
context
details
secondary editing
```

Example:

```text
Task details
Goal context
Linked notes
```

---

## 54. Toast rules

Toasts are for:

```text
success
non-blocking failure
sync completion
```

Never use toasts as the only place for a critical error.

---

## 55. Forms

Every form should define:

```text
label
input
helper text
validation
server error
success state
```

Errors must appear near fields.

---

## 56. Validation language

Use human language.

Bad:

```text
422 VALIDATION_ERROR
```

Good:

```text
Duration must be at least 1 minute.
```

Technical error codes may exist beneath the UI for diagnostics.

---

## 57. Mobile UX

Mobile must be considered a real product, not a shrunken desktop.

Today:

```text
NOW
NEXT
timeline scroll
```

Goal:

```text
progress
milestones
```

Knowledge:

```text
note list → editor
```

Canvas:

```text
editor with minimal surrounding chrome
```

---

## 58. Responsive Breakpoints

Recommended:

```text
xs < 640
sm 640
md 768
lg 1024
xl 1280
2xl 1536
```

Avoid designing against exact device models.

---

## 59. Density Modes

Provide optional:

```text
Comfortable
Compact
```

Default:

```text
Comfortable
```

Compact is for desktop power users.

---

## 60. Empty/Onboarding Experience

First-run should guide without overwhelming.

Sequence:

```text
Welcome
 ↓
Profile
 ↓
Create first Goal
 ↓
Create first Milestone
 ↓
Capture first Task
 ↓
Generate first Schedule
 ↓
Open Today
```

Do not show the entire feature catalogue during onboarding.

---

## 61. First Goal wizard

Prompt:

```text
What do you want to accomplish?

Deadline:
[optional]

Why does it matter?

[Continue]
```

Then:

```text
Suggested milestones
```

AI may assist, but the deterministic system remains authoritative.

---

## 62. UX copy tone

Tone:

```text
clear
direct
human
non-judgmental
actionable
```

Avoid:

```text
corporate jargon
motivational clichés
pseudo-scientific claims
guilt-inducing language
```

Bad:

> "You failed to optimize your capacity."

Good:

> "This week ran 20% below your usual capacity. Kinevo reduced the recommended workload."

---

## 63. Progress language

Prefer:

```text
Progress
Momentum
Completed
Remaining
```

Avoid:

```text
Productivity score
Human performance score
Brain score
```

unless a specific evidence-backed definition exists.

---

## 64. Error language

Prefer:

```text
Your schedule could not be updated.
Your previous schedule is still safe.
```

instead of:

```text
Unhandled exception.
```

---

## 65. Design Tokens

Create a centralized token system.

Example:

```text
colors.ts
spacing.ts
radius.ts
shadows.ts
typography.ts
motion.ts
zindex.ts
```

Do not hard-code visual values throughout components.

---

## 66. Z-index system

Centralize:

```text
base
sticky
dropdown
popover
drawer
modal
toast
command palette
critical overlay
```

Avoid:

```css
z-index: 99999;
```

throughout the application.

---

## 67. Iconography

Use one consistent icon set.

Icons should:

- have consistent stroke weight;
- be accessible;
- not replace essential text;
- communicate state quickly.

Do not mix five icon libraries.

---

## 68. Tables

Use tables for:

```text
analytics
history
logs
structured lists
```

Mobile tables should transform into:

```text
stacked cards
```

or horizontal scrolling with clear semantics.

---

## 69. Dense data surfaces

Analytics and logs may be denser than Today.

Density MUST be contextual.

Rule:

```text
execution surface = low density
planning surface = medium density
analysis surface = high density
```

---

## 70. Design QA

Each page must pass:

```text
visual consistency
keyboard navigation
responsive layout
loading
empty
error
offline
conflict
success
long content
small content
dark mode
light mode
reduced motion
```

---

## 71. Browser QA Matrix

Critical product E2E MUST run in at least:

```text
Chromium
Firefox
WebKit/Safari-equivalent
```

with focus on:

```text
Today
Quick Capture
Task
Schedule
Notes
Canvas
Offline
```

---

## 72. Canvas QA Matrix

Canvas requires real browser tests.

Minimum:

```text
Open new canvas
Open existing canvas
Draw
Text
Move
Delete
Undo
Redo
Save
Reload
Offline
Reconnect
Conflict
Archive
Rename
Context links
Light mode
Dark mode
Window resize
Mobile-compatible fallback where supported
```

---

## 73. Golden User Journeys

The primary UX specification is not isolated component tests.

Required golden journeys:

### Journey A — Plan

```text
Create Goal
Create Milestone
Create Program
Create Task
```

### Journey B — Execute

```text
Open Today
Start task
Pause
Resume
Complete
```

### Journey C — Recover

```text
Miss task
EOD
Morning Recovery
Reschedule
```

### Journey D — Knowledge

```text
Create Note
Edit
Link Goal
Create Canvas
Link Canvas
```

### Journey E — Offline

```text
Go offline
Quick Capture
Edit
Reconnect
Sync
```

### Journey F — AI

```text
Goal
AI proposal
Review
Accept
Milestones
```

---

## 74. Rescue Plan — Central Insight

The repository has many tasks marked DONE, including full frontend tasks and canvas integration. However, the evidence is heavily weighted toward:

```text
unit tests
feature tests
typecheck
build
adapter tests
```

The Canvas adapter tests specifically mock the React/Excalidraw island because the test environment lacks WebGL/canvas.

Therefore:

> **DONE means "implementation contract verified", not necessarily "real browser UX is production-ready".**

This is the central rescue insight.

---

## 75. Rescue Phase R0 — Freeze Feature Development

Immediately freeze:

```text
new AI features
new scheduling algorithms
new major domain concepts
new dependencies
```

Focus only on:

```text
stability
usability
integration
browser correctness
visual consistency
```

---

## 76. Rescue Phase R1 — Real Browser Smoke Test

Create:

```text
tests/e2e/
```

and run real browser smoke tests.

First verify:

```text
login
app shell
Today
task
goal
note
canvas
```

Do not trust unit tests.

---

## 77. Rescue Phase R2 — Build a Bug Taxonomy

Every bug must be classified:

```text
P0:
data loss
cannot authenticate
cannot save
cannot open core feature
canvas crashes
offline mutation lost

P1:
feature unusable
wrong schedule
wrong state
navigation broken
major UX confusion

P2:
visual inconsistency
minor workflow friction
poor copy

P3:
cosmetic
enhancement
```

P0 bugs block all feature work.

---

## 78. Rescue Phase R3 — Runtime Diagnostics

Add development-only diagnostics for:

```text
API
Auth
Offline
Canvas
Tiptap
Scheduler
```

Especially Canvas.

No more debugging through arbitrary browser console output.

---

## 79. Rescue Phase R4 — Visual Refactor

After runtime stability:

```text
Foundation
 ↓
Shell
 ↓
Today
 ↓
Task
 ↓
Goal
 ↓
Knowledge
 ↓
Canvas
 ↓
Analytics
```

Do not redesign every page simultaneously.

---

## 80. Rescue Phase R5 — Remove UI duplication

Search for:

```text
duplicate buttons
duplicate badge logic
duplicate modal implementations
duplicate API error mapping
duplicate spacing values
duplicate color values
duplicate card components
```

Replace with design-system components.

---

## 81. Rescue Phase R6 — Simplify

Delete:

```text
unused panels
decorative metrics
redundant cards
dead states
dead navigation
```

Kinevo should feel smaller after redesign. Not larger.

---

## 82. Rescue Phase R7 — Canvas first-class stabilization

Canvas is a critical rescue target.

Required sequence:

```text
Canvas route
 ↓
React island mount
 ↓
Excalidraw render
 ↓
load scene
 ↓
change event
 ↓
autosave
 ↓
server persistence
 ↓
offline
 ↓
reconnect
```

Measure each boundary separately.

---

## 83. Rescue Phase R8 — Frontend Architecture Audit

Ensure:

```text
Vue
 ↓
feature store
 ↓
API client
 ↓
application API
```

and NOT:

```text
Vue component
 ↓
business logic
 ↓
fetch
 ↓
local mutation
 ↓
magic side effect
```

---

## 84. Rescue Phase R9 — State Machine UI

Every backend state transition must have a corresponding UX state.

Create a matrix:

```text
TaskStatus
↓
Allowed action
↓
Primary button
↓
Secondary actions
↓
Visual state
↓
Confirmation
↓
Activity event
```

Do this for:

```text
Task
Goal
Milestone
Program
Canvas
Note
Schedule
AI Proposal
```

---

## 85. Rescue Phase R10 — Design Consistency Audit

For every route check:

```text
Typography
Spacing
Borders
Shadow
Color
Button hierarchy
Empty state
Loading
Error
Focus
Mobile
Dark mode
```

Any deviation must be intentional.

---

## 86. Definition of UX Done

A page is NOT done because:

```text
component exists
```

It is done when:

```text
happy path works
+
error path works
+
empty works
+
loading works
+
offline works where relevant
+
keyboard works
+
mobile works
+
dark mode works
+
real API works
+
browser E2E passes
```

---

## 87. Visual Regression

Introduce visual regression for critical screens:

```text
Today
Task detail
Goals
Notes
Canvas shell
Analytics
```

Snapshots SHOULD be reviewed intentionally.

Do not accept every snapshot automatically.

---

## 88. Performance

Target:

```text
fast initial shell
fast navigation
no layout shift
no unnecessary re-render
```

Specific expectations:

```text
Today:
interactive quickly

Notes:
editor mount < acceptable human-perceived delay

Canvas:
editor loading visibly managed

Analytics:
lazy load heavy charts
```

---

## 89. Bundle strategy

Avoid loading everything on first page.

Split:

```text
Canvas
Analytics
Knowledge editor
```

by route where practical.

Excalidraw is especially suitable for lazy loading because Canvas is not needed on every screen.

---

## 90. Data safety UX

Users must always be able to understand:

```text
Where is my latest data?
```

Examples:

```text
Saved to server
Saved locally
Pending synchronization
Conflict requiring action
```

Never hide data-loss risk.

---

## 91. Privacy UX

Because Kinevo is a personal OS:

Do not expose sensitive note content in:

```text
analytics
logs
toast messages
server errors
AI status
browser console in production
```

---

## 92. Design Review Checklist

Before accepting any UI PR:

```text
[ ] follows design tokens
[ ] follows Neo-Brutalist language
[ ] primary action is obvious
[ ] no unnecessary card nesting
[ ] no color-only state
[ ] keyboard accessible
[ ] mobile usable
[ ] dark mode
[ ] reduced motion
[ ] loading
[ ] empty
[ ] error
[ ] offline where applicable
[ ] conflict where applicable
[ ] API-backed
[ ] no business authority in Vue
```

---

## 93. Anti-patterns

Never introduce:

```text
giant dashboard
```

```text
cardception
```

```text
modalception
```

```text
pill soup
```

```text
rainbow metrics
```

```text
AI magic button
```

```text
full-page spinner
```

```text
silent autosave failure
```

```text
silent schedule mutation
```

```text
silent conflict resolution
```

```text
canvas black screen
```

---

## 94. UX Information Hierarchy

Every screen should answer:

```text
WHERE AM I?
WHAT MATTERS?
WHAT CAN I DO?
WHAT HAPPENED?
WHAT DO I DO NEXT?
```

If the screen cannot answer these in seconds, simplify it.

---

## 95. Component Acceptance Model

Every shared component must have:

```text
Default
Hover
Focus
Active
Disabled
Loading
Error
Success
Dark
Light
Reduced motion
```

where semantically applicable.

---

## 96. UI Test Strategy

For every critical shared component:

```text
behavior test
accessibility test
visual regression
```

Do not depend only on snapshots.

---

## 97. Design documentation maintenance

When UI changes materially:

```text
design.md
 ↓
component
 ↓
tests
```

If a component behavior changes but design.md remains accurate:

```text
code only
```

Do not rewrite the design document unnecessarily.

---

## 98. Implementation order for redesign

The recommended redesign sequence is:

```text
R0 Freeze
 ↓
R1 Browser smoke
 ↓
R2 Bug taxonomy
 ↓
R3 Diagnostics
 ↓
R4 Design tokens
 ↓
R5 Shell redesign
 ↓
R6 Today redesign
 ↓
R7 Task / Goal redesign
 ↓
R8 Knowledge redesign
 ↓
R9 Canvas stabilization
 ↓
R10 Analytics redesign
 ↓
R11 Accessibility
 ↓
R12 Visual regression
 ↓
R13 Full E2E
 ↓
R14 Release candidate
```

---

## 99. The most important rescue rule

Do NOT attempt to "fix everything."

First make this loop beautiful and reliable:

```text
LOGIN
 ↓
TODAY
 ↓
NOW TASK
 ↓
START
 ↓
COMPLETE
 ↓
PROGRESS
 ↓
NEXT TASK
```

Then:

```text
GOAL
 ↓
MILESTONE
 ↓
TASK
 ↓
SCHEDULE
```

Then:

```text
NOTE
 ↓
CANVAS
 ↓
TASK
```

Then:

```text
AI
 ↓
PROPOSAL
 ↓
USER APPROVAL
```

---

## 100. Final Product Vision

Kinevo should visually feel like:

> **a physical personal command center.**

Not:

> another SaaS dashboard.

The design should communicate:

```text
I can see what matters.
I know what to do.
I know why it is scheduled.
I know whether my work is safe.
I can capture an idea immediately.
I can think visually.
I can recover when life interrupts.
I can work offline.
AI helps, but never controls me.
```

---

## 101. Final UX Contract

The complete Kinevo UI MUST satisfy:

```text
Clarity
+
Physicality
+
Explainability
+
Safety
+
Speed
+
Accessibility
+
Responsiveness
+
Consistency
```

Neo-Brutalism is the visual expression.

The underlying principle remains:

> **Reduce cognitive load while increasing perceived control.**

---

## 102. Final Acceptance Gate

The UI/UX system is considered production-ready only when:

```text
[x] Design tokens centralized                     (R2: tokens/ + design-tokens.md)
[x] All major routes redesigned consistently      (R3 surface work; surface-qa suite)
[x] Today feels production-ready                  (R1 core loop + R3 capacity/check-in)
[x] Task execution flow is production-ready       (R1 START/COMPLETE/PROGRESS/NEXT proofs)
[x] Goal/Milestone flow is production-ready       (R3 goal detail + roadmap; journey A)
[x] Notes flow is production-ready                (R3 §31 toolbar + autosave state; journey D)
[x] Canvas works in a real browser                (R4 canvas-hardening matrix)
[x] Canvas offline works in a real browser        (R7 journey E: offline draw → reconnect → restored)
[x] Canvas conflict works in a real browser       (R4 409 conflict banner + manual reconcile)
[x] Offline UX is understandable                  (§90 states + live regions; R7 SR smoke)
[x] AI proposals visibly separate from committed data
                                                  (proposals never auto-applied — AiProposalTest;
                                                   no AI mutation UI ships during freeze)
[x] Accessibility audit passes                    (R5 axe scans + keyboard; R7 canvas keyboard,
                                                   dark scans, SR smoke)
[x] Mobile audit passes                           (R7 375px overflow proofs after UI-012)
[x] Dark mode passes                              (R7 dark WCAG scans after UI-011)
[x] Reduced motion passes                         (R5 emulated prefers-reduced-motion)
[x] Visual regression baseline established        (R6 visual-baseline suite)
[x] Golden user journeys pass in real browsers    (A/B/D/E/C proven; F backend-only — recorded gap)
[x] No critical P0/P1 UX bugs                     (ui-audit findings all P2/P3, closed with evidence)
[x] No silent data-loss scenarios                 (F-R7-2 offline-reconnect loss fixed + proven;
                                                   conflicts surface, never auto-resolve)
[x] Release candidate build passes                (gates: e2e 124✓ unit 386✓ phpstan✓ tc/build/audit✓)
```

---

## 103. Final rescue deliverables

The stabilization effort produces:

```text
docs/design.md           (this document)
docs/design-tokens.md    (centralized token system)
docs/ui-audit.md         (living gap/P0-P3 audit)
docs/browser-e2e.md      (browser QA matrix + journey records)
```

and the task board phases:

```text
Phase R0 — Stabilization
Phase R1 — Browser Verification
Phase R2 — Design System
Phase R3 — UI Refinement
Phase R4 — Canvas Hardening
Phase R5 — Accessibility
Phase R6 — Visual Regression
Phase R7 — Release Readiness
```

Do not close the rescue phase based solely on automated unit test counts.

The final criterion is:

> A human user can complete the core Kinevo journey smoothly, understand the system state at every step, and trust that their data is safe.

## 104. Phase 17 — Product Cohesion & Intelligence

Rescue R0–R7 (this document §74–§103) closed 2026-08-22: foundations stabilized, browser-proven, accessible, release-gated. The next phase changes what the product *feels like*: individual features exist but must become one coherent operating system.

### Six diagnosed gaps

```text
1. Product cohesion gap      — Goals/Tasks/Calendar/Knowledge/Canvas/Analytics/AI
                               feel like separate apps, not one system
2. AI configuration gap      — no AI & Providers settings surface
3. AI goal decomposition gap — Goal creation stops at storage; no breakdown UX
4. UX cognition gap          — missing hierarchy/context/progression/feedback
5. Feedback/micro-interaction gap — system state changes are not felt
6. Feature discovery gap     — features are not explained in-product
```

None of these is solved by "making the UI prettier". The intended mental model:

```text
GOAL → AI/MANUAL BREAKDOWN → MILESTONE → PROGRAM → TASK → SCHEDULE → TODAY →
FOCUS → COMPLETE → PROGRESS → CAPACITY → ADAPTATION → NEXT ACTION
```

Every major screen must answer: where am I? why am I here? what matters? what can I do? what should I do next? what happened after I acted?

### Goals

```text
P17-A Product Information Architecture        (EXECUTE / PLAN / KNOWLEDGE /
                                                 REVIEW / SYSTEM groups)
P17-B End-to-End Workflow Cohesion            (contextual navigation everywhere)
P17-C AI Provider & AI Workflow UX            (settings + status, no hidden AI)
P17-D Goal → AI Breakdown → Milestone → Task  (proposal → approval → persist)
P17-E Contextual Feature Education            (in-product, once-dismissed)
P17-F Micro-interaction & Feedback System     (state feels alive)
P17-G Analytics / Decision Support UX         (charts drive action)
```

### Non-negotiable product rule

Navigation must reflect the user's mental workflow, not the backend module list. No dead-end pages: every entity surfaces its upstream/downstream objects.

### AI safety rule (unchanged, restated for the AI breakdown UX)

"Automatically break a goal down" means **automatically OFFER** the breakdown — never **silently mutate** the goal/milestones/tasks. AI output is untrusted input: schema → domain validation → user review/approval → change. Goal decomposition is the UX bridge between Goals and Scheduling; its proposal and acceptance MUST flow through the existing validated proposal contract (SRS FR-52, FR-61, FR-62). Never expose AI chain-of-thought; show decision summary, assumptions, inputs, constraints.

### AI Provider settings contract

`Settings → AI & Providers` exposes provider, connection status, model, base URL, API key (masked), test connection, enable/disable, and a privacy note. API key rules: never stored in browser storage; never returned raw to the client; encrypted server-side; masked after save; replace/remove only. Ollama shows "API key not required". AI status derives from ONE source of truth (GET /api/v1/ai/status) with states Disabled / Not Configured / Configured / Testing / Connected / Unavailable / Degraded; the UI must distinguish *configured* from *available*.

### Micro-interaction rules

Micro-interactions are feedback, not decoration. They answer: did my action work? what changed? what is available now? Required sequences:

```text
task complete  → checkbox snap → progress advance → activity toast → next task emphasis
save           → Saving… → Saved ✓
offline        → Offline → Queued → Syncing → Synced
AI generation  → Preparing context → Generating → Validating → Proposal ready
accepted breakdown → milestone/progress tree expands
```

Interaction language (existing tokens): rest 4px / hover 6px / pressed 2px offset shadow; tactile primary components; ≈100–250ms; reduced-motion intact.

### Golden journey (primary success criterion)

> I create a long-horizon goal, Kinevo immediately helps me break it down, I approve the milestones, I see what happens next, the tasks get scheduled, Today tells me what to do now, completing the work updates progress, and Analytics tells me the next adaptation.

```text
Login → Create goal → Kinevo offers AI breakdown → Generate → Review → Accept →
Milestones → Programs → Tasks → Schedule → Today → Start → Complete → Progress →
Analytics → Capacity → adaptation → next action
```

It MUST be executable in a real browser (Playwright chromium/firefox/webkit).

### Theme

Theme persistence/dark mode are considered UNVERIFIED until real-browser proof: light→reload→light, dark→reload→dark, system→OS switch→follows system, both desktop and mobile, authenticated and unauth shell, Excalidraw shell adapts, no flash of wrong theme where practical, keyboard accessible.

### Done criteria for P17

P17 is not done because screens exist; it is done when the product feels like one coherent workflow and the golden journey above runs in a real browser. Task board: Phase 17 in `TASK.md` (TASK-P17-001…038); product gate PRODUCT COHESION READY (TASK-P17-038).

### Appendix — Feature surface inventory (TASK-P17-022, UX contract)

One row per feature surface. This matrix IS the UX contract: any new surface
or changed action must update its row in the same change. Cells are
telegraphic on purpose. Legend: **✅** behavior browser-proven by the cited
spec under `tests/e2e/tests/`; **⚪** designed and implemented but not yet
individually browser-proven. Sources of record: `shell/navigation.ts`
(entry groups), `ui-audit.md` §3 CTA checklist (2026-08-23), P17-011
micro-interaction cascades, `analytics/interpretation.ts` signal mapping,
offline outbox (`offline/http-applier.ts`, entity-agnostic queue + LWW +
service worker).

| Surface | Purpose | Entry | Primary / Secondary | Explanation | Empty / Success / Failure | Offline | Analytics link | Downstream | Proven by |
| ------- | ------- | ----- | ------------------- | ----------- | ------------------------- | ------- | -------------- | ---------- | --------- |
| Today | Do the now-next task; capacity at a glance | Nav EXECUTE → Today (`nav-today`) | Start (state-driven) / Complete·Pause·Break | FeatureHelp block | empty: education block / success: checkbox snap → progress → toast → next-task spotlight / failure: error banner + retry | mutations queue with op UUID; Offline→Queued→Syncing→Synced announcements | capacity load bar feeds overload/imbalance signals | Tasks, Scheduling, Capacity, Adaptive Context, Next Action | core-loop ✅, feature-education ✅ |
| Week | Read-only week planner | Nav EXECUTE → Week | — / day navigation | FeatureHelp ⚪ | empty: planner grid persists / success: n/a (read-only) / failure: load banner ⚪ | cached read ⚪ | daily distribution context for days/pillars charts | Scheduling, Calendar | journeys.spec ✅ |
| Calendar (Hard Landscape) | Fixed commitments the scheduler must respect | Nav EXECUTE → Calendar | — / view controls | FeatureHelp ⚪ | empty: prompt to add landscape / success: block renders / failure: overlap flagged server-side (SRS) | read cache ⚪; create/edit queues ⚪ | excluded from flexible-capacity math | Scheduling (constraint input), Today | journeys.spec ✅ (view), overlap ⚪ |
| Goals / Roadmap list | Long-horizon intent + progress | Nav PLAN → Goals (`nav-goals`) | Create ⇄ Generate with AI → inline proposal review/edit/accept (P17-026, no page exit) / manual breakdown option | FeatureHelp block | empty: education + single primary Create / success: goal card + progress bar / failure: generation stages honest, server-truth errors | goal create/update queue ⚪ | goal-pressure signal drives Review-milestone action | Milestones, Programs, Tasks, AI breakdown | golden-journeys G/G2 ✅, ui-audit CTA ✅ |
| Goal detail | Milestone roadmap + program tree; accept AI proposal | Goal card → detail | Accept proposal / state action / inline milestone edit | ProposalReviewCard explains assumptions — decision summary, assumptions, inputs used, constraints honoured (P17-027, no chain-of-thought) | empty: no-proposal guidance / success: accepted breakdown expands tree / failure: validation errors surfaced pre-acceptance (AI untrusted path) | proposal accept online-required (material mutation) ⚪ | falling-behind milestones signal | Programs, Milestones, Tasks, Scheduling | journey-c-e ✅ |
| Tasks list | Backlog triage | Nav PLAN → Tasks (`nav-tasks`) | Add task / filter·status controls | FeatureHelp block | empty: education / success: row appears + Saved ✓ / failure: inline field errors | task CRUD queues (op UUID + LWW) ✅ | completion-rate source for Execution card | Task detail, Scheduling, Today | feature-education ✅, Journey E offline ✅ |
| Task detail | One task: edit, transition, run | Tasks row → detail | Status transition (per state) / edit Save, secondary transitions | Field help text | empty: n/a / success: transition cascade + toasts / failure: conflict → stable 409 UX ⚪ | queued transitions ⚪ | execution minutes feed days chart | ExecutionTimer/focus, Today, Programs | ui-audit CTA ✅, core-loop ✅ |
| Schedule draft | Generate/apply a deterministic plan | Nav PLAN → Schedule | Generate → Apply (staged, tactile) / Cancel, Dynamic Reschedule | Draft explainer | empty: generate prompt / success: slots persist + Applied ✓ / failure: constraint errors listed, draft never silently applied | apply online-required (transactional) ⚪ | scheduled-vs-capacity overload signal | Calendar constraints, Today, Tasks | ui-audit CTA ✅ (UI-013), journeys ✅ |
| Reschedule | Move work without breaking the plan | Schedule draft → Reschedule | Propose → Apply (staged) / Back, Cancel | Diff explainer ⚪ | empty: nothing-to-move / success: new slots persisted / failure: conflicts rejected with reasons | propose online ⚪ | rebalance affects imbalance signal | Scheduling, Calendar | ui-audit CTA ✅ |
| Quick Capture | Zero-friction inbox deposit anywhere | Global overlay from AppShell (keyboard) | Capture / dismiss | Hint placeholder | empty: n/a / success: item stored + toast / failure: queued when offline (quick_capture:create) | first-class queued op ✅ | captured items count toward workload later ⚪ | Tasks (triage target), Today | offline http-applier unit ✅, e2e ⚪ |
| Knowledge desk (Notes) | Reference knowledge + links | Nav KNOWLEDGE → Knowledge (`nav-knowledge`) | New note / toolbar actions | Desk toolbar explainer (§31) | empty: education / success: Saving…→Saved ✓ / failure: save flash ⚪ pending token pass | note edits queue ⚪ | notes linked to entities enrich context panels ⚪ | Links, Attachments, Canvas embeds | ui-audit CTA ✅, accessibility ✅ |
| Canvas | Free-form visual thinking | Nav KNOWLEDGE → Canvas (`nav-canvas`) | Excalidraw-owned tools / Kinevo shell chrome | External boundary notice (§104 rule) | empty: blank scene / success: scene autosaves ⚪ / failure: Excalidraw-owned | canvas ops queue ⚪ | none (deliberate boundary) | Notes (embeds), exports | canvas-hardening ✅, keyboard flow ✅ |
| Analytics | Read the truth; choose next adaptation | Nav REVIEW → Analytics (`nav-analytics`) | — (read-only) / range·pillar filters, per-section actions | Executive-signal-first hierarchy; ChartMeta on every chart | empty: education block / success: signal → interpretation → action / failure: per-section error rows | read-only; safe on flaky network | IS the analytics surface: overload, imbalance, completion, goal pressure → routed actions | Every producer surface; routes into Goals/Today/Schedule | analytics-hierarchy ✅, journey-j ✅ |
| Adaptive Context check-in | Daily energy/context calibration | Today panel | Check-in submit / skip | Panel microcopy | empty: gentle prompt / success: Saved ✓ + capacity adapts / failure: retry keeps answers ⚪ | check-in queues ⚪ | recalibrates capacity + recharge advice | Capacity, Recovery, Today | ui-audit ✅ (save cascade) |
| Recovery (recharge blocks) | Protect rest inside the plan | Today imbalance action / schedule slots | Plan a recharge block / adjust | Why-rest explainer ⚪ | empty: suggestion only / success: block appears in plan / failure: slot-conflict error ⚪ | queued like tasks ⚪ | imbalance signal origin | Scheduling, Today, Capacity | journey-j action route ✅ |
| AI & Providers settings | Own the AI runtime; configured ≠ available | Nav SYSTEM → AI & Providers (`nav-ai-settings`) | Save provider / Test connection, enable·disable | Privacy note; masked key rules (§104 contract) | empty: setup guidance / success: status chip Configured→Connected / failure: test-connection error surfaced verbatim-safe | settings require online (secrets never client-stored) ✅ | ai availability gates breakdown suggestions | Goal breakdown, proposal review | golden-journeys H ✅ |
| Settings | Account, theme, data portability | Nav SYSTEM → Settings (`nav-settings`) | Save changes / theme Light·Dark·System, imports·exports | Section help text | empty: n/a / success: Saving…→Saved ✓ / failure: conflict/save-state shown ✅ | theme local-first; imports/exports online | n/a | Auth profile, Imports/Exports, Theme shell | golden-journeys H ✅, theme.spec ✅ |
| Notification center | What changed while away | Topbar bell (`notifications-center`) | Open panel / mark-read, group filters Unread·Today·Earlier | Group labels self-explanatory | empty: "You're all caught up" / success: unread badge clears / failure: fetch error banner ⚪ | badge from last sync ⚪ | activity events mirror progress/analytics events | Activity log, Today spotlight | ui-audit R3 ✅ |

Rules of maintenance:

1. New surface ⇒ new row before merge (same PR).
2. A row's Primary must obey §51/§2.3 ONE-primary-per-state.
3. State cells name the FEEDBACK (§ micro-interaction rules), not just the outcome.
4. Offline cell cites the queue op when one exists; "online-required" must
   say why (material transaction / secrets).
5. Proven-by links rot → re-run the named spec when touching the row.