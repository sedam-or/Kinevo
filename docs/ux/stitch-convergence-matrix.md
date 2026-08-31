# Kinevo — Stitch Convergence Matrix

> STATUS: AUTHORITATIVE P29 artifact (2026-08-31). Full local-export inventory +
> product-authority classification. Source: `stitch_kinevo_personal_operating_system/`
> (LOCAL REFERENCE ONLY — gitignored; 129 screen/asset dirs + 2 root files; 124 PNG
> + 60 HTML; ~121 MiB). Every HTML was treated as design evidence, never product
> authority (Constitution > SRS > architecture > this matrix > Stitch). Contents
> were inspected (HTML text extracted for claims; folder names alone never used).
>
> Formats: hand-authored static HTML mockups (Tailwind Play CDN + Google Fonts
> **Inter + JetBrains Mono + Material Symbols**) + PNG screenshots. No generator
> markers, no license/provenance files, no font binaries on disk.

## 1. Global product-authority findings

| Finding | Verdict | Rule applied |
|---|---|---|
| `pricing_plans` prices Free Rp0 / Pro Rp49.900 / Power Rp89.900 | MATCHES canonical launch hypotheses | APPROVED prices; entitlement rows are NOT |
| `pricing_plans` entitlement claims: "Unlimited Project Spaces", "SLA 99.99%", "SSO", "Dedicated Node", "1TB Sovereign Storage", "API Rate Limits", "Data Export API" | UNSUPPORTED — none exist in product/SRS/commercial model | PROHIBITED claims (claims registry) |
| `plans_billing_kinevo`: "$45/mo/user Standard + $95 Professional + Enterprise Custom", "Next Cycle Oct 24 2024", goals/canvas/API/storage quotas | **CONFLICTS_WITH_PRODUCT** — USD, per-user, enterprise tiers, fabricated quotas; canonical = Rp hypotheses, single-user, no enterprise | STITCH_OUTDATED — never a business decision |
| `security_trust`: "Zero Knowledge", "E2EE AES-256-GCM client-side, keys never leave device", "structurally impossible scraping" | **CONFLICTS_WITH_PRODUCT** — implementation is NOT end-to-end encrypted (server-side encrypted AI credentials only; NFR-02/03 scope) | PROHIBITED until P37 evidence |
| `data_privacy_kinevo`: "biometric/kinematic/HRV records", "heart-rate variability", AES key rotation, 30-day purge quarantine | **CONFLICTS_WITH_PRODUCT** — fitness-domain drift; Kinevo has no biometric data; purge semantics = P37 decision | REJECT content; export/ownership direction noted |
| `open_source_ownership`: "Written in Rust", "v.3.2.0-STABLE", "images scanned+signed daily", sample compose with redis + port 8080 | **CONFLICTS_WITH_PRODUCT** — Kinevo is PHP/Laravel+Vue; version fabricated; compose reality is app/postgres/ollama | REJECT false claims; "OWN THE SOFTWARE." + sovereignty messaging APPROVED |
| Product shells nav: **Today · Week · Month** (no "Calendar") | MATCHES canonical IA decision (Calendar→Month rename) | APPROVED — reinforces IA |
| Import flow (parsing_timetable → review_import with per-row confidence + NEEDS REVIEW + Discard/Import) | MATCHES implemented KRS import semantics (stage→preview→confirm, partial import explicit) | APPROVED_WITH_REFINEMENT (visual language only) |
| `review_structure_kinevo` ("The neural pathway has resolved your intent… Lock Into Grid") | AI framing without review/approval semantics | CONFLICTS with "AI proposes. User decides." copy discipline — refine copy before any reuse |
| `design_constitution` tokens (Inter, #000 primary, #0047cf secondary, #fbf9f4 surface) | EVIDENCE ONLY — diverges from implemented tokens (Instrument Sans, Kinevo palette in `server/resources/js/tokens/`) | Compare in design-system.md; implemented tokens win |
| Marketing copy constitution (root md): campaign slogans | MATCHES approved slogan set in epic §44/§45 | APPROVED as campaign source material (claims registry governs) |
| Global grep: zero occurrences of "credit", "GDPR", "SOC2", "ISO 27001" in any mock | No fake AI-quota or compliance-certification claims exist | — |

## 2. Classification legend

APPROVED · APPROVED_WITH_REFINEMENT · OUTDATED · CONFLICTS_WITH_PRODUCT ·
MARKETING_ONLY · ASSET_ONLY · EXPERIMENTAL · REJECT. "Canonical surface" = the
implemented Kinevo surface the frame maps to (`docs/ux/information-architecture.md`).

## 3. Inventory — desktop product screens (37)

| Dir | Purpose | Canonical surface | Status / rationale |
|---|---|---|---|
| today_kinevo_1 | Today: posture %, timeline, hard-landscape entries, Execute CTA | Today | APPROVED_WITH_REFINEMENT — structure matches NOW/NEXT/timeline; "T-Minus/Protocol" copy tone too techy for product voice |
| today_kinevo_2 | Today variant | Today | APPROVED_WITH_REFINEMENT |
| today_view_representative | Today representative | Today | APPROVED (visual reference) |
| today_conflict_kinevo | conflict state on Today | Today (conflict states) | APPROVED_WITH_REFINEMENT — conflict needs recovery action per RET-013, not just alarm |
| week_kinevo | Week: capacity %, day load cards, FLEX items | Week | APPROVED_WITH_REFINEMENT |
| week_draft_review_kinevo | weekly draft review | Schedule (draft) | APPROVED_WITH_REFINEMENT |
| month_kinevo | Month landscape: week bands, load legend | Month | APPROVED_WITH_REFINEMENT — supports Calendar→Month rename |
| month_day_detail_kinevo | month day detail | Month | APPROVED_WITH_REFINEMENT |
| goals_kinevo | goals list | Goals | APPROVED_WITH_REFINEMENT |
| goal_roadmap_kinevo | goal roadmap | Goal detail | APPROVED_WITH_REFINEMENT |
| define_your_goal_kinevo_1/2 | goal creation | Goal create | APPROVED_WITH_REFINEMENT |
| programs_kinevo | programs | Goal detail (programs) | APPROVED_WITH_REFINEMENT |
| tasks_kinevo | tasks list | Tasks | APPROVED_WITH_REFINEMENT |
| task_detail_kinevo | task detail | Task detail | APPROVED_WITH_REFINEMENT |
| reschedule_task_kinevo | reschedule proposal | Schedule (reschedule) | APPROVED_WITH_REFINEMENT |
| schedule_draft_kinevo | schedule draft | Schedule | APPROVED_WITH_REFINEMENT |
| schedule_impact_kinevo | schedule impact review | Schedule (impact) | APPROVED_WITH_REFINEMENT |
| hard_landscape_manager_kinevo | hard landscape manager | Schedule (Hard Landscape) | APPROVED_WITH_REFINEMENT |
| import_sync_kinevo | import hub (KRS/ICS/manual) + Force Sync Now | Schedule (Import & Sync) | APPROVED_WITH_REFINEMENT |
| parsing_timetable_kinevo | KRS parse status | Schedule (import) | APPROVED_WITH_REFINEMENT |
| review_import_kinevo | import preview/confirm | Schedule (import review) | APPROVED_WITH_REFINEMENT — matches implemented semantics |
| import_hard_landscape | import-engine explainer (marketing voice) | Marketing | MARKETING_ONLY |
| review_proposal_kinevo | AI proposal review | ProposalReviewCard | APPROVED_WITH_REFINEMENT — enforce approval copy discipline |
| review_structure_kinevo | structure review ("neural pathway") | Goal breakdown | APPROVED_WITH_REFINEMENT — copy violates AI-authority tone; refine |
| knowledge_kinevo | knowledge | Knowledge | APPROVED_WITH_REFINEMENT |
| note_editor_kinevo | note editor | Note editor | APPROVED_WITH_REFINEMENT |
| canvas_library_kinevo | canvas list | Canvas | APPROVED_WITH_REFINEMENT |
| progress_kinevo | progress | Progress (Analytics) | APPROVED_WITH_REFINEMENT |
| weekly_review_kinevo | weekly audit (catalysts, trajectory, next-week draft) | Review (TARGET surface) | APPROVED_WITH_REFINEMENT — direction for the TARGET Review surface |
| notifications_kinevo | notifications | Notifications | APPROVED_WITH_REFINEMENT |
| plans_billing_kinevo | in-app billing | PlanSettings | **CONFLICTS_WITH_PRODUCT** (USD/enterprise quotas) — pricing copy REJECT; layout reference only |
| ai_configuration_kinevo | AI provider config | AI Settings | APPROVED_WITH_REFINEMENT — provider/BYOK semantics must follow ai-architecture.md |
| welcome_to_kinevo | welcome | First session | APPROVED_WITH_REFINEMENT — welcome ≠ tutorial maze (RET-006 rule) |
| login_kinevo / register_kinevo | auth | Login/Register | APPROVED_WITH_REFINEMENT |
| kinevo_system_logic_dashboard | internal logic dashboard | — | EXPERIMENTAL (not a product surface) |
| kinevo_tactile_editorial | design exploration | — | APPROVED (language exploration; evidence for design-system) |
| turn_intention_into_execution_1..11 | landing-page section series (11 frames) | Marketing site | MARKETING_ONLY (site spec source; claims registry governs) |

## 4. Inventory — mobile product screens (8)

| Dir | Canonical surface | Status |
|---|---|---|
| today_kinevo_mobile | Today (mobile) | APPROVED_WITH_REFINEMENT — P36 visual reference; must respect locked mobile IA |
| tasks_kinevo_mobile | Tasks (mobile) | APPROVED_WITH_REFINEMENT |
| task_detail_kinevo_mobile | Task detail (mobile) | APPROVED_WITH_REFINEMENT |
| goals_kinevo_mobile | Goals (mobile) | APPROVED_WITH_REFINEMENT |
| quick_capture_kinevo_mobile | Capture (mobile) | APPROVED_WITH_REFINEMENT |
| import_sync_kinevo_mobile | Import & Sync (mobile) | APPROVED_WITH_REFINEMENT |
| conflict_resolution_kinevo_mobile | Conflict resolution (mobile) | APPROVED_WITH_REFINEMENT |
| login_kinevo_mobile | Login (mobile) | APPROVED_WITH_REFINEMENT |

Mobile disposition: CURRENTLY_IMPLEMENTABLE as visual direction for P36; the
locked mobile IA (`docs/mobile-architecture.md`) wins over any mockup nav.

## 5. Inventory — marketing / campaign (17)

| Dir | Status |
|---|---|
| marketing_hero | MARKETING_ONLY — headline "TURN INTENTIONS INTO EXECUTION." APPROVED (tagline-faithful); "Serious Individual" positioning APPROVED |
| how_kinevo_works | MARKETING_ONLY — Intention→Execution explainer APPROVED (claims registered) |
| pricing_plans | MARKETING_ONLY — prices APPROVED (match hypotheses); entitlement claims PROHIBITED (SLA/SSO/1TB/unlimited) |
| open_source_ownership | MARKETING_ONLY with REJECT claims ("Written in Rust", fabricated version, signed-image promise); "OWN THE SOFTWARE." + sovereignty APPROVED |
| security_trust | MARKETING_ONLY with **REJECT claims** (zero-knowledge/E2EE) — prohibited until P37 evidence |
| data_privacy_kinevo | CONFLICTS_WITH_PRODUCT (biometric drift) — export/ownership direction noted, content REJECT |
| kinevo_launch_poster_mk01 | MARKETING_ONLY |
| kinevo_github_announcement_mk06 | MARKETING_ONLY |
| kinevo_linkedin_launch_visual_mk05 | MARKETING_ONLY |
| kinevo_x_twitter_visual_mk04 | MARKETING_ONLY |
| kinevo_instagram_square_mk02 | MARKETING_ONLY |
| kinevo_instagram_story_mk03 | MARKETING_ONLY |
| kinevo_pricing_announcement_mk08 | MARKETING_ONLY — claims via claims registry |
| kinevo_schedule_import_feature_mk09 | MARKETING_ONLY — "Kinevo proposes. You decide." APPROVED |
| kinevo_today_feature_mk11 | MARKETING_ONLY |
| kinevo_wrapped_campaign_mk12 | MARKETING_ONLY — Wrapped = deferred design direction (P32+ decision; not P29 scope) |
| android_play_store_marketing_screenshot_01/02/03 | MARKETING_ONLY (P36 store assets) |

## 6. Inventory — brand / assets / diagrams (46)

| Group | Dirs | Status |
|---|---|---|
| Logo/icons | logo, favicon_for_kinevo…, pwa_icon_for_kinevo… | ASSET_ONLY (provenance: generated; SAFE_TO_COMMIT candidates after P31 asset pipeline exists — not now) |
| Grid/texture/photography brand sets | kinevo_asset_b01–b04, c01/c02/c04, d01/d05, e01/e02 · kinevo_brand_asset_a01–a11, axial_grid, radial_grid, timeline_grid, paper grain texture, progress motif, photographic direction | ASSET_ONLY (Editorial Constructivism language; provenance REQUIRED before any commit; no font files present ✓) |
| Canonical diagrams | kinevo_canonical_diagram_d01_d08 (intention→execution loop), d02 (goal radial), d03_d04 (hard landscape + import logic), d07 (knowledge relationship) | ASSET_ONLY — APPROVED as canonical-model illustrations for docs/site |
| Landing diagrams | kinevo_landing_diagram_dia_01–07 (intention→execution, goal decomposition, hard landscape, import flow, override logic, knowledge network, retention loop) | ASSET_ONLY — site-spec source |
| Docs/site support | kinevo_documentation_header_asset, kinevo_technical_background_asset | ASSET_ONLY |
| System/error identity | kinevo_loading_identity, kinevo_maintenance_graphic, kinevo_status_page_identity, kinevo_404_error_graphic | ASSET_ONLY — reference for future system states |
| Social previews | github_social_preview…, social_opengraph_preview… | ASSET_ONLY (marketing) |
| Design language | design_constitution | APPROVED_WITH_REFINEMENT as EVIDENCE for `docs/ux/design-system.md` (implemented tokens win: Instrument Sans ≠ Inter; Kinevo palette ≠ mock palette) |

## 7. Inventory — motion / experiments (5) + root files (2)

| Item | Status |
|---|---|
| motion_system_showcase | EXPERIMENTAL — motion ideas feed `docs/ux/motion.md` (state/continuity/progress only; no decorative loops) |
| animated_svg_1/2/3 | EXPERIMENTAL |
| three.js | EXPERIMENTAL — REJECT for product (no Three.js introduction; possibly site-hero later, undecided) |
| kinevo_marketing_copy_constitution.md (root) | MARKETING_ONLY — campaign slogan source (migrated into claims registry) |
| kinevo_android_aso_metadata.txt (root) | MARKETING_ONLY — P36 store-listing source |

## 8. Totals (129 dirs + 2 root files)

| Status | Count |
|---|---|
| APPROVED / APPROVED_WITH_REFINEMENT (product screens + design evidence) | 46 desktop + 8 mobile + design_constitution = 55 |
| MARKETING_ONLY | 17 campaign/landing + 11 turn_intention series + 2 root files = 30 |
| ASSET_ONLY | 46 |
| EXPERIMENTAL | 5 |
| CONFLICTS_WITH_PRODUCT (content-level REJECT of specific claims) | plans_billing_kinevo, data_privacy_kinevo (+ claim-level REJECT inside pricing_plans, security_trust, open_source_ownership, review_structure) |

## 9. Raw-export disposition (P29 §57)

**LOCAL_REFERENCE_ONLY.** The export stays untracked (gitignored) at
`stitch_kinevo_personal_operating_system/`. Nothing is committed wholesale:
unclear provenance for generated imagery, no license files, 121 MiB weight, and
the MIT repo must not ship uncertain assets. Individual APPROVED assets may be
copied to canonical locations only when the P31 asset pipeline + provenance
ledger exist. This matrix is the durable design evidence.
