# Kinevo — UI/UX Design System & Interaction Specification

### Purpose
`design.md` adalah implementable UX/UI specification. Ia menerjemahkan requirements menjadi information architecture, interaction patterns, component behavior, accessibility, responsive behavior, visual hierarchy, states, empty states, error handling, motion, and usability rules. Dokumen ini tidak boleh mengubah business rules dari SRS.

### Design philosophy
Kinevo harus membuat planning dan execution terasa ringan. UI harus:
- mengurangi cognitive load;
- memperjelas “what now?”;
- menjaga context;
- menunjukkan meaningful progress;
- membuat automation explainable;
- tidak mengubah productivity dashboard menjadi sumber distraction.

### Core UX principles
#### 1. Today-first
Today adalah primary execution surface. User harus dapat memahami current context dalam beberapa detik.

#### 2. Progressive disclosure
Detail tidak ditampilkan semuanya sekaligus. Ringkas dulu, detail saat diminta.

#### 3. One primary decision per viewport
Setiap primary screen harus mempunyai satu dominant intent.

#### 4. Human override
Automation selalu menyediakan explainability dan manual override untuk aksi penting.

#### 5. No destructive surprise
Reschedule, Emergency Pause, delete, lifecycle transition, dan conflict resolution harus jelas sebelum commit.

#### 6. State visibility
`locked`, `conflict`, `overdue`, `offline`, `syncing`, `queued`, `draft`, dan `proposed` harus terlihat tanpa membutuhkan developer knowledge.

#### 7. Keyboard parity
Setiap drag-and-drop interaction yang penting harus punya keyboard alternative.

#### 8. Color is never the only signal
Gunakan icon, text, label, border, pattern, atau semantic state selain warna.

### Information architecture
```text
App Shell
├── Today
├── Week
├── Calendar
├── Goals / Roadmap
│   ├── Goals
│   ├── Milestones
│   └── Programs
├── Knowledge
│   ├── Notes
│   ├── Canvas
│   └── Search
├── Analytics
├── Input & Sync
├── Break Mode
└── Settings
```

### App shell
Shell terdiri dari:
- primary navigation;
- contextual secondary navigation;
- content surface;
- command/quick action access;
- sync/status indicator;
- notification center;
- responsive mobile navigation.

Desktop SHOULD use a persistent side navigation where space permits. Mobile SHOULD use bottom navigation or compact navigation pattern with Today as the primary entry.

### Global UI states
Every data surface MUST support:
- initial loading;
- skeleton loading;
- empty state;
- populated state;
- stale cached state;
- offline state;
- syncing state;
- partial failure;
- authorization failure;
- retry state;
- conflict state.

### Today screen
#### Primary objective
Help the user execute the next appropriate unit of work, not browse all life data.

#### Recommended layout
```text
┌─────────────────────────────────────────────┐
│ Header / Date / Sync                         │
├─────────────────────────────────────────────┤
│ Recovery / Important notice                  │
├─────────────────────────────────────────────┤
│ NOW                                          │
│ Current task / timer / context               │
├─────────────────────────────────────────────┤
│ NEXT                                         │
│ Next scheduled item                          │
├─────────────────────────────────────────────┤
│ TIMELINE                                     │
│ 06:00 ─────── events / slots ───── 24:00   │
├─────────────────────────────────────────────┤
│ Quick Capture / Today actions                │
└─────────────────────────────────────────────┘
```

#### NOW card
Must show:
- task title;
- program/goal context where available;
- duration;
- remaining time if timer active;
- difficulty/context hints when useful;
- lock/conflict state;
- completion action;
- notes shortcut;
- canvas shortcut when linked.

Do not show analytics or long secondary metadata inside the NOW card.

### Quick Capture
Quick Capture SHOULD be globally available. Default fields:
- title;
- priority;
- duration;
- optional program;
- optional goal/milestone;
- optional due date.

If no capacity exists, show exactly three primary strategies in the order defined by SRS:
1. Manual Swap.
2. Auto Swap.
3. Schedule Later.

The task MUST NOT disappear.

