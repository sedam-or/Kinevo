---
id: T-303
title: Build the AI breakdown UI: provider setup, sent-data preview, proposal review and accept
milestone: M6
kind: feature
status: todo
depends_on: [T-302, T-110]
requirements: [AI-FR-01, AI-FR-07, AI-FR-10, SEC-AC-32, FE-04, FE-14, UXS-02, DS-A11Y-13, J6]
branch: m6-t303-web-ai
budget_lines: 420
assignee: ""
---

# T-303: Build the AI breakdown UI: provider setup, sent-data preview, proposal review and accept

## Goal

A user can set their own provider, see exactly what will be sent, review and edit an AI proposal, and accept or reject it; with AI off the app shows only an honest explanation.

## Context

> **Surfaces** (`spec/08-frontend.md` §3): Settings → AI (BYOK form: kind, base URL, model, API key field that is write-only and shows `••••` + last 4; **Test connection**; Remove); on the goal detail, a **Break down with AI** action visible only when the module is enabled; flow: preview sheet (`ai.sent.preview` showing `user_prompt_preview` and `data_fields`) → Generate (progress state) → proposal review (milestones with tasks and subtasks as an editable tree: edit titles, estimates, priority, remove or add items, reorder) → Accept / Reject.
> **States** (`standards/ux-states.md`): `ai.not_configured` (admin: link to settings; user: note), `ai.output_invalid` (reasons rendered from codes, Try again), `ai.limit` (`ai_limit_reached` with window), `ai.provider_unavailable`/`timeout` (Retry), `ai.private_ip` (`ai_host_not_allowed` inline), proposal `pending`/`accepted`/`rejected`/`expired`, `version_conflict` keeps edits. With the module off (AI-FR-10) no AI UI appears except the explanation page reached from settings.
> **Safety in the UI** (SEC-AC-32): all AI text is rendered as text nodes; the editor uses plain inputs; no HTML injection. Every mutating call sends `Idempotency-Key`; accept sends `base_version`. The browser never calls a provider; no key is stored in the browser beyond the form field until submit.
> **Journey J6**: set BYOK (fake provider in CI) → open a goal → see the preview → generate → review → edit one task → accept → milestones and tasks exist; reject path; invalid-output path; limit path; private-IP URL refused.

## Scope

**In:**
- Feature folder `web/src/features/ai/**`, settings route `/settings/ai`, goal-detail action, i18n (en, id), MSW handlers, Vitest tests, Playwright `j6.spec.ts` against the server with the fake provider server started by the e2e harness, a11y gate routes.

**Out:**
- Server changes.

## Files

| Path | Action | Notes |
|---|---|---|
| `web/src/features/ai/**` | create |  |
| `web/src/i18n/{en,id}/ai.json` | create |  |
| `web/src/features/goals/GoalDetail.tsx` | modify | AI action slot only |
| `web/tests/{ai.test.tsx,e2e/journeys/j6.spec.ts}` | create | AC-1…AC-9 |
| `web/tests/e2e/harness/fake-provider.ts` | create | e2e fake provider |
| `web/tests/e2e/gates/a11y.spec.ts` | modify | add routes |

## Interfaces

```ts
useAiStatus(); useAiProvider(); useSaveProvider(); useTestProvider(); usePreviewBreakdown(goalId, hint); useGenerateBreakdown(goalId); useProposal(id); useSaveProposal(id); useAcceptProposal(id); useRejectProposal(id);
```

## Business rules

1. Reasons and errors render from codes through the mapper.
2. The API key input is never prefilled; after save only `key_last4` is shown.
3. With `ai` disabled the nav and goal action are absent.

## Security

- XSS test: a title `<img onerror=...>` renders as text.
- No key in localStorage, query cache or logs.

## Implementation notes

- The proposal tree editor is a controlled form using the T-045 primitives; keep components small.

## Acceptance

| # | Given / When / Then | Test name |
|---|---|---|
| AC-1 | **Given** AI not configured **When** the goal action is used **Then** `ai.not_configured` explains next steps | `ai::ai_fr_10_not_configured_state` |
| AC-2 | **Given** the preview **When** shown **Then** it lists the data fields and the exact text | `ai::ai_fr_01_preview_shows_sent_data` |
| AC-3 | **Given** a valid proposal **When** a task is edited and accepted **Then** the edited payload is sent with `base_version` | `ai::ai_fr_07_edit_then_accept` |
| AC-4 | **Given** `ai_output_invalid` **When** received **Then** reason codes render with Try again | `ai::output_invalid_state` |
| AC-5 | **Given** `ai_limit_reached` **When** received **Then** the limit state shows the window | `ai::limit_state` |
| AC-6 | **Given** a private-IP URL **When** saved **Then** the inline `ai_host_not_allowed` error shows | `ai::private_ip_refused_inline` |
| AC-7 | **Given** a title containing HTML **When** rendered **Then** it is literal text | `ai::sec_ac_32_html_is_text` |
| AC-8 | **Given** the AI module off **When** the shell loads **Then** no AI nav or action exists | `ai::module_off_hides_ui` |
| AC-9 | **Given** J6 **When** run with the fake provider **Then** accept, reject, invalid, limit and private-IP paths pass | `e2e::j6_ai_breakdown` |

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
