# Kinevo — Motion

> STATUS: AUTHORITATIVE (P29, 2026-08-31). Canonical motion authority
> (consolidates design-tokens.md §7 + design.md §48/§47). Stitch motion
> experiments (`motion_system_showcase`, `animated_svg_1–3`, `three.js`) are
> EXPERIMENTAL evidence — classified in `stitch-convergence-matrix.md` §7; none
> is committed or adopted wholesale (Three.js is REJECT for product).

## 1. Principle

Motion communicates: **state, continuity, progress, spatial relationship**.
Motion never decorates, never bounces, never loops for attention. The product
feels mechanical-editorial: precise, quick, physical.

## 2. Implemented duration tokens (single truth)

`tokens/motion.ts`: **micro 130ms** (state flips, badges, focus) · **panel 190ms**
(drawers, popovers, panels) · **modal 220ms** (dialogs, overlays). Easing:
standard ease-out for entrances, ease-in for exits; no spring/bounce curves.

## 3. Allowed motion

- State transitions: highlight/underline/border emphasis on completion (e.g.
  NEXT-card ring ~2s spotlight after task completion).
- Panel/modal enter-exit at token durations.
- Progress bar width transitions (`transition-all` on completion).
- Smooth scroll for focus hand-off (e.g. first-session capture CTA → input).
- Scanning-line / grid motifs: MARKETING ONLY (landing/site, not product).

## 4. Forbidden

Bounce, wobble, confetti, streak celebrations, parallax in product surfaces,
infinite decorative loops, hover animation on surface heroes (design rule:
heroes are still at rest), skeleton shimmer floods.

## 5. Reduced motion

`prefers-reduced-motion: reduce` always wins (design.md §47 contract): transitions
collapse to opacity-only or none; smooth-scroll becomes instant; the ~2s
completion spotlight is skipped. Every new motion effect must declare its
reduced-motion behavior in review.
