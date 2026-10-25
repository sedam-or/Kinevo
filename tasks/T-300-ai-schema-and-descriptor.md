---
id: T-300
title: Create the ai module: migration, descriptor and the breakdown validation core
milestone: M6
kind: feature
status: todo
depends_on: [T-256]
requirements: [AI-FR-03, AI-FR-04, AI-FR-05, AI-FR-09, DOM-20, D-44, D-31, SEC-AC-01, SEC-AC-31, SEC-AC-32]
branch: m6-t300-ai-schema-and-descriptor
budget_lines: 400
assignee: ""
---

# T-300: Create the ai module: migration, descriptor and the breakdown validation core

## Goal

The ai crate owns its three tables, and untrusted model output is turned into a validated, sanitised proposal payload or a precise rejection by pure functions.

## Context

> **Module** (`spec/modules/ai.md`): kind **toggleable**, default **off**; depends on `goals` and `tasks`; tables `ai_account_providers`, `ai_proposals`, `ai_runs`; permissions `ai.status.read`, `ai.breakdown.create`, `ai.proposal.read|update|accept|reject`, `ai.provider.manage` for `account_owner` and `instance_admin`. Principle (D-31): AI proposes, the user decides; output is untrusted input; AI never changes the schedule and never calls tools.
> **DDL** (`06-domain-model.md` §9): `ai_account_providers(account_id PK, kind in (openai_compatible, ollama), base_url, model, updated_at, version)` (the API key is the account secret `ai.api_key` via `SecretStore`, no FK); `ai_proposals(id, account_id, kind in (goal_breakdown), goal_id → goals CASCADE, status in (pending, accepted, rejected, expired) as proposal_status_ck, payload jsonb, provider_kind, model, created_at, decided_at, version)`; `ai_runs(id, account_id, proposal_id → ai_proposals SET NULL, provider_kind, model, status in (succeeded, provider_error, invalid_output, timeout, blocked), error_code, input_chars, output_chars, latency_ms, created_at)` — **no prompt or output text** (AI-FR-09). Standard RLS. Migration `crates/kinevo-mod-ai/migrations/20270401000000_ai_ai.sql`.
> **Output schema** `ai_breakdown_v1` (§4): `{schema, milestones:[{title 1..200, description? ≤1000, target_date?, tasks:[{title 1..200, estimate_minutes? 5..480, priority_tier? 1..3, subtasks? [str 1..200] (≤ 8)}] (≤ ai.breakdown.max_tasks_per_milestone)}] (1 ≤ n ≤ ai.breakdown.max_milestones)}`; unknown fields rejected.
> **AI-FR-03** the response must be one JSON object; a surrounding Markdown code fence is stripped; anything else is invalid; size ≤ 64 KiB; nesting depth bounded (SEC-AC-31). **AI-FR-04** schema validation with policy bounds. **AI-FR-05** domain validation: every `target_date` ≥ today and ≤ the goal's `target_date` (when set); milestone dates non-decreasing in order; no duplicate milestone titles (case-insensitive) and none equal to an existing milestone title; text is NFC, control characters removed, HTML tags kept as literal text. Violations → `ai_output_invalid` with `details.reasons[]` codes only (never the raw output).
> **Constraint mapping**: none expected beyond RLS/FKs; `ai_proposals` has no unique constraint.

## Scope

**In:**
- Crate skeleton, descriptor (toggleable, default off, `requires: ["goals","tasks"]`; emits `AiProposalCreated`, `AiProposalAccepted`, `AiProposalRejected`), migration, grants, `domain/{parse,schema,validate,sanitize}.rs` with `parse_output(bytes) -> Result<Breakdown, Vec<Reason>>` and `validate_domain(&Breakdown, &GoalFacts, today) -> Result<(), Vec<Reason>>`, wiring: Register the crate in the workspace `Cargo.toml`, as a dependency of `kinevo-app`, and in the composition root list (`kinevo-app/src/modules.rs`), in dependency order; the architecture test of T-042 must stay green.

