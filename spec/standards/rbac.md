# Standard — Access control (RBAC)

Decision: D-12. Two roles only (single-user product, D-30). Roles are derived server-side from the account row, never from token claims (API-12).

| Role | Derived from | Meaning |
|---|---|---|
| `account_owner` | every `active` account | full control of **own** data; held by all accounts |
| `instance_admin` | `accounts.is_instance_admin = true` | `account_owner` **plus** instance administration; sees no one else's content |

An account with status `disabled` or `deleting` has no role (all requests 401/403 except `GET /instance`).

## 1. Decision order for every request (CA-14, SEC-05)

1. Route is public? (SEC-05 list) → allow.
2. Valid access token → account loaded (cached ≤ 60 s) → `status = active`, else 403 `account_disabled`.
3. Module enabled? else 404 `module_disabled`.
4. Permission key in the route descriptor ∈ role's set? else 403 `forbidden`.
5. Entitlement (plan limit via `EntitlementPort`) where the descriptor declares one → else 409/429 module-specific code.
6. Ownership: enforced by RLS at the database (foreign rows do not exist for the caller → 404).

## 2. Permission matrix

`✓` = granted. Keys are defined in the module specs (§1 of each); this table is the registry copy and `scripts/spec_tool.py check` fails if they diverge.

| Key | `account_owner` | `instance_admin` |
|---|:-:|:-:|
| `identity.session.manage`, `identity.account.read`, `identity.account.update`, `identity.account.delete`, `identity.account.export`, `identity.totp.manage` | ✓ | ✓ |
| `identity.admin.accounts.read`, `identity.admin.accounts.update`, `identity.admin.invite.create`, `identity.admin.password_reset.issue` | — | ✓ |
| `admin.policies.account.read`, `admin.policies.account.write`, `admin.policies.instance.read`, `admin.instance.read`, `admin.audit.read_own` | ✓ | ✓ |
| `admin.policies.instance.write`, `admin.modules.manage`, `admin.audit.read`, `admin.jobs.read`, `admin.jobs.retry`, `admin.secrets.manage` | — | ✓ |
| `workspaces.workspace.read`, `workspaces.workspace.create`, `workspaces.workspace.update`, `workspaces.workspace.archive`, `workspaces.workspace.delete` | ✓ | ✓ |
| `goals.goal.read`, `goals.goal.create`, `goals.goal.update`, `goals.goal.delete`, `goals.milestone.read`, `goals.milestone.create`, `goals.milestone.update`, `goals.milestone.delete` | ✓ | ✓ |
| `tasks.task.read`, `tasks.task.create`, `tasks.task.update`, `tasks.task.delete`, `tasks.task.status`, `tasks.subtask.read`, `tasks.subtask.create`, `tasks.subtask.update`, `tasks.subtask.delete` | ✓ | ✓ |
| `landscape.event.read`, `landscape.event.create`, `landscape.event.update`, `landscape.event.delete`, `landscape.override.read`, `landscape.override.create`, `landscape.override.delete`, `landscape.import.create`, `landscape.import.delete`, `landscape.export.read` | ✓ | ✓ |
| `scheduler.schedule.read`, `scheduler.draft.create`, `scheduler.draft.apply`, `scheduler.draft.dismiss`, `scheduler.assignment.place`, `scheduler.assignment.lock`, `scheduler.assignment.remove`, `scheduler.export.read` | ✓ | ✓ |
| `execution.today.read`, `execution.session.read`, `execution.session.start`, `execution.session.complete`, `execution.session.abandon` | ✓ | ✓ |
| `progress.summary.read`, `progress.goals.read`, `progress.review.read` | ✓ | ✓ |
| `sync.reconcile.execute`, `sync.ledger.read` | ✓ | ✓ |
| `ai.status.read`, `ai.breakdown.create`, `ai.proposal.read`, `ai.proposal.update`, `ai.proposal.accept`, `ai.proposal.reject`, `ai.provider.manage` | ✓ | ✓ |
| `knowledge.note.read`, `knowledge.note.create`, `knowledge.note.update`, `knowledge.note.delete`, `knowledge.link.manage`, `knowledge.search` | ✓ | ✓ |
| `canvas.canvas.read`, `canvas.canvas.create`, `canvas.canvas.update`, `canvas.canvas.delete`, `canvas.file.manage`, `canvas.link.manage` | ✓ | ✓ |
| `billing.plan.read`, `billing.subscription.read`, `billing.subscription.manage`, `billing.invoice.read` | ✓ | ✓ |

Notes: `instance_admin` has no key that reads another account's S2 content; admin lists show account metadata only (SEC-AC-10). The CLI (`kinevo admin`) acts with operator authority and is audited (ADM-FR-09).

## 3. Query scopes

Scope is not a role feature: every repository method runs inside `Tx::begin(account_id)` so RLS restricts rows to the caller's account (ARCH-22). Workspace filters (`workspace_id`) are *filters*, not security scopes. Cross-account listing exists only in admin endpoints, which read the `accounts` table via the system role and return metadata columns only.

## 4. Requirements

| ID | Requirement | Acceptance |
|---|---|---|
| RBAC-01 | Every route descriptor declares exactly one permission key (or `public`); a route without it fails the architecture test. | CA-05d |
| RBAC-02 | A generated test iterates every (route × role) pair and asserts the matrix: allowed pairs reach the handler, denied pairs get 403 (an authenticated non-admin calling an admin route may know the route exists, API-23). | CI `rbac-matrix` |
| RBAC-03 | Permission keys are strings validated by a `PermissionKey` newtype and exist in this registry. | Unit |
| RBAC-04 | Changing `is_instance_admin` is possible only via CLI or the last-admin-protected admin endpoint (ID `PATCH /admin/accounts/{id}` is limited to `status`; promotion/demotion of admins is CLI-only in 1.0). | Test |
