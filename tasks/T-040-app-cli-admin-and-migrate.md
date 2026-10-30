---
id: T-040
title: Add the migrate, admin and setup-token CLI commands
milestone: M1
kind: feature
status: in_progress
depends_on: [T-039, T-029, T-028, T-032, T-035]
requirements: [ADM-FR-09, ADM-FR-06, SET-04, TPL-04, ARCH-01, ARCH-21, D-47, D-50]
branch: m1-t040-app-cli-admin-and-migrate
budget_lines: 360
assignee: "OpenAI Codex"
---

# T-040: Add the migrate, admin and setup-token CLI commands

## Goal

Operators can migrate, reset a locked admin, list accounts, rotate the secret key, print a setup token, apply a template and switch modules from the command line, each action audited.

## Context

> **ADM-FR-09** (`spec/modules/admin.md`): `kinevo admin` offers `setup-token`, `reset-password <email>` (prints a one-time link), `list-accounts`, `rotate-secret-key`, `apply-template <id>`, `module <key> on|off`. Each action writes an audit entry with `actor_id = null` and `details.via = "cli"`.
> **ARCH-01**: subcommands `migrate`, `admin`, `openapi`, `version`, `health`, `serve`. `migrate` uses the migrator URL and the merged runner (T-009); exit 0 when current.
> **SET-04**: the setup token is shown by `kinevo admin setup-token`; the command issues a new token (invalidating the old hash) when setup is not complete and refuses after completion.
> **ADM-FR-06 / D-47**: `rotate-secret-key --old --new` re-seals per scope and is safe to rerun.
> **ID reset**: `reset-password` issues a reset token through the identity function of T-028 and prints the link `<KINEVO_PUBLIC_URL>/reset/<token>`; the token is shown once. The link text contains a secret, so the command prints it to stdout only and never logs it.
> **TPL-04**: `apply-template <id>` uses `apply_plan` (T-035) and is idempotent.
> **Output**: human text by default, `--json` for scripting on `list-accounts`.

## Scope

**In:**
- clap subcommands and handlers in `kinevo-app/src/cli/` using the same use cases as HTTP, with a `CliActor` that writes audit rows with `actor_id = null`, `details.via = "cli"`.
- `migrate` (and `migrate --check` returning non-zero when pending) and `migrate --bootstrap` (D-50): reads `KINEVO_BOOTSTRAP_DATABASE_URL`/`_FILE`, takes the three role passwords from the migrator, app and system URLs, calls `bootstrap::run` (T-009) for the database named in the app URL, then runs migrations.
- Exit codes: 0 success, 1 failure, 2 usage error; messages never include secrets except the two intentional one-time outputs (setup token, reset link).

**Out:**
- `openapi` command: T-041. `serve`/`health`: T-039.

## Files

| Path | Action | Notes |
|---|---|---|
| `crates/kinevo-app/src/cli.rs` | modify | subcommand tree |
| `crates/kinevo-app/src/cli/admin.rs` | create | 6 admin actions |
| `crates/kinevo-app/src/cli/migrate.rs` | create | migrate |
| `crates/kinevo-app/tests/cli.rs` | create | AC-1…AC-8 |

## Interfaces

```text
kinevo migrate [--check] [--bootstrap]
kinevo admin setup-token
kinevo admin reset-password <email>
kinevo admin list-accounts [--json]
kinevo admin rotate-secret-key --old <file|-> --new <file|->
kinevo admin apply-template <id>
kinevo admin module <key> on|off
```

## Business rules

1. `setup-token` after completion exits 1 with the `setup_already_completed` message.
2. `reset-password` for an unknown email exits 1 with a neutral message (no account enumeration is needed locally, but the same text as the API).
3. `module` uses `plan_switch`; the same errors as HTTP map to non-zero exit with the registry code printed.
4. Old and new keys are read from files or stdin, never from argv (argv leaks into process lists).

## Security

- `--old`/`--new` accept a path or `-`; passing the key as a literal argument is rejected.
- Every action audits `via = cli`; the PII scan test covers these rows.
- `list-accounts` prints ids, email, status, role; it never prints content fields (SEC-AC-10 analogue).

## Implementation notes

- Run the handlers against `TestApp` (T-011) and assert on returned structs; one test spawns the real binary for exit codes using `assert_cmd`.
- `assert_cmd` and `predicates` go to the stack map (dev dependencies).

## Acceptance

| # | Given / When / Then | Test name |
|---|---|---|
| AC-1 | **Given** a fresh instance **When** `admin setup-token` runs twice **Then** each prints a token, only the latest opens the wizard | `cli::set_04_cli_setup_token_rotates` |
| AC-2 | **Given** a completed instance **When** `admin setup-token` **Then** exit 1 | `cli::setup_token_refused_after_setup` |
| AC-3 | **Given** an existing account **When** `admin reset-password` **Then** a link with a one-time token is printed and works once | `cli::reset_password_prints_one_time_link` |
| AC-4 | **Given** accounts **When** `admin list-accounts --json` **Then** only id, email, status, role fields appear | `cli::list_accounts_no_content_fields` |
| AC-5 | **Given** sealed secrets **When** `rotate-secret-key` runs, fails midway and reruns **Then** all values stay readable | `cli::adm_fr_06_rotate_rerun_safe` |
| AC-6 | **Given** `apply-template student` twice **When** the second runs **Then** no new policy versions | `cli::tpl_04_cli_apply_idempotent` |
| AC-7 | **Given** `module canvas off` then `module tasks off` **When** run **Then** the first succeeds, the second exits 1 with `module_core_locked` | `cli::module_switch_and_core_locked` |
| AC-8 | **Given** any admin action **When** finished **Then** an audit row exists with null actor and `via=cli` | `cli::adm_fr_09_every_action_audited` |
| AC-9 | **Given** an empty server and a bootstrap URL **When** `migrate --bootstrap` runs twice **Then** the three roles exist without superuser rights, migrations are applied once, and the passwords come from the three URLs | `cli::d_50_bootstrap_creates_roles_and_migrates` |

## Tests

```
cargo nextest run -p kinevo-app --features kinevo-db/testing   # DB tests environment-limited without Docker/PostgreSQL
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
