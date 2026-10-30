import { useCallback, useEffect, useRef, useState } from "react";
import { AppError, mapProblem } from "../../api/errors";
import type { AuthManager } from "../../auth/AuthManager";
import { type Locale, setAccountLocale } from "../../i18n";

export type SetupTemplate = {
  id: string;
  name_key: string;
  description_key: string;
};

export type SetupStatus = {
  setup_completed: boolean;
  templates: SetupTemplate[];
  locales: Locale[];
};

export type PolicyField = {
  key: string;
  type: string;
  bounds: Record<string, unknown>;
  label_key: string;
  value: unknown;
  scope: "instance" | "account";
};

export type SetupModuleField = {
  key: string;
  label_key: string;
  kind: "core" | "toggleable";
  enabled: boolean;
  requires: string[];
};

export type WizardState = {
  session_id: string;
  step: 1 | 2 | 3;
  template_id: string;
  locale: Locale;
  base_url?: string | null;
  modules: Record<string, boolean>;
  policies: Record<string, unknown>;
  module_form: SetupModuleField[];
  policy_form: PolicyField[];
};

export type WizardPatch = {
  step: 1 | 2 | 3;
  template_id?: string;
  locale?: Locale;
  base_url?: string;
  modules?: Record<string, boolean>;
  policies?: Record<string, unknown>;
};

type HistoryState = {
  setupSessionId?: string;
  setupIdempotencyKey?: string;
};

export function useWizard(initialToken: string | undefined, manager: AuthManager) {
  const [status, setStatus] = useState<SetupStatus>();
  const [wizard, setWizard] = useState<WizardState>();
  const [error, setError] = useState<AppError>();
  const [loading, setLoading] = useState(true);
  const sessionId = useRef<string | undefined>(undefined);
  const finishKey = useRef<string | undefined>(undefined);

  const start = useCallback(
    async (token: string) => {
      setError(undefined);
      setLoading(true);
      stripSetupToken();
      try {
        const opened = await requestJson<{ session_id: string; step: number }>(
          manager,
          "/api/v1/setup/session",
          { method: "POST", body: JSON.stringify({ token }) },
        );
        sessionId.current = opened.session_id;
        finishKey.current = globalThis.crypto.randomUUID();
        writeHistory({
          setupSessionId: opened.session_id,
          setupIdempotencyKey: finishKey.current,
        });
        const state = await requestJson<WizardState>(
          manager,
          "/api/v1/setup/session/" + encodeURIComponent(opened.session_id),
        );
        setWizard(state);
        if (state.step > 1) await setAccountLocale(state.locale);
      } catch (caught) {
        setError(asAppError(caught));
      } finally {
        setLoading(false);
      }
    },
    [manager],
  );

  useEffect(() => {
    let active = true;
    const initialize = async () => {
      const history = readHistory();
      const session = history.setupSessionId;
      finishKey.current = history.setupIdempotencyKey;
      try {
        const nextStatus = await requestJson<SetupStatus>(manager, "/api/v1/setup/status");
        if (!active) return;
        setStatus(nextStatus);
        if (nextStatus.setup_completed) return;

        if (initialToken) {
          stripSetupToken();
          const opened = await requestJson<{ session_id: string; step: number }>(
            manager,
            "/api/v1/setup/session",
            { method: "POST", body: JSON.stringify({ token: initialToken }) },
          );
          if (!active) return;
          sessionId.current = opened.session_id;
          finishKey.current = globalThis.crypto.randomUUID();
          writeHistory({
            setupSessionId: opened.session_id,
            setupIdempotencyKey: finishKey.current,
          });
          const state = await requestJson<WizardState>(
            manager,
            "/api/v1/setup/session/" + encodeURIComponent(opened.session_id),
          );
          if (!active) return;
          setWizard(state);
          if (state.step > 1) await setAccountLocale(state.locale);
          return;
        }

        if (session) {
          sessionId.current = session;
          const state = await requestJson<WizardState>(
            manager,
            "/api/v1/setup/session/" + encodeURIComponent(session),
          );
          if (!active) return;
          setWizard(state);
          if (state.step > 1) await setAccountLocale(state.locale);
        }
      } catch (caught) {
        if (!active) return;
        const appError = asAppError(caught);
        if (session && appError.code === "not_found") {
          sessionId.current = undefined;
          clearHistory();
        }
        setError(appError);
      } finally {
        if (active) setLoading(false);
      }
    };

    void initialize();
    return () => {
      active = false;
    };
  }, [initialToken, manager]);

  const patch = useCallback(
    async (value: WizardPatch) => {
      const id = sessionId.current;
      if (!id) throw new AppError("not_found", 404, undefined, [], {}, "never");
      setError(undefined);
      try {
        const state = await requestJson<WizardState>(
          manager,
          "/api/v1/setup/session/" + encodeURIComponent(id),
          { method: "PUT", body: JSON.stringify(value) },
        );
        setWizard(state);
        await setAccountLocale(state.locale);
        return state;
      } catch (caught) {
        const appError = asAppError(caught);
        setError(appError);
        throw appError;
      }
    },
    [manager],
  );

  const finish = useCallback(
    async (account: {
      email: string;
      password: string;
      display_name?: string;
      timezone: string;
    }) => {
      const id = sessionId.current;
      if (!id) throw new AppError("not_found", 404, undefined, [], {}, "never");
      setError(undefined);
      finishKey.current ??= readHistory().setupIdempotencyKey ?? globalThis.crypto.randomUUID();
      writeHistory({ setupIdempotencyKey: finishKey.current });
      try {
        const result = await requestJson<{ access_token: string; expires_in: number }>(
          manager,
          "/api/v1/setup/session/" + encodeURIComponent(id) + "/finish",
          {
            method: "POST",
            headers: {
              "Idempotency-Key": finishKey.current,
              "X-Kinevo-Client": "web",
            },
            body: JSON.stringify({ account }),
          },
        );
        manager.acceptSession({ access_token: result.access_token, expires_in: result.expires_in });
        sessionId.current = undefined;
        finishKey.current = undefined;
        clearHistory();
      } catch (caught) {
        const appError = asAppError(caught);
        setError(appError);
        throw appError;
      }
    },
    [manager],
  );

  return { status, wizard, error, loading, start, patch, finish };
}

