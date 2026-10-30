import {
  createContext,
  type ReactNode,
  useContext,
  useEffect,
  useState,
  useSyncExternalStore,
} from "react";
import { SessionRevokedPage } from "../features/identity/SessionRevokedPage";
import { useT } from "../i18n";
import { type AuthEvent, type AuthManager, type AuthState, authManager } from "./AuthManager";
import { safeNext } from "./guards";

const AuthContext = createContext<AuthManager>(authManager);

export function AuthProvider({
  children,
  manager = authManager,
}: {
  children: ReactNode;
  manager?: AuthManager;
}) {
  const [notice, setNotice] = useState<AuthEvent | undefined>();
  const state = useSyncExternalStore(
    (listener) => manager.subscribe(listener),
    () => manager.state,
    () => manager.state,
  );

  useEffect(() => manager.onEvent(setNotice), [manager]);
  useEffect(() => {
    if (state === "authenticated") setNotice(undefined);
  }, [state]);

  if (notice === "revoked") {
    return (
      <AuthContext.Provider value={manager}>
        <SessionRevokedPage />
      </AuthContext.Provider>
    );
  }

  return (
    <AuthContext.Provider value={manager}>
      {children}
      {notice === "signedOut" && <SessionExpiredModal />}
    </AuthContext.Provider>
  );
}

export function useAuth(): { manager: AuthManager; state: AuthState } {
  const manager = useContext(AuthContext);
  const state = useSyncExternalStore(
    (listener) => manager.subscribe(listener),
    () => manager.state,
    () => manager.state,
  );
  return { manager, state };
}

function SessionExpiredModal() {
  const t = useT();
  const next = `${window.location.pathname}${window.location.search}`;
  return (
    <div
      className="fixed inset-0 z-[70] grid place-items-center bg-ink/40 p-4"
      role="alertdialog"
      aria-modal="true"
      aria-labelledby="session-expired-title"
    >
      <div className="grid max-w-lg gap-4 rounded-lg border border-rule bg-surface p-6 text-ink shadow-overlay">
        <h2 id="session-expired-title" className="font-display text-title-2">
          {t("auth.session.expired.heading")}
        </h2>
        <p>{t("state.session.expired")}</p>
        <a
          className="inline-flex min-h-11 items-center justify-center rounded-md bg-accent px-4 py-2 font-medium text-accent-ink"
          href={`/login?next=${encodeURIComponent(safeNext(next))}`}
        >
          {t("auth.form.action.sign_in")}
        </a>
      </div>
    </div>
  );
}
