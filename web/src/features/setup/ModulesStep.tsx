import { Switch } from "../../components/Switch";
import { useT } from "../../i18n";
import type { SetupModuleField } from "./useWizard";

export function ModulesStep({
  fields,
  templateId,
  values,
  dependencyMissing,
  failedModule,
  onChange,
}: {
  fields: SetupModuleField[];
  templateId: string;
  values: Record<string, boolean>;
  dependencyMissing: string[];
  failedModule?: string;
  onChange: (key: string, enabled: boolean) => void;
}) {
  const t = useT();
  return (
    <section className="grid gap-4" aria-labelledby="setup-modules-heading">
      <h2 id="setup-modules-heading" className="font-display text-title-2">
        {t("setup.step.modules")}
      </h2>
      <p className="text-sm text-ink-muted">
        {t("setup.module.template", { template: templateId })}
      </p>
      <div className="grid gap-2">
        {fields.map((field) => {
          const isCore = field.kind === "core";
          const enabled = isCore ? true : (values[field.key] ?? field.enabled);
          return (
            <div key={field.key} className="grid gap-1 rounded-md border border-rule p-3">
              <Switch
                label={field.key.replaceAll("_", " ")}
                isSelected={enabled}
                isDisabled={isCore}
                onLabel={t("setup.module.on")}
                offLabel={t("setup.module.off")}
                onChange={(next) => onChange(field.key, next)}
              />
              {failedModule === field.key && dependencyMissing.length > 0 && (
                <p role="alert" className="text-sm text-danger">
                  {t("admin.module.dependency", {
                    missing: dependencyMissing.map((key) => key.replaceAll("_", " ")).join(", "),
                  })}
                </p>
              )}
            </div>
          );
        })}
      </div>
    </section>
  );
}
