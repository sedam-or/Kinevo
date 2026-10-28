# Standard — Error handling and the error-code registry (ERR)

Response shape: `07-api-conventions.md` §3 (RFC 9457 `application/problem+json` + `code`). Codes are owned by `kinevo-kernel::errors`, stable forever, `snake_case`. Each code has an HTTP status, a client retry class, and message keys `error.<code>.title` / `error.<code>.detail` in both catalogs (UXS-01; English titles below are normative, Indonesian text is written and reviewed at implementation time, maintainer action M-L10N).

## 1. Rules

| ID | Rule |
|---|---|
| ERR-01 | A code is added only in this registry (PR touches this file + the Rust enum + both catalogs); tests fail if any of the three diverge. |
| ERR-02 | **Retry class** tells clients what to do: `never` (fix the request), `after_change` (re-read state / user action then retry), `backoff` (retry same request with exponential backoff and jitter, honouring `Retry-After`), `reauth` (obtain a new access token). |
| ERR-03 | `details` is a JSON object whose keys are listed below; unlisted keys are not emitted. `details` never contains S2/S3 values (only ids, counts, enums, numbers, and the safe current-resource projection required by API-60). |
| ERR-04 | Logging: 4xx at `info` with `error_code` and `request_id` only (never request content); 5xx at `error` with the same plus the internal error chain **without** user data; security-relevant codes (`invalid_credentials`, `account_locked`, `refresh_reuse_detected`, `signature_invalid`, `csrf_check_failed`, `setup_token_invalid`) also write an audit entry. |
| ERR-05 | Internal errors never leak: unmapped database errors become `internal_error` and log `unmapped_constraint` with the constraint name (VAL-11). |
| ERR-06 | Idempotent deterministic errors may be replayed by the idempotency store; 5xx and 429 are never stored (API-71). |
| ERR-07 | In sync results the same codes appear in `error.code` with the same `http_status` (SYN §2). |

## 2. Registry

Legend — Retry: N = never, C = after_change, B = backoff, R = reauth. "Module" is the owner of the scenario (the kernel owns generic codes).

### 2.1 Generic

| Code | HTTP | Retry | Title (EN) | `details` | Module |
|---|---|---|---|---|---|
| `unauthenticated` | 401 | R | Sign in required | — | kernel |
| `forbidden` | 403 | N | Not allowed | — | kernel |
| `not_found` | 404 | N | Not found | — | kernel |
| `validation_failed` | 422 | N | Check the highlighted fields | `errors[]` at top level | kernel |
| `unknown_field` | 422 | N | Unknown field | — (field entry) | kernel |
| `field_not_nullable` | 422 | N | This field can't be empty | — (field entry) | kernel |
| `unsupported_media_type` | 415 | N | Unsupported content type | `accepted[]` | kernel |
| `payload_too_large` | 413 | N | Request too large | `max_bytes` | kernel |
| `cursor_invalid` | 400 | N | Invalid page cursor | — | kernel |
| `sort_invalid` | 422 | N | Invalid sort | `allowed[]` | kernel |
| `query_param_unknown` | 422 | N | Unknown query parameter | `name` | kernel |
| `window_too_large` | 422 | N | Date range too large | `max_days` | kernel |
| `date_out_of_range` | 422 | N | Date out of range | `min`, `max` | execution |
| `version_conflict` | 409 | C | The item changed | `current_version`, `current` | kernel |
| `precondition_required` | 428 | N | Version required | `header: "If-Match"` | kernel |
| `idempotency_key_reuse` | 422 | N | Idempotency key already used for a different request | — | kernel |
| `idempotency_in_progress` | 409 | B | Request still in progress | — | kernel |
| `rate_limited` | 429 | B | Too many requests | `retry_after_seconds` | kernel |
| `internal_error` | 500 | B | Something went wrong | — | kernel |
| `service_unavailable` | 503 | B | Service temporarily unavailable | — | kernel |
| `module_disabled` | 404 | N | Feature turned off | `module` | admin |
| `invalid_transition` | 409 | C | That change isn't allowed from the current state | `from`, `to` | goals/tasks/billing |

### 2.2 Field-level validation codes (appear in `errors[].code`, status 422 via `validation_failed`)

`unknown_field`, `required`, `too_long`, `too_short`, `invalid_characters`, `email_invalid`, `uuid_invalid`, `url_invalid`, `timezone_invalid`, `instant_invalid`, `date_invalid`, `time_invalid`, `int_invalid`, `out_of_range`, `money_invalid`, `enum_invalid`, `bool_invalid`, `digits_invalid`, `token_invalid`, `too_many_items`, `duplicate_item`, `schema_invalid`, `password_too_short`, `password_too_common`, `key_invalid`. `details` per code: `max`/`min`/`allowed`/`path` as relevant.

### 2.3 Setup, admin, policies, modules

