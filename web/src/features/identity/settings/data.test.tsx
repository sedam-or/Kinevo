import AxeBuilder from "@axe-core/playwright";
import { chromium } from "@playwright/test";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { cleanup, render, screen, waitFor, within } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { HttpResponse, http } from "msw";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { authManager } from "../../../auth/AuthManager";
import { AuthProvider } from "../../../auth/AuthProvider";
import { ToastProvider } from "../../../components/Toast";
import { server } from "../../../test/setup";
import { DataPanel } from "./DataPanel";

const exportId = "00000000-0000-7000-8000-000000000053";
const expiry = "2026-11-10T12:00:00Z";

function problem(code: string, status: number, details: Record<string, unknown> = {}) {
  return {
    type: "about:blank",
    title: "Synthetic response",
    status,
    detail: "Synthetic response",
    code,
    request_id: "00000000-0000-7000-8000-000000000053",
    details,
  };
}

function renderDataPanel() {
  const queryClient = new QueryClient({
    defaultOptions: { queries: { retry: false }, mutations: { retry: false } },
  });
  const view = render(
    <QueryClientProvider client={queryClient}>
      <AuthProvider manager={authManager}>
        <ToastProvider>
          <DataPanel />
        </ToastProvider>
      </AuthProvider>
    </QueryClientProvider>,
  );
  return { ...view, queryClient };
}

const originalCreateObjectURL = Object.getOwnPropertyDescriptor(URL, "createObjectURL");
const originalRevokeObjectURL = Object.getOwnPropertyDescriptor(URL, "revokeObjectURL");

function mockDownload() {
  const createObjectURL = vi.fn(() => "blob:synthetic-export");
  const revokeObjectURL = vi.fn();
  Object.defineProperty(URL, "createObjectURL", { configurable: true, value: createObjectURL });
  Object.defineProperty(URL, "revokeObjectURL", { configurable: true, value: revokeObjectURL });
  vi.spyOn(HTMLAnchorElement.prototype, "click").mockImplementation(() => {});
  return { createObjectURL, revokeObjectURL };
}

function restoreDescriptor(
  name: "createObjectURL" | "revokeObjectURL",
  descriptor?: PropertyDescriptor,
) {
  if (descriptor) Object.defineProperty(URL, name, descriptor);
  else Reflect.deleteProperty(URL, name);
}

beforeEach(() => {
  authManager.acceptSession({ access_token: "synthetic-session-token", expires_in: 600 });
});

afterEach(() => {
  cleanup();
  server.resetHandlers();
  vi.restoreAllMocks();
  restoreDescriptor("createObjectURL", originalCreateObjectURL);
  restoreDescriptor("revokeObjectURL", originalRevokeObjectURL);
});

