import type { ReactNode } from "react";
import { useT } from "../i18n";
import { Button } from "./Button";

export function ConflictCard({
  yourChange,
  currentVersion,
  onKeepMine,
  onUseCurrent,
}: {
  yourChange: ReactNode;
  currentVersion: ReactNode;
  onKeepMine: () => void;
  onUseCurrent: () => void;
}) {
  const t = useT();
  return (
    <section
      aria-labelledby="conflict-heading"
      className="grid gap-3 rounded-lg border border-warning bg-surface p-4 text-ink"
    >
      <h2 id="conflict-heading" className="font-semibold">
        {t("conflict.heading")}
      </h2>
      <div className="grid gap-3 sm:grid-cols-2">
        <div>
          <h3 className="text-sm font-semibold">{t("conflict.your_change")}</h3>
          <div>{yourChange}</div>
        </div>
        <div>
          <h3 className="text-sm font-semibold">{t("conflict.current_version")}</h3>
          <div>{currentVersion}</div>
        </div>
      </div>
      <div className="flex flex-wrap gap-2">
        <Button onPress={onKeepMine}>{t("conflict.keep_mine")}</Button>
        <Button variant="secondary" onPress={onUseCurrent}>
          {t("conflict.use_current")}
        </Button>
      </div>
    </section>
  );
}
