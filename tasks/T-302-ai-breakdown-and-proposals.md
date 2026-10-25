---
id: T-302
title: Implement breakdown preview and generation, proposal editing, accept and reject, limits and jobs
milestone: M6
kind: feature
status: todo
depends_on: [T-301]
requirements: [AI-FR-01, AI-FR-02, AI-FR-06, AI-FR-07, AI-FR-08, AI-FR-12, AI-FR-13, SEC-AC-33, TST-12, TST-19, CA-10]
branch: m6-t302-ai-breakdown-and-proposals
budget_lines: 420
assignee: ""
---

# T-302: Implement breakdown preview and generation, proposal editing, accept and reject, limits and jobs

## Goal

A user can ask for a breakdown of a goal, see exactly what is sent, review and edit the proposal, and accept it into real milestones, tasks and subtasks in one transaction.

## Context

> **Endpoints** (`spec/modules/ai.md` §3): `POST /ai/goals/{goal_id}/breakdown:preview {hint?}` (200 `{user_prompt_preview, char_count, data_fields[]}` — exactly the user part sent); `POST /ai/goals/{goal_id}/breakdown {hint?, max_milestones?}` + `Idempotency-Key` (201 proposal; `ai_not_configured` 409, `ai_limit_reached` 429 with `limit`,`window`, `rate_limited`, `ai_provider_unavailable` 503, `ai_provider_timeout` 504, `ai_output_invalid` 422 `details.reasons[]`, `goal_not_active` 409, `ai_prompt_too_large` 422); `GET /ai/proposals?status=&goal_id=`; `GET /ai/proposals/{id}`; `PUT /ai/proposals/{id}` (If-Match, re-validated; `proposal_not_pending`, `ai_output_invalid`, `version_conflict`); `POST /ai/proposals/{id}/accept {base_version}` + `Idempotency-Key` (200 `{milestone_ids[], task_ids[]}`; `proposal_not_pending`, `version_conflict`, `goal_not_active`, `workspace_archived`); `POST /ai/proposals/{id}/reject` (204).
> **AI-FR-01** the prompt contains only: goal title, goal description, goal target date, today's date (account zone), titles of existing milestones/tasks of that goal (≤ 50), the account locale, and the optional hint — never notes, canvases, landscape, other goals, email or any identifier; trim the existing-item lists first, then reject `ai_prompt_too_large` when `ai.prompt.max_chars` is exceeded. **AI-FR-02** the system prompt is a versioned constant (`prompt_version` stored on the run, text kept in the repo under `crates/kinevo-mod-ai/prompts/breakdown_v1.txt`); user text goes into a delimited block labelled as data; the model is told to answer with the schema only.
> **Flow** (§7): module + permission check; limits (read-only) and `ratelimit.ai_per_minute`; resolve provider; build the prompt through ports (`GoalReadPort`, `TaskReadPort`/`TaskSummaryPort`); call the provider **outside any DB transaction**; parse → schema → domain validation (T-300); one transaction inserts `ai_runs` and `ai_proposals`; 201. **AI-FR-08** limits: `ratelimit.ai_per_minute`, `ai.runs.per_day` (null = unlimited), and when `billing` is enabled the plan allowance via `EntitlementPort` (default allow-all fake until M9); failed provider calls (timeout, 5xx) do **not** consume the count; provider responses failing validation **do**.
> **Accept** (AI-FR-06): one transaction through `CreateMilestonePort`/`CreateTaskPort` (new ports in `kinevo-ports`, implemented by `goals`/`tasks` with the same domain validation as manual creation): milestones in order with `position`, tasks with `goal_id`, `milestone_id`, the goal's workspace, `priority_tier` default policy and `estimate_minutes` as given, subtasks created; any failure rolls back everything; accepted twice returns the same ids (AI-FR-13); creating a proposal with a repeated key returns the stored proposal without calling the provider. **AI-FR-07** editing re-runs validation (including user-added items); only `pending` proposals can be edited. **AI-FR-12** jobs `ai.proposals.expire` (tick; `ai.proposal.ttl`) and `ai.proposals.prune` (daily; `ai.proposal.retention`). **AI-FR-09** `ai_runs` hold counts and codes only. **SEC-AC-33** accepting another account's proposal → 404. Events `AiProposalCreated|Accepted|Rejected`.

## Scope

**In:**
- Prompt builder with the exact preview, use cases, repositories, handlers, `CreateMilestonePort`/`CreateTaskPort` with implementations; `EntitlementPort` already exists (T-101, allow-all default), jobs, OpenAPI regeneration.

**Out:**
- Web: T-303.

