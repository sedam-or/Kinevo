import { Button } from "../../components/Button";
import { Sheet } from "../../components/Sheet";
import { useT } from "../../i18n";
import type { NavEntry } from "./Nav";

export function MoreSheet({
  entries,
  onNavigate,
}: {
  entries: NavEntry[];
  onNavigate?: (path: string) => void;
}) {
  const t = useT();
  return (
    <div className="md:hidden">
      <Sheet
        trigger={<Button variant="quiet">{t("nav.more")}</Button>}
        title={t("nav.more")}
        description={t("nav.more.description")}
      >
        <nav aria-label={t("nav.more")}>
          <ul className="grid gap-1">
            {entries.map((entry) => (
              <li key={entry.key}>
                <a
                  href={entry.route}
                  className="flex min-h-11 items-center rounded-md px-3 py-2 no-underline text-ink hover:bg-sunken"
                  onClick={(event) => {
                    if (
                      event.button === 0 &&
                      !event.metaKey &&
                      !event.ctrlKey &&
                      !event.shiftKey &&
                      !event.altKey &&
                      onNavigate
                    ) {
                      event.preventDefault();
                      onNavigate(entry.route);
                    }
                  }}
                >
                  {t(entry.labelKey)}
                </a>
              </li>
            ))}
          </ul>
        </nav>
      </Sheet>
    </div>
  );
}
