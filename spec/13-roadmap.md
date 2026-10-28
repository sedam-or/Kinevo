# 13 — Roadmap, milestones and validation plan

Depends on: `01-product.md`, `decisions.md` (D-20). Prefix: `RM`. A milestone is a set of tasks (`tasks/`) ending in an **integration task** that runs the milestone's user journey end to end. Only the next two milestones' tasks are `todo`; later ones stay `draft` until the previous integration task is accepted by the architect.

## 1. Milestones

| M | Name | Scope (specs) | Exit gate (observable) | Release tag |
|---|---|---|---|---|
| **M0** | Specification & repository | this `spec/`, agent rules, tasks, CI skeleton, governance, brand | Phase 6 gate report "0 known contradictions" or residual list; PRs open | — |
| **M1** | Foundation | Cargo workspace; `kinevo-kernel` (typed field validators and shared fixtures), `kinevo-db` (roles, `Tx`, RLS, migrations runner, test kit), `kinevo-http`, modules `identity` + `admin` (setup wizard, modules, policies, audit, jobs, Argon2id password policy and provenance-tracked common-password data); web scaffold (shell, auth manager, i18n, tokens, design primitives, error/loading/empty states); Docker image + compose; CI incl. isolation/architecture/contrast checks | J0: clean `docker compose up` → wizard (Next, Next, Finish) → login → empty Today; TST-06 green | `v0.1.0` |
| **M2** | Planning core | `workspaces`, `goals` (+milestones), `tasks` (+subtasks, status machine); web: Goals, Tasks | J2: create workspace, goal, milestones, tasks; complete a task; undo | `v0.2.0` |
| **M3** | Reality & scheduling | `landscape` (events, recurrence, overrides, effective view, ICS import/export); `kinevo-scheduler` crate with golden + WASM parity; `scheduler` module (drafts, apply, Sync Now, weekly job, review state); web: Landscape, Schedule | J3: import ICS → generate draft → review reasons → apply → move landscape → Sync Now proposes changes | `v0.3.0` |
| **M4** | Execution & progress — the first-love loop | `execution` (Today, focus sessions), `progress`; PWA manifest + install; keyboard shortcuts; a11y pass on core screens | J1 (login → Today → Now task → Start → Complete → Progress → Next) on 3 browsers; axe clean; **usability trial T3** | `v0.4.0` (first alpha for outside testers) |
| **M5** | Offline | `sync` module; web offline queue, cache, conflict cards; WASM preview | J5 offline journey on 3 browsers; TST-19/20 for reconcile | `v0.5.0` |
| **M6** | AI breakdown | `ai` module, BYOK, Ollama/OpenAI-compatible adapters, preview-of-sent-data, proposal review UI | J6: configure provider → breakdown → edit → accept creates milestones + tasks; abuse cases green | `v0.6.0` |
| **M7** | Knowledge | `knowledge` (Tiptap notes, links, search, backlinks) | J7a: write, link, search, conflict handling | `v0.7.0` |
| **M8** | Canvas | `canvas` (Excalidraw boards, files, links) | J7b: draw, attach image, link, reload; offline read-only | `v0.8.0` |
| **M9** | Cloud | `billing` + operator tooling (OWNER-01/02/04/05/06 answered first) | J8: checkout (fake + one real sandbox), webhook, entitlements, cancel; ops runbooks; privacy/terms drafts reviewed (M-LEGAL) | `v0.9.0` (cloud beta) |
| **M10** | Android | Capacitor shell, Kotlin plugins, token transport, CORS origin, release pipeline | J9 on emulator + 1 physical device; AND-01…11 tests | `v0.10.0` |
| **M11** | Hardening → 1.0 | manual a11y audit (DS-A11Y-16), security review, benchmarks, docs, upgrade tests, beta feedback, licence/provenance audit | all 1.0 gates (§3) green | `v1.0.0` |

Why this order: it delivers the core loop (M1–M4) before any optional module, so each increment is usable and testable; AI/Knowledge/Canvas depend on `goals`/`tasks`/`workspaces` only; `billing` and Android are last because they depend on owner decisions and a stable API.

## 2. Validation plan (real-user validation is NOT done; these are the smallest useful exercises)

Nothing below has been performed. Results must be recorded in `docs/research/` as raw notes + summary; no result may be written before it happens.

