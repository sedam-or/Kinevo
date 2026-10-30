import { render, screen } from "@testing-library/react";
import { describe, expect, it, vi } from "vitest";
import { AuthManager } from "../../auth/AuthManager";
import { AuthProvider } from "../../auth/AuthProvider";

describe("auth", () => {
  it("session_revoked_page", async () => {
    const response = {
      type: "about:blank",
      title: "Session revoked",
      status: 401,
      detail: "Session revoked",
      code: "refresh_reuse_detected",
      request_id: "00000000-0000-7000-8000-000000000006",
    };
    const manager = new AuthManager(
      vi.fn(
        async () =>
          new Response(JSON.stringify(response), {
            status: 401,
            headers: { "Content-Type": "application/problem+json" },
          }),
      ),
    );
    render(
      <AuthProvider manager={manager}>
        <div>Protected content</div>
      </AuthProvider>,
    );
    await manager.restore();
    expect(
      screen.getByText(
        "You were signed out because your sign-in was used elsewhere. Please sign in again.",
      ),
    ).toBeVisible();
    expect(screen.queryByText("Protected content")).not.toBeInTheDocument();
  });
});
