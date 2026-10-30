import { EmptyState } from "../../components/EmptyState";
import { Link } from "../../components/Link";
import { useT } from "../../i18n";
import { useModules } from "../shell/useModules";

export function TodayPage() {
  const t = useT();
  const modules = useModules();
  const goalsEnabled = modules.data?.items.some(
    (module) => module.key === "goals" && module.enabled,
  );
  return (
    <>
      <h1>{t("nav.today")}</h1>
      <EmptyState
        headline={t("today.empty.no_tasks")}
        body={t("today.empty.nothing_scheduled")}
        action={goalsEnabled ? <Link href="/goals">{t("today.action.create_goal")}</Link> : null}
      />
    </>
  );
}
