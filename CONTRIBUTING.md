# Contributing to Nivaras

Thank you for helping. This file is short on purpose: it lists what you must do. The
rules behind it are in [`GOVERNANCE.md`](GOVERNANCE.md), [`COMMIT_CONVENTION.md`](COMMIT_CONVENTION.md)
and [`BRANCHING.md`](BRANCHING.md).

By contributing you agree to the [Code of Conduct](CODE_OF_CONDUCT.md).

## Before you start

- **Bugs and small fixes:** open an issue, then a pull request.
- **Features and behaviour changes:** open an issue labelled `rfc` first (GOVERNANCE.md §3).
  Wait for the comment period before you write code.
- **Security problems:** never a public issue. See [`SECURITY.md`](SECURITY.md).

## Set up

```sh
scripts/setup-dev.sh --all     # toolchain and tools; idempotent
just setup                     # project dependencies
just check                     # the gate CI runs; must pass before you open a PR
```

`just check` is the single gate. CI runs the same command, so a green local run is the
expected result.

## Pull requests

1. Fork the repository and branch from `dev`.
2. Target **`dev`**. Pull requests to `main` are closed.
3. Keep one logical change per pull request. Link the issue with `Refs #<n>`.
4. Use the [PR template](.github/PULL_REQUEST_TEMPLATE.md). The title must be a
   Conventional Commit, because CI checks it.
5. A maintainer named in [`.github/CODEOWNERS`](.github/CODEOWNERS) reviews. Maintainers
   merge; contributors never merge their own pull requests.

## Commits

- [Conventional Commits 1.0.0](https://www.conventionalcommits.org/en/v1.0.0/), subject at most 72 characters.
- Every commit carries a sign-off: `git commit -s`. It certifies the
  [Developer Certificate of Origin](https://developercert.org/). CI rejects commits without one.
- Use an email address you are happy to make public. The GitHub noreply address is recommended.
- Full rules and the type table: [`COMMIT_CONVENTION.md`](COMMIT_CONVENTION.md).

## Specs and decisions

Behaviour is defined in `spec/`. A change that alters behaviour updates the spec in the same
pull request. A change that reverses or refines a decision adds a new `D-nn` entry in
`spec/decisions.md` that names what it supersedes. Architecture changes use an ADR in
`spec/adr/`.

There is no documentation website before v1.0 (D-144). The Markdown files in the repository are
the documentation.

## Licence

The project is AGPL-3.0-only ([`LICENSE`](LICENSE), D-04). Your contribution is accepted
under the same licence, through the sign-off above. No CLA is required.

## Translations

- English (`web/src/i18n/en.json`) is the source of truth.
- Indonesian (`web/src/i18n/id.json`) and later locales must have the same keys.
  `pnpm --dir web check:i18n` enforces key parity and runs in `just lint`.
- A missing key falls back to English at runtime; do not add an English string to a
  locale file as a translation.

## AI-assisted contributions

You may use AI tools. You remain responsible for every line you submit: you must understand
it, have tested it, and be able to answer review questions about it. Agent rules are in
[`AGENTS.md`](AGENTS.md). Add a `Co-Authored-By:` trailer when the tool's policy asks for it.

## Questions

Open a discussion or an issue. Response times are best-effort; see [`SUPPORT.md`](SUPPORT.md).
