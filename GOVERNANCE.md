# Governance

Nivaras is run by its maintainers in the open. This document says who decides what,
how changes are proposed, and how the project keeps going if a maintainer steps away.

## 1. Roles

| Role | What it means | How you get it |
|---|---|---|
| Contributor | anyone who opens an issue, reviews, or sends a pull request | no approval needed |
| Maintainer | merges pull requests, cuts releases, decides disputed questions, administers the repository | named in `MAINTAINERS.md`, by the existing maintainers |

The current maintainer list and the backup slot are in [`MAINTAINERS.md`](MAINTAINERS.md).

## 2. Decisions

- Day-to-day changes: lazy consensus. A pull request with one approving maintainer and green
  `ci-ok` merges.
- Product, architecture, licence, security and process decisions are recorded in
  `spec/decisions.md` (D-nn) or `spec/adr/` (accepted ADRs). A decision is not reversed
  silently; a new entry names the decision it supersedes.
- When contributors disagree, the maintainers decide and record the reason in the decision log.
  Technical questions are decided on the evidence in the repository, not by seniority.

## 3. Proposals (RFC)

Use an issue labelled `rfc` for any change to behaviour, public interfaces, data formats,
licensing or governance.

1. Describe the problem, the proposal, the alternatives you rejected and the risks.
2. Comment period: **14 days** from the issue opening (a default; a maintainer may shorten
   it for a small change or extend it for a large one, and records the change).
3. A maintainer records the outcome as a `D-nn` entry or an ADR, and links it from the issue.
   Accepted proposals then go to a pull request that updates the spec.

No separate RFC repository is kept: the issue is the record.

## 4. Releases

Releases are cut by maintainers from `main` using the procedure in [`RELEASING.md`](RELEASING.md).
Agents and contributors never create or push tags.

## 5. Continuity (bus factor)

The project must not depend on one person's memory or one person's accounts.

- **Accounts:** at least two maintainers hold administrative access to the repository and to
  any hosting account. Until a second maintainer is named, this is an open maintainer action
  (`M-BUS` in `spec/decisions.md`).
- **Secrets:** no secret is stored in the repository (SECURITY.md, AGENTS.md rule 8). Deployment
  and signing secrets are kept in the hosting platform's secret store, with a recovery procedure
  written down by the maintainers and held by at least two people.
- **Documentation:** the repository is self-describing. `AGENTS.md`, `WORKFLOW.md`, the spec and
  the decision log let a new maintainer continue without the original author.
- **Forks:** the AGPL licence guarantees that anyone can continue the project if it stops. The
  trademark policy (TRADEMARK.md) governs only the name and logo.

## 6. Changing this document

Governance changes follow §3 and need approval from every maintainer.
