/**
 * Compile-time build flags (injected by vite.config.ts `define`).
 * True only in e2e artifacts built with KINEVO_E2E_SEAM=1; plain builds get a
 * literal false, so the guarded seam code is dead-code-eliminated.
 */
declare const __KINEVO_E2E_SEAM__: boolean;
