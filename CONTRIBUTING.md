# Contributing to Kinevo

Thanks for your interest in contributing. This guide keeps contributions aligned
with the project's requirements, architecture, and governance. Please read it
before opening a pull request or issue.

## Table of contents

1. Development environment
2. Repository layout
3. Branch naming
4. Commit conventions
5. Pull request rules
6. Testing
7. Documentation changes
8. SRS change process
9. ADR process
10. Database migration rules
11. API contract rules
12. Third-party dependency rules
13. License / provenance rules
14. Security-sensitive changes
15. AI-assisted development policy
16. Definition of Done

---

## 1. Development environment

The canonical development workflow is Docker-based:

```bash
make setup       # create .env, install deps, run migrations
make up          # build + start app and PostgreSQL
make migrate     # run migrations
make test        # run the PHP test suite
make lint        # Pint style check
make analyse     # PHPStan static analysis
make validate    # repository baseline validation
```

See `docs/deployment.md` and `README.md` for details. A local PHP 8.4 +
PostgreSQL install is optional; all commands also run inside the app container.

## 2. Repository layout

```text
AGENTS.md                 AI-agent operating contract (read first)
docs/SRS.md               normative requirements (single source of truth)
docs/architecture.md      technical architecture
docs/domain-model.md      domain concepts and invariants
docs/api/openapi.yaml     API contract
database/migrations/      versioned schema migrations
server/                   Laravel modular monolith (PHP backend)
resources/                frontend assets (Vue 3 / TypeScript / Inertia)
infrastructure/           Docker and deployment profiles
scripts/                  repository automation
TASK.md                   execution board (status only, not requirements)
```

## 3. Branch naming

- Use descriptive kebab-case branch names.
- Prefix when useful: `feat/`, `fix/`, `docs/`, `refactor/`, `chore/`.
- Example: `feat/goal-aggregate`, `fix/schedule-conflict-409`.

## 4. Commit conventions

Use conventional commits:

- `feat:` new capability
- `fix:` bug fix
- `refactor:` behavior-preserving change
- `docs:` documentation only
- `chore:` tooling/maintenance
- `test:` test-only change

Reference the relevant task when applicable, e.g.
`feat: TASK-011 — Goal aggregate (FR-50)`.

## 5. Pull request rules

- Keep PRs small and focused on one task or issue.
- State explicitly: `No architecture change` **or**
  `Architecture change — ADR included`.
- Fill in every section of the PR template (SRS impact, DB/API/UX/offline/AI/
  security impact, tests, docs, migrations, third-party changes).
- Do not merge until checks pass and reviews are resolved.

### UI/UX stabilization freeze (rescue R0–R7)

- Effect: no PRs introducing new AI features, new scheduling algorithms, new
  major domain concepts, or new dependencies are accepted during the rescue
  phase. See `AGENTS.md` and `docs/design.md` §74–§103.
- Allowed: stability, usability, integration, browser correctness, visual
  consistency; work tracked via `docs/ui-audit.md` and `docs/browser-e2e.md`.
- Exemption path: P0 fixes only — data loss, auth/save failure, core feature
  blocked, canvas crash, offline mutation lost — classified per
  `docs/ui-audit.md` §3 and logged there (§6) before the fix lands, with group
  approval. P1+ waits.
- The freeze lifts only when the rescue phase (TASK-R7) completes.

## 6. Testing

- Backend: PHPUnit feature/unit tests live in `server/tests/`.
- Run `make test` (or `composer ci` inside `server/`).
- Add tests for new behavior and for fixed bugs.
- Do not weaken tests to make them pass.

## 7. Documentation changes

- Keep `docs/` synchronized with any behavior, API, or schema change.
- `README.md` is a navigation surface; requirements live in `docs/SRS.md`.
- Never duplicate requirements in lower-level documents.

## 8. SRS change process

- `docs/SRS.md` is the normative single source of truth.
- A requirement change MUST update: SRS version, affected FR/NFR, domain/data/API
  contracts, acceptance criteria, traceability, and migration/backward-compat
  plan.
- Do not silently edit normative SRS wording; propose the change for review.

## 9. ADR process

- Any architectural decision beyond an existing approved ADR requires a new ADR
  under `docs/adr/`.
- Each ADR records decision, context, alternatives rejected, and consequences.
- Link the ADR from the PR description and the affected docs.

## 10. Database migration rules

- Add the smallest correct migration; never edit applied migrations.
- Never delete migration history.
- `database/schema.sql` is a snapshot of the current schema, kept in sync with
  migrations, not a competing authority.
- Use `make migrate` and verify `make validate` after schema changes.

## 11. API contract rules

- Update `docs/api/openapi.yaml` whenever request/response contracts change.
- Business mutations MUST validate authorization, ownership, payload shape,
  state transitions, and idempotency semantics server-side.

## 12. Third-party dependency rules

- Prefer existing repository abstractions over new dependencies.
- Record every new runtime dependency in `docs/third-party/licenses.md`.
- Check `docs/third-party/licenses.md` before copying any external source.

## 13. License / provenance rules

- Kinevo is licensed under the MIT License (see `LICENSE`).
- Third-party components retain their own licenses and required notices.
- Never strip license headers from copied source.
- Record provenance (name, URL, version, license, modifications) for any
  copied, vendored, or embedded component.

## 14. Security-sensitive changes

- Never commit secrets, tokens, private notes, or real user data.
- Follow the disclosure policy in `SECURITY.md`.
- Authentication, authorization, ownership, and secret handling changes are
  security-sensitive: add tests and call them out in the PR.

## 15. AI-assisted development policy

- AI-generated or AI-assisted code is welcome but treated as untrusted input.
- All AI output that mutates state MUST pass schema validation, domain
  validation, and human approval where the change is material.
- AI MUST NOT directly mutate authoritative schedule state or bypass invariants.
- Review AI-proposed changes as carefully as human-written ones.

## 16. Definition of Done

A non-trivial change is done only when:

- requirement linkage exists (SRS ID);
- domain behavior exists;
- persistence/API impact is handled;
- relevant tests pass;
- UI/UX is handled where user-facing;
- offline behavior is handled where in scope;
- docs/contracts are synchronized;
- no unresolved compile/test errors remain unless documented.

When in doubt, ask. Thank you for contributing.