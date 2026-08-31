> Planned phase document (R0 rebaseline 2026-08-31). Authority: docs/roadmap/KINEVO_MASTER_EXECUTION_PROGRAM.md.
> Detailed microtasks are authored at phase activation — never fabricated in advance.

# P37 — Production Security & Privacy Gate
Status: PLANNED · GATE PHASE (not a feature bucket) · Depends On: P36 gate

## Objective
Prove security & privacy before production traffic.

## Scope / major workstreams
- Security review surfaces: authentication, authorization, Workspace isolation, offline replay, AI context/keys, billing webhooks, email, assets/uploads, mobile, operator surfaces, logging, observability, backups, secrets, third-party processors.
- Required tests: cross-user access, cross-workspace access, long-running worker leakage, IDOR, mass assignment, rate limits, replay, webhook spoofing, file validation, path traversal, secret exposure, log redaction, XSS, CSRF, CSP, dependency audit, mobile secure storage.
- Privacy: document what Kinevo stores/why/retention/AI data flow/analytics flow/email/billing/object storage/deletion/export/account closure. Implement account deletion + full export if outstanding. No unsupported privacy claim.

## Gate
No P0/P1 known security blocker; privacy docs match implementation; account deletion/export works; third-party data processing documented; security tests green. STOP.

## Known open decisions
Data residency statement; retention schedule numbers.
