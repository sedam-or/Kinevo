# 15 — Nivaras Cloud (business layer)

Depends on: `modules/billing.md`, `11-operations.md`, `10-security-privacy.md`. Decisions: D-04, D-25, D-26. Prefix: `CLD`.
Nivaras Cloud is the **optional managed service**: the same AGPL image, operated by the maintainers, funded by plans.
It must never make the open-source build worse: self-hosters get every feature without limits (D-26).

## 1. What is open and what is private

| Piece | Where | Licence |
|---|---|---|
| Application (all modules incl. `billing` with provider-neutral port and adapters, off by default) | this repository | AGPL-3.0-only |
| Marketing site, docs | this repository (`docs/`, `site/` later) | AGPL (code) / CC-BY-4.0 (prose) `[owner to confirm]` |
| Cloud infrastructure-as-code, runbooks with secrets, monitoring config, support tooling | **private** repository `kinevo-cloud` | proprietary (it contains no application logic) |
| Brand: name "Nivaras", logo | this repository, subject to `TRADEMARK.md` | trademark policy (Phase 5): code is free to fork, the name/logo are not |

Rule: **no feature is cloud-only.** The cloud differs by operation (hosting, backups, managed AI allowance, support), not by code. If a feature requires infrastructure (e.g., hosted AI), the code path exists for self-hosters via their own provider.

## 2. Plans (placeholders — OWNER-01/02)

Plans are data (`billing.plans`). The spec fixes only the **shape** and the constraints:

| Constraint | Rule |
|---|---|
| A free plan always exists in cloud | yes; limits from `billing.plans` (e.g., `max_workspaces`, `ai_monthly_runs`) — values pending OWNER-01/02 |
| Paid plans | monthly/yearly intervals; price `money` in the single `billing.currency` |
| Entitlements | `max_workspaces`, `ai_monthly_runs`, `byok_allowed` only in 1.0 (BIL-FR-04) |
| Features gated by plan | none beyond the three limits; no "premium" modules |
| Data on downgrade | retained; creation limited (BIL-FR-04) |
| Trials | optional per plan (`trial_days`) |

## 3. Requirements

| ID | Requirement | Acceptance |
|---|---|---|
| CLD-01 | The cloud template (`05`) enables `sync`, `knowledge`, `canvas`, `ai`, `billing`; `auth.registration.mode=open`; billing stays disabled until OWNER-01 values exist (module refuses to enable: `billing_not_configured`). | Template test. |
| CLD-02 | Multi-tenancy is the single-schema RLS model (D-08); the cloud runs one instance per region initially; no per-tenant databases. | Architecture review. |
| CLD-03 | Hosted AI uses the instance provider (`ai.provider.*` + secret) with per-account allowance; BYOK remains available where plan allows (`byok_allowed`). Prompts/outputs are not retained beyond AI-FR-12/09. | Tests. |
| CLD-04 | Operational baseline: managed PostgreSQL with automated daily backups, WAL archiving for ≤ 1 h RPO (target), encryption at rest, restore drill monthly (OPS-06), uptime monitoring against `/readyz`, log shipping with SEC-04 fields only, alerting on `outbox_dead`, `jobs_queue_age_seconds`, error rate, certificate expiry. | Cloud runbook checklist. |
| CLD-05 | Support access to production data follows a written least-privilege policy, is time-boxed, logged and reviewed (PRV §4); no routine access. | Policy doc. |
| CLD-06 | Abuse handling: account disable (admin), rate limits, AI per-account caps, report address in `SECURITY.md`/terms; DMCA/abuse process drafted with counsel. | Docs. |
| CLD-07 | Status page and incident process: public status page, post-incident summaries for events > 30 min or any data exposure; breach notification obligations per counsel (M-LEGAL). | Docs. |
| CLD-08 | Data location and subprocessors are published (PRV-05); changes announced ≥ 30 days ahead for paying customers. | Docs. |
| CLD-09 | Terms of service, privacy notice, acceptable use, refund/cancellation policy exist, are counsel-reviewed and linked in the app footer and checkout (OWNER-06). | Legal checklist. |
| CLD-10 | Export and deletion work for cloud accounts (ID-FR-11/12) including subscription cancellation (BIL-FR-09). | Journey J7 variant. |
| CLD-11 | Pricing/plan changes never alter existing invoices (plan snapshot) and are communicated before they apply. | Billing tests. |

## 4. Maintainer actions required before public cloud (cannot be done by agents)

| ID | Action |
|---|---|
| M-LEGAL | Counsel review of privacy notice, terms, data-protection obligations (e.g., Indonesia's Personal Data Protection Law, GDPR if serving EU users), minimum age, tax/invoice obligations (OWNER-05) |
| M-ENTITY | Decide the legal entity/merchant account needed by the payment provider (OWNER-04) |
| M-DOMAIN | Domain registration, DNS, email sending domain (SPF/DKIM) if email is added |
| M-INFRA | Create hosting/DB/backup accounts; store secrets in the operator's secret manager |
| M-STORE | Google Play developer account, signing key custody (AND-09), F-Droid submission |
| M-SEC | Optional external security review of auth and isolation before 1.0 |

## 5. Business-model risks (honest list)

AGPL may reduce enterprise adoption (acceptable: the target is individuals); a hosted service competes with self-hosting on convenience, so reliability and support quality are the product; payment and tax compliance depends on jurisdiction; AI costs scale with usage and need hard caps; sustainability depends on conversion rates that are **unknown** until V5 and a pilot — no revenue figure is assumed anywhere in this specification.
