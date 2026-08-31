# P29 — Convergence Matrix (Reality Inventory)

> STATUS: AUTHORITATIVE P29.1 artifact (2026-08-31). Produced during P29 Phase P29.1
> (reality inventory). Maps every truth category to its current doc claim, verified
> implementation reality, target decision, and canonical destination. When this phase
> closes, every ACTION row must be discharged and this file becomes historical evidence.
>
> Method: doc-by-doc audit (SRS v2.0.0 = 69 FR + 15 NFR; 17 ADRs; 30 canonical
> candidate docs) cross-checked against code (config/billing.php, config/saas.php,
> shell/navigation.ts, composer/package manifests, migrations head
> `2026_08_31_110000_create_offline_operations_table.php`).

## 1. Document inventory verdicts

| Document | Verdict | Basis |
|---|---|---|
| docs/SRS.md (v2.0.0, 69 FR + 15 NFR) | NEEDS_REWRITE | Workspace not first-class (0 Workspace FRs); §9.4 LWW text contradicts FR-44/ADR-017; §14.1 IA still shows `Calendar`; no Effective-Landscape concept section; otherwise core FRs accurate |
| docs/architecture.md | NEEDS_REWRITE | No status header; no systematic CURRENT/TARGET split; provider boundaries incomplete (no Email/Assets/Analytics/Billing boundaries); missing offline-reconcile + Core/Cloud |
| docs/domain-model.md | NEEDS_REWRITE | Missing all 9 newer concepts: HardLandscapeOccurrence, EffectiveLandscapeResolver, ScheduleDraft lifecycle, schedule_assignment_history, OfflineOperation ledger, ScheduleState, AIProposal (entity), Subscription, Entitlement; Workspace absent entirely |
| docs/design.md (105 §, 3704 ln) | MERGE+SPLIT | Duplicates token/QA/state-matrix content owned elsewhere; contains roadmap features (Wrapped, pricing) as design contract; is current UI/UX authority incl. §74–103 |
| docs/design-tokens.md | MERGE | 2 factual inaccuracies (`--k-*` prefix claim — actual `--color-*`/`--radius-*`; `#DE3005` vs actual `#F53003` in tokens/colors.ts); otherwise accurate token contract |
| docs/brand.md | MERGE | Stale font claim ("Inter" — actual Instrument Sans, tokens/typography.ts:8); useful 3-level color/typography hierarchy |
| docs/state-machine-ui.md | MERGE | CURRENT_ACCURATE (R3 baselines + P28-011 matrix + RET-013 failure matrix); becomes interaction-states authority |
| docs/ui-audit.md | ARCHIVE (evidence) | Living gap audit with dated §10 inventory; historical audit value, not design authority |
| docs/browser-e2e.md | KEEP | Living browser-run evidence record (updated P28-013) |
| docs/billing.md | KEEP (pointer) | CURRENT_ACCURATE: locked hypothesis prices, BYOK Pro/Power, AI allowance deprecated-baseline posture — remains technical billing authority; commercial product policy moves to docs/product |
| docs/billing-capability-matrix.md | KEEP | Dated vendor capability evidence |
| docs/ai-architecture.md | KEEP (status header added) | CURRENT_ACCURATE (provider abstraction, metering, BYOK ledger); only gap = no status header |
| docs/ai-economics/regression-classification-2026-08-28.md | KEEP (TEMPORARY) | Point-in-time evidence log by design |
| docs/mobile-architecture.md | REWRITE (narrow) | 3 stale Inertia mentions (L24, L28, L38); ADR-013 mis-cite L96; otherwise current (ADR-017 reuse, web-first billing, locked mobile IA) |
| docs/offline-sync.md | KEEP | CURRENT_ACCURATE (ADR-017 contract) |
| docs/scheduling-engine.md | KEEP (status header added) | CURRENT_ACCURATE (deterministic contract + ADR-015/016 sections) |
| docs/knowledge-layer.md | REWRITE (narrow) | Missing base_version/409 conflict contract; attachment→Uppy/AssetStorage linkage missing |
| docs/third-party/* (3 files) | KEEP | CURRENT_ACCURATE (planning baseline + license governance) |
| docs/retention-events.md | KEEP | CURRENT (P28 RET-007 artifact) |
| docs/state-machine-ui.md §P28-011/RET-013 | KEEP (until merged) | Canonical until interaction-states doc exists |
| docs/implementation-status.md | ARCHIVE | Undated running execution mirror; authority moved to TASK.md (self-recorded supersession L183–184) |
| docs/hardening-evidence.md | ARCHIVE (evidence) | Point-in-time hardening evidence |
| docs/convergence/TARGET_DECISION_REGISTER.md | MIGRATE→ARCHIVE | 25 owner-approved decisions: 13 already CURRENT (implemented via ADR-015/016/017 + config), 12 TARGET_LOCKED/DECISION_REQUIRED — migrate into Product Constitution / architecture / commercial docs, then archive as evidence |
| docs/convergence/OFFLINE_CAPABILITY_MATRIX_2026-08-31.md | ARCHIVE (evidence) | Dated capability snapshot |
| docs/audit/* (4 dated snapshots) | KEEP | FROZEN dated audits — never rewritten |
| docs/adr/* (17 ADRs) | KEEP | Accepted historical authority; ADR-002 amendment already records Inertia never installed |
| docs/roadmap/** | KEEP + refine | Master program authoritative; P30–P39 planned docs refined at P29.53 |
| docs/environment.md, deployment.md, compatibility.md, release-management.md, test-strategy.md | KEEP | Operational governance, current |
| README.md (root), AGENTS.md, docs/README.md | REWRITE (targeted) | Update to final P29 authority map |
| TASK.md | KEEP (slim) | Control-plane status only |

## 2. Concept-level convergence matrix

| CONCEPT | Current doc claim | Current implementation (verified) | Conflict? | Target decision | Canonical destination | ACTION |
|---|---|---|---|---|---|---|
| Product identity | SRS §1.2 vision; master program §1 | single-user personal OS, workspace-scoped | No | Preserve: "workspace-scoped personal operating system reconciling intention, reality, context into executable action" | docs/product/product-constitution.md | CREATE |
| Tagline | master program §1 ("Turn intentions into execution") | — | No | Preserve | product-constitution.md + docs/marketing/site-specification.md | CREATE+PRESERVE |
| Core loop | design.md §99 (LOGIN→TODAY→NOW→START→COMPLETE→PROGRESS→NEXT); SRS FR-01/03/27/47 | implemented + browser-proven (core-loop.spec) | No | Canonicalize 9-stage loop (Intention→Structure→Schedule→Today→Execution→Progress→Review→Adapt) | product-constitution.md | CREATE |
| AI authority | SRS FR-60/62; ai-architecture.md; ADR-011 | pending-only proposals, accept/reject endpoints, no auto-accept (P28 evidence) | No | "AI proposes. User decides." — locked | product-constitution.md §AI authority | CREATE (already enforced) |
| Workspace semantics | MASTER PROGRAM §1 (locked); SRS: NOT covered (0 FRs) | workspace-scoped goals/programs/tasks/notes/canvases (migration 2026_08_26_000001); Hard Landscape global (no workspace_id); Today/Week/Month user-scoped cross-workspace | **SRS GAP** | Register decisions 2–8 verbatim as canonical | docs/product/workspace-model.md + SRS v3 Workspace FRs | CREATE + SRS REWRITE |
| Workspace as filter (Today/Week/Month) | register #6 TARGET | lists read `activeWorkspaceId` (task/goal/note/canvas stores) — scoped reads exist; filter UI for cross-workspace views NOT built | Partial | CURRENT for scoped entities; TARGET (MIGRATION_REQUIRED) for Today/Week/Month filter layer | workspace-model.md | DOCUMENT split |
| Progress/Review workspace-filtered | register #8/#9 TARGET | server accepts `?workspace_id` (AnalyticsController:71); web UI global-only | Partial | TARGET wiring; server CURRENT | workspace-model.md | DOCUMENT split |
| Hard Landscape | SRS FR-04 (Sacred Anchor), FR-36; ADR-015 | `hard_landscape_events` global; occurrence expansion; KRS import (FR-24) | No | Global personal reality — locked | workspace-model.md + SRS | PRESERVE |
| Effective Schedule / overrides | ADR-015 (480 ln); scheduling-engine.md §ADR-015 | recurrence+UNTIL, Permanent Shift, One-Time Exception, assignment history, locked producer; browser B/C/D/LOCK | No | Canonical pipeline: source→expansion→override resolution→effective occurrence→landscape→capacity→deterministic scheduler→draft→approval | domain-model.md + scheduling-engine.md (already) | DOMAIN REWRITE captures it |
| Schedule draft lifecycle | ADR-016; SRS FR-29 | weekly `schedule:prepare-weekly` (never auto-applies), `POST /schedule/sync`, run locks, review/apply | No | Weekly may calculate, must not auto-apply; Sync Now must not silently mutate | domain-model.md + SRS | PRESERVE |
| Offline reconciliation | ADR-017; offline-sync.md; SRS FR-44 | `offline_operations` ledger, `/sync/reconcile`, idempotent replay, optimistic conflicts, web MutationQueue | **SRS §9.4 internal LWW conflict** | No LWW, no client-clock authority — ADR-017 governs | SRS v3 §Offline (fix §9.4) | FIX IN SRS |
| AI providers | ADR-011; ai-architecture.md | disabled/ollama/openai/mock + runtime states; BYOK ledger split | No | Provider abstraction; BYOK Pro/Power; hosted allowance DECISION_REQUIRED (P33) | commercial-model.md + ai-architecture.md | PRESERVE |
| Commercial plans | billing.md LOCKED hypotheses; register #22; config/billing.php (49_900/89_900 + launch_hypothesis:true) | Free Rp0 / Pro Rp49.900 / Power Rp89.900 | No | LOCKED as launch hypotheses (not eternal); no annual, no trial | docs/product/commercial-model.md | CREATE + PRESERVE billing.md as technical authority |
| Hosted AI allowance | billing.md L28–32 (deprecated baseline 20/150/500); config/saas.php 20/300/1000 "DEPRECATED BASELINE" | functional placeholders, not policy | No | DECISION_REQUIRED → P33 FinOps; never promote config numbers to policy | commercial-model.md | DOCUMENT open decision |
| Entitlements | register #25; config/saas.php (max_workspaces 1/5/15, reserved keys unenforced) | enforced server-side (EntitlementService) | No | capacity/depth/history/intelligence/convenience; Power ≠ teams/RBAC | commercial-model.md | CREATE |
| Billing architecture | billing.md; ADR-012 | Midtrans sandbox; signed idempotent webhooks; Kinevo = subscription authority | No | PAYMENT ≠ SUBSCRIPTION ≠ ENTITLEMENT ≠ USAGE — separate concepts | commercial-model.md + billing.md | PRESERVE |
| Third-party adoption | adoption-matrix.md; ADR-014 | Excalidraw CURRENT_EMBED (React island); Tiptap CURRENT_EMBED; compose has app/postgres/ollama only | No | Classifications EMBED/HARVEST/REIMPLEMENT/ADAPTER+SERVICE/REFERENCE/REJECT; planned = TARGET only | architecture.md §third-party + adoption-matrix stays | PRESERVE |
| Runtime | architecture.md (no TARGET split); register #18/#19 | nginx+php-fpm / artisan serve | No | FrankenPHP+Octane TARGET_LOCKED(migration) → P30; BENCHMARK_REQUIRED | architecture.md §runtime | REWRITE with split |
| Email | register #20/#21 | no email provider installed | No | Resend TARGET_LOCKED via EmailProvider abstraction → P30 | architecture.md §provider boundaries | DOCUMENT TARGET |
| Assets pipeline | SRS FR-65 (Uppy+Pic Smaller); adoption-matrix | not installed | No | TARGET → P31 | architecture.md + SRS (TARGET label) | DOCUMENT TARGET |
| Analytics/observability | adoption-matrix (OpenPanel/Langfuse TARGET); retention-events.md | not installed | No | TARGET → P32; retention taxonomy v1 = retention-events.md | architecture.md + retention-events.md | PRESERVE |
| Repository split | register: NO split before P34; architecture.md §Repository boundaries | single repo (Core=whole repo) | No | Core/Cloud split → P34 | architecture.md | PRESERVE |
| Information architecture | SRS §14.1 says `Calendar` (STALE); design.md §9/§104; navigation.ts EXECUTE/PLAN/KNOWLEDGE/REVIEW/SYSTEM | 13 destinations; Calendar nav exists (navigation.ts:22) | **STALE SRS + naming decision needed** | Canonical: NOW(Today/Week/Month) · BUILD(Goals/Tasks) · THINK(Knowledge/Canvas) · REFLECT(Progress/Review) + Import & Sync/Notifications/Settings + Capture; Week vs Calendar vs Month naming settled (Week=7-day plan; Month=month grid; Calendar term deprecated in nav) | docs/ux/information-architecture.md | CREATE + SRS FIX |
| Design language | design.md (Tactile Editorial); brand.md; design-tokens.md; design_constitution export | tokens implemented (colors/typography/motion in tokens/) | Overlap | One canonical design system doc set under docs/ux/ (Tactile Editorial product; Editorial Constructivism marketing) | docs/ux/design-system.md | CREATE (consolidate) |
| Interaction states | state-machine-ui.md (accurate) | VisualStateBadge + InlineError + sync states | No | Merge state-machine-ui into canonical interaction-states doc | docs/ux/interaction-states.md | MERGE |
| Motion | design-tokens.md §7; design.md §motion | motion scale implemented | No | Mechanical/editorial/precise; reduced-motion respected; Three.js = experiment only | docs/ux/motion.md | CREATE |
| Content/terminology | scattered (design.md §17, §104); retention-events privacy | UI copy implemented (EN) | No | Canonical terminology table + bilingual readiness | docs/ux/content-design.md | CREATE |
| Accessibility | SRS NFR-07; ui-audit; accessibility.spec (axe zero) | implemented + tested | No | Canonical a11y contract | docs/ux/accessibility.md | CREATE |
| Marketing/site | brand.md; marketing copy constitution (Stitch root md) | no site implemented | No | Site spec + claims registry; landing = vertical manifesto | docs/marketing/site-specification.md + claims-registry.md | CREATE |
| Security/privacy claims | data_privacy_kinevo + security_trust (Stitch, unverified) | NFR-02/03; encrypted AI keys; no GDPR/SOC2 evidence | **CLAIM RISK** | SAFE current claims list; prohibit unverified (GDPR/SOC2/zero-knowledge) | docs/marketing/claims-registry.md | CREATE with prohibitions |
| Stitch export | none (untracked 122MB local) | 129 dirs + 2 root files (124 PNG + 60 HTML) | n/a | LOCAL_REFERENCE_ONLY; gitignored; convergence matrix classifies every unit | docs/ux/stitch-convergence-matrix.md + stitch-reference.md | INVENTORY+CLASSIFY |
| Requirements traceability | SRS §18 (10 chains) | tests exist per chain | Partial | Extend to 10 canonical flows with UX surface + evidence columns | docs/requirements/requirements-traceability.md | CREATE |
| Wrapped | design.md §105; Stitch wrapped assets | not implemented | No | Deferred design direction (P32+ decision); marketing intensity allowed | docs/ux/stitch-convergence-matrix.md + site-spec | DOCUMENT ONLY |
| Mobile IA | mobile-architecture.md L92–97 (locked: Today·Tasks·Capture·Workspace·More) | NativePHP screens built (Phase 27) | No | Preserve locked mobile nav; Stitch mobile screens = P36 reference | mobile-architecture.md + stitch matrix | PRESERVE |

## 3. Stale-claim register (fix during P29)

| Where | Claim | Verdict | Fix |
|---|---|---|---|
| SRS.md §9.4 (L2419/2421) | "last-write-wins remains valid for the narrow MVP offline queue" | STALE (contradicts FR-44 L1326 + ADR-017) | Rewrite §Offline in v3 |
| SRS.md §14.1 (L2626) | nav list contains `Calendar` | STALE vs implementation | IA decision + rewrite |
| SRS.md (whole) | Workspace absent as requirement domain | GAP | New Workspace FRs |
| domain-model.md | 9 newer domain concepts missing; Workspace absent | GAP | Full rewrite |
| architecture.md | no status header; no CURRENT/TARGET; missing provider boundaries | GAP | Rewrite |
| mobile-architecture.md L24/L28/L38 | "Inertia + Vue SPA" | STALE (never installed) | Narrow rewrite |
| mobile-architecture.md L96 | mobile-IA principle mis-cited to "(ADR-013)" | STALE cite | Fix cite |
| design-tokens.md L36 | "`--k-*` CSS prefix" | STALE (actual `--color-*`/`--radius-*`) | Fix during merge |
| design-tokens.md L290–293 | primary deepened to `#DE3005` | STALE (colors.ts = `#F53003`) | Fix during merge |
| brand.md L16 | "Inter stack" | STALE (Instrument Sans) | Fix during merge |
| knowledge-layer.md | missing base_version/409 contract; no asset-pipeline linkage | GAP | Narrow rewrite |

## 4. Disposition plan (executed at P29.16)

- CREATE: docs/product/{product-constitution,vision-mission,product-model,workspace-model,commercial-model}.md · docs/requirements/requirements-traceability.md · docs/ux/{information-architecture,stitch-reference,stitch-convergence-matrix,design-system,interaction-states,content-design,motion,accessibility}.md · docs/marketing/{site-specification,claims-registry,asset-provenance}.md
- REWRITE in place: docs/SRS.md (v3.0.0) · docs/architecture.md · docs/domain-model.md · docs/knowledge-layer.md (narrow) · docs/mobile-architecture.md (narrow)
- MERGE then ARCHIVE: design.md + design-tokens.md + brand.md + state-machine-ui.md → docs/ux/* (originals → docs/archive/design-legacy-2026-08-31/)
- ARCHIVE (evidence): docs/ui-audit.md, docs/implementation-status.md, docs/hardening-evidence.md, docs/convergence/TARGET_DECISION_REGISTER.md (after migration), docs/convergence/OFFLINE_CAPABILITY_MATRIX_2026-08-31.md → docs/archive/
- KEEP: billing.md (+ pointer), billing-capability-matrix.md, ai-architecture.md (+ header), offline-sync.md, scheduling-engine.md (+ header), third-party/*, retention-events.md, browser-e2e.md, audit/*, adr/*, roadmap/*, ops docs
- STITCH EXPORT: LOCAL_REFERENCE_ONLY (gitignored; never committed)
