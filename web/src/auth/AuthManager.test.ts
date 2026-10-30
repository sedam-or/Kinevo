import { describe, expect, it, vi } from "vitest";
import { authOpenApiFixtures } from "../test/handlers/auth";
import { AuthManager } from "./AuthManager";
import { redirectIfAnonymous } from "./guards";

function json(body: unknown, status = 200) {
  return new Response(JSON.stringify(body), {
    status,
    headers: { "Content-Type": status === 200 ? "application/json" : "application/problem+json" },
  });
}

describe("auth", () => {
  it("register_refreshes_session_when_openapi_returns_account_profile", async () => {
    let refreshRequests = 0;
    const fetcher = vi.fn(async (input: RequestInfo | URL) => {
      const request = new Request(input);
      if (new URL(request.url).pathname.endsWith("/auth/register")) {
        return json(authOpenApiFixtures.login.account);
      }
      refreshRequests += 1;
      return json(authOpenApiFixtures.refresh);
    });
    const manager = new AuthManager(fetcher);

    await manager.register({
      display_name: "Example Person",
      email: "person@example.test",
      password: "synthetic-password",
      timezone: "Etc/UTC",
    });

    expect(refreshRequests).toBe(1);
    expect(manager.state).toBe("authenticated");
    expect(manager.accessToken()).toBe(authOpenApiFixtures.refresh.access_token);
  });

  it("fe_02_restore_single_refresh_on_start", async () => {
    const fetcher = vi.fn(async () => json(authOpenApiFixtures.refresh));
    const manager = new AuthManager(fetcher);

    await Promise.all([manager.restore(), manager.restore(), manager.restore()]);

    expect(fetcher).toHaveBeenCalledTimes(1);
    expect(manager.state).toBe("authenticated");
    expect(manager.accessToken()).toBe(authOpenApiFixtures.refresh.access_token);
  });

  it("fe_02_single_flight_refresh", async () => {
    let protectedRequests = 0;
    let refreshRequests = 0;
    const protectedAttempts: Array<{
      path: string;
      idempotencyKey: string | null;
      client: string | null;
      authorization: string | null;
    }> = [];
    const fetcher = vi.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
      const request = new Request(input, init);
      const path = new URL(request.url).pathname;
      if (path.endsWith("/auth/login")) return json(authOpenApiFixtures.login);
      if (path.endsWith("/auth/refresh")) {
        refreshRequests += 1;
        await new Promise((resolve) => setTimeout(resolve, 0));
        return json(authOpenApiFixtures.refresh);
      }
      protectedRequests += 1;
      protectedAttempts.push({
        path,
        idempotencyKey: request.headers.get("Idempotency-Key"),
        client: request.headers.get("X-Kinevo-Client"),
        authorization: request.headers.get("Authorization"),
      });
      return request.headers.get("Authorization") === "Bearer refreshed_access_token_for_tests_only"
        ? json({ ok: true })
        : json(authOpenApiFixtures.invalidCredentials, 401);
    });
    const manager = new AuthManager(fetcher);
    await manager.login({ email: "person@example.test", password: "synthetic-password" });

    const responses = await Promise.all([
      manager.fetch("http://localhost/api/v1/tasks", { method: "POST", body: "{}" }),
      manager.fetch("http://localhost/api/v1/goals", { method: "POST", body: "{}" }),
      manager.fetch("http://localhost/api/v1/workspaces", { method: "POST", body: "{}" }),
    ]);

    expect(refreshRequests).toBe(1);
    expect(protectedRequests).toBe(6);
    expect(responses.every((response) => response.status === 200)).toBe(true);
    expect(protectedAttempts.every((attempt) => attempt.client === "web")).toBe(true);
    expect(protectedAttempts.every((attempt) => attempt.idempotencyKey !== null)).toBe(true);
    for (const path of ["/api/v1/tasks", "/api/v1/goals", "/api/v1/workspaces"]) {
      const attempts = protectedAttempts.filter((attempt) => attempt.path === path);
      expect(attempts).toHaveLength(2);
      expect(attempts[0]?.idempotencyKey).toBe(attempts[1]?.idempotencyKey);
      expect(attempts[0]?.authorization).toBe("Bearer access_token_for_tests_only");
      expect(attempts[1]?.authorization).toBe("Bearer refreshed_access_token_for_tests_only");
    }
  });

  it("fe_02_refresh_failure_redirects", async () => {
    const fetcher = vi.fn(async () => json(authOpenApiFixtures.invalidCredentials, 401));
    const manager = new AuthManager(fetcher);

    const redirect = await redirectIfAnonymous(manager, "/tasks?draft=kept");

    expect(manager.state).toBe("anonymous");
    expect(redirect).toBe("/login?next=%2Ftasks%3Fdraft%3Dkept");
  });

  it("logout_clears_local_session_and_emits_signed_out", async () => {
    const fetcher = vi.fn(async (input: RequestInfo | URL) => {
      const request = new Request(input);
      return new URL(request.url).pathname.endsWith("/auth/logout")
        ? new Response(null, { status: 204 })
        : json(authOpenApiFixtures.login);
    });
    const manager = new AuthManager(fetcher);
    const events: string[] = [];
    await manager.login({ email: "person@example.test", password: "synthetic-password" });
    manager.onEvent((event) => events.push(event));

    await manager.logout();

    expect(manager.state).toBe("anonymous");
    expect(manager.accessToken()).toBeUndefined();
    expect(events).toEqual(["signedOut"]);
  });
});