| Code | HTTP | Retry | Title (EN) | `details` | Module |
|---|---|---|---|---|---|
| `setup_required` | 409 | C | Setup required | — | admin |
| `setup_already_completed` | 409 | N | Setup already completed | — | admin |
| `setup_token_invalid` | 422 | N | Setup link not valid | — | admin |
| `template_invalid` | 422 | N | Template invalid | `path` | admin |
| `module_core_locked` | 409 | N | This feature can't be turned off | `module` | admin |
| `module_dependency_unmet` | 409 | C | Turn on required features first | `missing[]` | admin |
| `module_required_by` | 409 | C | Other features need this | `dependents[]` | admin |
| `policy_unknown` | 404 | N | Unknown setting | — | admin |
| `policy_value_invalid` | 422 | N | Value not allowed | `bounds` | admin |
| `policy_scope_invalid` | 422 | N | Setting can't be changed at this level | — | admin |
| `policy_effective_in_past` | 422 | N | Start time must not be in the past | — | admin |
| `policy_effective_order` | 422 | N | Start time must be after the current setting's start | — | admin |
| `secret_name_unknown` | 422 | N | Unknown secret name | `allowed[]` | admin |
| `not_dead` | 409 | N | Delivery isn't failed | — | admin |

### 2.4 Identity

| Code | HTTP | Retry | Title (EN) | `details` | Module |
|---|---|---|---|---|---|
| `registration_closed` | 403 | N | Registration is closed | — | identity |
| `invite_invalid` | 422 | N | Invite not valid | — | identity |
| `email_taken` | 409 | N | Email already in use | — | identity |
| `invalid_credentials` | 401 | N | Email or password incorrect | — | identity |
| `mfa_required` | 401 | C | Verification code required | `methods[]` | identity |
| `invalid_mfa_code` | 401 | N | Code not valid | — | identity (login only) |
| `current_password_incorrect` | 422 | N | Current password is incorrect | — | identity (authenticated re-verification) |
| `totp_code_invalid` | 422 | N | Code not valid | — | identity (authenticated enrol/disable) |
| `account_locked` | 429 | B | Too many attempts | `retry_after_seconds` | identity |
| `account_disabled` | 403 | N | Account disabled | — | identity |
| `refresh_reuse_detected` | 401 | R | Signed out for security | — | identity |
| `csrf_check_failed` | 403 | N | Request origin not allowed | — | identity |
| `reset_token_invalid` | 422 | N | Reset link not valid | — | identity |
| `totp_already_enabled` | 409 | N | Two-step verification already on | — | identity |
| `totp_required_by_policy` | 409 | N | Two-step verification is required here | — | identity |
| `export_in_progress` | 409 | C | An export is already running | `export_id` | identity |
| `export_not_ready` | 409 | C | Export not ready | — | identity |
| `export_expired` | 410 | N | Export expired | — | identity |
| `last_admin` | 409 | N | At least one administrator is required | — | identity |

### 2.5 Workspaces, goals, tasks

| Code | HTTP | Retry | Title (EN) | `details` | Module |
|---|---|---|---|---|---|
| `workspace_name_taken` | 409 | N | Workspace name already used | — | workspaces |
| `workspace_limit_reached` | 409 | C | Workspace limit reached | `max` | workspaces |
| `workspace_archived` | 409 | C | Workspace is archived | — | workspaces |
| `workspace_is_default` | 409 | N | The default workspace can't be removed | — | workspaces |
| `workspace_not_empty` | 409 | C | Workspace isn't empty | `counts` | workspaces |
| `goal_has_tasks` | 409 | C | Goal still has tasks | `count` | goals |
| `goal_not_active` | 409 | C | Goal isn't active | `status` | goals |
| `reorder_set_mismatch` | 422 | N | Order must list every item once | — | goals |
| `milestone_after_goal` | 422 | N | Milestone date is after the goal date | — | goals |
| `task_goal_workspace_mismatch` | 422 | N | Task and goal are in different workspaces | — | tasks |
| `task_milestone_goal_mismatch` | 422 | N | Milestone belongs to another goal | — | tasks |
| `milestone_requires_goal` | 422 | N | Choose a goal to use a milestone | — | tasks |
| `anchor_exists` | 409 | C | You already have an anchor task | `task_id` | tasks |
| `task_has_running_session` | 409 | C | Task has a running timer | `session_id` | tasks |
| `subtask_limit_reached` | 409 | N | Too many subtasks | `max` | tasks |

### 2.6 Landscape, scheduler, execution

