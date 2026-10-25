# AGENTS.md

> Interim version (Phase 1, archive). The full agent contract lands with `WORKFLOW.md` in the
> spec phase. Until then these rules bind every agent and human.

## Binding rules
1. **`CLAUDE.md` applies to every change** by any agent or human: code, docs, tasks, CI and brand files.
   It is vendored verbatim (D-23) and must not be edited.
2. **Never open, unzip, list or read `legacy/kinevo-legacy-v0.zip`.** It exists only as an
   archive. To cite old behaviour, use `legacy-v0:<path>` and read it with
   `git show legacy-v0:<path>`.
3. Decisions live in `spec/decisions.md` (D-nn) and `spec/adr/`. Do not re-decide them.
4. No application code is written before a task file in `tasks/` authorises it (D-32).

## Legacy archive (D-06)
| Item | Value |
|---|---|
| Tag / branch | `legacy-v0` / `archive/legacy-v0` → commit `c3bab8c9b557a0873550627c3673f7a7c644fcdf` |
| In-repo ZIP | `legacy/kinevo-legacy-v0.zip` — tracked tree minus vendored third-party `vendor/`; checksum in `legacy/SHA256SUMS` |
| Off-repo | git bundle `kinevo-legacy-v0.bundle` (sha256 `3efc0f08…6bce`) and tarball `kinevo-legacy-v0-src.tar.gz` (sha256 `1c7d048f…b911`), kept by the maintainer outside the repository |
| License | `legacy-v0` remains MIT; everything after it is AGPL-3.0-only (D-04) |
| Baseline | `spec/00-verdict.md` §1 |
