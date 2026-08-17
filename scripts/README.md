# LIFESYNC Repository Bootstrap Utilities

## Purpose

These scripts convert the LIFESYNC Documentation Master Pack into the repository structure while preserving the document boundaries defined by `## <path>` headings.

## Commands

```bash
make setup
make validate
make status
make doctor
```

For a clean overwrite of generated documentation:

```bash
make setup-force
```

Preview without writing:

```bash
make dry-run
```

## Important behavior

- `bootstrap-docs.sh` is idempotent by default: existing files are never overwritten.
- `--force` is explicit and required for replacement.
- `docs/SRS.md` can be replaced with the authoritative SRS passed through `--srs`.
- Directory sections such as `server/` and `database/migrations/` are materialized as directories.
- No application source code is fabricated by this utility.
- `validate-repo.sh` validates the agreed documentation and repository skeleton.
- `status.sh` reads task status from `TASK.md`; it does not mutate task state.
