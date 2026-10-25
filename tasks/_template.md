---
id: T-000
title: Short imperative title
milestone: M1
kind: feature            # feature | integration
status: draft            # draft | todo | in_progress | review | changes_requested | blocked | done
depends_on: []           # task ids, e.g. [T-001, T-002]
requirements: []         # spec ids this task implements or tests, e.g. [ARCH-22, SEC-AC-01]
branch: m1-t000-short-slug
budget_lines: 400        # expected changed lines (code + tests); > 1.5x means block (WORKFLOW.md §3)
assignee: ""
---

# T-000: Short imperative title

## Goal

One or two sentences: what observable capability exists when this task is done.

## Context

Why this task exists and where it sits in the milestone. Inline every spec excerpt the implementer needs, quoted and labelled with its id and file, so the task can be done without hunting. The spec stays authoritative: if an excerpt differs from the spec, block.

> **ID** (`spec/file.md` §n): quoted text

## Scope

**In:**
- …

**Out (do not do):**
- …

## Files

| Path | Action | Notes |
|---|---|---|
| `path/to/file` | create / modify | what goes in it |

Touch no other file. Needing one means blocking (WORKFLOW.md §3).

## Interfaces

Exact public signatures, types, endpoints, SQL objects or CLI commands this task creates or consumes. Code blocks are shapes, not finished code.

## Business rules

Numbered rules, each traceable to a spec id. Values come from policies or registries, never literals (CQ-05).

## Security

Data classes touched (S0–S3), threats, and required controls with ids (SEC-…, SEC-AC-…). Write "none beyond the standards" only when that is true.

## Implementation notes

Ordering hints, pitfalls, library calls to use, things that look tempting but are wrong.

## Acceptance

Each criterion maps to exactly one named test.

| # | Given / When / Then | Test name |
|---|---|---|
| AC-1 | **Given** … **When** … **Then** … | `crate::module::tests::name` |

## Tests

Commands to run, and what each proves. Mark checks that need tools an agent may not have as `environment-limited`.

```
just check
```

## Definition of done

- [ ] Every acceptance criterion has its named test, and it passes.
- [ ] `just check` passes locally (before M1 creates it: the scripts in `AGENTS.md`).
- [ ] Only the files listed under *Files* changed; diff within budget.
- [ ] No new dependency without a stack-map entry (CQ-16); no business literal (CQ-05); no secret, PII or content in logs (SEC-04).
- [ ] `// SPEC: <ID>` marks the code implementing each requirement (CQ-15).
- [ ] Report below is filled in.

## Report

_Filled in by the implementer (WORKFLOW.md §4)._

## Questions

_None yet. Blocking questions go here (WORKFLOW.md §3)._