| Code | HTTP | Retry | Title (EN) | `details` | Module |
|---|---|---|---|---|---|
| `rrule_invalid` | 422 | N | Repeat rule not valid | `path` | landscape |
| `override_requires_recurring` | 422 | N | Only repeating events can be shifted | — | landscape |
| `override_range_invalid` | 422 | N | Override dates not valid | — | landscape |
| `ics_too_large` | 413 | N | Calendar file too large | `max_bytes` | landscape |
| `ics_invalid` | 422 | N | Not a valid calendar file | — | landscape |
| `ics_too_many_events` | 422 | N | Too many events in the file | `max` | landscape |
| `scheduler_busy` | 409 | B | A schedule update is running | — | scheduler |
| `no_eligible_tasks` | 422 | C | No tasks to schedule | — | scheduler |
| `schedule_version_conflict` | 409 | C | Schedule changed | `current_version` | scheduler |
| `draft_infeasible` | 409 | C | Proposal no longer fits | `violations[]` | scheduler |
| `draft_not_pending` | 409 | C | Proposal already handled | `status` | scheduler |
| `task_ids_not_in_draft` | 422 | N | Selected tasks aren't in the proposal | — | scheduler |
| `placement_invalid` | 422 | C | That time doesn't work | `violations[]` | scheduler |
| `task_not_schedulable` | 409 | C | Task can't be scheduled in its state | `status` | scheduler |
| `task_in_progress` | 409 | C | Task is in progress | — | scheduler |
| `session_already_running` | 409 | C | A timer is already running | `session_id`, `task_id` | execution |
| `task_not_startable` | 409 | C | Task can't be started in its state | `status` | execution |
| `session_not_running` | 409 | C | Timer isn't running | — | execution |
| `ended_at_invalid` | 422 | N | End time not valid | — | execution |

### 2.7 Sync, AI

| Code | HTTP | Retry | Title (EN) | `details` | Module |
|---|---|---|---|---|---|
| `sync_protocol_unsupported` | 422 | R | Update the app to sync | `supported` | sync |
| `sync_batch_too_large` | 422 | N | Too many changes in one request | `max` | sync |
| `sync_payload_too_large` | 413 | N | A change is too large | `max_bytes` | sync |
| `operation_id_reused` | 422 | N | Operation id reused | — | sync |
| `op_unsupported` | 422 | N | Change type not supported offline | `op_type` | sync |
| `entity_id_taken` | 409 | N | Item id already used | — | sync/tasks/knowledge |
| `dependency_failed` | 422 | N | Depends on a failed change | `entity_id` | sync |
| `ai_not_configured` | 409 | C | AI isn't set up | — | ai |
| `ai_limit_reached` | 429 | C | AI limit reached | `limit`, `window` | ai |
| `ai_provider_unavailable` | 503 | B | AI service unavailable | — | ai |
| `ai_provider_timeout` | 504 | B | AI service timed out | — | ai |
| `ai_output_invalid` | 422 | C | AI answer unusable | `reasons[]` | ai |
| `ai_prompt_too_large` | 422 | C | Description too long for AI | `max_chars` | ai |
| `ai_byok_disabled` | 403 | N | Own AI keys are turned off | — | ai |
| `ai_host_not_allowed` | 422 | N | That address isn't allowed | — | ai |
| `proposal_not_pending` | 409 | C | Proposal already handled | `status` | ai |

### 2.8 Knowledge, canvas, billing

| Code | HTTP | Retry | Title (EN) | `details` | Module |
|---|---|---|---|---|---|
| `note_doc_invalid` | 422 | N | Note content not valid | `errors[].path` | knowledge |
| `note_too_large` | 413 | N | Note too large | `max_bytes` | knowledge |
| `query_too_short` | 422 | N | Search too short | `min` | knowledge |
| `query_too_long` | 422 | N | Search too long | `max` | knowledge |
| `link_target_not_found` | 404 | N | Link target not found | `index` | knowledge/canvas |
| `link_limit_reached` | 409 | N | Too many links | `max` | knowledge/canvas |
| `canvas_scene_invalid` | 422 | N | Board content not valid | `errors[].path` | canvas |
| `canvas_too_large` | 413 | N | Board too large | `max_bytes` | canvas |
| `canvas_file_invalid` | 422 | N | Image not allowed | — | canvas |
| `canvas_file_too_large` | 413 | N | Image too large | `max_bytes` | canvas |
| `canvas_files_limit` | 409 | N | Too many images on this board | `max` | canvas |
| `plan_unknown` | 404 | N | Plan not found | — | billing |
| `billing_not_configured` | 409 | N | Billing isn't set up | — | billing |
| `gateway_unavailable` | 503 | B | Payment service unavailable | — | billing |
| `already_subscribed` | 409 | C | Already subscribed | — | billing |
| `signature_invalid` | 401 | N | Signature not valid | — | billing |
| `gateway_unknown` | 404 | N | Unknown payment provider | — | billing |

### 2.9 Non-HTTP (process-level) codes

`config_invalid` (startup failure naming the variable), `isolation_check_failed` (startup refuses to run), `migration_failed`, `unmapped_constraint` (log only). They never appear in API responses.