### Slot visualization
Use a consistent temporal grammar:
- hard landscape = hard boundary;
- task = executable unit;
- recharge = recovery context;
- empty slot = invitation/availability;
- conflict = explicit warning state;
- locked = automation-protected state.

Avoid excessive vertical lines and tiny labels. Use tooltips/details on demand.

### Week screen
Primary intent: workload awareness and weekly planning.

Required visual hierarchy:
1. overload/status summary;
2. deadline board;
3. day columns;
4. workload and capacity indicators;
5. 4-pillar summary.

A week with overload MUST explain where overload originates.

### Goal workspace
Goal workspace is a strategic surface, not a task list.

Recommended order:
1. goal title/outcome;
2. deadline and horizon;
3. progress;
4. milestone sequence;
5. programs;
6. linked knowledge;
7. workload/capacity impact;
8. activity/progress history.

### Goal detail pattern
```text
GOAL
Outcome
Deadline
Progress

MILESTONES
✓ Completed milestone
● Current milestone
○ Future milestone

WORKSTREAMS
Program A
Program B

KNOWLEDGE
Notes
Canvas
References

NEXT ACTIONS
Top executable tasks
```

### Milestone interaction
Each milestone MUST expose:
- status;
- target date;
- workload estimate;
- progress;
- dependency state;
- linked program/task counts;
- knowledge references.

The current milestone should be visually emphasized without creating anxiety-heavy styling.

### Roadmap visualization
Use a combination of:
- milestone timeline;
- date markers;
- progress segments;
- dependency indicators.

Avoid full enterprise Gantt complexity for MVP unless a requirement explicitly requires it.

### Task detail drawer/page
Sections:
1. title/status;
2. schedule;
3. context;
4. subtasks;
5. notes;
6. evidence/attachments;
7. activity history;
8. linked goal/milestone/program;
9. scheduling explanation.

### Task states
Visual states:
- backlog;
- scheduled;
- in progress;
- partial;
- continued;
- completed;
- skipped;
- missed;
- conflict.

The UI MUST avoid using color alone to distinguish them.

### Lock interaction
Lock icon should have a tooltip such as:
> “Protected from automatic scheduling. You can still move it manually.”

### Rescheduler diff UI
Never show a vague “Schedule updated.”

Show an explicit diff:
```text
BEFORE
Wed 14:00 — Task A

AFTER
Thu 09:00 — Task A

Reason
Wed slot became unavailable because of Hard Landscape change.
```

Actions:
- Apply;
- Cancel;
- Inspect details.

### Conflict UI
A conflict surface MUST explain:
- what conflicts;
- why it conflicts;
- which rules prevent automatic resolution;
- available manual options.

### Recovery UI
Morning Recovery SHOULD be concise:
```text
3 items missed yesterday

[Task A]   → Reschedule
[Task B]   → Complete
[Task C]   → Keep in backlog
```

Avoid guilt framing.

### Recharge UX
Recharge is a valid product state. The UI must never visually imply failure when the user starts Recharge/Mini Pause/Emergency Pause.

### Analytics UX
Analytics are for reflection, not constant interruption.

Recommended hierarchy:
- current period summary;
- progress versus target;
- capacity trend;
- pillar distribution;
- heatmap;
- detail on demand.

### Meaningful progress
Show progress events as meaningful changes rather than only numeric XP.

Examples:
- milestone advanced;
- experiment completed;
- note/research evidence added;
- chapter completed;
- goal breakdown accepted.

### Cognitive-load safeguards
The UI MUST:
- avoid presenting every available metric at once;
- limit primary action count;
- use grouping and chunking;
- preserve stable spatial layout;
- provide sensible defaults;
- minimize repeated decisions;
- avoid unnecessary animations;
- use progressive disclosure for advanced controls.

### Focus mode
Focus mode SHOULD reduce the interface to:
```text
Task title
Context
Timer
Minimal controls
Exit
```

The full dashboard should not compete with the current task.

### Adaptive focus block UI
When a task has a recommended focus duration, show it as a recommendation, not an order.

