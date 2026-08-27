import { expect } from '@playwright/test';
import { asAuthenticated } from './helpers';

/**
 * COMMERCIAL PRICING DELTA D-003 (revisi-finance §13/§14) — plan settings
 * renders the NEW launch-price hypotheses, per-tier positioning copy, the
 * Power Rp40.000 gap, and an explicit upgrade CTA. Old prices must be absent.
 */
asAuthenticated('D-003: plan page shows Pro 49.900 / Power 89.900 with positioning and upgrade CTA', async (page) => {
    await page.getByTestId('nav-plan-settings').click();
    await page.getByTestId('plan-settings-view').waitFor({ state: 'visible' });

    // New prices rendered (id-ID formatting may insert a non-breaking space).
    await expect(page.getByTestId('plan-price-pro')).toContainText(/Rp\s*49\.900/);
    await expect(page.getByTestId('plan-price-power')).toContainText(/Rp\s*89\.900/);
    await expect(page.getByTestId('plan-price-free')).toContainText('Rp0');

    // Positional tier copy (revisi-finance §1/§14).
    await expect(page.getByTestId('plan-position-free')).toContainText('Experience the system.');
    await expect(page.getByTestId('plan-position-pro')).toContainText('For serious personal use.');
    await expect(page.getByTestId('plan-position-power')).toContainText('For intensive personal use.');

    // Power differentiation must name value (capacity/depth/intelligence), not cosmetics.
    await expect(page.getByTestId('plan-bullets-power')).toContainText('Largest included AI allowance');
    await expect(page.getByTestId('plan-bullets-power')).toContainText('Deepest history');

    // The Pro->Power gap is computed from the API and explained.
    await expect(page.getByTestId('plan-power-gap')).toContainText('Rp40.000');

    // Truthful data source: entitlement counts render from the API catalog
    // (Free=1, Pro=5); Power is positioned on capacity/depth, not cosmetics.
    await expect(page.getByTestId('plan-bullets-free')).toContainText('1 workspace');
    await expect(page.getByTestId('plan-bullets-pro')).toContainText('5 workspaces');
    await expect(page.getByTestId('plan-bullets-power')).toContainText('Higher workspace capacity');

    // Explicit upgrade CTA to Power exists.
    await expect(page.getByTestId('plan-upgrade-power')).toBeVisible();
});