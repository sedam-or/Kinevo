# 10 — Security and privacy

Depends on: `standards/security-by-design.md`, `standards/auth.md`, `06-domain-model.md`. Prefix: `PRV` (privacy) and threat ids `THR`.
This document is engineering guidance, **not legal advice**. Anything touching regulation (Indonesia's Personal Data Protection Law, GDPR, app-store policies) needs review by a qualified person before the hosted cloud launches (maintainer action M-LEGAL in `15-cloud.md`).

## 1. Assets and trust boundaries

| Asset | Class | Where |
|---|---|---|
| Account identity (email, display name, timezone) | S2 | `accounts` |
| Credentials (password hash, tokens, TOTP seed, recovery codes) | S3 | `accounts`, `refresh_*`, `secrets`, `recovery_codes` |
| Plans and notes (goals, tasks, notes, canvases, schedule, landscape) | S2 | tenant tables |
| Provider/payment secrets | S3 | `secrets` (instance) |
| Operational data (policies, modules, jobs, audit) | S1 | instance tables |

```
 [Browser/Android WebView] ──TLS──▶ [Reverse proxy (optional)] ──▶ [kinevo process] ──▶ [PostgreSQL]
        │ (untrusted)                       │ (semi-trusted)         │ (trusted core)      │ (trusted, same operator)
        └── local storage: access token in memory only; IndexedDB cache/queue (S2, device-level protection)
 [kinevo process] ──TLS──▶ [AI provider] (optional, operator/user chosen)   [Payment gateway] (cloud)   [SMTP] (optional)
```
Boundaries: **B1** client↔server (everything from the client is hostile input); **B2** server↔database (RLS limits blast radius of server bugs); **B3** server↔external providers (outbound; SSRF and data-minimisation controls); **B4** operator↔instance (the operator can read the database; self-host operator = user; cloud operator = Nivaras, see §4).

## 2. Threat model (STRIDE per component)

| ID | Component | Threat | Control | Test |
|---|---|---|---|---|
| THR-01 | Auth endpoints | Spoofing: credential stuffing / guessing | argon2id, lockout, rate limits, constant-work, TOTP | SEC-AC-03, 07 |
| THR-02 | Refresh cookie | Spoofing/Tampering: theft, CSRF, replay | HttpOnly+Secure+SameSite=Strict, Origin + header check, rotation + reuse detection | SEC-AC-04, 05 |
| THR-03 | Access token | Spoofing: XSS token theft | memory-only token, CSP, no HTML injection, 10-min TTL | SEC-AC-01b, 36 |
| THR-04 | API | Elevation: IDOR / cross-tenant access | RLS forced, tenant from context only, 404 on foreign ids | SEC-AC-01, 12–15, 21, 24, 28, 33, 37, 40, 44 |
| THR-05 | API | Tampering: mass assignment | `deny_unknown_fields`, server-computed fields | SEC-AC-14 |
| THR-06 | API | DoS: oversized/expensive requests | stream size limits, per-route caps, rate limits, bounded parsers, argon2 concurrency cap | SEC-AC-16, 27, 31, 39 |
| THR-07 | Setup | Elevation: instance takeover on fresh public deploy | setup token, lockout | SEC-AC-08 |
| THR-08 | Admin endpoints | Elevation: owner → admin | permission matrix, route descriptors | SEC-AC-09 |
| THR-09 | Admin endpoints | Information disclosure: admin reads user content | admin APIs expose no S2 content; DB access is the operator's, documented (§4) | SEC-AC-10 |
| THR-10 | Logs/metrics | Information disclosure | SEC-04 allow-list, typed log structs | SEC-AC-45 |
| THR-11 | Secrets at rest | Information disclosure (DB dump) | AEAD with external master key, hashes for tokens/passwords | SEC-AC-46 |
| THR-12 | Outbound AI call | SSRF / data exfiltration | SEC-15, data minimisation AI-FR-01, preview of what is sent | SEC-AC-30 |
| THR-13 | AI output | Tampering/XSS via model output | schema+domain validation, text rendering, human approval | SEC-AC-31–32 |
| THR-14 | ICS/notes/canvas import | Tampering/DoS/XSS | bounded parsers, allow-lists, re-serialisation | SEC-AC-16, 17, 36, 38 |
| THR-15 | Webhooks | Spoofing/replay | signature, timestamp window, dedupe | SEC-AC-41, 42 |
| THR-16 | Sync | Repudiation/duplication/replay | ledger, hash compare, per-account scope | SEC-AC-25–29 |
| THR-17 | Offline queue on device | Information disclosure (lost device) | device lock responsibility of the user; queue cleared on account switch; no tokens in IndexedDB; documented residual risk | manual review |
| THR-18 | Supply chain | Tampering | lockfiles, pinned actions, deny/audit, signed images + SBOM + provenance | SEC-AC-48 |
| THR-19 | Container | Elevation | non-root, read-only rootfs, no caps | SEC-AC-47 |
| THR-20 | Backups | Information disclosure | operator guidance: encrypt backups; secrets need the master key | SEC-AC-46 |
| THR-21 | Audit | Repudiation | append-only audit with request id and actor | ADM-FR-07 |
| THR-22 | Timing | Information disclosure (user enumeration) | dummy hash, identical responses, 404 for foreign | ID-FR-03 |

Residual risks (accepted, documented to operators): operator with database access can read S2 data (no end-to-end encryption in 1.0); stolen unlocked device exposes the offline cache; access tokens remain valid up to `auth.access_token.ttl` after revocation; the in-process rate limiter and grace cache assume a single node.

## 3. Privacy by design

### 3.1 Data inventory

| Data | Purpose | Retention | Subject control |
|---|---|---|---|
| Email, display name, locale, timezone, password hash | account, login, localisation | life of account | edit (profile), export, delete |
| Goals, milestones, tasks, subtasks, notes, canvases, landscape events, assignments, focus sessions | the product | life of account | export, delete, per-item delete |
| Progress events (derived) | progress views | life of account | deleted with account |
| AI proposals | review before accepting | decided/expired deleted after `ai.proposal.retention` | reject/delete |
| AI run metadata (counts, status) | limits, diagnostics | `audit.retention_days` (pruned with runs) | deleted with account |
| Audit log | security | `audit.retention_days`; entries about the account are removed on account deletion except the instance-level fact that an account was deleted | view own entries |
| Refresh token families | sessions | until expiry/revocation | list, revoke |
| IP address, user agent | rate limiting (memory only); security events store truncated IP (/24, /48) and UA family | event retention = audit retention | — |
| Billing: plan, invoices (cloud) | service | per legal retention (OWNER-05) | export; deletion limited by law |

No analytics, advertising identifiers, third-party scripts, tracking pixels or fingerprinting. No sale or sharing of personal data.

### 3.2 Requirements

| ID | Requirement | Acceptance |
|---|---|---|
| PRV-01 | Collect only what the inventory lists; any new personal-data field needs an inventory row and a purpose in the PR. | review checklist |
| PRV-02 | Export (ID-FR-12) and deletion (ID-FR-11) work without operator involvement and complete within the documented time (`identity.purge.delay` + job latency). | journey J7 |
| PRV-03 | A single strictly-necessary cookie (`__Secure-kinevo_rt`) is used; no consent banner is required for it `[counsel to confirm for cloud]`; preferences (`theme`) use `localStorage`. | review |
| PRV-04 | The "what is sent to the AI provider" preview (AI-FR-01) is available before the first AI use per session, and the settings page states which provider (instance or BYOK) receives data. | UI test |
| PRV-05 | Cloud: a published privacy notice and terms (maintainer-authored, counsel-reviewed), a subprocessor list (hosting, payment gateway, AI provider if offered, email if used), data-location statement, incident notification process (SEC-20 + operator runbook). | `15-cloud.md` checklist |
| PRV-06 | Minors: the cloud terms set a minimum age `[OWNER/COUNSEL]`; the product does not collect birth dates. | decision recorded |
| PRV-07 | Data at rest in cloud is encrypted at the storage layer (provider disk encryption) and backups are encrypted; self-host guidance recommends the same (`11-operations.md`). | ops checklist |
| PRV-08 | Support/debug access: operators must not use production S2 data for testing; fixtures use `example.test` addresses and synthetic content only (also applies to this repository: no real personal data in tests, docs or examples). | grep check `check-no-pii` in CI |
| PRV-09 | Account export is S2 and delivered only to the authenticated account; download links are not shareable (bearer-authenticated) and expire. | test |

## 4. Who can see what

| Actor | Can see | Cannot see |
|---|---|---|
| Account owner | own data | other accounts |
| `instance_admin` (via the product) | account list (email, name, status, created/last login), module/policy settings, audit, job health | any S2 content of others, through any endpoint |
| Instance operator with DB access | everything in the database except secrets without the master key | — (by design; trust the operator you choose; self-host = yourself) |
| Nivaras Cloud operator (maintainers) | as operator, under written access policy: production data access only for incident response, logged | — |
| AI provider (if used) | the minimal prompt (AI-FR-01) | notes, canvases, other goals, identity |
| Payment gateway (cloud) | what the checkout needs | task/note content |
