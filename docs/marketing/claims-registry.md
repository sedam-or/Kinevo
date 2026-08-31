# Kinevo — Marketing Claims Registry

> STATUS: AUTHORITATIVE (P29, 2026-08-31). Every external claim (site, store,
> social, README-facing) must exist here with product support before publication.
> Governed by: Product Constitution, SRS v3, commercial model, NFR-02/03.
> Stitch frames never clear claims by themselves (STITCH_OUTDATED rule).

| # | Claim | Surface | Product support | CURRENT/TARGET | Risk | Allowed? | Required qualifier |
|---|---|---|---|---|---|---|---|
| 1 | "Kinevo — Turn intentions into execution." | all | Constitution §1/§4; core loop implemented + browser-proven | CURRENT | none | YES | — |
| 2 | "Your life is not a todo list." | site/social | positioning (vision-mission §7) | CURRENT | none | YES | — |
| 3 | "Upload the schedule. Keep your life." | site/social | KRS/ICS import (FR-24/30) implemented | CURRENT | none | YES | — |
| 4 | "AI proposes. You decide." | all | FR-62 pending-only proposals; no auto-accept (verified) | CURRENT | none | YES | — |
| 5 | "Own the software. Or let Kinevo host it for you." | site | MIT Core (LICENSE) + Cloud model | CURRENT (model) | none | YES | Cloud = managed hosting convenience |
| 6 | "Your data isn't the product." | site | Constitution §6 | CURRENT | none | YES | — |
| 7 | "Workspace-scoped personal operating system" | all | Constitution §1 | CURRENT | none | YES | — |
| 8 | Free Rp0 · Pro Rp49.900 · Power Rp89.900 | site/pricing | config/billing.php `launch_hypothesis: true` | CURRENT values, LAUNCH_HYPOTHESIS status | low | YES | "Launch pricing — subject to change" |
| 9 | BYOK on Pro/Power; never consumes hosted allowance | pricing/AI settings | FR-73b implemented (separate ledgers) | CURRENT | none | YES | — |
| 10 | Hosted AI allowance numbers (20/300/1000 or 20/150/500) | anywhere | config values = DEPRECATED BASELINE, not policy | DECISION_REQUIRED (P33) | HIGH | **NO** | prohibited until P33 locks quotas |
| 11 | "Unlimited Project Spaces" / "SLA 99.99%" / "SSO" / "Dedicated Node" / "1TB storage" / "API rate limits" | pricing_plans mock | no product support | — | HIGH | **NO** | prohibited (Stitch fabrication) |
| 12 | "Zero knowledge" / "E2EE AES-256-GCM client-side / keys never leave device" | security_trust mock | implementation is NOT end-to-end encrypted | — | HIGH | **NO** | prohibited until P37 delivers and verifies E2EE scope |
| 13 | "GDPR compliant" / "SOC 2" / "ISO 27001" | anywhere | no evidence exists | — | HIGH | **NO** | prohibited until P37 + legal review |
| 14 | "No telemetry. No call-homes." | open-source page | self-host build has no telemetry today; Cloud is a separate matter | CURRENT for self-host | medium | YES | qualify: "the open-source Core phones nothing home" |
| 15 | "Your data stays yours: export your data." | privacy copy | export = P37 committed scope; partial exports exist (notes/canvas JSON) | TARGET (full) | medium | PARTIAL | publish only after P37 full-export ships, or scope to implemented exports |
| 16 | Account deletion / purge guarantees ("30-day quarantine, cryptographic erasure") | data_privacy mock | deletion/export = P37 scope, semantics undecided | TARGET | HIGH | **NO** | prohibited until P37 defines semantics |
| 17 | "Works offline." | site/social | offline queue + reconciliation implemented (ADR-017, web) | CURRENT (web) | low | YES | mobile durable offline lands P36 — do not claim mobile parity |
| 18 | "Deterministic scheduling — same inputs, same plan." | site/docs | ADR-015/016 verified | CURRENT | none | YES | — |
| 19 | "Locked commitments never move." | site | FR-08 lock + Sacred Anchor verified | CURRENT | none | YES | — |
| 20 | "Kinevo is written in Rust." | open_source mock | FALSE (PHP/Laravel + Vue) | — | HIGH | **NO** | prohibited (factual error) |
| 21 | Team/enterprise features, SSO, RBAC | pricing mock | out of scope through v1 (Constitution §9) | — | HIGH | **NO** | prohibited |
| 22 | "BUILT FOR PEOPLE WITH TOO MUCH GOING ON." | site | positioning | CURRENT | none | YES | — |
| 23 | "START FREE. BUILD YOUR SYSTEM." | pricing CTA | Free tier real | CURRENT | none | YES | — |
| 24 | Version/status badges ("v3.2.0-STABLE") | open_source mock | version fabrication | — | medium | **NO** | versions come from release tags only |
| 25 | Wrapped ("Your year unwrapped") | campaign | not implemented; deferred (P32+ decision) | TARGET | low | DEFER | no launch-site placement until a Wrapped decision exists |

Audit notes: `security_trust` (zero-knowledge/E2EE), `data_privacy_kinevo`
(biometric/kinematic drift + purge guarantees), `open_source_ownership` (Rust,
fabricated version, signed-images promise), `plans_billing_kinevo` (USD/enterprise
quotas), `pricing_plans` (entitlement rows) — all claim-level REJECT per
`stitch-convergence-matrix.md` §1. SAFE current security claims: "AI credentials
are encrypted on the server", "your data is not the product", "self-host for full
control", "no behavioral sale of data".
