import AxeBuilder from "@axe-core/playwright";
import { chromium } from "@playwright/test";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { cleanup, render, screen, waitFor, within } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { HttpResponse, http } from "msw";
import type { ReactNode } from "react";
import { afterEach, beforeEach, describe, expect, it } from "vitest";
import type { components } from "../../../api/schema";
import { authManager } from "../../../auth/AuthManager";
import { AuthProvider } from "../../../auth/AuthProvider";
import { server } from "../../../test/setup";
import { SettingsLayout } from "../../shell/SettingsLayout";
import { PasswordForm } from "./PasswordForm";
import { ProfilePanel } from "./ProfilePanel";
import { SessionsPanel } from "./SessionsPanel";
import { TotpPanel } from "./TotpPanel";

const account: components["schemas"]["AccountProfile"] = {
  id: "00000000-0000-7000-8000-000000000052",
  email: "person@example.test",
  display_name: "Test person",
  locale: "en",
  timezone: "Etc/UTC",
  totp_enabled: false,
  is_instance_admin: false,
  version: 7,
  week_start: 1,
};

const sessionItems = [
  {
    id: "current-session",
    device_label: "Firefox on Linux",
    created_at: "2026-10-30T00:00:00Z",
    last_used_at: "2026-10-30T00:00:00Z",
    current: true,
  },
  {
    id: "old-session",
    device_label: "Chrome on Linux",
    created_at: "2026-10-29T00:00:00Z",
    last_used_at: "2026-10-29T12:00:00Z",
    current: false,
  },
];

function problem(code: string, status: number) {
  return {
    type: "about:blank",
    title: "Synthetic response",
    status,
    detail: "Synthetic response",
    code,
    request_id: "00000000-0000-7000-8000-000000000052",
  };
}

function renderSettings(node: ReactNode) {
  const queryClient = new QueryClient({
    defaultOptions: { queries: { retry: false }, mutations: { retry: false } },
  });
  const view = render(
    <QueryClientProvider client={queryClient}>
      <AuthProvider manager={authManager}>{node}</AuthProvider>
    </QueryClientProvider>,
  );
  return { ...view, queryClient };
}

beforeEach(() => {
  authManager.acceptSession({ access_token: "synthetic-session-token", expires_in: 600 });
});

afterEach(() => {
  cleanup();
  server.resetHandlers();
});

