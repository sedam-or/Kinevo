import { expect } from '@playwright/test';
import { apiFetch, asAuthenticated } from './helpers';

/**
 * COMMERCIAL PRICING DELTA D-003 (revisi-finance §13/§14) — plan settings
 * renders the NEW launch-price hypotheses, per-tier positioning copy, the
 * Power Rp40.000 gap, and explicit upgrade CTAs. Old prices (and any reading
 * of 4.990.000/8.990.000 as a price) must be absent.
 *
 * Deterministic: the shared owner starts on a paid plan, so the spec first
 * switches to Free to exercise the upgrade path, then restores Power.
 */
asAuthenticated('D-003: prices render as Rp49.900/Rp89.900 with upgrade CTAs (never 4,9 jt)', async (page) => {
    await page.getByTestId('nav-plan-settings').click();
    await page.getByTestId('plan-settings-view').waitFor({ state: 'visible' });

    // Switch to Free so the upgrade CTAs exist for sure (self-serve switch).
    await apiFetch(page, '/api/v1/saas/plan', { method: 'PATCH', body: JSON.stringify({ plan_code: 'free' }) });
    await page.reload();
    await page.getByTestId('nav-plan-settings').click();
    await page.getByTestId('plan-settings-view').waitFor({ state: 'visible' });

    // New prices rendered (id-ID formatting may insert a non-breaking space).
    await expect(page.getByTestId('plan-price-pro')).toContainText(/Rp\s*49\.900/);
    await expect(page.getByTestId('plan-price-power')).toContainText(/Rp\s*89\.900/);
    await expect(page.getByTestId('plan-price-free')).toContainText('Rp0');

    // Hard guard: the CENT-equivalent values must never surface as the price
    // (4_990_000 minor == Rp49.900, never "4,9 juta"); only CANONICAL majors
    // appear, and no "4,9"/"8,9 juta" phrasing.
    await expect(page.getByTestId('plan-price-pro')).not.toContainText('4.990.000');
    await expect(page.getByTestId('plan-price-power')).not.toContainText('8.990.000');
    await expect(page.getByTestId('plan-price-pro')).not.toContainText('4.9 juta');
    await expect(page.getByTestId('plan-price-power')).not.toContainText('8.9 juta');

    // Positional tier copy (revisi-finance §1/§14).
    await expect(page.getByTestId('plan-position-free')).toContainText('Experience the system.');
    await expect(page.getByTestId('plan-position-pro')).toContainText('For serious personal use.');
    await expect(page.getByTestId('plan-position-power')).toContainText('For intensive personal use.');

    // Power differentiation must name value (capacity/depth/intelligence), not cosmetics.
    await expect(page.getByTestId('plan-bullets-power')).toContainText('Largest included AI allowance');
    await expect(page.getByTestId('plan-bullets-power')).toContainText('Deepest history');

    // The Pro->Power gap is computed from the API and explained.
    await expect(page.getByTestId('plan-power-gap')).toContainText('Rp40.000');

    // Truthful data source: entitlement counts render from the API catalog.
    await expect(page.getByTestId('plan-bullets-free')).toContainText('1 workspace');
    await expect(page.getByTestId('plan-bullets-pro')).toContainText('5 workspaces');

    // Explicit upgrade CTAs to Pro and Power (Free state).
    await expect(page.getByTestId('plan-upgrade-pro')).toBeVisible();
    await expect(page.getByTestId('plan-upgrade-power')).toBeVisible();

    // Restore the shared owner world to Power and confirm the downgrade CTA.
    await apiFetch(page, '/api/v1/saas/plan', { method: 'PATCH', body: JSON.stringify({ plan_code: 'power' }) });
    await page.reload();
    await page.getByTestId('nav-plan-settings').click();
    await page.getByTestId('plan-settings-view').waitFor({ state: 'visible' });
    await expect(page.getByTestId('plan-current-name')).toContainText('Power');
    await expect(page.getByTestId('plan-switch-free')).toBeVisible();
});