Example:
> “Recommended focus: 45 min based on your recent completion patterns.”

Avoid claims like “scientifically optimal.”

### Notes UX
Notes should support:
- rich text;
- semantic links;
- task/goal embeds;
- search;
- attachments;
- autosave status;
- version conflict state.

Autosave indicator states:
- Saved;
- Saving…;
- Offline — queued;
- Conflict — review required;
- Error — retry.

### Tiptap editor UX
The editor SHOULD have:
- slash command or command palette;
- keyboard shortcuts;
- headings;
- lists;
- links;
- code block;
- quote;
- task/checklist block;
- contextual entity embed.

Do not implement every Tiptap extension by default. Add extensions only when tied to requirements.

### Canvas UX
Canvas is a visual thinking surface.

Entry points:
- Goal → Canvas;
- Milestone → Canvas;
- Program → Canvas;
- Task → Canvas;
- Note → embedded canvas.

Canvas chrome SHOULD be minimal and expose Kinevo context at the edges rather than modifying Excalidraw’s core semantics.

### Canvas save states
Display:
- Saved;
- Saving;
- Offline;
- Syncing;
- Conflict;
- Failed.

### Canvas context panel
A Kinevo side panel MAY expose:
- linked goal;
- milestone;
- program;
- related notes;
- related tasks;
- AI actions.

This panel belongs to Kinevo, not to Excalidraw core.

### AI UX
AI actions MUST be framed as proposals.

Recommended labels:
- Suggest breakdown;
- Summarize note;
- Extract tasks;
- Create canvas draft;
- Explain schedule;
- Find related knowledge.

Avoid:
- “AI decided”; 
- “Optimal”; 
- “Guaranteed.”

### AI preview
Before persisting non-trivial AI suggestions, show:
- proposed changes;
- affected entities;
- reasoning summary / source context where appropriate;
- Accept;
- Edit;
- Reject.

### Accessibility
Requirements:
- keyboard navigation;
- visible focus states;
- semantic landmarks;
- accessible names for controls;
- proper form errors;
- reduced motion support;
- minimum usable contrast;
- non-color-only status encoding;
- keyboard equivalent for drag actions;
- dialogs trap focus appropriately;
- screen-reader labels for timers and state changes.

### Responsive behavior
Desktop-first for dense planning surfaces, but Today MUST remain useful at mobile width.

Breakpoint behavior MUST be documented in implementation rather than relying on accidental CSS wrapping.

### Interaction density
- Primary actions: high clarity.
- Secondary actions: available but visually quieter.
- Destructive actions: separated and confirmed.
- Advanced system controls: nested/settings level.

### Motion
Motion exists to communicate state, not decorate.

Use:
- subtle transitions for context changes;
- progress transitions;
- drawer/dialog transitions.

Avoid:
- constant background animation;
- excessive bouncing;
- attention-seeking gamification.

### Empty states
Every empty state MUST answer:
1. what is empty;
2. why it may be empty;
3. what the user can do next.

### Error states
Every user-facing error SHOULD include:
- human-readable explanation;
- safe next action;
- retry where applicable;
- operation ID only where reporting is needed.

### Offline UI
Offline indicator should be subtle but persistent enough to prevent confusion.

Example:
> Offline — changes are saved locally and will sync when connection returns.

### Visual design tokens
Define tokens for:
- spacing;
- typography scale;
- radii;
- elevations;
- semantic status colors;
- focus rings;
- motion durations;
- content widths.

Do not hardcode visual values separately in every component.

### Component architecture
Prefer:
```text
Primitives
↓
Patterns
↓
Domain Components
↓
Screen Components
```

Examples:
- Button → ActionGroup → TaskActions → TodayScreen.
- Badge → StatusIndicator → TaskStateBadge → TaskCard.
- Modal → ConfirmationDialog → EmergencyPauseDialog.

### UX acceptance checklist
Every screen MUST be reviewed for:
- primary intent;
- empty state;
- loading state;
- error state;
- offline state where applicable;
- keyboard behavior;
- responsive behavior;
- accessibility;
- destructive action confirmation;
- information density.

---

