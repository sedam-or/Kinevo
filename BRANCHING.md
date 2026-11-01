# Branching and pull requests

Decisions: D-57 defines the branch model (`main`, `dev`, `staging`). D-15 still defines the protection and the `ci-ok` gate for `main`. D-133 makes the workflow in this file current.

## 1. Branches

| Branch | Holds | Who writes it | Protection |
|---|---|---|---|
| `main` | releasable code only | maintainers, by merging `staging` | protected: pull request, `ci-ok` green, one maintainer approval, linear history, no force-push (`M-PROTECT`) |
| `staging` | milestone work that passed its integration task | maintainers, by merging a draft promotion PR from `dev` | protected like `main` (`M-PROTECT`) |
| `dev` | all task commits and the integration work | the implementer in a D-57 run; contributors by pull request | `ci-ok` required; no force-push (`M-PROTECT`) |

- Releases are tags on `main`, created by release-please when a maintainer merges its release pull request (`RELEASING.md`). Agents and contributors never create or push tags.
- The task `branch:` front-matter field is a label only. It never creates a branch.
- Do not create task, track or milestone branches.
- Historical branches kept by the maintainer (`m0-archive`, `m0-spec`, `m1-tasks`, `m1-impl`, `archive/legacy-v0`) may remain until the maintainer removes them. Removal is a maintainer action, or a named deletion authorised in the run prompt.

## 2. Working rules

- Implementation happens on `dev`. A milestone reaches `staging` through a draft promotion PR from `dev` after its integration task passes.
- `staging` reaches `main` through a maintainer-merged PR when a release is cut. The first such merge replaces the legacy tree on `main` under the checks in D-149 (`M-LEGACY`).
- Never rebase or rewrite pushed history. Never force-push.
- Pushes to a shared branch are fast-forward only. If a push is rejected, fetch and rebase your own unpushed commits; never overwrite someone else's commits.
- Never commit directly to `main`. Never merge your own pull request.
- Work from a separate checkout or worktree when another agent or person is using the main folder. Do not change branches in a shared folder.

## 3. Pull requests

- **External contributions** target `dev` from a fork. The title is a Conventional Commit (CI checks it). The body names the issue with `Refs #<n>`, never `Closes`.
- **Promotion PRs** go `dev → staging` as drafts, after a milestone's integration task passes. Their body lists each task with its `Refs #<milestone issue>` and summarises the task Reports, environment limits and `D-nn` entries added.
- **Release PRs** are opened by release-please on `main` (`RELEASING.md`).
- Review: at least one maintainer from `.github/CODEOWNERS`. Changes to `spec/`, `tasks/`, `.github/` and security-relevant code need a CODEOWNERS reviewer.
- Agents never merge.

## 4. Issues

- One GitHub issue per milestone (`milestone:M<N>` label), listing its tasks and linking their front matter. Task commits reference it as `Refs #<milestone issue>`.
- A separate issue is opened only for a task that needs discussion, a blocking question (WORKFLOW.md §3), or a security finding.
- Per-task issues are no longer created (D-133, superseding the per-task issue step of D-57).

## 5. Historical stacked pull requests

The original M0 stack was `m0-archive → main`, then `m0-spec` (targeting `m0-archive`), `m0-oss` and `m1-tasks`. Those PRs remain useful as history. New work does not stack.

## 6. Direct-to-dev implementation run (maintainer-authorised, D-57)

When the maintainer authorises an unattended run, process the selected `todo` tasks in dependency order without merging in between. Development uses only `dev`; `main` is untouched; `staging` receives milestone promotion PRs.

- Process tasks in topological order, with the task id breaking ties. Do not create task or milestone branches.
- A task's tests and `just check` pass before its status becomes `review` and its commit lands on `dev` (WORKFLOW.md §2).
- A dependency is satisfied when its task is `review` or `done` and its commit is on `dev`.
- After a milestone integration task passes, open a draft promotion PR from `dev` to `staging`.
- D-52 lets the run cover all milestones. Environment gaps are `environment-limited` with the exact command and error; they do not fail a gate.
- Ambiguities and implementer decisions go into the task Report and, when they change project rules, into `spec/decisions.md`. They do not stop the run.
- Agents never merge, never force-push, never push to `main`, and never start `draft` tasks.
