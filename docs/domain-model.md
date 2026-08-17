# LIFESYNC OS — Domain Model

### Core principle
Business concepts must be represented explicitly. Eloquent is a persistence mechanism, not a substitute for domain semantics.

### Goal
A Goal represents an intended outcome with a time horizon and measurable completion semantics.

Core properties:
- title;
- description;
- horizon (`yearly|quarterly|monthly|custom`);
- start date;
- target date/deadline;
- target metric where applicable;
- status;
- progress mode;
- derived progress;
- user ownership.

### Milestone
A Milestone represents an intermediate outcome proving progress toward a Goal.

Properties:
- goal;
- title;
- sequence;
- target date;
- estimate;
- dependencies;
- status;
- progress.

Invariants:
- milestone belongs to exactly one goal;
- milestone cannot be marked complete if mandatory dependencies remain unresolved unless policy explicitly permits override;
- progress cannot exceed configured bounds.

### Program
A Program is a sustained workstream contributing to one or more goals.

### Contribution
Program-to-goal relationship with weighted contribution and explicit normalization policy.

### Task
Atomic executable work unit.

Task hierarchy MUST stop at Subtask. No recursive subtask-of-subtask.

### Subtask
A checklist child of exactly one Task.

### Assignment
A concrete placement of a Task on a date/time range.

### Hard Landscape Event
External/non-negotiable schedule boundary.

### Schedule Override
Modification/exception to recurring Hard Landscape without destroying source history.

### Note
A knowledge artifact containing structured rich text.

### Knowledge Link
A typed relationship between a knowledge artifact and another domain entity.

### Canvas
Visual knowledge/ideation artifact containing Excalidraw-compatible scene data.

### Progress Event
Immutable or append-only event representing meaningful progress.

### Context Observation
User/context signal such as energy, stress, task difficulty, familiarity, interruptions, or focus duration.

### State machines
Task:
```text
Backlog → Scheduled → InProgress → Completed
                        ├→ Partial → Continued → Scheduled
                        ├→ Skipped
                        ├→ Missed → Backlog
                        └→ Conflict ↔ Scheduled
```

Program:
```text
Active ↔ Paused
Active → Completed
Active → Dropped
Paused → Completed
Paused → Dropped
```

### Goal state
Recommended:
```text
Draft → Active → AtRisk → Completed
                  └→ Archived
```

### Milestone state
```text
Planned → Active → Completed
             └→ AtRisk
```

### Knowledge invariants
- every note/canvas is owned by a user;
- links cannot cross ownership boundaries;
- deleted domain objects do not leave invalid live links without a defined tombstone/purge policy.

### Canvas invariants
- current version is authoritative;
- version increments monotonically;
- stale update returns conflict;
- binary files are referenced separately from scene JSON.

### Value objects
Recommended:
- `TimeRange`;
- `DurationMinutes`;
- `Deadline`;
- `GoalHorizon`;
- `PriorityTier`;
- `ScheduleVersion`;
- `OperationId`;
- `CapacityMinutes`;
- `EnergyLevel`;
- `StressLevel`.

### Domain services
- `GoalBreakdownService`;
- `MilestoneProgressService`;
- `SlotCalculator`;
- `ScheduleGenerator`;
- `ScheduleValidator`;
- `ConflictResolver`;
- `CapacityCalculator`;
- `ProgressEventService`;
- `KnowledgeLinkService`;
- `CanvasPersistenceService`;
- `OfflineReconciliationService`;
- `AIProposalValidator`.

---

