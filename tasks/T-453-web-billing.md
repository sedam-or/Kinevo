---
id: T-453
title: Build the Billing UI: plans, checkout, subscription, invoices and over-limit states
milestone: M9
kind: feature
status: todo
depends_on: [T-452, T-254]
requirements: [BIL-FR-02, BIL-FR-04, CLD-09, CLD-10, FE-04, FE-14, UXS-02, DS-A11Y-13, J8]
branch: m9-t453-web-billing
budget_lines: 400
assignee: ""
---

# T-453: Build the Billing UI: plans, checkout, subscription, invoices and over-limit states

## Goal

A cloud user can pick a plan, pay through the hosted checkout, see the real subscription state, cancel or resume, and understand limits — while self-hosted instances show none of it.

## Context

> **Routes** (`spec/08-frontend.md` §3): `/settings/billing` (nav entry contributed by the `billing` descriptor, permission `billing.subscription.read`; absent when the module is off). Sections: current plan and period, plan cards (name via `name_key`, price formatted with `Intl.NumberFormat` from `{amount_minor, currency}` — the browser does not do arithmetic on money beyond formatting; limits listed), checkout button (calls `POST /billing/checkout`, then navigates to `checkout_url` only if it is `https` or the app's own test origin), return page `/settings/billing/return` that shows **processing** and polls `GET /billing/subscription` for up to 5 minutes with a manual refresh, then "contact support" (spec §6), cancel / resume with confirmation, invoices table (`GET /billing/invoices`, amounts via `Intl`).
> **States**: `billing.free`, `billing.processing`, `billing.active`, `billing.past_due` (grace explanation with the end date), `billing.canceled`, `billing.over_limit` (shown on the Workspaces and AI screens as a non-blocking banner linking here; reads and edits stay available), `billing.not_configured` for admins when enabling is refused. Footer/checkout links to terms, privacy, refund and cancellation policy (CLD-09) read their URLs from instance settings; when unset the links are hidden and the missing-legal-copy warning appears in the admin policies screen.
> **Journey J8**: checkout (fake gateway) → webhook (minted by the e2e harness helper) → state active → entitlements applied → cancel → period end. The browser never decides state from the redirect (BIL-FR-02).

## Scope

**In:**
- Feature folder `web/src/features/billing/**`, i18n (en, id), MSW handlers, Vitest tests, Playwright `j8.spec.ts` with a harness endpoint behind the cargo feature `e2e-fake-clock` that mints fake-gateway webhooks, over-limit banners on the workspaces and AI screens, a11y gate routes.

**Out:**
- Server changes.

## Files

| Path | Action | Notes |
|---|---|---|
| `web/src/features/billing/**` | create |  |
| `web/src/i18n/{en,id}/billing.json` | create |  |
| `web/src/features/workspaces/**, web/src/features/ai/**` | modify | over-limit banner slot only |
| `web/tests/{billing.test.tsx,e2e/journeys/j8.spec.ts}` | create | AC-1…AC-8 |
| `web/tests/e2e/gates/a11y.spec.ts` | modify | add routes |

## Interfaces

```ts
usePlans(); useSubscription({ poll }); useStartCheckout(); useCancelSubscription(); useResumeSubscription(); useInvoices();
```

## Business rules

1. Money is formatted, never computed, in the browser.
2. State shown always comes from `GET /billing/subscription`.
3. With the module off, no billing UI or nav exists.

## Security

- Only `https` checkout URLs (or the declared test origin in e2e builds) are followed.
- No payment data is handled in the browser.

## Implementation notes

- The e2e webhook-minting endpoint exists only under the cargo feature `e2e-fake-clock` (D-42) and is never compiled into release builds; a test asserts the route is absent without the feature.

## Acceptance

| # | Given / When / Then | Test name |
|---|---|---|
| AC-1 | **Given** plans **When** rendered **Then** prices are formatted from minor units with the plan currency | `billing::bil_fr_01_price_formatting` |
| AC-2 | **Given** checkout **When** the return page loads without an active subscription **Then** `billing.processing` and polling, then support text after 5 minutes | `billing::bil_fr_02_processing_then_support` |
| AC-3 | **Given** a webhook-driven change **When** polling sees `active` **Then** the active state shows | `billing::active_state_from_server` |
| AC-4 | **Given** `past_due` **When** shown **Then** the grace end date and explanation appear | `billing::past_due_state` |
| AC-5 | **Given** over-limit usage **When** the workspaces screen loads **Then** the banner shows and edits still work | `billing::bil_fr_04_over_limit_banner` |
| AC-6 | **Given** billing off **When** the shell loads **Then** no billing nav or route | `billing::module_off_hides_ui` |
| AC-7 | **Given** the routes **When** axe runs **Then** no serious violations | `e2e::a11y_billing_routes` |
| AC-8 | **Given** J8 **When** run with the fake gateway **Then** checkout, webhook, entitlements, cancel pass | `e2e::j8_cloud_billing` |

## Tests

```
pnpm --dir web test
pnpm --dir web check-i18n
just e2e   # environment-limited without browsers
just check
```

## Definition of done

- [ ] Every acceptance criterion has its named test, and it passes (or is marked environment-limited with the exact command not run).
- [ ] `just check` passes locally.
- [ ] Only the files listed under *Files* changed; diff within budget.
- [ ] Report below is filled in.

## Report

_Filled in by the implementer (WORKFLOW.md §4)._

## Questions

_None yet._
