/** UI pacing only; server export state and expiry remain authoritative. */
const exportPollBaseMs = 1_000;
const exportPollMaxMs = 30_000;

export function exportStatusPollInterval(pollCount: number): number {
  const exponent = Math.min(Math.max(pollCount - 1, 0), 5);
  return Math.min(exportPollBaseMs * 2 ** exponent, exportPollMaxMs);
}
