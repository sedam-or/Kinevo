import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { HttpResponse, http } from "msw";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { AuthManager } from "../../auth/AuthManager";
import { AuthProvider } from "../../auth/AuthProvider";
import { server } from "../../test/setup";
import { SetupPage } from "./SetupPage";

const sessionId = "0123456789abcdef0123456789abcdef";

const templates = [
  {
    id: "personal",
    name_key: "templates.personal.name",
    description_key: "templates.personal.description",
  },
  {
    id: "student",
    name_key: "templates.student.name",
    description_key: "templates.student.description",
  },
];

const policyForm = [
  {
    key: "auth.registration.mode",
    type: "enum",
    bounds: { allowed: ["closed", "open"] },
    label_key: "admin.policy.auth.registration.mode",
    value: "closed",
    scope: "instance",
  },
];

function wizard(overrides: Record<string, unknown> = {}) {
  return {
    session_id: sessionId,
    step: 1,
    template_id: "personal",
    locale: "en",
    base_url: "https://setup.example.test",
    modules: { ai: false, canvas: false },
    module_form: [
      { key: "identity", kind: "core", enabled: true, requires: [] },
      { key: "goals", kind: "core", enabled: true, requires: ["workspaces"] },
      { key: "ai", kind: "toggleable", enabled: false, requires: ["goals", "tasks"] },
      { key: "canvas", kind: "toggleable", enabled: false, requires: ["workspaces"] },
    ],
    policies: { "auth.registration.mode": "closed" },
    policy_form: policyForm,
    ...overrides,
  };
}

function installSetupHandlers(
  options: {
    initial?: Record<string, unknown>;
    finishFailures?: number;
    onFinishKey?: (key: string) => void;
    onFinishBody?: (body: unknown) => void;
  } = {},
) {
  let current = wizard(options.initial);
  let finishFailures = options.finishFailures ?? 0;
  let invalidTokenAttempts = 0;
  server.use(
    http.get("/api/v1/setup/status", () =>
      HttpResponse.json({ setup_completed: false, templates, locales: ["en", "id"] }),
    ),
    http.post("/api/v1/setup/session", async ({ request }) => {
      const body = (await request.json()) as { token: string };
      if (body.token === "wrong-token") {
        invalidTokenAttempts += 1;
        if (invalidTokenAttempts >= 3) {
          return HttpResponse.json(
            { code: "rate_limited", status: 429, errors: [], details: {} },
            { status: 429, headers: { "Retry-After": "30" } },
          );
        }
        return HttpResponse.json(
          {
            code: "setup_token_invalid",
            status: 422,
            errors: [],
            details: {},
          },
          { status: 422 },
        );
      }
      return HttpResponse.json({ session_id: sessionId, step: 1 }, { status: 201 });
    }),
    http.get("/api/v1/setup/session/" + sessionId, () => HttpResponse.json(current)),
    http.put("/api/v1/setup/session/" + sessionId, async ({ request }) => {
      const patch = (await request.json()) as Record<string, unknown>;
      const modules = patch.modules as Record<string, boolean> | undefined;
      if (modules?.ai) {
        return HttpResponse.json(
          {
            code: "module_dependency_unmet",
            status: 409,
            details: { missing: ["goals"] },
            errors: [],
          },
          { status: 409 },
        );
      }
      current = { ...current, ...patch };
      return HttpResponse.json(current);
    }),
    http.post("/api/v1/setup/session/" + sessionId + "/finish", async ({ request }) => {
      options.onFinishKey?.(request.headers.get("Idempotency-Key") ?? "");
      options.onFinishBody?.(await request.json());
      if (finishFailures > 0) {
        finishFailures -= 1;
        return HttpResponse.json(
          { code: "service_unavailable", status: 503, details: {}, errors: [] },
          { status: 503 },
        );
      }
      return HttpResponse.json(
        {
          account: { id: "00000000-0000-7000-8000-000000000001" },
          access_token: "synthetic-access-token",
          expires_in: 600,
        },
        { status: 201 },
      );
    }),
  );
  return {
    get current() {
      return current;
    },
  };
}

function renderSetup(onComplete = vi.fn()) {
  const manager = new AuthManager();
  const view = render(
    <AuthProvider manager={manager}>
      <SetupPage onComplete={onComplete} />
    </AuthProvider>,
  );
  return { ...view, manager, onComplete };
}

beforeEach(() => {
  window.history.replaceState(null, "", "/setup");
  Object.defineProperty(window.navigator, "languages", {
    configurable: true,
    value: ["en-US"],
  });
  Object.defineProperty(window.navigator, "language", {
    configurable: true,
    value: "en-US",
  });
});

afterEach(() => {
  server.resetHandlers();
  window.history.replaceState(null, "", "/");
});

