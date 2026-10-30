import { type FormEvent, useState } from "react";
import { api } from "../../../api/client";
import type { components } from "../../../api/schema";
import { Button } from "../../../components/Button";
import { useFormErrors } from "../../../components/form/use-form-errors";
import { TextField } from "../../../components/TextField";
import { useT } from "../../../i18n";
import { passwordChangeSchema } from "./schemas";

type Problem = components["schemas"]["Problem"];

export function PasswordForm() {
  const t = useT();
  const [currentPassword, setCurrentPassword] = useState("");
  const [newPassword, setNewPassword] = useState("");
  const [problem, setProblem] = useState<Problem>();
  const [clientError, setClientError] = useState("");
  const [saving, setSaving] = useState(false);
  const [saved, setSaved] = useState(false);
  const { fieldErrors, formError } = useFormErrors(problem);

  async function changePassword(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setProblem(undefined);
    setClientError("");
    setSaved(false);
    const parsed = passwordChangeSchema.safeParse({
      current_password: currentPassword,
      new_password: newPassword,
    });
    if (!parsed.success) {
      setClientError(t("settings.field.required"));
      return;
    }

    setSaving(true);
    const result = await api.POST("/api/v1/account/password", {
      params: { header: { "Idempotency-Key": crypto.randomUUID() } },
      body: parsed.data,
    });
    setSaving(false);
    if (result.error) {
      setProblem(result.error);
      return;
    }
    setCurrentPassword("");
    setNewPassword("");
    setSaved(true);
  }

  const currentError = fieldErrors.current_password
    ? errorMessage(problem, fieldErrors.current_password, t)
    : problem?.code === "current_password_incorrect"
      ? t("error.current_password_incorrect.title")
      : undefined;
  const newError = fieldErrors.new_password
    ? errorMessage(problem, fieldErrors.new_password, t)
    : undefined;

  return (
    <section aria-labelledby="password-heading" className="grid gap-4">
      <h2 id="password-heading" className="font-display text-title-2">
        {t("settings.password")}
      </h2>
      <form
        className="grid max-w-xl gap-4"
        noValidate
        onSubmit={(event) => void changePassword(event)}
      >
        <TextField
          label={t("settings.password.current")}
          type="password"
          autoComplete="current-password"
          value={currentPassword}
          onChange={setCurrentPassword}
          error={currentError}
        />
        <TextField
          label={t("settings.password.new")}
          type="password"
          autoComplete="new-password"
          value={newPassword}
          onChange={setNewPassword}
          error={newError}
          maxLength={128}
        />
        {clientError && <p role="alert">{clientError}</p>}
        {formError && !currentError && <p role="alert">{errorMessage(problem, formError, t)}</p>}
        {saved && <p role="status">{t("settings.password.saved")}</p>}
        <div>
          <Button type="submit" isLoading={saving}>
            {t("settings.password.change")}
          </Button>
        </div>
      </form>
    </section>
  );
}

function errorMessage(
  problem: Problem | undefined,
  code: string,
  t: ReturnType<typeof useT>,
): string {
  if (code === "password_too_short") {
    const min = problem?.details?.min;
    return t("auth.password.too_short", { min: typeof min === "number" ? min : "" });
  }
  if (code === "password_too_common") return t("auth.password.too_common");
  return t("settings.field.invalid");
}
