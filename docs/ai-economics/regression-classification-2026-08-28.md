# Commercial Pricing Delta — Regression Search Classification

Status: ACTIVE · Evidence date: 2026-08-28 · Source: `revisi-finance.md` §21

Every repository occurrence of the old commercial assumptions was searched and
classified. Migration history and changelog history are PRESERVE (never
rewritten). Active commercial config and tests are REPLACE.

## Old values searched

| Pattern | Found | Classification |
|---|---|---|
| `34.900` / `3_490_000` (old Pro) | billing.php, nativephp mirror, billing tests | REPLACE (done 2026-08-28) |
| `49.900` / `4_990_000` (old Power / current Pro) | billing.php, billing tests | REPLACE (done 2026-08-28) |
| `20 credits` / `150 credits` / `500 credits` | master prompt §2.2 (historical) | PRESERVE + DEPRECATED BASELINE note |
| `25% markup` | none in repo (only referenced in delta docs as an anti-pattern) | n/a |
| workspace counts `2/10/25` | config/saas.php + mirror | REPLACE (done — 1/5/15 per delta §2) |

## Replacements applied (D-001/D-002)

- `server/config/billing.php` — `pro` → `4_990_000` (IDR 49,900), `power` → `8_990_000` (IDR 89,900);
  comment declares LOCKED launch hypotheses (revisi-finance §0).
- `server/config/saas.php` — `max_workspaces` 1/5/15 (Free/Pro/Power); `ai_credits` values kept
  functional but reclassified DEPRECATED BASELINE in the header docblock (never final policy until
  FinOps simulation — D-004 produced the first evidence run).
- `server/nativephp/android/laravel/config/{billing,saas}.php` — mirrored (git-ignored build
  artifact; synced in the working copy).
- `server/app/Application/Saas/GetPlanOverviewUseCase.php` — exposes `pricing` (locked prices +
  `launch_hypothesis`) and `catalog` (all tiers, from config) so the UI never hardcodes numbers.
- `server/tests/Feature/Api/SaasApiTest.php` — new price assertions + Free=1 workspace limit +
  downgrade-preservation test (Power/Pro -> Free preserves data, blocks new usage).
- `server/tests/Feature/BillingCheckoutTest.php`, `BillingSubscriptionReadTest.php` — price fixtures
  to 4_990_000 / 8_990_000.
- `server/tests/Feature/Api/AiUsageTest.php` — Power BYOK acceptance + per-request budget-gate tests.
- `server/resources/js/saas/PlanSettingsView.vue` — no hardcoded numbers; prices/entitlements from
  API; tier positioning copy + Power Rp40,000 gap computed; explicit upgrade/downgrade CTAs;
  launch-hypothesis footnote.
- `server/resources/js/ai/AiUsageSummaryCard.vue` — copy aligned to the allowance model + next-step
  guidance.

## Preserved (historical or intentionally kept)

- `docs/adr/ADR-013-product-tiers-pricing.md` — decision history; SUPERSEDED (prices only) banner
  added, body untouched.
- `KINEVO_POST_P27_MASTER_EXECUTION_PROMPT.md` §2.1/§2.2 — original numbers + explicit
  DELTA banner (2026-08-28) atop §2; §2 bodies kept as recorded baseline.
- `TASK.md` old phase execution records (P24 lines referencing the 2026-08-26 prices) — historical
  execution log, preserved.
- `docs/billing.md` § sandbox E2E evidence — `4_900_000`/`personal` plan rows are the legacy tier
  (retired) sandbox evidence, unrelated to Pro/Power; preserved.
- `server/tests/Feature/Billing{Webhook,CancelResume,Refund}Test.php` — `4_900_000` belongs to the
  legacy `personal` plan fixtures; preserved.
- `database/migrations/` — untouched by design (migration history is never edited).
- `CHANGELOG.md` — historical releases preserved.

## Residual obligations

- Provider catalog prices (`config/ai.php cost.catalog`) remain empty; the D-004 evidence run used a
  `--price-override` with public list prices, `verified=false` — must be confirmed against official
  sources before production.
- AI quota numbers remain DECISION_REQUIRED (D-004/D-008); the simulation report is the starting
  economics shape, not the locked answer.