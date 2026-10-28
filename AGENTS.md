# AGENTS.md

Binding on every agent and every human contributor.

## The one rule

**Work only from a task file.** Implementation starts from `tasks/T-nnn-*.md` with status `todo` and every dependency `done`, and follows [`WORKFLOW.md`](WORKFLOW.md). In the maintainer-authorised D-57 run, dependencies in `review` on `dev` also count. No task, no change. If something is missing, wrong or ambiguous, follow the run-specific decision authority in [`BRANCHING.md`](BRANCHING.md) §6 and record the choice; otherwise use WORKFLOW.md §3.

The architect (spec and task author) works on `spec/`, `tasks/` and repository files under the same rules, but its work items are milestones instead of task files.

## Precedence (D-32)

| Layer | Answers | File |
|---|---|---|
| 1. Task | **what** to build now: scope, files, acceptance | `tasks/T-nnn-*.md` |
| 2. Spec | **which rules** the result must obey | `spec/` (start at [`spec/index.md`](spec/index.md)) |
| 3. Guidelines | **how** to work | [`CLAUDE.md`](CLAUDE.md) (vendored verbatim, D-23: never edit it) |

A task may narrow the spec but never contradict it. If task and spec disagree, the spec is right and the task is blocked. Spec files disagreeing with each other are blocked too: report the conflict and stop.

`CLAUDE.md` binds every change: code, docs, tasks, CI and brand files. In particular: state assumptions, keep the change minimal, touch only what the task lists, and define success before starting.

## Repository map (D-29)

| Path | Contents | Exists from |
|---|---|---|
| `spec/` | Specification: product, architecture, modules, standards, decisions, ADRs | M0 |
| `tasks/` | Task files `T-nnn-*.md`, template, generated `index.md` | M0 |
| `scripts/` | Stdlib-only Python checks (`spec_tool.py`, `contrast.py`, `tasks_index.py`) | M0 |
| `crates/` | Rust workspace: `kinevo-kernel`, `-ports`, `-scheduler`, `-db`, `-http`, `-app`, `kinevo-mod-<key>` | M1 |
| `web/` | React + Vite PWA (also the Android WebView content) | M1 |
| `android/` | Capacitor shell + Kotlin plugins | M10 |
| `legacy/` | `kinevo-legacy-v0.zip` + `SHA256SUMS`: **archive only** | M0 |
| `.github/` | CI workflows, issue/PR templates, Dependabot, CODEOWNERS | M0 |
| root files | `AGENTS.md`, `CLAUDE.md`, `WORKFLOW.md`, `COMMIT_CONVENTION.md`, `BRANCHING.md`, governance files, `LICENSE` | M0 |

## Commands

Until M1 creates the `justfile` (D-38):

| Command | Purpose |
|---|---|
| `python3 scripts/spec_tool.py check` | Spec consistency: file references, ids, error codes, policy keys, permissions (exit 0 = clean) |
| `python3 scripts/contrast.py` | Design-token contrast and gamut check (TST-10) |
| `python3 scripts/tasks_index.py --check` | Task files valid, DAG acyclic, `tasks/index.md` up to date |
| `scripts/setup-dev.sh [--sudo] [--android] [--browsers] [--rustup] [--all]` | Installs the whole toolchain (D-53); idempotent; maintainer runs the `sudo` and licence parts |
| `scripts/preflight.sh` | Checks and installs tools for a stacked implementation run, creates the issue labels (BRANCHING.md §6) |
| `scripts/dev-postgres.sh env` | Starts or reuses the one shared disposable PostgreSQL test server; prints `KINEVO_TEST_DATABASE_URL` |

From M1 on: `just setup` (one-time), `just dev`, `just fmt`, `just lint`, `just test`, `just check` (everything CI runs; must pass before a task goes to `review`), and `just spec` (the three scripts above).

For the maintainer-authorised implementation run (D-105), run `df -h /` immediately before every build, test, or container start. Do not build Docker images or pull from a registry; database tests use the existing `postgres:17-alpine` image with pulls disabled or an installed local PostgreSQL. See `WORKFLOW.md` §2 for the low-disk threshold and cleanup limits.

## Non-negotiables

1. **Never open, unzip, list or read `legacy/kinevo-legacy-v0.zip`.** Cite old behaviour as `legacy-v0:<path>` and read it with `git show legacy-v0:<path>`.
2. **Decisions are not re-decided.** `spec/decisions.md` (D-nn) and accepted ADRs (`spec/adr/`) bind. A change needs a new decision entry that names what it supersedes.
3. **No hard-coded business values.** A value an operator could want to change is a policy (`spec/04-policies.md`); a missing value is a question, not a guess.
4. **Server is the source of truth.** Clients cache and queue; tenant isolation is row-level security through `Tx` only (ADR-0004); the scheduler stays pure and deterministic (ADR-0010).
5. **Money and time.** Integer minor units and basis points, never floats; instants in UTC, half-open ranges, injected `Clock`/`IdGen` (D-17, D-18).
6. **Security by design.** Data classes S0–S3, redacting secret types, no PII, note contents, prompts or tokens in logs (`spec/standards/security-by-design.md`).
7. **Tests prove requirements.** Every touched requirement id has a test named after it. A test is never weakened, skipped or deleted to get green. Unrun checks are never reported as passed.
8. **Privacy.** No personal email addresses, real names of private people, secrets or tokens in any file, commit message, issue or PR. Use the GitHub noreply address for commits. Fixtures use `example.test`.
9. **Git.** Follow [`BRANCHING.md`](BRANCHING.md) and [`COMMIT_CONVENTION.md`](COMMIT_CONVENTION.md): signed-off Conventional Commits, PRs for everything, never push to `main`, never merge your own PR, never force-push a shared branch, never rewrite pushed history.
10. **Dependencies.** A new crate or package needs an entry in `spec/standards/open-source-stack.md` first (CQ-16). Check licence compatibility with AGPL-3.0-only.

## Mapping rule (rule M)

A change to any document updates, in the same commit: `spec/index.md`, this file (repository map, if paths change), `.github/CODEOWNERS`, `spec/13-roadmap.md` and affected task files. Evidence is the output of `python3 scripts/spec_tool.py check` and `python3 scripts/tasks_index.py --check`, quoted in the PR.

## Legacy archive (D-06)

| Item | Value |
|---|---|
| Tag / branch | `legacy-v0` / `archive/legacy-v0` → commit `c3bab8c9b557a0873550627c3673f7a7c644fcdf` |
| In-repo ZIP | `legacy/kinevo-legacy-v0.zip`: the tracked tree minus vendored third-party `vendor/`; checksum in `legacy/SHA256SUMS` |
| Off-repo | git bundle `kinevo-legacy-v0.bundle` (sha256 `3efc0f08…6bce`) and tarball `kinevo-legacy-v0-src.tar.gz` (sha256 `1c7d048f…b911`), kept by the maintainer outside the repository |
| License | `legacy-v0` remains MIT; everything after it is AGPL-3.0-only (D-04) |
| Baseline | `spec/00-verdict.md` §1 |
