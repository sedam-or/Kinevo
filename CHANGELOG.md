# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog 1.1.0](https://keepachangelog.com/en/1.1.0/), and the project uses
[Semantic Versioning](https://semver.org/) (see RELEASING.md).

This file is maintained by release-please from Conventional Commit subjects. Do not hand-edit
released sections. Wording may be improved in the release pull request before it is merged.

## [Unreleased]

### Changed

- Repository licence set to AGPL-3.0-only, as recorded in D-04 and ADR-0002. The previous
  `LICENSE` text was MIT and did not match the accepted decision. `legacy-v0` remains MIT.
- Governance, security, contribution and release documents added (see `CONTRIBUTING.md`,
  `SECURITY.md`, `GOVERNANCE.md`, `RELEASING.md`).
- Development process simplified (`WORKFLOW.md`): no separate task-start commit, one gate
  (`just check`), and a shorter report format.
