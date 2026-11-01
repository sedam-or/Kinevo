import { useQueryClient } from "@tanstack/react-query";
import { useState } from "react";
import { useAuth } from "../../../auth/AuthProvider";
import { useT } from "../../../i18n";
import { DeleteDialog } from "./DeleteDialog";
import { ExportCard } from "./ExportCard";

export function DataPanel({ onDeleted }: { onDeleted?: () => void }) {
  const t = useT();
  const { manager } = useAuth();
  const queryClient = useQueryClient();
  const [deleted, setDeleted] = useState(false);

  function finishDeletion() {
    queryClient.clear();
    manager.clearSessionAfterAccountDeletion();
    if (onDeleted) onDeleted();
    else setDeleted(true);
  }

  if (deleted) return <AccountDeletedPage />;

  return (
    <section aria-labelledby="data-heading" className="grid gap-8">
      <h2 id="data-heading" className="font-display text-title-2">
        {t("data.heading")}
      </h2>
      <ExportCard />
      <DeleteDialog onDeleted={finishDeletion} />
    </section>
  );
}

export function AccountDeletedPage() {
  const t = useT();
  return (
    <main className="mx-auto grid min-h-full max-w-lg content-center gap-4 p-6 text-ink">
      <h1 className="font-display text-title-2">{t("data.delete.goodbye.title")}</h1>
      <p>{t("data.delete.goodbye.body")}</p>
    </main>
  );
}
