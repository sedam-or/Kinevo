# Support

## Where documentation lives

All project documentation lives in the repository under `docs/`:

- `docs/SRS.md` — requirements (normative).
- `docs/architecture.md` — technical architecture.
- `docs/domain-model.md` — domain concepts and invariants.
- `docs/scheduling-engine.md` — scheduling contract.
- `docs/deployment.md` — deployment and operations.
- `docs/environment.md` — environment configuration and secrets.
- `docs/implementation-status.md` — current progress mirror.

`README.md` is a navigation surface; `AGENTS.md` defines the operating contract
for AI coding agents.

## Troubleshooting

- Check the Docker stack: `make logs`.
- Run repository validation: `make doctor` (validate + status).
- Check the environment documentation before touching configuration.
- Migrations are the schema authority; run `make migrate`.

## Where to ask questions

Prefer public, searchable channels so answers help others:

- **GitHub Discussions** — general questions and design discussions.
- **GitHub Issues** — bugs and feature requests (see templates below).

## Where to report bugs

Open a **GitHub Issue** using the bug report template. Include:

- Environment.
- Application version.
- SRS version (from `docs/SRS.md`).
- Steps to reproduce.
- Expected vs actual behavior.
- Logs **without secrets**.
- Screenshots when appropriate.

## Where to request features

Open a **GitHub Issue** using the feature request template. Describe the
problem, the proposed outcome, the current workaround, and the affected domain.

## What should NOT go into GitHub Issues

- **Security vulnerabilities** — use the private disclosure flow in
  `SECURITY.md`. Never post secrets, tokens, or passwords in issues.
- **Confidential personal data** — private notes, private attachments, or
  production database dumps.
- **Support for other products** — issues here cover LIFESYNC OS only.

## License

LIFESYNC OS is MIT-licensed. See `LICENSE`.