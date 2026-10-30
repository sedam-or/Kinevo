import { useState } from "react";
import { useForm } from "react-hook-form";
import { AppError } from "../../api/errors";
import { useAuth } from "../../auth/AuthProvider";
import { Button } from "../../components/Button";
import { type MessageKey, useT } from "../../i18n";
import { type ResetConfirmValues, resetConfirmSchema, resolverFor } from "./schemas";

export function ResetPage({ token }: { token: string }) {
  const t = useT();
  const { manager } = useAuth();
  const [complete, setComplete] = useState(false);
  const [serverError, setServerError] = useState<string>();
  const form = useForm<ResetConfirmValues>({
    resolver: resolverFor(resetConfirmSchema),
    mode: "onBlur",
    defaultValues: { new_password: "" },
  });

  const submit = async ({ new_password }: ResetConfirmValues) => {
    setServerError(undefined);
    try {
      await manager.confirmPasswordReset(token, new_password);
      form.reset({ new_password: "" });
      setComplete(true);
    } catch (error) {
      form.reset({ new_password: "" });
      if (error instanceof AppError && error.code === "reset_token_invalid") {
        setServerError(t("auth.reset.invalid"));
      } else if (
        error instanceof AppError &&
        error.code === "password_too_short" &&
        typeof error.details.min === "number"
      ) {
        setServerError(t("auth.password.too_short", { min: error.details.min }));
      } else if (error instanceof AppError && error.code === "password_too_common") {
        setServerError(t("auth.password.too_common"));
      } else {
        setServerError(
          t(
            `error.${error instanceof AppError ? error.code : "internal_error"}.detail` as MessageKey,
          ),
        );
      }
    }
  };

  return (
    <main className="grid min-h-full place-items-center p-4 text-ink">
      <section className="grid w-full max-w-md gap-4 rounded-lg border border-rule bg-surface p-6 shadow-rest">
        <h1 className="font-display text-title-2">{t("auth.form.title.reset_confirm")}</h1>
        {complete ? (
          <>
            <p role="status">{t("auth.reset.completed")}</p>
            <a className="inline-flex min-h-11 items-center underline" href="/login">
              {t("auth.form.action.sign_in")}
            </a>
          </>
        ) : (
          <form
            className="grid gap-4"
            aria-label={t("auth.form.title.reset_confirm")}
            onSubmit={form.handleSubmit(submit)}
          >
            <label className="grid gap-1 font-medium" htmlFor="reset-new-password">
              {t("auth.form.new_password")}
              <input
                {...form.register("new_password")}
                id="reset-new-password"
                type="password"
                autoComplete="new-password"
                maxLength={128}
                aria-invalid={Boolean(form.formState.errors.new_password)}
                className="min-h-11 rounded-md border border-rule-strong bg-sunken px-3 py-2 text-ink outline-focus-ring focus-visible:outline-2"
              />
            </label>
            {form.formState.errors.new_password && (
              <p role="alert">
                {t(`error.field.${form.formState.errors.new_password.message}` as never)}
              </p>
            )}
            {serverError && <p role="alert">{serverError}</p>}
            <Button type="submit" isDisabled={form.formState.isSubmitting}>
              {t("auth.form.action.save_password")}
            </Button>
          </form>
        )}
      </section>
    </main>
  );
}
