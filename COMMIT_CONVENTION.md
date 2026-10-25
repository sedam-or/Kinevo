# Commit convention

Commits follow [Conventional Commits 1.0.0](https://www.conventionalcommits.org/en/v1.0.0/). Merges into `main` are squash merges, so **the PR title becomes the commit on `main`** and must follow the same rules. CI checks PR titles. Release notes are generated from these subjects (release-please), so they are written for users.

## Format

```
<type>(<scope>)<!>: <subject> [T-nnn]

<body>

<footers>
Signed-off-by: <name> <email>
```

- **Subject line:** at most **72 characters** in total, including type, scope and the task reference. Imperative mood ("add", not "added"), lowercase after the colon, no full stop at the end.
- **`[T-nnn]`** ends the subject whenever the commit implements or changes a task. Architect commits on `spec/` or repository files that are not tied to one task leave it out.
- **Body:** optional. Wrap at 72 columns. Explain *why*, and what a reviewer cannot see in the diff. Bullet lists are fine.
- **Footers:** `BREAKING CHANGE: <what breaks and how to migrate>` for breaking changes; `Refs: #<issue>` for links; `Co-Authored-By:` lines for additional authors, including AI tools when their policy asks for it.
- **Sign-off (DCO, D-04):** every commit carries `Signed-off-by:`, added with `git commit -s`. It certifies the [Developer Certificate of Origin](https://developercert.org/). Use a name and an email you are happy to make public. The GitHub noreply address (`<id>+<login>@users.noreply.github.com`) is recommended. Commits without a sign-off fail CI.

## Types

| Type | Use for | In release notes |
|---|---|---|
| `feat` | a user-visible capability | Features |
| `fix` | a user-visible bug fix | Bug fixes |
| `perf` | a performance improvement with no behaviour change | Performance |
| `security` | a security fix or hardening (coordinate with `SECURITY.md` before disclosing details) | Security |
| `docs` | documentation, specs, tasks | hidden |
| `test` | tests only | hidden |
| `refactor` | code change with no behaviour change | hidden |
| `build` | build system, Dockerfile, dependencies | hidden (shown for dependency security updates) |
| `ci` | CI workflows and scripts | hidden |
| `chore` | repository housekeeping that fits nothing above | hidden |
| `revert` | reverts a previous commit; the body names the reverted hash | Reverts |

A breaking change adds `!` after the type or scope (`feat(identity)!: …`) **and** a `BREAKING CHANGE:` footer.

## Scopes

One scope per commit. Use the narrowest one that fits:

| Group | Scopes |
|---|---|
| Server crates | `kernel`, `ports`, `scheduler-core` (the pure `kinevo-scheduler` crate), `db`, `http`, `app` |
| Modules | `identity`, `admin`, `workspaces`, `goals`, `tasks`, `landscape`, `scheduler`, `execution`, `progress`, `sync`, `ai`, `knowledge`, `canvas`, `billing` |
| Clients | `web`, `design` (tokens and primitives), `i18n`, `android` |
| Repository | `spec`, `adr`, `tasks` (task files, not the module), `ci`, `deps`, `docker`, `release`, `brand`, `governance`, `legacy` |

A change that spans several scopes is usually more than one task. If it really is one change (for example a renamed error code touching kernel, web and i18n), leave the scope out: `fix: rename invalid_mfa_code to totp_code_invalid [T-031]`.

## Examples

```
feat(tasks): add task status state machine [T-042]
fix(scheduler-core): keep slot order stable for equal ranks [T-057]
docs(spec): declare jobs.retry.cap_seconds policy
build(deps): bump axum to 0.8.10
feat(identity)!: return 403 for disabled accounts [T-018]
```

## Not allowed

- Subjects like "update", "wip", "fix stuff", or a task id with no description.
- Personal emails or private names in any part of the message (AGENTS.md non-negotiable 8).
- Rewriting pushed history to tidy messages. Fix the PR title instead, since the squash merge uses it.
