import AxeBuilder from "@axe-core/playwright";
import { expect, test } from "@playwright/test";

test("overlays.a11y::tst_11_overlays_axe_and_live_regions", async ({ page }) => {
  await page.goto("/__components");
  await page.getByRole("button", { name: "Show success toast" }).click();
  await expect(
    page.getByRole("region", { name: "Notifications" }).getByRole("status"),
  ).toContainText("Saved successfully.");

  const results = await new AxeBuilder({ page })
    .withTags(["wcag2a", "wcag2aa", "wcag21aa", "wcag22aa"])
    .analyze();
  expect(
    results.violations.filter((violation) =>
      ["serious", "critical"].includes(violation.impact ?? ""),
    ),
  ).toEqual([]);
});

test("overlays::reduced_motion_respected", async ({ page }) => {
  await page.emulateMedia({ reducedMotion: "reduce" });
  await page.goto("/__components");
  await expect(page.getByTestId("loading-skeleton")).toBeVisible();
  await page.getByRole("button", { name: "Open dialog" }).click();
  const modal = page.getByRole("dialog", { name: "Edit preview" });
  const motionStyles = await modal.evaluate((element) => ({
    transform: getComputedStyle(element).transform,
    transitionDuration: getComputedStyle(element).transitionDuration,
  }));
  expect(motionStyles.transform).toBe("none");
  expect(Number.parseFloat(motionStyles.transitionDuration) * 1000).toBeLessThanOrEqual(120);
});
