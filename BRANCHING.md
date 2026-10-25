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
