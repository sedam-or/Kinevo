# Kinevo — Commercial Model

> STATUS: AUTHORITATIVE (P29, 2026-08-31). Commercial/product-policy authority
> under `product-constitution.md`. Migrates TARGET_DECISION_REGISTER decisions
> #22/#23/#24/#25. Technical billing implementation authority remains
> `docs/billing.md` (CURRENT_ACCURATE — plans, ledgers, firewall, downgrade
> safety) + `docs/billing-capability-matrix.md` (vendor evidence) + ADR-012/013.
> This file defines PRODUCT meaning; billing.md defines MECHANISM.

## 1. Business model

- **Kinevo Core** — genuinely open source (MIT), self-hostable. Never hollowed
  out to manufacture Cloud value.
- **Kinevo Cloud** — managed paid SaaS: hosting, managed AI allowance, sync/
  storage operations, backups.

Canonical message: **"Own the software. Or let Kinevo host it for you."**

## 2. Plans

| Plan | Meaning | Price (launch hypothesis) | Classification |
|---|---|---|---|
| Free | START / DISCOVER — full core product, honest limits | Rp0 | LOCKED value; limits = launch hypothesis |
| Pro | OPERATE — capacity + BYOK for daily driving | Rp49.900/month | LAUNCH_HYPOTHESIS (config/billing.php:36, `launch_hypothesis: true`) |
| Power | OPTIMIZE / GO DEEPER — depth, history, intelligence, convenience | Rp89.900/month | LAUNCH_HYPOTHESIS (config/billing.php:37) |

- Values are **launch hypotheses**, not immutable economic truth. No annual plan.
  No trial language. Both require separate owner decisions.
- The retired intermediate `personal` tier degrades gracefully to plan-catalog
  defaults (billing.md §downgrade safety).
- Power is a coherent combination of **capacity, depth, history, intelligence,
  convenience, personalization** — never teams/RBAC/enterprise administration.

## 3. AI commercial boundary

Canonical hybrid model: subscription value + bounded hosted AI + optional
prepaid/top-up (if later approved) + BYOK.

- **Hosted AI allowance: DECISION_REQUIRED → P33 FinOps.** Config values
  (`config/saas.php` 20/300/1000) and older doc values (20/150/500) are
  **deprecated baselines** — functional placeholders, never policy. They must not
  be published.
- **BYOK:** Free = no · Pro = yes · Power = yes (matches implementation:
  AiUsageSummaryCard, plan gating). BYOK usage never consumes hosted allowance
  (separate ledger rows, `billing_ledger = byok`) and remains subject to abuse,
  safety, and platform bounds (AiCreditGuard, request firewall).

## 4. Separated concepts (billing truth chain)

| Concept | Owner | Authority |
|---|---|---|
| PAYMENT | Midtrans (sandbox now; production flip = P33) | provider; redirects are never payment truth |
| SUBSCRIPTION | Kinevo | `subscriptions` table + Kinevo business rules |
| ENTITLEMENT | Kinevo | `config/saas.php` matrix enforced via EntitlementService — Kinevo-owned access truth |
| AI USAGE | Kinevo | AI Ledger (`ai_runs` cost columns + hosted/BYOK split) — billing truth, never the analytics provider |

Webhooks are signed + idempotent. Provider redirects never grant entitlement.

## 5. Open decisions (recorded, not guessed)

1. Production hosted-AI quota numbers — P33 (AI FinOps simulation: P50–P99 +
   abuse scenarios) before any quota lock.
2. Power exact entitlement parameters (advanced_analytics / wrapped /
   mobile_access keys reserved, currently unenforced; no storage entitlement
   exists) — evidence-driven, P33.
3. Prepaid/top-up AI credits — not approved; P33 decision.
4. Annual billing — architecturally supported, unpriced, no decision.
5. Upgrade/proration/grace semantics — recorded for P33 (billing.md §open).

## 6. Disposition note

`docs/convergence/TARGET_DECISION_REGISTER.md` decisions #22 (plan model),
#32 (price hypotheses), #24 (AI numbers not locked), #25 (Power parameters)
migrated here; the register is archived as historical evidence at P29 close.
No Stitch mockup price/number overrides this file (STITCH_OUTDATED rule,
`docs/ux/stitch-convergence-matrix.md`).
