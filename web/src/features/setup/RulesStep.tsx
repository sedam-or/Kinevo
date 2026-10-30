import { useState } from "react";
import { AppError } from "../../api/errors";
import { Banner } from "../../components/Banner";
import { Button } from "../../components/Button";
import { TextField } from "../../components/TextField";
import { useT } from "../../i18n";
import { type SetupAccountValues, setupAccountSchema } from "./schemas";
import type { PolicyField } from "./useWizard";

export function RulesStep({
  fields,
  policies,
  account,
  error,
  isLoading,
  onPoliciesChange,
  onAccountChange,
  onFinish,
}: {
  fields: PolicyField[];
  policies: Record<string, unknown>;
  account: SetupAccountValues;
  error?: AppError;
  isLoading: boolean;
  onPoliciesChange: (policies: Record<string, unknown>) => void;
  onAccountChange: (account: SetupAccountValues) => void;
  onFinish: (account: SetupAccountValues) => Promise<void>;
}) {
  const t = useT();
  const [validation, setValidation] = useState<Record<string, string>>({});
  const [submitting, setSubmitting] = useState(false);
  const uniqueFields = fields.filter(
    (field, index) => fields.findIndex((candidate) => candidate.key === field.key) === index,
  );
  const failedPassword = error?.fields.find((field) => field.path === "password");
  const passwordError =
    failedPassword?.code === "password_too_short" && typeof failedPassword.details.min === "number"
      ? t("error.field.password_too_short", { min: failedPassword.details.min })
      : undefined;
  const finishFailed =
    error?.code === "service_unavailable" || error?.code === "network_error" || undefined;

  const updatePolicy = (key: string, value: unknown) => {
    onPoliciesChange({ ...policies, [key]: value });
  };
  const submit = async () => {
    const result = setupAccountSchema.safeParse(account);
    if (!result.success) {
      const next: Record<string, string> = {};
      for (const issue of result.error.issues) {
        const key = issue.path[0];
        if (typeof key === "string" && !next[key]) {
          next[key] = key === "email" ? t("error.field.email_invalid") : t("error.field.required");
        }
      }
      setValidation(next);
      return;
    }
    setValidation({});
    setSubmitting(true);
    try {
      await onFinish(result.data);
    } catch {
      // The hook retains the server error so this form can show its recovery action.
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <section className="grid gap-5" aria-labelledby="setup-rules-heading">
      <h2 id="setup-rules-heading" className="font-display text-title-2">
        {t("setup.step.rules")}
      </h2>
      <div className="grid gap-4">
        {uniqueFields.map((field) => (
          <PolicyControl
            key={field.key}
            field={field}
            value={policies[field.key] ?? field.value}
            onChange={(value) => updatePolicy(field.key, value)}
          />
        ))}
      </div>
      <div className="grid gap-4 border-t border-rule pt-4">
        <h3 className="font-semibold">{t("setup.account.heading")}</h3>
        <TextField
          label={t("auth.form.email")}
          type="email"
          autoComplete="email"
          isRequired
          value={account.email}
          onChange={(email) => onAccountChange({ ...account, email })}
          error={validation.email}
        />
        <TextField
          label={t("auth.form.password")}
          type="password"
          autoComplete="new-password"
          isRequired
          value={account.password}
          onChange={(password) => onAccountChange({ ...account, password })}
          error={passwordError ?? validation.password}
        />
        <TextField
          label={t("auth.form.display_name")}
          autoComplete="name"
          value={account.display_name ?? ""}
          onChange={(display_name) => onAccountChange({ ...account, display_name })}
        />
        <TextField
          label={t("setup.account.timezone")}
          value={account.timezone}
          onChange={(timezone) => onAccountChange({ ...account, timezone })}
          error={validation.timezone}
        />
      </div>
      {finishFailed && (
        <Banner variant="danger" title={t("setup.finish.error")}>
          <Button
            variant="secondary"
            onPress={() => void submit()}
            isLoading={isLoading || submitting}
          >
            {t("setup.action.retry")}
          </Button>
        </Banner>
      )}
      {error?.code === "network_error" && (
        <p role="status" className="text-sm text-ink-muted">
          {t("state.offline.banner_no_sync")}
        </p>
      )}
      <Button onPress={() => void submit()} isLoading={isLoading || submitting}>
        {t("setup.action.finish")}
      </Button>
    </section>
  );
}

function PolicyControl({
  field,
  value,
  onChange,
}: {
  field: PolicyField;
  value: unknown;
  onChange: (value: unknown) => void;
}) {
  const t = useT();
  const label = t(field.label_key as never);
  if (field.type === "enum") {
    const allowed = Array.isArray(field.bounds.allowed)
      ? field.bounds.allowed.filter((entry): entry is string => typeof entry === "string")
      : [];
    return (
      <label className="grid gap-1 font-medium">
        {label}
        <select
          aria-label={label}
          value={typeof value === "string" ? value : ""}
          onChange={(event) => onChange(event.currentTarget.value)}
          className="min-h-11 rounded-md border border-rule-strong bg-sunken px-3 py-2 font-normal outline-focus-ring focus-visible:outline-2"
        >
          {allowed.map((entry) => (
            <option key={entry} value={entry}>
              {entry}
            </option>
          ))}
        </select>
      </label>
    );
  }
  if (field.type === "bool") {
    return (
      <label className="inline-flex min-h-11 items-center gap-3">
        <input
          type="checkbox"
          aria-label={label}
          checked={value === true}
          onChange={(event) => onChange(event.currentTarget.checked)}
        />
        {label}
      </label>
    );
  }
  const inputType = field.type === "local_time" ? "time" : "number";
  const numberProps =
    inputType === "number"
      ? {
          min: typeof field.bounds.min === "number" ? field.bounds.min : undefined,
          max: typeof field.bounds.max === "number" ? field.bounds.max : undefined,
        }
      : {};
  return (
    <label className="grid gap-1 font-medium">
      {label}
      <input
        type={inputType}
        aria-label={label}
        value={typeof value === "string" || typeof value === "number" ? value : ""}
        {...numberProps}
        onChange={(event) =>
          onChange(
            inputType === "number" ? Number(event.currentTarget.value) : event.currentTarget.value,
          )
        }
        className="min-h-11 rounded-md border border-rule-strong bg-sunken px-3 py-2 font-normal outline-focus-ring focus-visible:outline-2"
      />
    </label>
  );
}
