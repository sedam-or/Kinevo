import { TextField } from "../../components/TextField";
import { useT } from "../../i18n";
import type { SetupTemplate } from "./useWizard";

export function TemplateStep({
  templates,
  templateId,
  locale,
  baseUrl,
  onTemplateChange,
  onLocaleChange,
  onBaseUrlChange,
}: {
  templates: SetupTemplate[];
  templateId: string;
  locale: "en" | "id";
  baseUrl: string;
  onTemplateChange: (value: string) => void;
  onLocaleChange: (value: "en" | "id") => void;
  onBaseUrlChange: (value: string) => void;
}) {
  const t = useT();
  return (
    <section className="grid gap-4" aria-labelledby="setup-template-heading">
      <h2 id="setup-template-heading" className="font-display text-title-2">
        {t("setup.step.template")}
      </h2>
      <label className="grid gap-1 font-medium" htmlFor="setup-template">
        {t("setup.template.label")}
        <select
          id="setup-template"
          value={templateId}
          onChange={(event) => onTemplateChange(event.currentTarget.value)}
          className="min-h-11 rounded-md border border-rule-strong bg-sunken px-3 py-2 font-normal outline-focus-ring focus-visible:outline-2"
        >
          {templates.map((template) => (
            <option key={template.id} value={template.id}>
              {template.id}
            </option>
          ))}
        </select>
      </label>
      <label className="grid gap-1 font-medium" htmlFor="setup-locale">
        {t("setup.template.language")}
        <select
          id="setup-locale"
          value={locale}
          onChange={(event) => onLocaleChange(event.currentTarget.value as "en" | "id")}
          className="min-h-11 rounded-md border border-rule-strong bg-sunken px-3 py-2 font-normal outline-focus-ring focus-visible:outline-2"
        >
          <option value="en">{t("setup.language.en")}</option>
          <option value="id">{t("setup.language.id")}</option>
        </select>
      </label>
      <TextField
        label={t("setup.template.base_url")}
        type="url"
        value={baseUrl}
        onChange={onBaseUrlChange}
      />
    </section>
  );
}
