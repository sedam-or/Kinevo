import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { HttpResponse, http } from "msw";
import { afterEach, describe, expect, it, vi } from "vitest";
import { AuthManager } from "../../auth/AuthManager";
import { AuthProvider } from "../../auth/AuthProvider";
import { authHandlers, authOpenApiFixtures } from "../../test/handlers/auth";
import { server } from "../../test/setup";
import { LoginPage } from "./LoginPage";

function problem(code: string, status: number, details?: Record<string, unknown>) {
  return {
    type: "about:blank",
    title: "Synthetic response",
    status,
    detail: "Synthetic response",
    code,
    request_id: "00000000-0000-7000-8000-000000000005",
    ...(details ? { details } : {}),
  };
}

function renderLogin(onAuthenticated = vi.fn()) {
  const manager = new AuthManager();
  const view = render(
    <AuthProvider manager={manager}>
      <LoginPage next="/today" onAuthenticated={onAuthenticated} />
    </AuthProvider>,
  );
  return { ...view, onAuthenticated };
}

afterEach(() => server.resetHandlers());

describe("auth", () => {
  it("id_fr_03_identical_failure_message", async () => {
    server.use(authHandlers.invalidCredentials);
    const user = userEvent.setup();
    const first = renderLogin();
    await user.type(screen.getByRole("textbox", { name: "Email address" }), "unknown@example.test");
    await user.type(screen.getByLabelText("Password"), "synthetic-password");
    await user.click(screen.getByRole("button", { name: "Sign in" }));
    const firstMessage = await screen.findByText("Email or password is incorrect.");
    first.unmount();

    renderLogin();
    await user.type(screen.getByRole("textbox", { name: "Email address" }), "person@example.test");
    await user.type(screen.getByLabelText("Password"), "different-synthetic-password");
    await user.click(screen.getByRole("button", { name: "Sign in" }));
    const secondMessage = await screen.findByText("Email or password is incorrect.");

    expect(firstMessage.textContent).toBe(secondMessage.textContent);
  });

  it("mfa_step_flow", async () => {
    let attempts = 0;
    server.use(
      http.post("/api/v1/auth/login", async ({ request }) => {
        attempts += 1;
        const body = (await request.json()) as { totp_code?: string };
        if (attempts === 1)
          return HttpResponse.json(problem("mfa_required", 401, { methods: ["totp"] }), {
            status: 401,
          });
        if (body.totp_code !== "123456")
          return HttpResponse.json(authOpenApiFixtures.invalidMfaCode, { status: 401 });
        return HttpResponse.json(authOpenApiFixtures.login);
      }),
    );
    const user = userEvent.setup();
    const rendered = renderLogin();
    await user.type(screen.getByRole("textbox", { name: "Email address" }), "person@example.test");
    await user.type(screen.getByLabelText("Password"), "synthetic-password");
    await user.click(screen.getByRole("button", { name: "Sign in" }));
    expect(
      await screen.findByText("Enter the 6-digit code from your authenticator app."),
    ).toBeVisible();
    expect(screen.getByLabelText("Email address")).toHaveValue("person@example.test");

    const code = screen.getByLabelText("Authentication code");
    await user.type(code, "000000");
    await user.click(screen.getByRole("button", { name: "Continue" }));
    expect(await screen.findByText("That code didn't work.")).toBeVisible();

    await user.clear(code);
    await user.type(code, "123456");
    await user.click(screen.getByRole("button", { name: "Continue" }));
    await waitFor(() => expect(rendered.onAuthenticated).toHaveBeenCalledWith("/today"));
  });
});
