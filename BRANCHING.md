# Branching and pull requests

Decision D-15: trunk-based development, short-lived branches, squash merge, one required status check `ci-ok`.

## 1. `main`

- `main` is always releasable. It is protected: changes arrive only by pull request, with `ci-ok` green and one maintainer approval. Linear history; no direct pushes, no force-pushes (maintainer action: configure the ruleset).
- Releases are tags on `main` created by release-please and published by a maintainer (`RELEASING.md`). Agents never create or push tags.

## 2. Branch names

Branches are named after the **milestone** and the **track**. The track is either a task or an architect work area:

| Kind | Pattern | Example |
|---|---|---|
| Task implementation | `m<N>-t<nnn>-<slug>` | `m1-t004-db-roles` |
| Architect / docs track | `m<N>-<track>` | `m0-spec`, `m0-oss`, `m1-tasks` |
| Spec fix from a blocked task | `m<N>-t<nnn>-resolve` | `m2-t031-resolve` |
| Dependency updates | `dependabot/…` (created by Dependabot) | |
| Archive (frozen) | `archive/<name>` | `archive/legacy-v0` |

`<slug>` is lowercase ASCII words joined by hyphens, at most 5 words. One task = one branch = one PR.

## 3. Working rules

- Branch from an up-to-date `main`. Keep the branch short-lived: open the PR the same day the work is ready, and aim to merge within a week.
- To pick up new changes from `main`, merge `main` into your branch. Do **not** rebase a branch that has been pushed. Rebasing local, unpushed commits is fine.
- **Never force-push** a branch someone else may have fetched. **Never rewrite pushed history.**
- Never commit directly to `main`, and never merge your own PR. Agents never merge.
- Delete the branch after the squash merge (GitHub setting "Automatically delete head branches").

## 4. Pull requests

- The title follows `COMMIT_CONVENTION.md`, because it becomes the squash commit.
- The body uses `.github/pull_request_template.md`: linked task (`tasks/T-nnn-*.md`) and issue, the Report's *Commands run*, a checklist (DoD, tests named after requirement ids, no privacy leaks, rule M evidence for document changes).
- Open it as a draft if `ci-ok` is not green yet. Mark it ready only when every DoD line holds.
- Review: at least one maintainer (`CODEOWNERS`). Changes in `spec/`, `tasks/`, `.github/` or security-relevant code (`identity`, `kernel` secrets, `db` RLS) need an architect or maintainer from CODEOWNERS.

## 5. Stacked pull requests

When one branch depends on another unmerged branch, its PR targets that branch, not `main`. Merge the stack bottom-up. After each merge, GitHub retargets the next PR to `main`.

The M0 stack is merged in this order: `m0-archive → main`, then `m0-spec` (targets `m0-archive`), then `m0-oss`, then `m1-tasks`. Merging `m0-archive` removes the legacy `docs/` site from `main`; the maintainer re-enables GitHub Pages afterwards (maintainer action).

## 6. Stacked implementation run (maintainer-authorised, one branch per milestone, D-54)

When the maintainer authorises an unattended run, an implementer agent works through the `todo` tasks in dependency order without any merge happening in between. **Branches exist per milestone only, never per task** (D-54):

- Tasks are processed in the topological order of `tasks/index.md` (ties by task id). The run uses one branch per milestone, named `m<N>-impl` (e.g. `m1-impl`). `m1-impl` is created from `m1-tasks`; `m<N+1>-impl` is created from the last commit of `m<N>-impl`. The `branch:` field in a task's front matter is a label only during this run: **do not create it**. No other branch may be created.
- Each task is one or more commits on its milestone branch (`feat(<scope>): … (T-nnn)`), made only after that task's tests and `just check` pass. The task's status is set to `review` in the same commit series. There is one PR per milestone, opened as a draft from `m<N>-impl` as soon as its first task is pushed; it targets `m1-tasks` for M1 and `m<N-1>-impl` for later milestones (stacked, §5), and is marked ready only after the milestone's integration task passes.
- A dependency counts as satisfied when its task has status `review` and its commits are on the current branch. Statuses `done`, merging and retargeting stay with the maintainer, who merges the milestone PRs bottom-up.
- One GitHub issue per task is created before work starts (title `[T-nnn] <task title>`, labels `task`, `milestone:M<N>`). The milestone PR body lists every task with `Refs #<issue>` (never `Closes`, because only the maintainer closes work).
- The run covers the milestones named in the run prompt (D-52 allows all of M1–M11). After a milestone's integration task, the run continues to the next milestone only if that task's Report shows no failing gate (`environment-limited` items are not failures); otherwise it stops there and reports.
- **Skip rule.** If a task becomes `blocked` (WORKFLOW.md §3), the agent does not commit partial code for it: it sets the task `blocked`, writes the question in its *Questions* section, comments it on the issue (label `blocked`), and continues with the next task that does **not** depend, directly or transitively, on a blocked or skipped task. Blocked and skipped tasks are listed in the milestone PR body and the final report.
- **Environment gaps are not blockers** (D-52). Run `scripts/preflight.sh` first (the maintainer installs tools beforehand with `scripts/setup-dev.sh --all`, D-53); for database tests use Docker via testcontainers, or `eval "$(scripts/dev-postgres.sh env)"`; anything still impossible is reported as `environment-limited` with the exact command and error.
- Agents never merge, never force-push, never push to `main`, and never start `draft` tasks.

