import { AppError, mapProblem } from "../api/errors";

export type AuthState = "unknown" | "refreshing" | "authenticated" | "anonymous";
export type AuthEvent = "signedOut" | "revoked";

export type LoginInput = {
  email: string;
  password: string;
  totp_code?: string;
  recovery_code?: string;
};

export type RegisterInput = {
  display_name: string;
  email: string;
  password: string;
  timezone: string;
  invite_token?: string;
};

export type AuthSuccess = {
  access_token: string;
  expires_in: number;
};

export type LoginResult =
  | { kind: "authenticated"; session: AuthSuccess }
  | { kind: "mfa_required"; methods: string[] };

type RefreshReason = "restore" | "unauthorized";
type Fetcher = (input: RequestInfo | URL, init?: RequestInit) => Promise<Response>;

export class AuthManager {
  private currentState: AuthState = "unknown";
  private token: string | undefined;
  private restoreFlight: Promise<void> | undefined;
  private refreshFlight: Promise<boolean> | undefined;
  private readonly subscribers = new Set<() => void>();
  private readonly eventListeners = new Set<(event: AuthEvent) => void>();

  constructor(private readonly fetcher: Fetcher = (...args) => globalThis.fetch(...args)) {}

  get state(): AuthState {
    return this.currentState;
  }

  accessToken(): string | undefined {
    return this.token;
  }

  subscribe(listener: () => void): () => void {
    this.subscribers.add(listener);
    return () => this.subscribers.delete(listener);
  }

  onEvent(listener: (event: AuthEvent) => void): () => void {
    this.eventListeners.add(listener);
    return () => this.eventListeners.delete(listener);
  }

  restore(): Promise<void> {
    if (this.currentState === "authenticated" || this.currentState === "anonymous") {
      return Promise.resolve();
    }
    if (this.restoreFlight) return this.restoreFlight;

    this.setState("refreshing");
    const pending = this.refreshSingleFlight("restore").then(() => undefined);
    const flight = pending.finally(() => {
      if (this.restoreFlight === flight) this.restoreFlight = undefined;
      if (this.currentState === "refreshing") this.setState("anonymous");
    });
    this.restoreFlight = flight;
    return flight;
  }

  async login(input: LoginInput): Promise<LoginResult> {
    const response = await this.postAuth("/api/v1/auth/login", input);
    if (!response.ok) {
      const error = await this.errorFrom(response);
      if (error.code === "mfa_required") {
        const methods = error.details.methods;
        return {
          kind: "mfa_required",
          methods: Array.isArray(methods)
            ? methods.filter((value): value is string => typeof value === "string")
            : [],
        };
      }
      throw error;
    }
    const session = await this.readSession(response);
    this.setToken(session.access_token);
    return { kind: "authenticated", session };
  }

  async register(input: RegisterInput): Promise<void> {
    const { invite_token, ...requestBody } = input;
    const response = await this.postAuth("/api/v1/auth/register", {
      ...requestBody,
      ...(invite_token ? { invite_token } : {}),
    });
    if (!response.ok) throw await this.errorFrom(response);

    const body: unknown = await response.json().catch(() => undefined);
    const session =
      isRecord(body) && typeof body.access_token === "string"
        ? { access_token: body.access_token, expires_in: Number(body.expires_in) || 0 }
        : undefined;
    if (session) {
      this.setToken(session.access_token);
      return;
    }
    if (await this.refreshSingleFlight("restore")) return;
    throw new AppError("internal_error", 500, undefined, [], {}, "never");
  }

  async requestPasswordReset(email: string): Promise<void> {
    const response = await this.postAuth("/api/v1/auth/password-reset/request", { email });
    if (!response.ok) throw await this.errorFrom(response);
  }

  async confirmPasswordReset(token: string, newPassword: string): Promise<void> {
    const response = await this.postAuth("/api/v1/auth/password-reset/confirm", {
      token,
      new_password: newPassword,
    });
    if (!response.ok) throw await this.errorFrom(response);
  }

  async logout(): Promise<void> {
    try {
      const response = await this.postAuth("/api/v1/auth/logout", {});
      if (!response.ok) throw await this.errorFrom(response);
    } finally {
      this.clearToken();
      this.setState("anonymous");
      this.emit("signedOut");
    }
  }

