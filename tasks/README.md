# Tasks

Each file `T-nnn-<slug>.md` is one unit of work for an implementer: about 400 changed lines, self-contained, with inlined spec excerpts. How to work on a task: [`WORKFLOW.md`](../WORKFLOW.md). Template: [`_template.md`](_template.md). Generated overview: [`index.md`](index.md). Never edit the overview by hand.

## Rules

- **Ids are stable.** A task id is never reused or renumbered. A dropped task stays with `status: done` and a note in its *Report* saying it was withdrawn and why.
- **Strict DAG.** `depends_on` only points to tasks of the same or an earlier milestone, and the graph has no cycles.
- **One integration task per milestone** (`kind: integration`). It depends, directly or transitively, on every other task of its milestone and runs the milestone's user journey end to end.
- **Status gate.** Only the next milestones' tasks are `todo`; later ones are `draft`. A `draft` task may be an outline: the architect completes it before promoting it to `todo` (Phase 7 review loop), after the previous milestone's integration task is `done`.
- **Requirement ids** in the front matter must exist in `spec/`.

## Checks

```
python3 scripts/tasks_index.py           # regenerate tasks/index.md
python3 scripts/tasks_index.py --check   # validate tasks and fail if index.md is stale
```

`--check` validates: front-matter fields and values, id/filename match, dependency existence and order, no cycles, required sections in non-draft tasks, acceptance rows that name a test, requirement ids that exist in `spec/`, `todo` tasks whose dependencies are not `draft`, exactly one integration task per milestone covering all others, and an up-to-date `index.md`.

## Numbering

| Range | Milestone |
|---|---|
| T-001 – T-099 | M1 Foundation |
| T-100 – T-149 | M2 Planning core |
| T-150 – T-199 | M3 Reality & scheduling |
| T-200 – T-249 | M4 Execution & progress |
| T-250 – T-299 | M5 Offline |
| T-300 – T-349 | M6 AI breakdown |
| T-350 – T-399 | M7 Knowledge |
| T-400 – T-449 | M8 Canvas |
| T-450 – T-499 | M9 Cloud |
| T-500 – T-549 | M10 Android |
| T-550 – T-599 | M11 Hardening → 1.0 |