describe("data settings", () => {
  it("data::data_export_flow_states", async () => {
    const user = userEvent.setup();
    const { createObjectURL, revokeObjectURL } = mockDownload();
    let statusReads = 0;
    let authorization: string | null = null;
    server.use(
      http.post("/api/v1/account/export", () =>
        HttpResponse.json({ export_id: exportId }, { status: 202 }),
      ),
      http.get(`/api/v1/account/export/${exportId}`, () => {
        statusReads += 1;
        return HttpResponse.json(
          statusReads === 1
            ? { state: "queued" }
            : { state: "ready", size_bytes: 3, expires_at: expiry },
        );
      }),
      http.get(`/api/v1/account/export/${exportId}/download`, ({ request }) => {
        authorization = request.headers.get("Authorization");
        return new HttpResponse(new Uint8Array([1, 2, 3]), {
          headers: {
            "Content-Type": "application/zip",
            "Content-Disposition": 'attachment; filename="account-export.zip"',
          },
        });
      }),
    );

    renderDataPanel();
    await user.click(screen.getByRole("button", { name: "Request export" }));

    expect(
      await screen.findByText("Preparing your export. We'll show it here when it's ready."),
    ).toBeVisible();
    const download = await screen.findByRole("button", { name: "Download" }, { timeout: 10_000 });
    expect(statusReads).toBeGreaterThanOrEqual(2);
    expect(screen.getByText(/Your export is ready until/u)).toBeVisible();

    await user.click(download);
    await waitFor(() => expect(authorization).toBe("Bearer synthetic-session-token"));
    expect(createObjectURL).toHaveBeenCalledOnce();
    expect(revokeObjectURL).toHaveBeenCalledOnce();
  });

  it("data::export_in_progress_handled", async () => {
    const user = userEvent.setup();
    server.use(
      http.post("/api/v1/account/export", () =>
        HttpResponse.json(problem("export_in_progress", 409, { export_id: exportId }), {
          status: 409,
        }),
      ),
      http.get(`/api/v1/account/export/${exportId}`, () => HttpResponse.json({ state: "running" })),
    );

    renderDataPanel();
    await user.click(screen.getByRole("button", { name: "Request export" }));

    expect(await screen.findByText(/Your export is being prepared/u)).toBeVisible();
    expect(screen.getByText(/running/u)).toBeVisible();
  });

  it("data::export_expired_state", async () => {
    const user = userEvent.setup();
    let requests = 0;
    const replacementId = "00000000-0000-7000-8000-000000000054";
    server.use(
      http.post("/api/v1/account/export", () => {
        requests += 1;
        return HttpResponse.json(
          { export_id: requests === 1 ? exportId : replacementId },
          { status: 202 },
        );
      }),
      http.get("/api/v1/account/export/:id", () =>
        HttpResponse.json({ state: "ready", size_bytes: 3, expires_at: expiry }),
      ),
      http.get(`/api/v1/account/export/${exportId}/download`, () =>
        HttpResponse.json(problem("export_expired", 410), { status: 410 }),
      ),
    );

    renderDataPanel();
    await user.click(screen.getByRole("button", { name: "Request export" }));
    await user.click(await screen.findByRole("button", { name: "Download" }));

    expect(await screen.findByText("This export has expired.")).toBeVisible();
    await user.click(screen.getByRole("button", { name: "Request again" }));
    await waitFor(() => expect(requests).toBe(2));
  });

  it("data::uxs_04_delete_alertdialog", async () => {
    const user = userEvent.setup();
    renderDataPanel();
    await user.click(screen.getByRole("button", { name: "Delete account" }));

    const dialog = await screen.findByRole("alertdialog", { name: "Delete account" });
    expect(dialog).toHaveTextContent("account");
    expect(dialog).toHaveTextContent("delete my account");
    expect(document.activeElement).toBe(within(dialog).getByRole("button", { name: "Cancel" }));
  });

  it("data::id_fr_10_last_admin_inline", async () => {
    const user = userEvent.setup();
    server.use(
      http.delete("/api/v1/account", () =>
        HttpResponse.json(problem("last_admin", 409), { status: 409 }),
      ),
    );

    renderDataPanel();
    await user.click(screen.getByRole("button", { name: "Delete account" }));
    const dialog = await screen.findByRole("alertdialog", { name: "Delete account" });
    await user.type(within(dialog).getByLabelText("Current password"), "synthetic-password");
    await user.type(within(dialog).getByLabelText(/Type .* to confirm/u), "delete my account");
    await user.click(within(dialog).getByRole("button", { name: "Delete account" }));

    expect(
      await screen.findByText(
        "You're the only administrator. Make someone else an administrator first.",
      ),
    ).toBeVisible();
  });

  it("data::delete_clears_session_and_cache", async () => {
    const user = userEvent.setup();
    let deletion: unknown;
    server.use(
      http.delete("/api/v1/account", async ({ request }) => {
        deletion = await request.json();
        return new HttpResponse(null, { status: 202 });
      }),
    );

    const { queryClient } = renderDataPanel();
    queryClient.setQueryData(["private-data"], { synthetic: "cached" });
    await user.click(screen.getByRole("button", { name: "Delete account" }));
    const dialog = await screen.findByRole("alertdialog", { name: "Delete account" });
    await user.type(within(dialog).getByLabelText("Current password"), "synthetic-password");
    await user.type(within(dialog).getByLabelText(/Type .* to confirm/u), "delete my account");
    await user.click(within(dialog).getByRole("button", { name: "Delete account" }));

    expect(
      await screen.findByRole("heading", { name: "Your account is being deleted" }),
    ).toBeVisible();
    expect(deletion).toEqual({ password: "synthetic-password", confirm: "delete my account" });
    expect(authManager.accessToken()).toBeUndefined();
    expect(queryClient.getQueryCache().getAll()).toHaveLength(0);
  });

  it("data.a11y::tst_11_data_panel_axe_clean", async () => {
    renderDataPanel();
    await screen.findByRole("button", { name: "Request export" });
    const markup = document.body.innerHTML;
    const browser = await chromium.launch({ headless: true });
    try {
      const context = await browser.newContext();
      const page = await context.newPage();
      await page.setContent(
        `<html lang="en"><head><title>Kinevo data settings</title></head><body>${markup}</body></html>`,
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
