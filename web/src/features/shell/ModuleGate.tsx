import type { ReactNode } from "react";
import { Link } from "../../components/Link";
import { useT } from "../../i18n";
import type { ModuleItem } from "./useModules";

export function ModuleGate({
  module,
  modules,
  forbidden = false,
  children,
}: {
  module: string;
  modules: ModuleItem[];
  forbidden?: boolean;
  children: ReactNode;
}) {
  const t = useT();
  const found = modules.find((item) => item.key === module);
  if (!found) {
    return (
      <section className="grid gap-3">
        <h1>{t("state.not_found")}</h1>
        <Link href="/today">{t("nav.today")}</Link>
      </section>
    );
  }
  if (!found.enabled) {
    const displayName = module.replaceAll("_", " ").replace(/^./, (letter) => letter.toUpperCase());
    return (
      <section className="grid gap-3">
        <h1>{t("state.module.disabled", { module: displayName })}</h1>
        <Link href="/today">{t("nav.today")}</Link>
      </section>
    );
  }
  if (forbidden) {
    return (
      <section className="grid gap-3">
        <h1>{t("state.forbidden")}</h1>
        <Link href="/today">{t("nav.today")}</Link>
      </section>
    );
  }
  return children;
}

export function RegisteredModulePage({
  module,
  titleKey,
  modules,
}: {
  module: string;
  titleKey: import("../../i18n").MessageKey;
  modules: ModuleItem[];
}) {
  const t = useT();
  return (
    <ModuleGate module={module} modules={modules}>
      <h1>{t(titleKey)}</h1>
    </ModuleGate>
  );
}