describe("settings", () => {
  it("settings::profile_save_uses_if_match", async () => {
    const user = userEvent.setup();
    let match: string | null = null;
    let patch: unknown;
    server.use(
      http.get("/api/v1/account", () => HttpResponse.json(account)),
      http.patch("/api/v1/account", async ({ request }) => {
        match = request.headers.get("If-Match");
        patch = await request.json();
        return HttpResponse.json({ ...account, display_name: "Updated person", version: 8 });
      }),
    );

    renderSettings(<ProfilePanel />);
    const name = await screen.findByRole("textbox", { name: "Display name" });
    await user.clear(name);
    await user.type(name, "Updated person");
    await user.click(screen.getByRole("button", { name: "Save profile" }));

    await waitFor(() => expect(match).toBe('"7"'));
    expect(patch).toMatchObject({ display_name: "Updated person" });
    expect(await screen.findByDisplayValue("Updated person")).toBeVisible();
  });

  it("settings::fe_14_profile_conflict_keeps_text", async () => {
    const user = userEvent.setup();
    server.use(
      http.get("/api/v1/account", () => HttpResponse.json(account)),
      http.patch("/api/v1/account", () =>
        HttpResponse.json(problem("version_conflict", 409), { status: 409 }),
      ),
    );

    renderSettings(<ProfilePanel />);
    const name = await screen.findByRole("textbox", { name: "Display name" });
    await user.clear(name);
    await user.type(name, "My text stays here");
    await user.click(screen.getByRole("button", { name: "Save profile" }));

    expect(await screen.findByText("This changed elsewhere")).toBeVisible();
    expect(screen.getByRole("textbox", { name: "Display name" })).toHaveValue("My text stays here");
  });

  it("settings::password_change_error_mapping", async () => {
    const user = userEvent.setup();
    let revoked = false;
    server.use(
      http.post("/api/v1/account/password", () =>
        HttpResponse.json(problem("current_password_incorrect", 401), { status: 401 }),
      ),
      http.post("/api/v1/auth/logout-all", () => {
        revoked = true;
        return new HttpResponse(null, { status: 204 });
      }),
    );

    renderSettings(<PasswordForm />);
    await user.type(screen.getByLabelText("Current password"), "wrong-password");
    await user.type(screen.getByLabelText("New password"), "synthetic-new-password");
    await user.click(screen.getByRole("button", { name: "Change password" }));

    expect(await screen.findByText("Current password is incorrect")).toBeVisible();
    expect(revoked).toBe(false);
  });

  it("settings::id_fr_08_enrol_shows_codes_once", async () => {
    const user = userEvent.setup();
    const recoveryCodes = Array.from({ length: 10 }, (_, index) => `recovery-code-${index + 1}`);
    server.use(
      http.get("/api/v1/account", () => HttpResponse.json(account)),
      http.post("/api/v1/account/totp/enroll", () =>
        HttpResponse.json({
          otpauth_uri:
            "otpauth://totp/Nivaras:person%40example.test?secret=JBSWY3DPEHPK3PXP&issuer=Nivaras",
          recovery_codes: recoveryCodes,
        }),
      ),
      http.post("/api/v1/account/totp/confirm", () => new HttpResponse(null, { status: 204 })),
    );

    renderSettings(<TotpPanel />);
    await user.click(await screen.findByRole("button", { name: "Set up two-step verification" }));
    expect(await screen.findByText("JBSW-Y3DP-EHPK-3PXP")).toBeVisible();
    await user.type(screen.getByLabelText("Authentication code"), "123456");
    await user.click(screen.getByRole("button", { name: "Confirm code" }));

    const dialog = await screen.findByRole("dialog", { name: "Recovery codes" });
    for (const code of recoveryCodes) expect(within(dialog).getByText(code)).toBeVisible();
    expect(
      await screen.findByText("Two-step verification is on. Save your recovery codes."),
    ).toBeVisible();
  });

  it("settings::recovery_codes_not_retained", async () => {
    const user = userEvent.setup();
    const recoveryCodes = Array.from({ length: 10 }, (_, index) => `one-time-code-${index + 1}`);
    server.use(
      http.get("/api/v1/account", () => HttpResponse.json(account)),
      http.post("/api/v1/account/totp/enroll", () =>
        HttpResponse.json({
          otpauth_uri: "otpauth://totp/test?secret=JBSWY3DPEHPK3PXP",
          recovery_codes: recoveryCodes,
        }),
      ),
      http.post("/api/v1/account/totp/confirm", () => new HttpResponse(null, { status: 204 })),
    );

    const { container, queryClient } = renderSettings(<TotpPanel />);
    await user.click(await screen.findByRole("button", { name: "Set up two-step verification" }));
    await screen.findByLabelText("Authentication code");
    await user.type(screen.getByLabelText("Authentication code"), "123456");
    await user.click(screen.getByRole("button", { name: "Confirm code" }));
    const dialog = await screen.findByRole("dialog", { name: "Recovery codes" });

    await user.click(within(dialog).getByRole("checkbox", { name: "I saved these codes" }));
    await user.click(within(dialog).getByRole("button", { name: "Done" }));

    await waitFor(() =>
      expect(screen.queryByRole("dialog", { name: "Recovery codes" })).toBeNull(),
    );
    for (const code of recoveryCodes) expect(container).not.toHaveTextContent(code);
    expect(JSON.stringify(queryClient.getQueryCache().getAll())).not.toContain("one-time-code-");
  });

  it("settings::uxs_04_revoke_session_alertdialog", async () => {
    const user = userEvent.setup();
    let deleted = false;
    server.use(
      http.get("/api/v1/account/sessions", () =>
        HttpResponse.json({ items: sessionItems, next_cursor: null }),
      ),
      http.delete("/api/v1/account/sessions/old-session", () => {
        deleted = true;
        return new HttpResponse(null, { status: 204 });
      }),
    );

    renderSettings(<SessionsPanel />);
    await screen.findByText("Chrome on Linux");
    const currentRow = screen.getByText("Firefox on Linux").closest("li");
    expect(currentRow).toHaveTextContent("Current session");
    if (!currentRow) throw new Error("Current session row was missing");
    expect(within(currentRow).queryByRole("button")).toBeNull();
    await user.click(screen.getByRole("button", { name: "Revoke Chrome on Linux" }));

    const dialog = await screen.findByRole("alertdialog", { name: "Revoke session" });
    expect(document.activeElement).toBe(within(dialog).getByRole("button", { name: "Cancel" }));
    await user.click(within(dialog).getByRole("button", { name: "Cancel" }));
    expect(deleted).toBe(false);

    await user.click(screen.getByRole("button", { name: "Revoke Chrome on Linux" }));
    const confirm = await screen.findByRole("alertdialog", { name: "Revoke session" });
    await user.click(within(confirm).getByRole("button", { name: "Revoke session" }));
    await waitFor(() => expect(deleted).toBe(true));
    await waitFor(() => expect(screen.queryByText("Chrome on Linux")).toBeNull());
  });

  it("settings::totp_required_by_policy_message", async () => {
    const user = userEvent.setup();
    server.use(
      http.get("/api/v1/account", () => HttpResponse.json({ ...account, totp_enabled: true })),
      http.delete("/api/v1/account/totp", () =>
        HttpResponse.json(problem("totp_required_by_policy", 409), { status: 409 }),
      ),
    );

    renderSettings(<TotpPanel />);
    await user.type(await screen.findByLabelText("Current password"), "synthetic-password");
    await user.type(screen.getByLabelText("Authentication code"), "123456");
    await user.click(screen.getByRole("button", { name: "Turn off two-step verification" }));

    expect(
      await screen.findByText("Two-step verification is required by this server's policy."),
    ).toBeVisible();
  });

  it("settings.a11y::tst_11_settings_axe_clean", async () => {
    server.use(
      http.get("/api/v1/account", () => HttpResponse.json(account)),
      http.get("/api/v1/account/sessions", () =>
        HttpResponse.json({ items: sessionItems, next_cursor: null }),
      ),
    );
    renderSettings(
      <SettingsLayout
        activePanel="profile"
        panels={[
          { key: "profile", label_key: "settings.profile" },
          { key: "security", label_key: "settings.security" },
        ]}
      >
        <ProfilePanel />
        <PasswordForm />
        <TotpPanel />
        <SessionsPanel />
      </SettingsLayout>,
    );
    await screen.findByRole("textbox", { name: "Display name" });
    await screen.findByText("Firefox on Linux");
    await screen.findByRole("button", { name: "Set up two-step verification" });
    const markup = document.body.innerHTML;
    const browser = await chromium.launch({ headless: true });
    try {
      const context = await browser.newContext();
      const page = await context.newPage();
      await page.setContent(
        `<html lang="en"><head><title>Kinevo settings</title></head><body>${markup}</body></html>`,
      );
      const result = await new AxeBuilder({ page }).analyze();
      expect(
        result.violations.filter((item) => item.impact === "serious" || item.impact === "critical"),
      ).toEqual([]);
    } finally {
      await browser.close();
    }
  });
});
