import { type MessageKey, useT } from "../../i18n";
import { isRegisteredModuleRoute, registeredModuleKey } from "./routes";
import type { ModuleItem } from "./useModules";

export type NavEntry = {
  key: string;
  labelKey: MessageKey;
  route: string;
  group: "now" | "plan" | "think" | "reflect" | "settings" | "admin";
};

const groupOrder: NavEntry["group"][] = ["now", "plan", "think", "reflect", "settings", "admin"];

export function navigationFromModules(modules: ModuleItem[]): NavEntry[] {
  const entries: NavEntry[] = [
    { key: "today", labelKey: "nav.today", route: "/today", group: "now" },
  ];
  for (const module of modules) {
    if (!module.enabled || !registeredModuleKey(module.key)) continue;
    for (const item of [...module.nav].sort((left, right) => left.order - right.order)) {
      if (!isRegisteredModuleRoute(module.key, item.route) || !isLocalRoute(item.route)) continue;
      entries.push({
        key: `${module.key}.${item.key}`,
        labelKey: item.label_key as MessageKey,
        route: item.route,
        group: groupForRoute(item.route),
      });
    }
    for (const [order, panel] of module.settings_panels.entries()) {
      const route = `/settings/${panel.key}`;
      if (!isRegisteredModuleRoute(module.key, route)) continue;
      entries.push({
        key: `${module.key}.settings.${panel.key}.${order}`,
        labelKey: panel.label_key as MessageKey,
        route,
        group: "settings",
      });
    }
  }
  return entries;
}

export function Nav({
  entries,
  currentPath,
  groups = groupOrder,
  label = "Primary",
  className,
  onNavigate,
}: {
  entries: NavEntry[];
  currentPath: string;
  groups?: NavEntry["group"][];
  label?: string;
  className?: string;
  onNavigate?: (path: string) => void;
}) {
  const t = useT();
  return (
    <nav aria-label={label} className={className}>
      {groups.map((group) => {
        const grouped = entries.filter((entry) => entry.group === group);
        if (!grouped.length) return null;
        return (
          <section key={group} aria-label={t(`nav.group.${group}` as MessageKey)}>
            <h2 className="mb-1 px-3 text-xs font-semibold uppercase tracking-wide text-ink-muted">
              {t(`nav.group.${group}` as MessageKey)}
            </h2>
            <ul className="grid gap-1">
              {grouped.map((entry) => (
                <li key={entry.key}>
                  <a
                    href={entry.route}
                    className="flex min-h-11 items-center rounded-md px-3 py-2 no-underline text-ink hover:bg-sunken aria-[current=page]:bg-sunken aria-[current=page]:font-semibold"
                    {...(currentPath === entry.route ? { "aria-current": "page" as const } : {})}
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
          </section>
        );
      })}
    </nav>
  );
}

function groupForRoute(route: string): NavEntry["group"] {
  const first = route.split("/").filter(Boolean)[0];
  if (first === "settings") return "settings";
  if (first === "admin") return "admin";
  if (first === "think") return "think";
  if (first === "reflect") return "reflect";
  if (first === "today") return "now";
  return "plan";
}

function isLocalRoute(route: string): boolean {
  return route.startsWith("/") && !route.startsWith("//") && !route.includes("\\");
}
