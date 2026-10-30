import { useT } from "../../i18n";

export function DonePage() {
  const t = useT();
  return (
    <section role="status" aria-live="polite" className="grid gap-3">
      <h2 className="font-display text-title-2">{t("setup.done")}</h2>
    </section>
  );
}