describe("setup wizard", () => {
  it("setup_ui::set_token_hidden_and_language_default", async () => {
    installSetupHandlers();
    Object.defineProperty(window.navigator, "languages", {
      configurable: true,
      value: ["id-ID"],
    });
    Object.defineProperty(window.navigator, "language", {
      configurable: true,
      value: "id-ID",
    });
    window.history.replaceState(null, "", "/setup?token=synthetic-setup-token");

    renderSetup();

    expect(await screen.findByRole("heading", { name: "Pilih templat" })).toBeVisible();
    expect(screen.getByLabelText("Bahasa")).toHaveValue("id");
    expect(window.location.search).not.toContain("token");
    expect(window.location.search).not.toContain("synthetic-setup-token");
  });

  it("setup_ui::set_01_next_next_finish", async () => {
    installSetupHandlers();
    window.history.replaceState(null, "", "/setup?token=synthetic-setup-token");
    const user = userEvent.setup();
    const rendered = renderSetup();

    await screen.findByRole("heading", { name: "Choose a template" });
    await user.click(screen.getByRole("button", { name: "Next" }));
    await screen.findByRole("heading", { name: "Choose features" });
    await user.click(screen.getByRole("button", { name: "Next" }));
    await screen.findByRole("heading", { name: "Review defaults" });
    await user.type(screen.getByLabelText("Email address"), "owner@example.test");
    await user.type(screen.getByLabelText("Password"), "synthetic-long-password");
    await user.click(screen.getByRole("button", { name: "Finish" }));

    await waitFor(() => expect(rendered.onComplete).toHaveBeenCalledWith("/today"));
    expect(rendered.manager.accessToken()).toBe("synthetic-access-token");
  });

  it("setup_ui::sec_ac_08_lockout_message", async () => {
    installSetupHandlers();
    window.history.replaceState(null, "", "/setup?token=wrong-token");
    const user = userEvent.setup();
    renderSetup();

    const tokenField = await screen.findByLabelText("Setup token");
    await user.type(tokenField, "wrong-token");
    await user.click(screen.getByRole("button", { name: "Continue" }));
    await user.clear(tokenField);
    await user.type(tokenField, "wrong-token");
    await user.click(screen.getByRole("button", { name: "Continue" }));
    expect(await screen.findByText(/30 seconds/u)).toBeVisible();
    expect(window.location.search).not.toContain("wrong-token");
  });

  it("setup_ui::module_dependency_inline", async () => {
    installSetupHandlers();
    window.history.replaceState(null, "", "/setup?token=synthetic-setup-token");
    const user = userEvent.setup();
    renderSetup();

    await screen.findByRole("heading", { name: "Choose a template" });
    await user.click(screen.getByRole("button", { name: "Next" }));
    await screen.findByRole("heading", { name: "Choose features" });
    await user.click(screen.getByRole("switch", { name: "ai" }));
    await user.click(screen.getByRole("button", { name: "Next" }));

    expect(await screen.findByText("Turn on goals first.")).toBeVisible();
  });

  it("setup_ui::set_03_retry_reuses_key_and_banner", async () => {
    const finishKeys: string[] = [];
    installSetupHandlers({ finishFailures: 1, onFinishKey: (key) => finishKeys.push(key) });
    window.history.replaceState(null, "", "/setup?token=synthetic-setup-token");
    const user = userEvent.setup();
    renderSetup();

    await screen.findByRole("heading", { name: "Choose a template" });
    await user.click(screen.getByRole("button", { name: "Next" }));
    await screen.findByRole("heading", { name: "Choose features" });
    await user.click(screen.getByRole("button", { name: "Next" }));
    await screen.findByRole("heading", { name: "Review defaults" });
    await user.type(screen.getByLabelText("Email address"), "owner@example.test");
    await user.type(screen.getByLabelText("Password"), "synthetic-long-password");
    await user.click(screen.getByRole("button", { name: "Finish" }));
    expect(await screen.findByRole("alert")).toHaveTextContent(
      "Setup couldn't be completed. Nothing was changed. Try again.",
    );
    await user.click(screen.getByRole("button", { name: "Retry" }));
    await waitFor(() => expect(screen.queryByRole("alert")).not.toBeInTheDocument());
    expect(finishKeys.length).toBe(2);
    expect(finishKeys[0]).toBeTruthy();
    expect(finishKeys[0]).toBe(finishKeys[1]);
  });

  it("setup_ui::set_05_reload_resumes_step", async () => {
    installSetupHandlers({ initial: { step: 2, template_id: "student" } });
    window.history.replaceState({ setupSessionId: sessionId }, "", "/setup");
    renderSetup();

    expect(await screen.findByRole("heading", { name: "Choose features" })).toBeVisible();
    expect(screen.getByText("Using student")).toBeVisible();
  });

  it("setup_ui::password_policy_error_inline", async () => {
    installSetupHandlers();
    server.use(
      http.post("/api/v1/setup/session/" + sessionId + "/finish", () =>
        HttpResponse.json(
          {
            code: "validation_failed",
            status: 422,
            details: {},
            errors: [{ path: "password", code: "password_too_short", details: { min: 12 } }],
          },
          { status: 422 },
        ),
      ),
    );
    window.history.replaceState(null, "", "/setup?token=synthetic-setup-token");
    const user = userEvent.setup();
    renderSetup();

    await screen.findByRole("heading", { name: "Choose a template" });
    await user.click(screen.getByRole("button", { name: "Next" }));
    await screen.findByRole("heading", { name: "Choose features" });
    await user.click(screen.getByRole("button", { name: "Next" }));
    await screen.findByRole("heading", { name: "Review defaults" });
    await user.type(screen.getByLabelText("Email address"), "owner@example.test");
    await user.type(screen.getByLabelText("Password"), "short");
    await user.click(screen.getByRole("button", { name: "Finish" }));

    expect(await screen.findByText("Use at least 12 characters.")).toBeVisible();
  });
});
