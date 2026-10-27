# Branching and pull requests

Decision D-15: trunk-based development, short-lived branches, squash merge, one required status check `ci-ok`.

## 1. `main`

- `main` is always releasable. It is protected: changes arrive only by pull request, with `ci-ok` green and one maintainer approval. Linear history; no direct pushes, no force-pushes (maintainer action: configure the ruleset).
- Releases are tags on `main` created by release-please and published by a maintainer (`RELEASING.md`). Agents never create or push tags.

## 2. Branch names

The active development branches are `main`, `dev` and `staging` (D-57). All task implementation commits go directly to `dev`; `staging` is updated only through a draft promotion PR from `dev`. The task `branch:` field is descriptive metadata and never creates a branch.

Historical and archive branches explicitly retained by the maintainer (including `m0-archive`, `m0-spec`, `m1-tasks`, `m1-impl` and `archive/legacy-v0`) may remain until the maintainer removes them. Do not create new task, track or milestone branches.

## 3. Working rules

- Work on `dev`; promote completed milestone work to `staging` with a draft PR from `dev` after that milestone's integration task passes.
- Never rebase or rewrite pushed history.
- **Never force-push** a branch someone else may have fetched. **Never rewrite pushed history.**
- Never commit directly to `main`, and never merge your own PR. Agents never merge.
- Historical branch cleanup is a maintainer action unless a run prompt explicitly authorizes a named deletion.

## 4. Pull requests

- Task commits are reviewed through the milestone promotion PR from `dev` to `staging`, opened as a draft only after the milestone integration task passes.
- The body lists every task as `Refs #<issue>` (never `Closes`), summarizes its Report, and includes environment limits, implementer decisions and `just check` evidence.
- Agents never merge. Review: at least one maintainer (`CODEOWNERS`). Changes in `spec/`, `tasks/`, `.github/` or security-relevant code (`identity`, `kernel` secrets, `db` RLS) need an architect or maintainer from CODEOWNERS.

## 5. Historical stacked pull requests

The original M0 stack was `m0-archive → main`, followed by `m0-spec` (targeting `m0-archive`), `m0-oss` and `m1-tasks`. This historical stack remains useful when reviewing those PRs. The D-57 implementation run does not create stacked task or milestone branches; see §6.

## 6. Direct-to-dev implementation run (maintainer-authorised, D-57)

When the maintainer authorises an unattended run, process all selected `todo` tasks in dependency order without merging in between. Development uses only `dev`; `main` is untouched and `staging` receives milestone promotion PRs.

- Tasks are processed in topological dependency order (task id breaks ties). Do not create task or milestone branches; the task `branch:` field is a label only.
- Each task's tests and `just check` pass before its implementation is marked `review` and committed on `dev`. The task is associated with one GitHub issue created before work starts, titled `[T-nnn] <task title>` and labeled `task`, `milestone:M<N>`.
- A dependency counts as satisfied when its task is `review` (or `done`) and its commit is present on `dev`.
- After a milestone integration task passes, open a draft promotion PR from `dev` to `staging`. Its body lists each milestone task as `Refs #<issue>` (never `Closes`), summarizes Reports, and lists environment limits and implementer decisions.
- D-52 allows the run to cover all M1–M11. Environment gaps are recorded as `environment-limited` with the exact command and error; they do not fail a milestone gate.
- Ambiguities and implementer decisions are recorded in the task Report and, when they change project rules, in `spec/decisions.md`; they do not stop this authorized run.
- Agents never merge, never force-push, never push to `main`, and never start `draft` tasks.