  async fetch(input: RequestInfo | URL, init?: RequestInit): Promise<Response> {
    const normalized = input instanceof Request ? input : new URL(String(input), currentOrigin());
    const headers = new Headers(normalized instanceof Request ? normalized.headers : undefined);
    new Headers(init?.headers).forEach((value, key) => {
      headers.set(key, value);
    });
    headers.set("X-Kinevo-Client", "web");

    const method = (
      init?.method ?? (normalized instanceof Request ? normalized.method : "GET")
    ).toUpperCase();
    if (!["GET", "HEAD", "OPTIONS"].includes(method) && !headers.has("Idempotency-Key")) {
      headers.set("Idempotency-Key", crypto.randomUUID());
    }

    const url = new URL(normalized instanceof Request ? normalized.url : normalized.toString());
    const authPath = url.pathname === "/api/v1/auth" || url.pathname.startsWith("/api/v1/auth/");
    if (!authPath && this.token) headers.set("Authorization", `Bearer ${this.token}`);

    const request = new Request(normalized, {
      ...init,
      headers,
      credentials: init?.credentials ?? "include",
    });
    const response = await this.fetcher(request.clone());
    if (response.status !== 401 || authPath || !request.headers.has("Authorization"))
      return response;

    if (!(await this.refreshSingleFlight("unauthorized")) || !this.token) return response;
    const retryHeaders = new Headers(request.headers);
    retryHeaders.set("X-Kinevo-Client", "web");
    if (request.headers.has("Idempotency-Key")) {
      retryHeaders.set("Idempotency-Key", request.headers.get("Idempotency-Key") ?? "");
    }
    retryHeaders.set("Authorization", `Bearer ${this.token}`);
    return this.fetcher(new Request(request, { headers: retryHeaders }));
  }

  private async postAuth(path: string, body: unknown): Promise<Response> {
    return this.fetcher(
      new Request(new URL(path, currentOrigin()), {
        method: "POST",
        credentials: "include",
        headers: {
          "Content-Type": "application/json",
          "X-Kinevo-Client": "web",
          "Idempotency-Key": crypto.randomUUID(),
        },
        body: JSON.stringify(body),
      }),
    );
  }

  private refreshSingleFlight(reason: RefreshReason): Promise<boolean> {
    if (this.refreshFlight) return this.refreshFlight;
    const pending = this.performRefresh(reason);
    const flight = pending.finally(() => {
      if (this.refreshFlight === flight) this.refreshFlight = undefined;
    });
    this.refreshFlight = flight;
    return flight;
  }

  private async performRefresh(reason: RefreshReason): Promise<boolean> {
    this.setState("refreshing");
    try {
      const response = await this.postAuth("/api/v1/auth/refresh", {});
      if (!response.ok) {
        const error = await this.errorFrom(response);
        this.clearToken();
        this.setState("anonymous");
        if (error.code === "refresh_reuse_detected") this.emit("revoked");
        else if (reason === "unauthorized") this.emit("signedOut");
        return false;
      }
      const session = await this.readSession(response);
      this.setToken(session.access_token);
      return true;
    } catch {
      this.clearToken();
      this.setState("anonymous");
      if (reason === "unauthorized") this.emit("signedOut");
      return false;
    }
  }

  private async readSession(response: Response): Promise<AuthSuccess> {
    const body: unknown = await response.json();
    if (!isRecord(body) || typeof body.access_token !== "string" || !body.access_token) {
      throw new AppError("internal_error", 500, undefined, [], {}, "never");
    }
    return {
      access_token: body.access_token,
      expires_in: typeof body.expires_in === "number" ? body.expires_in : 0,
    };
  }

  private async errorFrom(response: Response): Promise<AppError> {
    const error = await mapProblem(response);
    const retryAfter = response.headers.get("Retry-After");
    if (error.code !== "rate_limited" || !retryAfter || !/^\d+$/u.test(retryAfter)) return error;
    return new AppError(
      error.code,
      error.status,
      error.requestId,
      error.fields,
      { ...error.details, retry_after_seconds: Number(retryAfter) },
      error.retry,
    );
  }

  private setToken(token: string): void {
    this.token = token;
    this.setState("authenticated");
  }

  private clearToken(): void {
    this.token = undefined;
  }

  private setState(state: AuthState): void {
    this.currentState = state;
    for (const listener of this.subscribers) listener();
  }

  private emit(event: AuthEvent): void {
    for (const listener of this.eventListeners) listener(event);
  }
}

export const authManager = new AuthManager();

function currentOrigin(): string {
  return typeof window === "undefined" ? "http://localhost" : window.location.origin;
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === "object" && value !== null && !Array.isArray(value);
}
