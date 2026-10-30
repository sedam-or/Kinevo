import { useState } from "react";
import { useForm } from "react-hook-form";
import { Button } from "../../components/Button";
import { useT } from "../../i18n";
import { type MfaValues, mfaSchema, recoveryCodeSchema, resolverFor } from "./schemas";

export function MfaStep({
  email,
  methods,
  error,
  isSubmitting = false,
  onSubmit,
  onCancel,
}: {
  email: string;
  methods: string[];
  error?: string | undefined;
  isSubmitting?: boolean;
  onSubmit: (value: string, recovery: boolean) => void;
  onCancel: () => void;
}) {
  const t = useT();
  const [recovery, setRecovery] = useState(false);
  const {
    register,
    handleSubmit,
    formState: { errors },
    reset,
  } = useForm<MfaValues>({
    resolver: resolverFor(recovery ? recoveryCodeSchema : mfaSchema),
    mode: "onBlur",
    defaultValues: { code: "" },
  });
  const canUseRecovery = methods.includes("recovery_code");
  const code = register("code");

  return (
    <form
      className="mx-auto grid w-full max-w-md gap-4 rounded-lg border border-rule bg-surface p-6 text-ink shadow-rest"
      aria-label={t("auth.form.title.login")}
      onSubmit={handleSubmit(({ code: value }) => onSubmit(value, recovery))}
    >
      <h1 className="font-display text-title-2">{t("auth.form.title.mfa")}</h1>
      <p>{t("auth.login.mfa_prompt")}</p>
      <input aria-label={t("auth.form.email")} className="sr-only" readOnly value={email} />
      <label className="grid gap-1 font-medium" htmlFor="auth-mfa-code">
        {t(recovery ? "auth.form.recovery_code" : "auth.form.authentication_code")}
        <input
          {...code}
          id="auth-mfa-code"
          autoComplete="one-time-code"
          inputMode={recovery ? "text" : "numeric"}
          maxLength={recovery ? 10 : 6}
          aria-invalid={Boolean(errors.code)}
          aria-describedby={errors.code ? "auth-mfa-error" : undefined}
          className="min-h-11 rounded-md border border-rule-strong bg-sunken px-3 py-2 text-ink outline-focus-ring focus-visible:outline-2"
        />
      </label>
      {errors.code && (
        <p id="auth-mfa-error" role="alert">
          {t(`error.field.${errors.code.message}` as never)}
        </p>
      )}
      {error && <p role="alert">{error}</p>}
      {canUseRecovery && (
        <Button
          type="button"
          variant="quiet"
          onPress={() => {
            reset({ code: "" });
            setRecovery((current) => !current);
          }}
        >
          {t(recovery ? "auth.form.action.use_authenticator" : "auth.form.action.use_recovery")}
        </Button>
      )}
      <div className="flex flex-wrap gap-3">
        <Button type="submit" isDisabled={isSubmitting}>
          {t("auth.form.action.continue")}
        </Button>
        <Button type="button" variant="secondary" onPress={onCancel}>
          {t("auth.form.action.cancel")}
        </Button>
      </div>
    </form>
  );
}
