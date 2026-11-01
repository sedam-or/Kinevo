# Security policy

## Reporting a vulnerability

**Do not open a public issue, pull request or discussion for a security problem.**

Report it privately through GitHub's private vulnerability reporting:
<https://github.com/sedam-or/Kinevo/security/advisories/new>

Include, where you can:

- the affected version or commit, and the component (crate, module, web client);
- steps to reproduce, or a proof of concept that does not touch other people's data;
- the impact you expect.

Do not include personal data, real secrets or tokens in the report. If you must show a
credential, redact it to its first four characters.

## Response targets

These are targets, not contractual guarantees (see SUPPORT.md).

| Step | Target |
|---|---|
| Acknowledge the report | 3 business days |
| Triage: confirm, rate severity, assign | 7 days |
| Fix or mitigation, critical / high / medium / low | 14 / 30 / 90 / next release days |
| Public disclosure | after a fix is released, or 90 days after triage, whichever is first; earlier only by agreement |

Severity follows CVSS v3.1 base scores. Credit is given in the advisory unless you ask otherwise.

## Supported versions

Before 1.0, only the latest release and the `main` branch receive security fixes.
From 1.0, the support window is defined in RELEASING.md.

## Scope

In scope: this repository's code, its container and release artifacts, and the
official hosted cloud once it exists.

Out of scope: third-party services, self-hosted instances configured in ways the
documentation advises against, social engineering of maintainers, and denial-of-service
testing against hosted infrastructure.

## Safe harbour

Good-faith research that follows this policy will not be pursued legally by the
maintainers. Stop testing and report as soon as you reach personal data or a
production system you do not own.

## Hardening references

The controls the project commits to are in `spec/standards/security-by-design.md`,
`spec/10-security-privacy.md` and `spec/standards/rbac.md`.
