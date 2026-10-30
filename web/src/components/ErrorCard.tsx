import { useState } from "react";
import { Button } from "./Button";
import type { AppError } from "./form/use-form-errors";

export function ErrorCard({ error, onRetry }: { error: AppError; onRetry: () => void }) {
  const [copyFailed, setCopyFailed] = useState(false);

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
          This section could not be loaded.
        </h2>
        <p className="text-sm text-ink-muted">Check your connection, then try again.</p>
        <p className="text-sm">
          Request reference: <span className="font-mono">{error.request_id}</span>
        </p>
      </div>
      <div className="flex flex-wrap gap-2">
        <Button variant="secondary" onPress={copyRequestId}>
          Copy request id
        </Button>
        <Button onPress={onRetry}>Reload this section</Button>
      </div>
      {copyFailed && (
        <p role="status" className="text-sm text-ink-muted">
          Copy is unavailable in this browser.
        </p>
      )}
    </section>
  );
}
