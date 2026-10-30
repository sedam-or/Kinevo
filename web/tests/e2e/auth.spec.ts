import { expect, test } from "@playwright/test";

const account = {
  id: "00000000-0000-7000-8000-000000000049",
  email: "person@example.test",
  display_name: "Test person",
  locale: "en",
  timezone: "Etc/UTC",
  totp_enabled: false,
  is_instance_admin: false,
  version: 1,
  week_start: 1,
};

test("auth.e2e::sec_ac_01b_no_token_in_storage", async ({ page }) => {
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
          request_id: "00000000-0000-7000-8000-000000000001",
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

  await page.goto("/login?next=%2F");
  await page.getByRole("textbox", { name: "Email address" }).fill("person@example.test");
  await page.getByLabel("Password").fill("synthetic-password");
  await page.getByRole("button", { name: "Sign in" }).click();
  await expect(page).toHaveURL(/\/$/);
  await page.reload();

  const storage = await page.evaluate(async () => {
    const values = [JSON.stringify(localStorage), JSON.stringify(sessionStorage)];
    const databases = typeof indexedDB.databases === "function" ? await indexedDB.databases() : [];
    for (const database of databases) {
      if (!database.name) continue;
      const request = indexedDB.open(database.name);
      const db = await new Promise<IDBDatabase>((resolve, reject) => {
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
      });
      for (const storeName of Array.from(db.objectStoreNames)) {
        const transaction = db.transaction(storeName, "readonly");
        const rows = await new Promise<unknown>((resolve, reject) => {
          const rowsRequest = transaction.objectStore(storeName).getAll();
          rowsRequest.onsuccess = () => resolve(rowsRequest.result);
          rowsRequest.onerror = () => reject(rowsRequest.error);
        });
        values.push(JSON.stringify(rows));
      }
      db.close();
    }
    return values.join(" ");
  });
  expect(storage).not.toContain("e2e_access_token_only_in_memory");
});