## Files

| Path | Action | Notes |
|---|---|---|
| `crates/kinevo-mod-ai/src/{domain,app,infra,http}/{prompt,breakdown,proposals,limits,jobs}.rs` | create |  |
| `crates/kinevo-mod-ai/prompts/breakdown_v1.txt` | create | versioned system prompt |
| `crates/kinevo-ports/src/create_planning.rs` | create | ports and fakes |
| `crates/kinevo-mod-goals/src/infra/create_milestone_port.rs, crates/kinevo-mod-tasks/src/infra/create_task_port.rs` | create | implementations |
| `crates/kinevo-mod-ai/tests/{prompt,breakdown_api,proposals_api,limits,jobs,accept_atomic}.rs` | create | AC-1…AC-13 |
| `crates/kinevo-app/src/modules.rs` | modify | wire ports |
| `contracts/openapi.json, web/src/api/schema.d.ts` | modify | regenerated |

## Interfaces

```rust
pub fn build_user_prompt(f: &PromptFacts, max_chars: usize) -> Result<UserPrompt, PromptError>;   // UserPrompt exposes text and data_fields
#[async_trait] pub trait CreateTaskPort<T: Send>: Send + Sync { async fn create_with_subtasks(&self, tx: &mut T, cmd: NewTreeTask) -> Result<TaskId, AppError>; }
```

## Business rules

1. The provider call is never inside a transaction.
2. Creating a proposal writes only `ai_proposals` and `ai_runs`.
3. Accept uses the same validation as manual creation; all or nothing.
4. Invalid output creates no proposal row but records the run as `invalid_output`.

## Security

- The preview equals the sent user part byte for byte.
- A scan test asserts no identifier (ids, email) appears in the prompt.
- Raw model output is never stored or logged.
- Foreign goal/proposal ids → 404.

## Implementation notes

- Use the fake provider server of T-301 for all tests; no network.
- Counting rules of AI-FR-08 need a table test with each outcome.

## Acceptance

| # | Given / When / Then | Test name |
|---|---|---|
| AC-1 | **Given** a goal with existing items **When** previewed **Then** the preview lists only the allowed fields and equals what the provider receives | `prompt::ai_fr_01_preview_equals_sent_and_field_list` |
| AC-2 | **Given** a prompt over the limit **When** built **Then** the lists are trimmed first, then `ai_prompt_too_large` | `prompt::ai_prompt_too_large_after_trimming` |
| AC-3 | **Given** an injection string in the hint **When** a breakdown runs **Then** the result is schema-valid or `ai_output_invalid` | `breakdown_api::ai_fr_02_injection_string` |
| AC-4 | **Given** a valid answer **When** generated **Then** 201, one proposal, one run without any text, and no milestones created | `breakdown_api::ai_fr_06_no_silent_mutation` |
| AC-5 | **Given** an invalid answer **When** generated **Then** 422 `ai_output_invalid` with reason codes, no proposal, a run `invalid_output` | `breakdown_api::ai_output_invalid_records_run` |
| AC-6 | **Given** timeout, 5xx and invalid output **When** counted **Then** only invalid output consumes the daily count | `limits::ai_fr_08_counting_rules` |
| AC-7 | **Given** the daily limit reached **When** generating **Then** 429 `ai_limit_reached` with limit and window | `limits::ai_limit_reached` |
| AC-8 | **Given** an edited payload **When** saved **Then** it is re-validated; non-pending proposals refuse edits | `proposals_api::ai_fr_07_edit_revalidates` |
| AC-9 | **Given** a pending proposal **When** accepted **Then** N milestones, M tasks and S subtasks exist in order | `proposals_api::ai_fr_06_accept_creates_tree` |
| AC-10 | **Given** a failure injected during accept **When** run **Then** nothing is created and the proposal stays pending | `accept_atomic::ai_fr_06_all_or_nothing` |
| AC-11 | **Given** the same idempotency key **When** generating or accepting twice **Then** one provider call and the same ids | `proposals_api::ai_fr_13_idempotent` |
| AC-12 | **Given** another account's proposal **When** accepted **Then** 404 | `proposals_api::sec_ac_33_foreign_proposal` |
| AC-13 | **Given** old pending and decided proposals **When** the jobs run **Then** expired and pruned per policy | `jobs::ai_fr_12_expire_and_prune` |

## Tests

```
cargo nextest run -p kinevo-mod-ai --features kinevo-db/testing   # DB tests environment-limited without Docker/PostgreSQL
just check
cargo nextest run -p kinevo-mod-goals -p kinevo-mod-tasks --features kinevo-db/testing
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
