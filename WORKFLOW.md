# WORKFLOW.md: how a task is done

Applies to every implementer, human or agent. Precedence and non-negotiables: [`AGENTS.md`](AGENTS.md). Task template: [`tasks/_template.md`](tasks/_template.md).
This is the lean process from D-133. The previous 12-step version is in git history (`git show 3e4a8f1:WORKFLOW.md`).

## 1. Status

```
draft ─► todo ─► in_progress ─► review ─► done
                    ▲              │
                    └ changes_requested ◄┘
                 (blocked: ordinary workflow only, §3)
```

| Transition | Who | When |
|---|---|---|
| `draft → todo` | architect | the upstream integration task is `done` and the consistency gate passed (D-52 exceptions stand) |
| `todo → in_progress` | implementer | in the first commit that changes the task's files; there is no separate claim commit (D-133) |
| `in_progress → review` | implementer | in the commit that delivers the work, after the gate in §2 step 4 passes |
| `review → changes_requested → in_progress` | reviewer, implementer | a defect against the task, spec or Definition of done |
| `review → done` | maintainer | the work is accepted; set in the next commit or the merge |
| `→ blocked` / `blocked → todo` | implementer / architect | §3 only |

Status lives in the front matter (`status:`). After any status change run `python3 scripts/tasks_index.py` and commit `tasks/index.md` in the same commit. Never edit that file by hand.

## 2. The steps

### Resource guard (D-102–D-109, D-125, D-128) — applies to every build, test or container start

- Run `df -h /` before each build, test or container start. Record the exact output at the start and end of the run in `tasks/RUN_REPORT.md`, and every removal (or `none`).
- Below 20 GB free: inspect stopped containers and dangling images, and prune only resources you have verified belong to Kinevo. Never prune volumes, named images, active containers or another project's resources. Recheck; still below 15 GB means hard stop, reported with the measured value.
- Never build or pull a Docker image. Use one shared database: `eval "$(scripts/dev-postgres.sh env)"`, one uniquely named database per test. No Testcontainers.
- `[profile.dev] debug = 0` stays in the root `Cargo.toml`; `CARGO_PROFILE_DEV_DEBUG=2` overrides it locally. At a milestone boundary only: `CARGO_TARGET_DIR=<repo>/target cargo sweep --time 7`. Never `cargo clean`, never delete `target/` by hand.
- An ad-hoc cleanup request does not widen this scope (D-128).

### Steps

1. **Read.** Read the whole task and every spec section it cites. An inlined excerpt is a copy: if it differs from the spec, the spec wins and the difference is a Deviation (D-56).
2. **Tests first.** One test per acceptance criterion, named as the task says. Run them and see each fail for the expected reason.
3. **Implement the minimum.** Touch only the files under *Files*. Follow *Interfaces*, *Business rules*, *Security* and *Implementation notes*. No extra features and no drive-by refactors (`CLAUDE.md` §2–3).
4. **Gate.** Run `just check`. Everything must pass. Never weaken, skip or delete a test. An environment gap is recorded as `environment-limited` with the command and the error (D-52).
5. **Contracts.** Regenerate only what the task names (OpenAPI, `sqlx` metadata, i18n catalogues). `just spec` reports any drift.
6. **Report.** Fill in §4 and tick the Definition of done. The Definition of done plus the task's *Security* section is the self-review; there is no separate pass.
7. **Commit and push.** Set `status: review` and commit with a Conventional Commit containing `[T-nnn]`, signed off with `git commit -s` (`COMMIT_CONVENTION.md`). In a D-57 run, push to `dev`. A push is fast-forward only: if it is rejected, fetch, rebase your own unpushed commit onto `origin/dev`, re-run step 4 if files changed, and push again. Never force-push.
8. **Changes requested.** Set `in_progress`, fix with new commits, re-run step 4, update the Report, set `review` again. Never merge your own work.

The diff budget is about 400 changed lines; lockfiles and generated files are excluded (D-55). An overrun is a Deviation in the Report, never a block.

## 3. Blocking

**In a maintainer-authorised run (D-57)** nothing technical blocks. Ambiguity, a missing value or an environment gap is decided conservatively, recorded in the Report or in `spec/decisions.md` as a `D-nn` entry, and the run continues (D-56). Only these hard stops apply, and you report them rather than work around them:

- force-push, merge, or any push to `main`;
- a personal email address, real name or secret appearing in a file, commit, issue or PR (grep before every push);
- a destructive action outside the authorised list (`AGENTS.md` rule 9, the resource guard).

**In the ordinary workflow**, block only when:

- the task and the spec disagree, and no higher-precedence document settles it (decision > spec > task);
- a business value the work needs is missing (a question, never a guess);
- a precondition fails and fixing it is outside the task;
- an acceptance criterion cannot be satisfied or tested as written.

To block: stop changing code, keep the work on the branch, and add one entry under *Questions* per issue:

```
- Q-n (implementer, YYYY-MM-DD): <what is wrong, with file:line or spec id>
  Options: (a) … (b) …
  Recommendation: (a) because …
```

Set `status: blocked`, commit `docs(tasks): block T-nnn: <reason> [T-nnn]`, and do not start a task that depends on this one. The architect answers with `Resolved (architect, YYYY-MM-DD): …`; the same commit fixes the spec and affected tasks, and the task returns to `todo`.

## 4. Report

Every task file ends with a Report in five parts, in this order. Keep each part short. "None" is valid only when it is true.

1. **Summary.** Two or three sentences: what now works.
2. **Acceptance.** One row per criterion: id → test name → `pass`, `fail` or `not run` (with the reason).
3. **Commands.** Each command with its exit code. Full output only for a command that failed or was skipped. Include the `df -h /` start and end lines.
4. **Files and deviations.** The file list comes from `git diff --stat dev` (paste it, do not retype it). Then *Deviations*: anything done differently from the task, with the reason, and any `D-nn` you added.
5. **Not verified and follow-ups.** Everything skipped or not checked, and out-of-scope ideas for the architect.
