import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { afterEach, describe, expect, it, vi } from "vitest";
import { AuthManager } from "../../auth/AuthManager";
import { AuthProvider } from "../../auth/AuthProvider";
import { authHandlers } from "../../test/handlers/auth";
import { server } from "../../test/setup";
import { RegisterPage } from "./RegisterPage";

afterEach(() => server.resetHandlers());

describe("auth", () => {
  it("registration_closed_hides_form", async () => {
    server.use(authHandlers.registrationClosed);
    const user = userEvent.setup();
    render(
      <AuthProvider manager={new AuthManager()}>
        <RegisterPage onAuthenticated={vi.fn()} />
      </AuthProvider>,
    );
    await user.type(screen.getByLabelText("Display name"), "Example Person");
    await user.type(screen.getByLabelText("Email address"), "person@example.test");
    await user.type(screen.getByLabelText("Password"), "synthetic-password");
    await user.click(screen.getByRole("button", { name: "Create account" }));

    expect(
      await screen.findByText(
        "Registration is closed on this server. Ask the administrator for an invite.",
      ),
    ).toBeVisible();
    expect(screen.queryByRole("form", { name: "Create account" })).not.toBeInTheDocument();
  });
});
