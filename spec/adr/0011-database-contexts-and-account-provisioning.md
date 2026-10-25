# ADR-0011: Database contexts, role-scoped RLS policies and account provisioning

- Status: **Accepted** (2026-10-25). Decision D-39. Refines ADR-0004 without changing its decision.

## Context

ADR-0004 forces RLS on every tenant table and gives the system role `kinevo_system` no `BYPASSRLS`. Several flows were specified in the "system context" and could not run as written (found while writing the M1 tasks):

1. **Login and refresh (FLW-03, FLW-04).** `accounts`, `refresh_token_families` and `refresh_tokens` are tenant tables with forced RLS. With `app.account_id` unset, `kinevo_system` sees no rows, so it cannot look up an email or a token hash. Login also inserts a family and a token, but the grants (`06-domain-model.md` §2.2) allowed only SELECT and UPDATE.
2. **Register and Finish setup (FLW-01, FLW-02).** These run in the system context, yet create tenant rows of *other* modules (default workspace, schedule state). The system role has no grants on those tables. `identity`/`admin` would also have to import `workspaces`/`scheduler`, which CA-03 forbids.
3. **Instance-scope writes** (module switches, instance policies) were said to go "via `kinevo_system`". Admin endpoints, though, run in an authenticated request as `kinevo_app`.

## Decision

**Three database contexts**, all created only by `kinevo-db`:

| Context | Role | GUCs set by `kinevo-db` (`set_config(…, true)`) | Used by |
|---|---|---|---|
| `Tx::begin(account_id)` | `kinevo_app` | `app.account_id` | every authenticated request; worker jobs for one account |
| `Tx::begin_admin(account_id)` | `kinevo_app` | `app.account_id`, `app.instance_admin = 'on'` | requests whose actor is an `instance_admin` (role derived from the account row, never from token claims, API-12) that write instance-level rows; setup Finish |
| `SystemTx::begin()` | `kinevo_system` | none | login lookup and counters, refresh rotation, worker claim/dispatch, instance maintenance jobs |

**Role-scoped RLS policies.** Besides `tenant_isolation` (applies to all roles), the three auth tables get a permissive policy limited to the system role:

```sql
CREATE POLICY system_auth ON <accounts | refresh_token_families | refresh_tokens>
  TO kinevo_system USING (true) WITH CHECK (true);
```

The system role's grants become: `accounts` SELECT, INSERT, UPDATE; `refresh_token_families` and `refresh_tokens` SELECT, INSERT, UPDATE; `outbox_events` SELECT, INSERT; `outbox_deliveries`, `jobs` SELECT, INSERT, UPDATE; `audit_log` INSERT; `instance_settings`, `instance_modules`, `policy_versions`, `invites` SELECT. It still has **no** privilege on any other tenant table. Granting it more is a security-reviewed change.

**Instance rows under the app role.** `instance_settings`, `instance_modules` and `invites` have no RLS. `kinevo_app` may SELECT them, and may UPDATE/INSERT them only through repositories that require an admin context: the repository constructor takes the admin `Tx`. `policy_versions` uses:

```sql
USING      (account_id IS NULL OR account_id = NULLIF(current_setting('app.account_id', true), '')::uuid)
WITH CHECK (account_id = NULLIF(current_setting('app.account_id', true), '')::uuid
            OR (account_id IS NULL AND current_setting('app.instance_admin', true) = 'on'))
```

**Account provisioning.** Register and Finish setup run in **one** `kinevo_app` transaction in the *new* account's context: the application generates the UUIDv7 first, then `Tx::begin(new_id)`, or `begin_admin(new_id)` for Finish. The `accounts` row passes RLS because its `id` equals `app.account_id`. Duplicate emails are still caught by the unique index, which RLS does not filter, and map to 409 `email_taken`. Rows owned by other modules are created by **account provisioners**: an `AccountProvisioner` port in `kinevo-ports` (`async fn provision(&self, tx: &mut Tx, account: &ProvisionedAccount) -> Result<(), DomainError>`). Each module that needs per-account rows declares one in its descriptor, and `identity`/`admin` invoke all provisioners of compiled modules in dependency order inside that same transaction (MOD-10). In 1.0: `workspaces` creates the default workspace; `scheduler` creates the `schedule_states` row.

**Jobs and outbox tables have no RLS.** Their payloads carry ids and enums only (DOM-09). The app role may only INSERT into them, through `kinevo-db` helpers inside a `Tx`. Reads across accounts happen only in `SystemTx` in the worker.

## Alternatives considered

| Option | Against |
|---|---|
| `BYPASSRLS` for `kinevo_system` | One role could read every tenant table: the opposite of ADR-0004's defence in depth |
| `SECURITY DEFINER` functions for login and provisioning | Moves business logic into SQL; harder to test and review; still needs per-table privileges |
| Two transactions (create account, then provision via an outbox event) | Breaks SET-02 / FLW-02 atomicity: a crash leaves an account without a default workspace |
| `SET ROLE` switching inside one transaction | The login role would need membership of both roles, which erases the separation |

## Consequences

- `kinevo-db` gains `Tx::begin_admin` and the provisioning entry point. The isolation test suite (TST-06) adds cases for `system_auth` (system sees auth tables only), for `app.instance_admin` (non-admins cannot write instance policy rows) and for provisioning (an account row can only be inserted for the current context id).
- `kinevo_app` must never accept `app.instance_admin` from a client. Only `kinevo-db` sets it, after the HTTP layer has loaded the account and confirmed `is_instance_admin`. A test asserts that no other crate calls `set_config`.
- **Revisit if** a flow needs system-role access to another tenant table; that requires a new ADR.
