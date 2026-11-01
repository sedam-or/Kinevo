# Releasing

Maintainers follow this procedure. Agents and contributors never create or push tags.

## 1. Versioning (SemVer 2.0.0)

- The public surface is the HTTP API (`contracts/openapi.json`), the configuration reference
  (`spec/11-operations.md` §2), the database migrations, the CLI and the `kinevo` binary's
  flags. Changing any of them in an incompatible way is a breaking change.
- **Before 1.0 (`0.y.z`):** a breaking change bumps the minor version (`bump-minor-pre-major`
  in `release-please-config.json`); features bump the minor; fixes bump the patch.
- **1.0.0** is cut by a maintainer decision (decision D-nn in `spec/decisions.md`) once the
  release criteria in §5 are met.
- **After 1.0:** breaking changes need a new major version and a deprecation period (§3).

Version numbers come from Conventional Commits through release-please. Commit types are in
`COMMIT_CONVENTION.md`.

## 2. Procedure

1. On `main`, release-please keeps a release pull request open with the version bump and the
   changelog entries. It updates `Cargo.toml`, `web/package.json` and `CHANGELOG.md`.
2. Review the release pull request. Check that every breaking change has a `BREAKING CHANGE:`
   footer and that the upgrade notes exist.
3. A maintainer merges the release pull request. release-please then creates the tag and the
   GitHub release. Only a maintainer merges it.
4. Artifacts (binaries, the container image, SBOM, provenance) are produced from the tag when
   the packaging milestone exists; until then a release is source-only.

The release pull request runs under a token from `RELEASE_PLEASE_TOKEN` when that secret is
set. A pull request opened with the default `GITHUB_TOKEN` does not trigger CI (`M-RELTOKEN`).

## 3. Deprecation

- Mark the item deprecated in the changelog (`Deprecated` section) and in the API response with
  the `Deprecation` header (RFC 9745) and a `Sunset` header (RFC 8594) with the removal date.
- Keep a deprecated item working for at least one minor release before 1.0, and for at least
  six months after 1.0. Removal is a breaking change.

## 4. Support window

- **Before 1.0:** only the latest release and `main` get fixes.
- **From 1.0:** the latest minor version gets security fixes until the next minor ships, and
  the previous minor gets security fixes for three months after that. These periods are a
  default (`[ASSUMED]`) until the maintainer confirms them.

## 5. Criteria for 1.0.0

- Every milestone's integration task is `done` and its Reports list no open security finding.
- The security review named in `spec/10-security-privacy.md` is complete (`M-SEC`).
- Migrations apply on a clean database and from the previous release's schema.
- The self-host quick start and the upgrade notes are written and tested on a clean machine.
- No `[ASSUMED]` value in the policy registry is unconfirmed.

## 6. Database migrations

Migrations move forward only. There are no down migrations. Every release note states whether
the upgrade is reversible, and the reversal path is a restore from a backup taken before the
upgrade.

## 7. Third-party licences

A release includes a generated third-party licence report for the Rust and web dependencies.
Until that generator exists, the release is blocked from claiming a complete licence inventory.
