# Kinevo — Release Compatibility Matrix

This document records the verified mapping between Kinevo **application
versions** and their associated contracts. It is maintained alongside releases.
Do **not** invent future values; only document verified releases.

The application version is distinct from the SRS version and the API major
version (see `docs/release-management.md` §2.3). The migration head is the
basename of the newest migration file at the release's commit.

| App version | SRS | API | Migration head | Min PHP | Status |
|---|---|---|---|---|---|
| 0.4.0 (historical) | 2.0.0 | v1 | — (pre-release notes) | ^8.4 | Superseded by 0.5.x |
| 0.5.x (planned) | 2.0.0 | v1 | `2026_08_19_130000_create_schedule_overrides_table.php` | ^8.4 | Next candidate |

> The 0.1.0–0.4.0 entries in `CHANGELOG.md` predate the release-management
> lifecycle and were not published as tagged GitHub Releases. The first
> canonical tagged release will be recorded here with a verified migration head.

## Notes

- Application, SRS, and API versions are independent contracts and may change
  at different rates.
- A new row is added here for every released application version, verified
  against the actual tag.
