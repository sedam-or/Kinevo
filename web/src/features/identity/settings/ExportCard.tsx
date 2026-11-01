import { useQuery } from "@tanstack/react-query";
import { useState } from "react";
import { api } from "../../../api/client";
import { mapProblem } from "../../../api/errors";
import { useAuth } from "../../../auth/AuthProvider";
import { Button } from "../../../components/Button";
import { useToast } from "../../../components/Toast";
import { useT } from "../../../i18n";
import { exportStatusPollInterval } from "../../../lib/polling";

type ExportState = "queued" | "running" | "ready" | "failed" | "expired";
type ExportStatus = { state: ExportState; expires_at?: string; size_bytes?: number };

export function ExportCard() {
  const t = useT();
  const toast = useToast();
  const { manager } = useAuth();
  const [exportId, setExportId] = useState<string>();
  const [requesting, setRequesting] = useState(false);
  const [downloading, setDownloading] = useState(false);
  const [downloadExpired, setDownloadExpired] = useState(false);
  const [error, setError] = useState("");

  const statusQuery = useQuery({
    queryKey: ["account-export-status", exportId],
    enabled: Boolean(exportId),
    retry: false,
    queryFn: async () => {
      if (!exportId) throw new Error("Export id is required");
      const result = await api.GET("/api/v1/account/export/{id}", {
        params: { path: { id: exportId } },
      });
      if (result.error) throw result.error;
      return result.data as unknown as ExportStatus;
    },
    refetchInterval: (query) =>
      isActive(query.state.data?.state)
        ? exportStatusPollInterval(query.state.dataUpdateCount)
        : false,
    refetchIntervalInBackground: false,
  });

  const state = downloadExpired ? "expired" : statusQuery.data?.state;
  const active = isActive(state);

  async function requestExport() {
    setRequesting(true);
    setError("");
    setDownloadExpired(false);
    try {
      const result = await api.POST("/api/v1/account/export", {
        params: { header: { "Idempotency-Key": crypto.randomUUID() } },
      });
      if (result.error) {
        if (result.error.code === "export_in_progress") {
          const activeId = result.error.details?.export_id;
          if (typeof activeId === "string") {
            setExportId(activeId);
            setError("");
            return;
          }
        }
        setError(t("error.internal_error.title"));
        return;
      }
      const created = result.data as { export_id?: unknown } | undefined;
      if (typeof created?.export_id !== "string") {
        setError(t("error.internal_error.title"));
        return;
      }
      setExportId(created.export_id);
      toast.info("data.export.queued");
    } catch {
      setError(t("error.internal_error.title"));
    } finally {
      setRequesting(false);
    }
  }

  async function downloadExport() {
    if (!exportId) return;
    setDownloading(true);
    setError("");
    try {
      const response = await manager.fetch(`/api/v1/account/export/${exportId}/download`);
      if (!response.ok) {
        const problem = await mapProblem(response);
        if (problem.code === "export_expired") setDownloadExpired(true);
        else setError(t("error.internal_error.title"));
        return;
      }

      const url = URL.createObjectURL(await response.blob());
      const link = document.createElement("a");
      link.href = url;
      link.download = downloadName(response.headers.get("Content-Disposition"));
      document.body.append(link);
      link.click();
      link.remove();
      URL.revokeObjectURL(url);
    } catch {
      setError(t("data.export.download_failed"));
    } finally {
      setDownloading(false);
    }
  }

  const currentState = statusQuery.isError ? "failed" : state;

  return (
    <section aria-labelledby="export-heading" className="grid gap-4">
      <h2 id="export-heading" className="font-display text-title-2">
        {t("data.export.heading")}
      </h2>
      <p>{t("data.export.description")}</p>
      <div className="flex flex-wrap gap-3">
        <Button onPress={() => void requestExport()} isLoading={requesting} isDisabled={active}>
          {currentState === "failed" || currentState === "expired"
            ? t("data.export.request_again")
            : t("data.export.request")}
        </Button>
        {currentState === "ready" && (
          <Button onPress={() => void downloadExport()} isLoading={downloading}>
            {t("data.export.download")}
          </Button>
        )}
      </div>
      {currentState === "queued" || currentState === "running" ? (
        <p role="status">
          {t("data.export.progress", { state: t(`data.export.state.${currentState}`) })}
        </p>
      ) : null}
      {currentState === "ready" && statusQuery.data?.expires_at && (
        <p role="status">
          {t("data.export.ready", { date: formatExpiry(statusQuery.data.expires_at) })}
        </p>
      )}
      {currentState === "failed" && <p role="alert">{t("data.export.failed")}</p>}
      {currentState === "expired" && <p role="alert">{t("data.export.expired")}</p>}
      {error && <p role="alert">{error}</p>}
    </section>
  );
}

function isActive(state: ExportState | undefined): state is "queued" | "running" {
  return state === "queued" || state === "running";
}

function formatExpiry(value: string): string {
  const date = new Date(value);
  if (!Number.isFinite(date.getTime())) return value;
  return new Intl.DateTimeFormat(undefined, { dateStyle: "medium", timeStyle: "short" }).format(
    date,
  );
}

function downloadName(disposition: string | null): string {
  const match = disposition?.match(/filename="?([^";]+)"?/iu);
  return match?.[1]?.trim() || "account-export.zip";
}