| ID | Exercise | Participants | Method | Success | Failure → action | When |
|---|---|---|---|---|---|---|
| **V1** | Problem interviews (is the problem real and who has it?) | 8–12 people who juggle several projects with fixed commitments: students, professionals | 30-min semi-structured interviews (script in `docs/research/interview-script.md`, Phase 5); ask about last time a plan broke, current tools, weekly replanning, willingness to self-host | ≥ 6 of 10 describe replanning at least weekly **and** ≥ 5 of 10 name fixed commitments as the cause **and** ≥ 4 of 10 would try a tool for it | persona/scope revised before M4 (execution UX) | start now; blocks M4 start and the 1.0 scope freeze |
| **V2** | Install trial (is self-hosting actually easy?) | 5 people comfortable with Docker | give README only; observe from `docker run` to first completed task | ≥ 4 of 5 within 10 minutes, no maintainer help | fix docs/wizard; repeat | after M1 (J0) |
| **V3** | First-love loop usability | 5 target users | think-aloud on J1 with the alpha; SUS questionnaire | ≥ 4 of 5 complete J1 unaided; mean SUS ≥ 70 | redesign Today/Start/Complete before M5 | after M4 |
| **V4** | Module demand | the same V1/V3 participants | card sort: which of AI, notes, canvas, offline matter and which would they switch off | each shipped optional module is wanted by ≥ 3 of 10; otherwise it moves behind a later RFC | adjust D-20 | with V1/V3 |
| **V5** | Cloud willingness (only if the cloud is pursued) | 10 V1 participants | ask what they would pay for hosted convenience; do not take payments | informs OWNER-01 only; no target set here | revisit business model | before M9 |

Thresholds are the architect's **assumptions** to make decisions explicit; the owner may change them before the exercise, not after seeing results.

## 3. 1.0 release gates

| Gate | Evidence |
|---|---|
| Functional | all journeys J0–J9 pass in CI on Chromium, Firefox, WebKit (J9 on emulator) |
| Specification | `spec_tool.py check` clean; traceability exceptions each justified |
| Security | all SEC-AC tests green; external review of auth and isolation (maintainer action M-SEC) or documented absence; no critical/high advisories |
| Accessibility | TST-11 green; manual audit DS-A11Y-16 done and recorded |
| Operations | restore drill and upgrade tests green; runbooks reviewed; image signed with SBOM/provenance |
| Privacy | PRV-01…09 satisfied; cloud documents counsel-reviewed |
| Validation | V1–V4 executed with recorded results; scope confirmed or amended by decision log entries |
| Legal/provenance | licence ledger complete; assets have provenance rows; fonts licences recorded; Karpathy `CLAUDE.md` permission resolved (D-23) |

## 4. Risks and mitigations

| ID | Risk | Likelihood / impact | Mitigation |
|---|---|---|---|
| RM-R1 | Second-system effect: rebuild stalls before parity of the core loop | medium / high | M1–M4 scope fixed to the core loop; optional modules gated by V-results; legacy tag stays runnable |
| RM-R2 | No real users validate the problem | medium / high | V1 early; scope narrowing is a planned outcome, not a failure |
| RM-R3 | One maintainer + AI implementers: spec/task ambiguity causes drift | high / medium | Phase 6 gate after every change; blocked-task procedure; small tasks (≤ 400 lines); architecture tests |
| RM-R4 | Rust CRUD productivity lower than expected | medium / medium | strict module templates; generated OpenAPI/TS client; shared test kit; defer low-value features |
| RM-R5 | RLS + sqlx complexity (connection state, pool reuse) | medium / high | `Tx` abstraction is the only path (CA-09); isolation tests (TST-06) in M1 before features |
| RM-R6 | WASM/native determinism divergence | low / high | integer-only crate, golden parity in CI (TST-05) |
| RM-R7 | Excalidraw/Tiptap upgrades break stored documents | medium / medium | stored data is validated JSON with `schema` versions; editors are adapters (CA-12); pin versions; migration readers (CON-C3) |
| RM-R8 | Legal/compliance for the cloud (privacy law, payments, store policies) | medium / high | M-LEGAL before M9 public beta; cloud is off by default in the open-source build |
| RM-R9 | AGPL deters contributors/adopters | medium / medium | stated clearly in README; DCO not CLA; revisit via RFC if adoption data shows harm |
| RM-R10 | AI provider cost/abuse in cloud | medium / medium | per-account limits, allowance policy (OWNER-02), BYOK |
| RM-R11 | Android WebView UX ceiling | medium / low | PWA first; measured limits before any native screen work |
| RM-R12 | Dependency churn (TypeScript 7, Vite 8, Tailwind 4, sqlx 0.9 …) | medium / medium | pin versions per `open-source-stack.md`; upgrade tasks are explicit and small |

## 5. Optional / deferred (each needs an RFC and evidence)

Plugin API; native Compose screens; Android widget; push notifications; Google/Microsoft calendar sync; monthly/yearly recurrence; multi-node scaling; end-to-end encryption; teams; legacy-data importer (OWNER-07); additional languages.