async function requestJson<T>(
  manager: AuthManager,
  path: string,
  init: RequestInit = {},
): Promise<T> {
  let response: Response;
  try {
    response = await manager.fetch(path, {
      ...init,
      headers: {
        ...(init.body ? { "Content-Type": "application/json" } : {}),
        ...init.headers,
      },
    });
  } catch {
    throw new AppError("network_error", 0, undefined, [], {}, "backoff");
  }
  if (!response.ok) {
    let error = await mapProblem(response);
    const retryAfter = response.headers.get("Retry-After");
    if (error.code === "rate_limited" && retryAfter && /^\d+$/u.test(retryAfter)) {
      error = new AppError(
        error.code,
        error.status,
        error.requestId,
        error.fields,
        { ...error.details, retry_after_seconds: Number(retryAfter) },
        error.retry,
      );
    }
    throw error;
  }
  return (await response.json()) as T;
}

function asAppError(error: unknown): AppError {
  return error instanceof AppError
    ? error
    : new AppError("network_error", 0, undefined, [], {}, "backoff");
}

function readHistory(): HistoryState {
  const value: unknown = window.history.state;
  if (typeof value !== "object" || value === null || Array.isArray(value)) return {};
  const state = value as Record<string, unknown>;
  return {
    ...(typeof state.setupSessionId === "string" ? { setupSessionId: state.setupSessionId } : {}),
    ...(typeof state.setupIdempotencyKey === "string"
      ? { setupIdempotencyKey: state.setupIdempotencyKey }
      : {}),
  };
}

function writeHistory(update: HistoryState): void {
  const previous: unknown = window.history.state;
  const state =
    typeof previous === "object" && previous !== null && !Array.isArray(previous)
      ? (previous as Record<string, unknown>)
      : {};
  window.history.replaceState({ ...state, ...update }, "", window.location.href);
}

function stripSetupToken(): void {
  const url = new URL(window.location.href);
  url.searchParams.delete("token");
  window.history.replaceState(window.history.state, "", url.pathname + url.search + url.hash);
}

function clearHistory(): void {
  const previous: unknown = window.history.state;
  const state =
    typeof previous === "object" && previous !== null && !Array.isArray(previous)
      ? { ...(previous as Record<string, unknown>) }
      : {};
  delete state.setupSessionId;
  delete state.setupIdempotencyKey;
  window.history.replaceState(state, "", window.location.href);
}
