import AxeBuilder from "@axe-core/playwright";
import { expect, test } from "@playwright/test";

test("components.a11y::tst_11_primitives_axe_clean", async ({ page }) => {
  await page.goto("/__components");
  const results = await new AxeBuilder({ page })
    .withTags(["wcag2a", "wcag2aa", "wcag21aa", "wcag22aa"])
    .analyze();
  expect(
    results.violations.filter(
      (violation) => violation.impact === "serious" || violation.impact === "critical",
    ),
  ).toEqual([]);
});

test("components.a11y::ds_a11y_03_target_size", async ({ page }) => {
  await page.goto("/__components");
  const primary = await page.getByRole("button", { name: "Primary action" }).boundingBox();
  expect(primary?.width).toBeGreaterThanOrEqual(44);
  expect(primary?.height).toBeGreaterThanOrEqual(44);

  const targets = page.locator("[data-a11y-target='standard']");
  for (const target of await targets.all()) {
    const box = await target.boundingBox();
    expect(box?.width).toBeGreaterThanOrEqual(24);
    expect(box?.height).toBeGreaterThanOrEqual(24);
  }
});
