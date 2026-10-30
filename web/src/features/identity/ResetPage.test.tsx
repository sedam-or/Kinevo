import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { afterEach, describe, expect, it } from "vitest";
import { AuthManager } from "../../auth/AuthManager";
import { AuthProvider } from "../../auth/AuthProvider";
import { authHandlers } from "../../test/handlers/auth";
import { server } from "../../test/setup";
import { ResetPage } from "./ResetPage";

afterEach(() => server.resetHandlers());

describe("auth", () => {
  it("reset_confirmation_uses_route_token", async () => {
    server.use(authHandlers.resetConfirmed);
    const user = userEvent.setup();
    render(
      <AuthProvider manager={new AuthManager()}>
        <ResetPage token="synthetic-reset-token" />
      </AuthProvider>,
    );
    await user.type(screen.getByLabelText("New password"), "synthetic-new-password");
    await user.click(screen.getByRole("button", { name: "Save password" }));

    expect(await screen.findByText("Password updated. Sign in to continue.")).toBeVisible();
  });
});
