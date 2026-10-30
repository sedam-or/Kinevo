import { useState } from "react";
import { useT } from "../i18n";
import { Button } from "./Button";
import type { AppError } from "./form/use-form-errors";

export function ErrorCard({ error, onRetry }: { error: AppError; onRetry: () => void }) {
  const [copyFailed, setCopyFailed] = useState(false);
  const t = useT();

  const copyRequestId = async () => {
    try {
      await navigator.clipboard.writeText(error.request_id);
      setCopyFailed(false);
    } catch {
      setCopyFailed(true);
    }
  };

  return (
    <section
      aria-labelledby="section-error-heading"
      className="grid justify-items-start gap-3 rounded-lg border border-danger bg-surface p-4 text-ink"
    >
      <div className="grid gap-1">
        <h2 id="section-error-heading" className="font-semibold">
          {t("feedback.section_error.title")}
        </h2>
        <p className="text-sm text-ink-muted">{t("feedback.section_error.detail")}</p>
        <p className="text-sm">
          {t("feedback.section_error.reference", { request_id: error.request_id })}
        </p>
      </div>
      <div className="flex flex-wrap gap-2">
        <Button variant="secondary" onPress={copyRequestId}>
          {t("action.copy_request_id")}
        </Button>
        <Button onPress={onRetry}>{t("action.reload_section")}</Button>
      </div>
      {copyFailed && (
        <p role="status" className="text-sm text-ink-muted">
          {t("action.copy_unavailable")}
        </p>
      )}
    </section>
  );
}
