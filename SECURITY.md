# Security Policy

## Supported versions

Kinevo is pre-1.0 software. Only the latest published release receives
security fixes. Backporting to older versions is considered on a case-by-case
basis; assume the current `main` branch and latest release are the only
supported targets.

| Version | Supported |
|---------|-----------|
| latest release / `main` | Yes |
| older releases | No |

## Reporting a vulnerability

**Do not open a public GitHub Issue for security problems.**

Report vulnerabilities privately via GitHub Security Advisories:

1. Open the repository's **Security** tab.
2. Select **Report a vulnerability**.
3. Provide the details described below.

If you cannot use the Security Advisories flow, contact the maintainers via a
private channel listed in `SUPPORT.md`. Use a GitHub Security Advisory or a
private message — never a public issue.

### What to include

- Affected component and version/commit.
- Vulnerability type and severity assessment.
- Steps to reproduce (without exposing real data).
- Impact if exploited.
- Suggested fix if you have one.
- Whether you are sharing this privately with anyone else.

### What NOT to include (in any public channel)

- Passwords, API keys, tokens, or secrets.
- Private notes or private attachments.
- Production database dumps.
- Any real personal data.

## Response workflow

1. **Acknowledgment** — the report is acknowledged privately, typically within
   a few business days.
2. **Triage** — the issue is triaged for severity and impact.
3. **Fix** — a fix is developed and tested against the relevant invariants.
4. **Release/Disclosure** — a patched version is published and the advisory is
   disclosed after users have had a reasonable window to upgrade.

We practice coordinated disclosure: we will not publicly disclose a
vulnerability before a fix is available, and we ask reporters to respect the
same window.

## Severity handling

- **Critical/High** — fixed as a priority; advisory published promptly.
- **Medium** — fixed in the next scheduled release; advisory when released.
- **Low** — fixed opportunistically; tracked as a normal issue once fixed.

## Security-sensitive areas

Treat these areas with extra scrutiny in reviews, tests, and changes:

- Authentication and token handling (Sanctum bearer tokens).
- Authorization and ownership scoping (`user_id` everywhere).
- API mutation validation (authorization, ownership, payload shape, state
  transition, idempotency).
- Secret and configuration handling (see `docs/environment.md`).
- Note and attachment content handling (privacy, XSS, file types, size limits).
- Offline operation reconciliation.
- AI structured-output validation (AI output is untrusted input).
- Log output (must never contain secrets, note contents, AI prompts, or private
  document content).

## Automated checks

The repository runs dependency, secret, and static analysis checks in CI. See
`.github/workflows/security.yml` and `scripts/check-secrets.sh` for details.