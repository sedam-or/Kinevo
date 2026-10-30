import type { ReactNode } from "react";
import type { MessageKey } from "../../i18n";
import { useT } from "../../i18n";
import type { ModuleSettingsPanel } from "./useModules";

export function SettingsLayout({
  activePanel,
  panels,
  children,
}: {
  activePanel: string;
  panels: ModuleSettingsPanel[];
  children: ReactNode;
}) {
  const t = useT();
  const current = panels.find((panel) => panel.key === activePanel);

  return (
    <section className="grid gap-5" aria-labelledby="settings-heading">
      <h1 id="settings-heading" className="font-display text-display-2">
        {current ? t(current.label_key as MessageKey) : t("settings.heading")}
      </h1>
      <nav aria-label={t("settings.panels")}>
        <ul className="flex flex-wrap gap-2 border-b border-rule">
          {panels.map((panel) => (
            <li key={panel.key}>
              <a
                href={`/settings/${encodeURIComponent(panel.key)}`}
                aria-current={panel.key === activePanel ? "page" : undefined}
                className="inline-flex min-h-11 items-center rounded-t-md border-b-2 border-transparent px-3 py-2 text-ink hover:bg-sunken aria-[current=page]:border-accent aria-[current=page]:font-semibold"
              >
                {t(panel.label_key as MessageKey)}
              </a>
            </li>
          ))}
        </ul>
      </nav>
      <div className="grid gap-6">{children}</div>
    </section>
  );
}
