# Standard — Data flows (FLW)

Multi-step behaviour end to end: who writes what, in which transaction, which event follows, what happens on failure. Each flow is implemented by one use case; its transaction boundary is normative (CA-08). Detailed field-level behaviour is in the module spec named in the last column.

Legend: **TX** = one database transaction in the account context (`Tx::begin(account)`), **SYS** = system context, **OUT** = outbound call (never inside a TX), **EV** = outbox event written in the same TX, **JOB** = background job.

| ID | Flow | Steps | Failure handling | Spec |
|---|---|---|---|---|
| FLW-01 | Finish setup | SYS-TX: lock `instance_settings` → apply modules, policies, create first account + default workspace + schedule state + refresh family → audit → EV `InstanceSetupCompleted` → COMMIT → respond | any error ⇒ rollback; wizard session intact; idempotent retry | `admin.md` §4 |
| FLW-02 | Register | SYS-TX: check mode/invite → insert account (+ default workspace, schedule state) → family/token → audit → EV `AccountRegistered` | duplicate email ⇒ 409; nothing persisted | `identity.md` |
| FLW-03 | Login | rate-limit → SYS read account → constant-work verify → SYS-TX: counters, family/token, audit, EV | wrong ⇒ counter/lock; transient DB error ⇒ 503 | `identity.md` §5 |
| FLW-04 | Refresh | SYS-TX: lock token + family → rotate or grace/reuse decision → audit on reuse | reuse ⇒ family revoked | `auth.md` §3 |
| FLW-05 | Create/update task | TX: validate refs (goal, milestone, workspace) → insert/update (version check) → recompute `progress_pct` if needed → EV `TaskCreated`/`TaskUpdated`/`TaskSchedulingInputsChanged` | constraint violations mapped to registry codes | `tasks.md` |
| FLW-06 | Change task status | TX: lock task → domain transition → persist → (port) supersede assignment if leaving scheduled → EV `TaskStatusChanged`(+`TaskCompleted`/`TaskReopened`) → after commit: progress, goals counters | guard failure ⇒ 409 with current state | `tasks.md` §5 |
| FLW-07 | Import ICS | preview: parse (no DB) → client selects → commit: TX: insert batch + events/overrides → EV `LandscapeImported`, `LandscapeChanged` | idempotency key; rollback on any invalid item | `landscape.md` §5 |
| FLW-08 | Change landscape | TX: write event/override → EV `LandscapeChanged{from,to}` → subscriber (scheduler, own TX): re-validate assignments → set `needs_review` | subscriber failure ⇒ retried (outbox); no data loss | `scheduler.md` SCH-FR-12 |
| FLW-09 | Generate draft | acquire run lease (TX) → assemble input (read TX) → `generate` (pure, no TX) → TX: insert draft (+hash, snapshot), finish run → EV `DraftCreated` | lease expiry on crash; `scheduler_busy` | `scheduler.md` |
| FLW-10 | Apply draft | single TX (see `scheduler.md` §6) | version/infeasible ⇒ 409 + draft `stale` | `scheduler.md` |
| FLW-11 | Weekly draft | JOB (tick) → per account: FLW-09 with kind `weekly` → notification row | unique key prevents duplicates | `scheduler.md` SCH-FR-11 |
| FLW-12 | Start session | TX: insert running session (unique index) → task `in_progress` via port → EV `SessionStarted`, `TaskStatusChanged` | concurrent start ⇒ one 409 | `execution.md` |
| FLW-13 | Complete session | TX: session → completed; task transition via port; EV `SessionCompleted`, `TaskStatusChanged`(+`TaskCompleted`) → after commit: progress subscriber | fault ⇒ all rolled back | `execution.md` §6 |
| FLW-14 | Sync reconcile | validate envelope (no TX) → per op: TX {ledger lookup → execute use case → insert ledger} | transient ⇒ `retry`, not ledgered | `sync.md` §6 |
| FLW-15 | AI breakdown | limits check (read) → build prompt → **OUT** provider call (no TX) → parse/validate → TX: insert run + proposal → EV | provider errors ⇒ 5xx mapped, no proposal; invalid output ⇒ 422 | `ai.md` §7 |
| FLW-16 | Accept proposal | TX: lock proposal → create milestones, tasks, subtasks via ports → proposal `accepted` → EV | any failure ⇒ rollback, stays `pending` | `ai.md` AI-FR-06 |
| FLW-17 | Save note | validate doc (no TX) → TX: version check → update doc + derived plain text (+search column generated) → EV `NoteUpdated` | conflict ⇒ 409 with current | `knowledge.md` |
| FLW-18 | Save canvas scene | stream-limit → validate → TX: version check → update scene | conflict ⇒ 409 | `canvas.md` |
| FLW-19 | Checkout and webhook | TX: nothing (adapter creates hosted session via **OUT**) → user pays at provider → webhook: verify signature (no TX) → TX: dedupe event, update subscription + invoice, audit, EV | bad signature ⇒ 401; duplicate ⇒ 200 ignored | `billing.md` §6 |
| FLW-20 | Export | request TX (row `queued`) → JOB builds ZIP (read TX snapshot `REPEATABLE READ`) → TX: store bytes, `ready` | failure ⇒ `failed`; retry allowed | `identity.md` ID-FR-12 |
| FLW-21 | Delete account | TX: verify password, status `deleting`, revoke families, enqueue purge, (billing port: cancel subscription **OUT** before purge job proceeds) → JOB purge: TX `DELETE FROM accounts WHERE id` (cascade) → audit (instance-level) | purge retried until success | `identity.md` ID-FR-11 |
| FLW-22 | Switch module / change policy | TX: write switch/policy version + audit + EV → cache invalidation on event | version conflict ⇒ 409 | `03`, `04` |
| FLW-23 | Outbox dispatch | JOB loop (SYS): claim due deliveries `SKIP LOCKED` → call subscriber in **its own account TX** → mark delivered/failed | backoff → dead (SM-18) | `02-architecture.md` §5 |
| FLW-24 | Retention prune | JOB daily per table (batch deletes ≤ 1000 rows/TX) | idempotent | `11-operations.md` OPS-13 |

## Rules

| ID | Rule |
|---|---|
| FLW-R1 | No OUT call inside a TX; no TX held across user think-time. |
| FLW-R2 | A flow that writes more than one table commits atomically or not at all; flows spanning modules use ports with the caller's `&mut Tx`, never separate transactions. |
| FLW-R3 | Every flow with an external side effect (payment, provider call) is idempotent from the caller's view (idempotency key or provider event id). |
| FLW-R4 | Every flow defines its events; subscribers are idempotent (`event_id`). |
| FLW-R5 | Fault-injection tests (TST-19) exist for FLW-01, 10, 13, 14, 16, 21. |