**Out:**
- Providers: T-301. Use cases and endpoints: T-302.

## Files

| Path | Action | Notes |
|---|---|---|
| `crates/kinevo-mod-ai/**` | create | skeleton |
| `crates/kinevo-mod-ai/migrations/20270401000000_ai_ai.sql` | create |  |
| `crates/kinevo-mod-ai/tests/{isolation,descriptor,parse,validate,sanitize}.rs` | create | AC-1…AC-10 |
| `Cargo.toml, crates/kinevo-app/**` | modify | wiring |

## Interfaces

```rust
pub fn parse_output(raw: &[u8], bounds: &Bounds) -> Result<Breakdown, Vec<Reason>>;   // Reason is a closed enum of codes
pub fn validate_domain(b: &Breakdown, goal: &GoalFacts, today: Date) -> Result<(), Vec<Reason>>;
pub fn sanitize_text(s: &str) -> String;                                               // NFC, strip control characters, keep tags literal
```

## Business rules

1. Reasons are codes such as `not_json`, `too_large`, `too_deep`, `unknown_field`, `title_length`, `date_in_past`, `date_after_goal`, `dates_not_ordered`, `duplicate_title`, `existing_title`; never raw output.
2. Sanitising never removes visible characters other than control characters.
3. The functions are pure and total.

## Security

- `run_isolation` covers the three tables.
- A 5 MB or deeply nested input is refused before full parsing (SEC-AC-31).
- `<script>` in a title is stored as literal text (SEC-AC-32).

## Implementation notes

- Use `serde_json` with a depth limit via a pre-scan or `serde_stacker`-free manual limit; test a 10 000-deep array.
- Unicode NFC via `unicode-normalization` (add to the stack map in the same PR).

## Acceptance

| # | Given / When / Then | Test name |
|---|---|---|
| AC-1 | **Given** a migrated database **When** compared with the DDL **Then** equal | `isolation::dom_ai_tables_match_ddl` |
| AC-2 | **Given** two accounts **When** `run_isolation` runs **Then** clean for the three tables | `isolation::sec_ac_01_ai_isolated` |
| AC-3 | **Given** a fenced JSON answer **When** parsed **Then** the fence is stripped; prose around JSON is invalid | `parse::ai_fr_03_fence_and_prose` |
| AC-4 | **Given** 5 MB or 10 000-deep JSON **When** parsed **Then** `too_large`/`too_deep` without exhausting memory | `parse::sec_ac_31_bombs_refused` |
| AC-5 | **Given** unknown fields or out-of-bound lengths **When** parsed **Then** the matching reason codes | `parse::ai_fr_04_schema_bounds` |
| AC-6 | **Given** dates in the past, after the goal, or decreasing **When** validated **Then** the reason codes | `validate::ai_fr_05_date_rules` |
| AC-7 | **Given** duplicate or existing milestone titles (case-insensitive) **When** validated **Then** the reason codes | `validate::ai_fr_05_title_rules` |
| AC-8 | **Given** a title with `<script>` and control characters **When** sanitised **Then** tags stay literal and controls are gone | `sanitize::sec_ac_32_literal_text` |
| AC-9 | **Given** an injection string in the hint **When** the model answer is not schema-valid **Then** `ai_output_invalid` | `parse::ai_fr_02_injection_string_still_validated` |
| AC-10 | **Given** the descriptor **When** validated **Then** toggleable, default off, right dependencies | `descriptor::mod_08_ai_descriptor_valid` |

## Tests

```
cargo nextest run -p kinevo-mod-ai --features kinevo-db/testing   # DB tests environment-limited without Docker/PostgreSQL
just check
```

## Definition of done

- [ ] Every acceptance criterion has its named test, and it passes (or is marked environment-limited with the exact command not run).
- [ ] `just check` passes locally.
- [ ] Only the files listed under *Files* changed; diff within budget.
- [ ] Report below is filled in.

## Report

_Filled in by the implementer (WORKFLOW.md §4)._

## Questions

_None yet._
