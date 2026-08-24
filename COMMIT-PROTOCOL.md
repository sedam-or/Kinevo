# Commit Protocol

Automated commit + push when a task is fully complete, with mandatory
pre-commit verification. Re-verify before every commit — never trust a
previous green run.

## Backing contract

Delegates to the rules already mandated in `AGENTS.md`, especially the
"Pre-Commit Mandatory Verification Protocol" and "Release & documentation
lifecycle" sections. This file is a shorthand operational copy; when the two
conflict, `AGENTS.md` wins (it is a higher-level governance document).

## Trigger

Run this protocol ONLY when a task is truly DONE — requirement linkage exists,
tests pass, docs/contracts synced — per the "Definition of done" in
`AGENTS.md`. Do not auto-commit mid-task.

## Mandatory pre-commit gates (must and always validate AGAIN)

Run all of these fresh, in order. A commit is FORBIDDEN if any fails. On
failure, fix the root cause and re-run the whole protocol.

1. `npm audit`
2. `npm run typecheck` and `npm run build`
3. `./vendor/bin/phpstan analyse` and `composer test` (or `make test`)

## Commit + push steps

```bash
# 0. Confirm working tree intent
git status
git diff

# 1. Manual Composer constraints (not automated here — agent judgment)
#    - Conventional Commits message: type(scope): subject (TASK-id)
#    - stage ONLY intended files; never stage secrets/notes/prompts
#    - do not merge TASK.md into CHANGELOG.md
#    - update TASK.md status only when task is actually complete

# 2. Stage intented files
git add <intended files>

# 3. Re-verify gates a second time on the exact staged content
npm audit && npm run typecheck && npm run build \
  && ./vendor/bin/phpstan analyse && composer test

# 4. Commit
git commit -m "type(scope): subject (TASK-id)"

# 5. Push
git push
```

## Rules

- Run the gates at least twice (before staging intent and after final staging).
- Never weaken a test/gate to go green.
- Do not tag or push a release without explicit instruction.
- Do not commit to `main` if the project/branch rules require a feature branch
  (see `CONTRIBUTING.md`); follow branch rules when present.
- `TASK.md.save` is untracked scratch — do not commit it unless intentional.
