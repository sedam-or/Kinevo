# ADR-013 — Product Tiers & Pricing (Locked Business Decisions)

### Decision
Adopt the owner-locked commercial model for P26–P30 exactly as decided (2026-08-26):

| Tier  | Monthly price        | BYOK | Notes |
|-------|----------------------|------|-------|
| FREE  | IDR 0                | ✗    | Core loop never blocked |
| PRO   | IDR 34,900 / month   | ✓    | Complete intelligent personal experience |
| POWER | IDR 49,900 / month   | ✓    | Heavy individual usage; deeper intelligence |

Additional locked constraints:
- **Annual billing**: architecture supports it, but NO annual price/discount exists — do not
  invent or display one until an explicit later decision.
- **Market/currency/language**: Indonesia-first; IDR; Bahasa Indonesia + English.
- **Web-first billing (v1)**: purchases happen through Kinevo web checkout (ADR-012 gateway —
  not re-decided here). Android v1 has NO Google Play subscription checkout. Architecture
  preserves explicit extension slots for future Android/iOS native provider adapters.
- **Android-first release**; iOS is a documented future extension, NOT a v1 target.
- **Single-user/personal product**; no teams/orgs/shared workspaces/RBAC/enterprise.
- **One subscription covers Web + Android**; no separate mobile subscription.
- **BYOK economics**: BYOK does NOT consume Kinevo-hosted AI credits but is ALWAYS bound by
  runtime safeguards (rate/context/timeout/abuse). Credits protect economics; safeguards protect
  infrastructure — they stay separate.
- **Neither Pro nor Power equals "unlimited AI"** — hard request/token/rate limits remain.

### Context
Prices are deliberately close (34,900 vs 49,900): Power must deliver real incremental value
(deeper history, expanded insights/share customization, highest allowances), not cosmetic extras.

### Implementation mapping
- Tier catalog: `server/config/saas.php` (Free/Pro/Power; legacy `personal` rows degrade to the
  default plan via `Subscription::effectivePlanCode()`).
- Price catalog: `server/config/billing.php` (`prices.pro`, `prices.power`; IDR minor units).
- Entitlement enforcement stays in P23's `EntitlementService` — no second engine.

### Alternatives rejected
- Keeping the intermediate `personal` tier (owner simplification).
- Inventing annual/trial/promo pricing now.

### Consequences
Positive: simpler funnel; clear upgrade economics; config-only price changes.
Negative: removing Personal requires the degrade guard for existing rows (landed with tests).

### Status
Accepted — authoritative for P26–P30 unless superseded by a new documented owner decision.

---
