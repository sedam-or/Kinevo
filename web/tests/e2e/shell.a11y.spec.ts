import AxeBuilder from "@axe-core/playwright";
import { expect, type Page, test } from "@playwright/test";

const account = {
  id: "00000000-0000-7000-8000-000000000050",
  email: "person@example.test",
  display_name: "Test person",
  locale: "en",
  timezone: "Etc/UTC",
  totp_enabled: false,
  is_instance_admin: false,
  version: 1,
  week_start: 1,
};

async function openShell(page: Page, modules: unknown[] = []) {
  let refreshCount = 0;
  await page.route("**/api/v1/auth/refresh", async (route) => {
    refreshCount += 1;
    if (refreshCount === 1) {
      await route.fulfill({
        status: 401,
        contentType: "application/problem+json",
        body: JSON.stringify({
          type: "about:blank",
          title: "Authentication required.",
          status: 401,
          detail: "Authentication required.",
          code: "unauthenticated",
          request_id: "00000000-0000-7000-8000-000000000050",
        }),
      });
      return;
    }
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({ access_token: "e2e_access_token_only_in_memory", expires_in: 600 }),
    });
  });
  await page.route("**/api/v1/auth/login", async (route) => {
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({
        access_token: "e2e_access_token_only_in_memory",
        account,
        expires_in: 600,
        mfa_enrol_required: false,
      }),
    });
  });
  await page.route("**/api/v1/modules", async (route) => {
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({ items: modules }),
    });
  });

  await page.goto("/login?next=%2Ftoday");
  await page.getByRole("textbox", { name: "Email address" }).fill("person@example.test");
  await page.getByLabel("Password").fill("synthetic-password");
  await page.getByRole("button", { name: "Sign in" }).click();
  await expect(page).toHaveURL(/\/today$/);
}

test("shell.e2e::fe_01_disabled_module_route_has_way_back", async ({ page }) => {
  await openShell(page, [
    {
      key: "identity",
      kind: "core",
      enabled: true,
      version: 1,
      nav: [],
      settings_panels: [],
    },
    {
      key: "canvas",
      kind: "toggleable",
      enabled: false,
      version: 1,
      nav: [],
      settings_panels: [],
    },
  ]);
  await page.goto("/think/canvas");

  await expect(
    page.getByRole("heading", { name: "Canvas is turned off on this server." }),
  ).toBeVisible();
  await expect(page.getByRole("main").getByRole("link", { name: "Today" })).toHaveAttribute(
    "href",
    "/today",
  );
});

test("shell.a11y::ds_a11y_08_reflow_320", async ({ page }) => {
  await openShell(page);

  for (const width of [320, 360]) {
    await page.setViewportSize({ width, height: 800 });
    await expect(page.getByRole("navigation", { name: "Primary" })).toBeVisible();
    expect(
      await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth),
    ).toBe(true);
  }
});

test("shell.a11y::ds_a11y_12_landmarks_skip_link_help", async ({ page }) => {
  await openShell(page);

  await page.keyboard.press("Tab");
  await expect(page.locator(":focus")).toHaveAttribute("href", "#main-content");
  await expect(page.getByRole("heading", { level: 1 })).toHaveCount(1);
  await expect(page.getByRole("banner")).toBeVisible();
  await expect(page.getByRole("navigation", { name: "Primary" })).toBeVisible();
  await expect(page.getByRole("main")).toBeVisible();
  const help = page.getByRole("button", { name: "Help & shortcuts" });
  const firstPosition = await help.boundingBox();
  expect(firstPosition).not.toBeNull();
  await new AxeBuilder({ page }).analyze().then((result) => expect(result.violations).toEqual([]));

  await page.goto("/settings/profile");
  await expect(page.getByRole("heading", { level: 1 })).toHaveCount(1);
  const secondPosition = await page.getByRole("button", { name: "Help & shortcuts" }).boundingBox();
  expect(secondPosition).not.toBeNull();
  expect(secondPosition?.x).toBe(firstPosition?.x);
  expect(secondPosition?.y).toBe(firstPosition?.y);
});
