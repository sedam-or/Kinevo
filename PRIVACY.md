# Privacy notice

This notice describes what the software stores about people and what rights they have. It
applies to every installation of Nivaras. The operator of an installation is responsible for
its use; the hosted cloud has its own notice, published before launch (`M-CLOUD-PRIVACY`).

## What is stored

Categories follow the data classes in `spec/10-security-privacy.md` (S0–S3).

| Category | Examples | Class |
|---|---|---|
| Account | login identifier, display name, timezone, locale | S2 |
| Planning content | goals, milestones, tasks, schedules, notes, canvas content | S2 |
| Credentials | password hashes, session and refresh tokens, TOTP seeds, recovery codes | S3 (never returned by the API) |
| Operational | policies, module state, jobs, audit log, request logs without bodies, error codes, metrics | S1 |

The software does not write note contents, prompts, tokens or passwords to logs (SEC-04). The
AI features are optional and off by default. When an operator enables an AI provider, the data
sent to that provider is described in the operator's own notice.

## Why it is stored

To run the features you use: planning, scheduling, sync between your devices, and account
security. The software does not sell data and does not use planning content for advertising.

## Retention

Data is kept while the account exists. Deleting an account removes its planning content and
sessions; audit records are kept for the period set by the operator policy (`04-policies.md`).
Backups follow the operator's backup retention.

## Your rights

- **Access and export:** the settings screen exports your data (identity module, T-053).
- **Deletion:** the settings screen deletes your account and its content.
- **Correction:** you edit your profile and planning content directly.

Operators of self-hosted installations provide these rights for their users through the same
features; the software does not contact users on the operator's behalf.

## Contact

For a privacy question about a self-hosted installation, contact its operator. For the project
itself, use the channels in [`MAINTAINERS.md`](MAINTAINERS.md).
