# Kinevo — Release Management

This document is the canonical release governance contract for Kinevo OS. It
explains versioning, release channels, cadence, eligibility, the changelog,
release notes, tagging, GitHub Releases, migration policy, breaking changes,
pre-releases, security releases, rollback expectations, document cleanup, and
post-release verification.

It governs **how releases are made** and **how the repository stays
maintainable** as an open-source project. It is an ENGINEERING AUTHORITY
document, not a requirements source. Product requirements live in
`docs/SRS.md`.

---

## 1. Standards

Kinevo uses:

| Concern | Standard |
|---|---|
| Versioning | [Semantic Versioning 2.0.0](https://semver.org/) |
| Changelog | [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) |
| Commits | [Conventional Commits](https://www.conventionalcommits.org/en/v1.0.0/) |

---

## 2. Versioning standard

Canonical format:

```text
MAJOR.MINOR.PATCH
```

Examples: `0.1.0`, `0.2.1`, `0.3.0-alpha.1`, `0.3.0-beta.1`, `1.0.0`, `2.0.0`.

Git tags use the conventional `v` prefix (`v0.5.0`, `v1.0.0`); the underlying
semantic version remains `0.5.0`, `1.0.0`.

### 2.1 Version phase policy

Kinevo remains in active product development and stays on `0.y.z` until the
public API, database migration policy, core scheduling model, offline
synchronization contract, and self-hosted operational contract are sufficiently
stable. Do not rush to `1.0.0`.

`1.0.0` represents a **stable public contract** (API, domain behavior, migration
expectations, deployment contract, offline behavior, documentation) — not merely
"the app looks complete".

### 2.2 Version bump policy

- **PATCH (`x.y.Z`)** — backward-compatible corrections: bug fixes, security
  patches, validation/UI/scheduler fixes that do not change public behavior,
  deployment fixes. Not for new public capability.
- **MINOR (`x.Y.z`)** — backward-compatible functionality: new UI capability,
  new optional endpoint, new import format, new optional AI proposal type. PATCH
  resets to 0.
- **MAJOR (`X.y.z`)** — backward-incompatible change: API contract change,
  endpoint removal, removal/renaming of publicly consumed fields, breaking
  authentication/offline/config/deployment/adapter/domain semantics, or a
  breaking migration expectation.

Classification is based on **public contract impact**, never on code size. When
multiple changes land together, select the **highest** compatibility impact (do
not average them).

### 2.3 Version source of truth

The canonical application version is derived from the **latest release tag**
(`v*`). There is intentionally no committed `VERSION` file and no version field
in `composer.json` (the composer project uses tags as its source of truth) or in
`package.json` (which is `private: true`).

`scripts/check-version.sh` verifies that the declared candidate version is
consistent with the changelog and that it is a monotonic increase over the
latest tag. The SRS version (`docs/SRS.md`, currently 2.0.0) and API major
version (`v1`, see `docs/api/openapi.yaml`) are **separate contracts** and are
intentionally excluded from version-drift failure.

---

## 3. Release channels

| Channel | Meaning |
|---|---|
| Development | `main` and feature branches. Not an installable stable release. |
| Pre-release | `v0.5.0-alpha.1`, `v0.5.0-beta.1`, `v0.5.0-rc.1`. For external testing. |
| Stable | `v0.5.0`. Published only when release acceptance criteria pass. |

Recommended pre-release flow: `alpha → beta → release candidate → stable`. Not
every release requires all phases.

---

## 4. Release cadence

Releases are **feature-driven**, not calendar-driven. Release when a coherent
feature set is complete, verification passes, migration is safe, and docs are
ready. Do not release every merged PR. Security releases may be out-of-band and
immediate.

---

## 5. What gets released

A release is a curated snapshot:

```text
source code snapshot
+ documented version
+ database migrations
+ configuration templates
+ build/deployment artifacts
+ release notes
+ migration notes
+ security notes
+ license/provenance state
+ tests/verification state
```

A release must let a fresh user clone/download, deploy, migrate, configure, run,
and upgrade per `docs/deployment.md`.

### 5.1 What does NOT belong in a release

Temporary spikes, local AI benchmarks, experimental prompts, generated scratch
code, private dev logs, local model caches, temporary screenshots, debug dumps,
and personal environment files. These are development-only and must not be
packaged as official product artifacts.

---

## 6. Development artifact classification

Every repository document/script belongs to exactly one classification:

| Class | Meaning | Examples |
|---|---|---|
| A. PRODUCT AUTHORITY | Normative requirements | `docs/SRS.md` |
| B. ENGINEERING AUTHORITY | Architecture/domain contracts | `docs/architecture.md`, `docs/domain-model.md`, `docs/release-management.md` |
| C. EXECUTION CONTROL | Task execution state | `TASK.md` |
| D. PUBLIC COMMUNITY DOCUMENT | Contributor-facing | `CONTRIBUTING.md`, `README.md`, `SECURITY.md` |
| E. DEVELOPMENT TOOLING | Automation | `scripts/*`, `Makefile` |
| F. TEMPORARY / EXPERIMENTAL | Spikes, prompts, scratch | must be archived or removed |

Development artifacts MUST NEVER silently outrank authoritative documents. See
the normative source hierarchy in `AGENTS.md`.

---

## 7. Changelog

`CHANGELOG.md` follows Keep a Changelog. It MUST open with a statement that the
project follows Semantic Versioning, and it tracks application versions only
(SRS/API versions are tracked separately).

### 7.1 Unreleased section

`## [Unreleased]` is the staging area for the next release. Feature-complete,
verified changes are added here during development. On release, `Unreleased`
becomes `## [0.x.y] - YYYY-MM-DD`, and a new empty `Unreleased` section is
created above it.

### 7.2 Categories

Use only the categories that contain meaningful changes: `Added`, `Changed`,
`Deprecated`, `Removed`, `Fixed`, `Security`. Do not generate empty headings in
released versions.

### 7.3 Entry rule and granularity

Changelog entries describe **user/developer-visible outcomes**, not internal
commits. Do not dump git history. Combine related implementation tasks into a
single user-facing entry. The changelog is for users and contributors, not the
internal task board.

`TASK.md` and `CHANGELOG.md` are separate documents with separate purposes and
MUST NOT be merged.

---

## 8. Commits

Use Conventional Commits. Common types: `feat`, `fix`, `docs`, `refactor`,
`test`, `build`, `ci`, `chore`, `perf`, `security`. Use scopes when useful
(`schedule`, `tasks`, `goals`, `knowledge`, `canvas`, `offline`, `ai`, `auth`,
`api`, `deploy`, `release`, `docs`).

Conventional Commits assist automation, but **never let raw commit messages
become the public release notes without curation.** Classify commits into
user-visible feature / bug fix / security / documentation / internal
engineering; only relevant categories appear in release notes.

---

## 9. Release process

A release follows:

```text
Implementation
→ TASK verified
→ CHANGELOG Unreleased updated
→ Release candidate decision
→ Version bump
→ Build
→ Test
→ Security checks
→ Migration verification
→ Documentation consistency check
→ Git tag
→ GitHub Release
→ Post-release verification
```

### 9.1 Release eligibility

A release candidate must satisfy all applicable gates: all release-scoped P0
tasks DONE, no blocking issues, CI green, backend/frontend tests, typecheck,
lint, PHPStan, security scan, secret scan, OpenAPI validation, doc links valid,
migrations tested, production Docker build succeeds, E2E golden path passes,
release notes and changelog prepared, version verified.

Do not release merely because "the code looks ready".

### 9.2 Release scope

Before preparing a release, define the release scope: target version, included
tasks, included features, breaking changes, database changes, API changes,
security changes, migration notes, and known limitations. A release must not
silently contain partially-completed features.

### 9.3 Tagging

Use **annotated** Git tags: `v0.5.0`. Never move an already-published tag. If an
error is found after release, publish a **new** version; do not rewrite an
immutable release or force-push a tag.

### 9.4 GitHub Release

A GitHub Release corresponds to an immutable Git tag (see
`.github/workflows/release.yml`) and contains: version, release notes, source
code reference, optional deployment artifacts, and upgrade notes. Do not create
a GitHub Release from an arbitrary untagged commit.

---

## 10. Release notes

Each GitHub Release MUST contain human-readable release notes. Sections
(include only applicable ones):

```text
Highlights
Added
Changed
Fixed
Security
Database / Migration
Breaking Changes
Upgrade Instructions
Known Issues
```

Release notes link to relevant documentation, issues, PRs, migration notes, and
public ADRs. Release notes are a **curated narrative** for one release, distinct
from the long-lived chronological changelog.

---

## 11. Database migration and API versioning

- A database migration does **not** automatically mean MAJOR. Classify by
  compatibility: a backward-compatible migration is MINOR/PATCH; a breaking
  migration (destructive column removal, incompatible data transformation,
  upgrade path requiring manual intervention) is MAJOR. Document upgrade steps.
- The public API lives under `/api/v1`. Do not create `/api/v2` merely because
  an internal implementation changed. Create a new API major version only for
  genuinely incompatible public API changes. Document deprecations before
  removal when practical.
- SRS version, application version, and API major version are independent
  contracts; see `docs/compatibility.md`.

---

## 12. Security releases

Security vulnerabilities may trigger an immediate PATCH release. If the
vulnerability changes a public security contract or requires an incompatible
mitigation, classify accordingly. Security releases MUST contain a security
summary, affected versions, fixed version, and upgrade recommendation. Do not
disclose exploit details before coordinated disclosure is appropriate.

---

## 13. Rollback / correction

Never rewrite an already-published Git tag. If a release is defective:

```text
identify problem → mark release affected if necessary → prepare corrected
release → publish new version
```

e.g. `v0.5.0` (bad) → `v0.5.1` (corrected). Use release notes to explain.

---

## 14. Post-release process

After publishing: Git tag → GitHub Release → CI/release workflow → artifact
verification → deployment smoke test → changelog check → start a new Unreleased
section. Then `TASK.md` records which release contains completed work (`Release:
v0.5.0` per task).

---

## 15. Release automation

The repository provides these gates (all safe — they never publish by
themselves):

| Make target | Script | Purpose |
|---|---|---|
| `make version-check` | `scripts/check-version.sh` | Validate candidate version + monotonic increase + changelog consistency |
| `make changelog-check` | `scripts/check-changelog.sh` | Validate Keep a Changelog structure |
| `make release-check` | `scripts/release-dry-run.sh` | Full dry-run readiness gate |
| `make release-dry-run VERSION=...` | `scripts/release-dry-run.sh` | Same, with explicit version |
| `make release-prepare VERSION=...` | (manual) | Prepare steps only; publishing is a deliberate manual action |

There is deliberately **no** `make release` that publishes automatically.
Publishing a tag + GitHub Release is a deliberate maintainer action (the
`release.yml` workflow only responds to an already-created `v*` tag).

---

## 16. Documentation hygiene

### 16.1 Document lifecycle

Every development document has one lifecycle state: ACTIVE, AUTHORITATIVE,
REFERENCE, TEMPORARY, DEPRECATED, or ARCHIVED. Preserve historical decision
context in ADRs, the changelog, release notes, and git history — not in random
markdown files, old prompts, or obsolete notes.

### 16.2 Cleanup moments

- **Cleanup A** — after an architectural decision: immediately retire obsolete
  architecture docs; promote the decision to an ADR and `docs/architecture.md`.
- **Cleanup B** — before a release candidate: remove temporary scripts, stale
  implementation notes, obsolete prompts, and duplicate architecture docs.
- **Cleanup C** — after a stable release: broader hygiene (archive completed
  task material, review `AGENTS.md`, `README.md`, release docs, examples,
  dependency ledger, known issues).

### 16.3 AGENTS.md

`AGENTS.md` is not a permanent scratchpad. It contains only current operating
rules, conventions, the source-of-truth hierarchy, safety constraints, and
testing requirements. Historical implementation notes, completed migration
instructions, temporary debugging info, old architecture proposals, obsolete
benchmarks, and old task-specific instructions are removed and, if of long-term
value, moved to git history, ADRs, the changelog, release notes, or
`docs/archive`.

### 16.4 TASK.md

`TASK.md` is a living execution board. Do not delete completed tasks; retain
`DONE` for traceability. When it grows too large to be usable, archive with a
documented policy (e.g. `docs/tasks/`). Do not archive merely for aesthetics.

### 16.5 Spikes and prompts

Architecture spikes follow: Spike → Experiment → Decision → ADR →
`architecture.md` → Spike obsolete. Retain reusable technical proof as reference;
otherwise archive or remove. Do not let a spike and `architecture.md` become
competing sources. Development prompts are not automatic project documentation;
promote permanent rules to `AGENTS.md`, architectural decisions to ADRs, product
requirements to `docs/SRS.md`, and historical content to git history/archive.

### 16.6 Delete-or-promote

Before deleting a development artifact ask: does it contain authoritative
information, historical decision context, is it already in an ADR, is it
referenced elsewhere, is it needed by CI, or is it required for reproducibility?
If no → remove. If yes → promote to the appropriate authority.

---

## 17. Compatibility matrix

See `docs/compatibility.md` for the verified mapping of application version →
SRS version → API version → migration head. Only document verified releases; do
not invent future values.

---

## 18. Release checklist

See `.github/PULL_REQUEST_TEMPLATE.md` and the Definition of Done in
`AGENTS.md`. A release is only DONE when all eligibility gates pass and the
release scope, changelog, release notes, version, tag, and GitHub Release are
verified consistent with the exact source snapshot.
