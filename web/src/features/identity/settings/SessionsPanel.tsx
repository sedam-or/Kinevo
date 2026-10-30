import { useEffect, useState } from "react";
import {
  Dialog as AriaDialog,
  DialogTrigger,
  Heading,
  Modal,
  ModalOverlay,
} from "react-aria-components";
import { api } from "../../../api/client";
import { useAuth } from "../../../auth/AuthProvider";
import { Button } from "../../../components/Button";
import { useT } from "../../../i18n";

type AccountSession = {
  id: string;
  device_label: string;
  created_at: string;
  last_used_at: string;
  current: boolean;
};

export function SessionsPanel() {
  const t = useT();
  const { manager } = useAuth();
  const [sessions, setSessions] = useState<AccountSession[]>([]);
  const [selected, setSelected] = useState<AccountSession>();
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");
  const [notice, setNotice] = useState("");

  const requestErrorMessage = t("error.internal_error.title");
  useEffect(() => {
    let active = true;
    void api
      .GET("/api/v1/account/sessions", {
        params: { query: { limit: 50, sort: "-last_used_at" } },
      })
      .then((result) => {
        if (!active) return;
        setLoading(false);
        if (result.error) setError(requestErrorMessage);
        else setSessions(result.data.items.flatMap(parseSession));
      });
    return () => {
      active = false;
    };
  }, [requestErrorMessage]);

  async function revoke() {
    if (!selected) return;
    setBusy(true);
    setError("");
    const result = await api.DELETE("/api/v1/account/sessions/{id}", {
      params: {
        path: { id: selected.id },
        header: { "Idempotency-Key": crypto.randomUUID() },
      },
    });
    setBusy(false);
    if (result.error) {
      setError(t("error.internal_error.title"));
      return;
    }
    setSessions((current) => current.filter((session) => session.id !== selected.id));
    setSelected(undefined);
    setNotice(t("settings.sessions.revoked"));
  }

  async function signOutEverywhere() {
    setBusy(true);
    setError("");
    const result = await api.POST("/api/v1/auth/logout-all");
    setBusy(false);
    if (result.error) {
      setError(t("error.internal_error.title"));
      return;
    }
    await manager.logout().catch(() => undefined);
    window.location.assign("/login");
  }

  return (
    <section aria-labelledby="sessions-heading" className="grid gap-4">
      <h2 id="sessions-heading" className="font-display text-title-2">
        {t("settings.sessions.heading")}
      </h2>
      <p>{t("settings.sessions.description")}</p>
      {loading ? (
        <p role="status">{t("state.loading")}</p>
      ) : (
        <ul className="grid gap-3">
          {sessions.map((session) => (
            <li
              key={session.id}
              className="flex flex-wrap items-center justify-between gap-3 rounded-md border border-rule bg-surface p-4"
            >
              <div className="grid gap-1">
                <p className="font-semibold">{session.device_label}</p>
                {session.current && (
                  <p className="text-sm text-ink-muted">{t("settings.sessions.current")}</p>
                )}
                <p className="text-sm text-ink-muted">
                  {t("settings.sessions.last_used", { time: formatInstant(session.last_used_at) })}
                </p>
              </div>
              {!session.current && (
                <DialogTrigger
                  isOpen={selected?.id === session.id}
                  onOpenChange={(open) => {
                    if (!open) setSelected(undefined);
                    else setSelected(session);
                  }}
                >
                  <Button
                    variant="danger"
                    onPress={() => setSelected(session)}
                    aria-label={t("settings.sessions.revoke_named", {
                      device: session.device_label,
                    })}
                  >
                    {t("settings.sessions.revoke_named", { device: session.device_label })}
                  </Button>
                  <ModalOverlay
                    isDismissable
                    className="fixed inset-0 z-50 grid place-items-center bg-ink/40 p-4"
                  >
                    <Modal className="w-full max-w-lg rounded-lg border border-rule bg-surface p-6 text-ink shadow-overlay">
                      <AriaDialog
                        role="alertdialog"
                        aria-labelledby="revoke-session-heading"
                        aria-describedby="revoke-session-description"
                        className="grid gap-4"
                      >
                        <Heading
                          id="revoke-session-heading"
                          slot="title"
                          className="font-display text-title-2"
                        >
                          {t("settings.sessions.confirm_title")}
                        </Heading>
                        <p id="revoke-session-description">
                          {t("settings.sessions.confirm_detail", { device: session.device_label })}
                        </p>
                        {error && <p role="alert">{error}</p>}
                        <div className="flex justify-end gap-2">
                          <Button
                            variant="secondary"
                            autoFocus
                            onPress={() => setSelected(undefined)}
                          >
                            {t("auth.form.action.cancel")}
                          </Button>
                          <Button variant="danger" isLoading={busy} onPress={() => void revoke()}>
                            {t("settings.sessions.revoke")}
                          </Button>
                        </div>
                      </AriaDialog>
                    </Modal>
                  </ModalOverlay>
                </DialogTrigger>
              )}
            </li>
          ))}
        </ul>
      )}
      {error && <p role="alert">{error}</p>}
      {notice && <p role="status">{notice}</p>}
      <div>
        <Button variant="secondary" isLoading={busy} onPress={() => void signOutEverywhere()}>
          {t("settings.sessions.logout_all")}
        </Button>
      </div>
    </section>
  );
}

function parseSession(value: unknown): AccountSession[] {
  if (typeof value !== "object" || value === null) return [];
  const item = value as Record<string, unknown>;
  if (
    typeof item.id !== "string" ||
    typeof item.device_label !== "string" ||
    typeof item.created_at !== "string" ||
    typeof item.last_used_at !== "string" ||
    typeof item.current !== "boolean"
  ) {
    return [];
  }
  return [
    {
      id: item.id,
      device_label: item.device_label,
      created_at: item.created_at,
      last_used_at: item.last_used_at,
      current: item.current,
    },
  ];
}

function formatInstant(value: string): string {
  const instant = new Date(value);
  if (!Number.isFinite(instant.getTime())) return value;
  return new Intl.DateTimeFormat(undefined, { dateStyle: "medium", timeStyle: "short" }).format(
    instant,
  );
}
