# Kinevo — Scheduling Engine Contract

### Mission
Generate safe, explainable, deterministic schedules and reschedule proposals while preserving user-controlled constraints.

### Scheduler modes
- `AUTO_DRAFT`: generate draft only.
- `RESCHEDULE_PROPOSAL`: generate diff, wait for confirmation.
- `MANUAL_ASSIST`: answer slot/capacity queries.
- `SIMULATION`: no mutation; used in tests/analysis.

### Core algorithm
```text
1. Acquire scheduler run lock.
2. Snapshot relevant state.
3. Normalize timezone and temporal intervals.
4. Build Hard Landscape intervals.
5. Build occupied intervals.
6. Calculate Dynamic Empty Slots.
7. Place Sacred Anchor.
8. Build task candidate set.
9. Apply hard constraints.
10. Rank candidates.
11. Score soft signals.
12. Assign candidate tasks.
13. Validate schedule.
14. Calculate overload/capacity.
15. Produce explanations.
16. Persist draft/version.
17. Release lock.
```

### Hard constraint ordering
1. No Hard Landscape collision.
2. Do not move locked task through automation.
3. Sacred Anchor rule.
4. Temporal validity.
5. Deadline feasibility.
6. Valid duration and slot fit.
7. No illegal overlap.
8. Safety reserve.
9. No automatic task deletion.

### Soft ranking
Recommended lexicographic ordering:
1. priority tier;
2. nearest goal deadline;
3. milestone urgency;
4. task deadline;
5. progress leverage;
6. context fit;
7. fragmentation penalty;
8. duration fit;
9. continuity preference.

### Soft scoring examples
Do not make one giant opaque score. Prefer independently testable components:
- `priority_score`;
- `deadline_score`;
- `milestone_score`;
- `capacity_fit_score`;
- `context_fit_score`;
- `progress_value_score`;
- `fragmentation_penalty`.

The final candidate ordering MAY combine them, but each component MUST remain observable in explanation/debug output.

### Explainability contract
Each generated assignment SHOULD include:
- candidate reason;
- accepted constraints;
- rejected alternatives where useful;
- primary priority;
- deadline pressure;
- capacity context;
- soft context signal used.

Example:
```json
{
  "decision": "ASSIGN",
  "task_id": "uuid",
  "slot": "2026-08-19T09:00:00+07:00/2026-08-19T09:45:00+07:00",
  "reason_codes": [
    "DEADLINE_NEAR",
    "SLOT_FIT_EXACT",
    "HIGH_PRIORITY"
  ]
}
```

### Dynamic Empty Slot contract
A Dynamic Empty Slot is an interval `[start,end)` with duration >= 15 minutes and free from prohibited overlap.

### Schedule versioning
Every meaningful schedule mutation MUST have a schedule version. Applying a stale schedule proposal MUST return `409 SCHEDULE_VERSION_CONFLICT`.

### Draft versus applied schedule
Auto-scheduler output is not authoritative until explicit application when the mode requires confirmation.

### Failure behavior
If no valid solution exists:
- do not delete task;
- retain task;
- create conflict marker;
- return actionable alternatives.

### Simulation test matrix
At minimum test:
- zero hard landscape;
- adjacent hard landscape blocks;
- gap exactly 15 minutes;
- gap 14 minutes;
- Sacred Anchor placement;
- locked task;
- multiple equal-priority tasks;
- deadline tie;
- no available solution;
- Emergency Pause;
- Break Mode;
- low effective capacity;
- concurrent mutation.

---

