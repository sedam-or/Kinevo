# ADR-0003 — React + Vite SPA embedded in the server binary

- Status: **Accepted** (2026-10-25) — D-07; owner chose React + Vite.
- Supersedes legacy ADR-002 (Vue 3 + Inertia; note that the legacy code was already a plain Vue SPA without Inertia, `00-verdict.md` §2.1).

## Context
Two heavy editors are required by the product: Tiptap (notes) and Excalidraw (boards). Excalidraw is React-only; the legacy app hosted React inside Vue just for it. The web build must be embeddable in the Rust binary and in the Android shell, work offline as a PWA, and meet WCAG 2.2 AA.

## Decision
React 19 + Vite + TypeScript (strict), TanStack Router and Query, Tailwind CSS 4 with OKLCH tokens, React Aria Components for accessible primitives, React Hook Form + zod for forms, i18next. Static build embedded with `rust-embed`; the same build is bundled into the Android shell.

## Alternatives
| Option | For | Against |
|---|---|---|
| Vue 3 + Vite | existing skills | Excalidraw needs a React bridge permanently; smaller accessible-primitives ecosystem |
| SvelteKit (static adapter) | small bundles | same bridge problem; fewer contributors |
| Rust frontend (Leptos/Dioxus) | one language | loses Tiptap/Excalidraw; immature a11y primitives |
| SSR framework (Next/Remix) | SEO | needs a Node runtime; contradicts single-binary goal |

## Consequences
- The Vue UI is rewritten (accepted cost; spec-first rebuild anyway).
- Bundle size managed by route-level code splitting (FE-09).
- Revisit if React 19/20 breaks Excalidraw or Tiptap with no fix path, or TypeScript 7 tooling causes sustained friction.
