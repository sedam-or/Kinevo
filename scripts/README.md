# LIFESYNC Repository Bootstrap & Validation Utilities

## Purpose

These scripts convert the LIFESYNC Documentation Master Pack into the repository
structure while preserving the document boundaries defined by `## <path>`
headings, then validate that the repository stays structurally consistent.

## Commands

```bash
make setup          # bootstrap docs from the master pack (idempotent)
make validate       # repository structure validation
make status         # task board summary
make doctor         # validate + status
make secrets        # committed-secret scan
make check-links    # markdown link integrity
make check-openapi  # OpenAPI structure validation
```

For a clean overwrite of generated documentation:

```bash
make setup-force
```

Preview without writing:

```bash
make dry-run
```

## Scripts

| Script | Purpose |
|---|---|
| `bootstrap-docs.sh` | Materialize `docs/` from the master pack (idempotent, `--force` to replace). |
| `validate-repo.sh` | Assert required files/dirs exist (incl. open-source governance files). |
| `status.sh` | Read task status from `TASK.md` (read-only). |
| `check-secrets.sh` | Scan for committed secrets (APP_KEY, tokens, credentials). |
| `check-doc-links.sh` | Verify relative markdown links resolve to real files. |
| `check-openapi.sh` | Verify `docs/api/openapi.yaml` has required structure. |

## Important behavior

- `bootstrap-docs.sh` is idempotent by default: existing files are never
  overwritten.
- `--force` is explicit and required for replacement.
- `docs/SRS.md` can be replaced with the authoritative SRS passed through
  `--srs`.
- Directory sections such as `server/` and `database/migrations/` are
  materialized as directories.
- No application source code is fabricated by this utility.
- `validate-repo.sh` validates the agreed documentation, governance, and
  repository skeleton.
- `status.sh` reads task status from `TASK.md`; it does not mutate task state.
- CI runs `validate-repo.sh`, `check-secrets.sh`, `status.sh`, and the doc/OpenAPI
  checks.

## Adding a new check

Prefer a small standalone script in `scripts/` wired into `make ci` and the CI
workflow over inline shell in CI. Keep scripts dependency-light (POSIX sh or
python3 stdlib) so they run anywhere.
