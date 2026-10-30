import { useState } from "react";
import { useForm } from "react-hook-form";
import { AppError } from "../../api/errors";
import { useAuth } from "../../auth/AuthProvider";
import { safeNext } from "../../auth/guards";
import { Button } from "../../components/Button";
import { type MessageKey, useT } from "../../i18n";
import { type RegisterValues, registerSchema, resolverFor } from "./schemas";

export function RegisterPage({
  inviteToken,
  onAuthenticated,
}: {
  inviteToken?: string;
  onAuthenticated?: (path: string) => void;
}) {
  const t = useT();
  const { manager } = useAuth();
  const [closed, setClosed] = useState(false);
  const [serverError, setServerError] = useState<string>();
  const form = useForm<RegisterValues>({
    resolver: resolverFor(registerSchema),
    mode: "onBlur",
    defaultValues: {
      display_name: "",
      email: "",
      password: "",
      timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
      invite_token: inviteToken ?? "",
    },
  });

  const submit = async (values: RegisterValues) => {
    setServerError(undefined);
    try {
      const { invite_token, ...body } = values;
      await manager.register({ ...body, ...(invite_token ? { invite_token } : {}) });
      form.reset({ ...values, password: "" });
      const destination = safeNext("/today");
      if (onAuthenticated) onAuthenticated(destination);
      else window.location.assign(destination);
    } catch (error) {
      form.setValue("password", "");
      if (error instanceof AppError && error.code === "registration_closed") {
        setClosed(true);
        return;
      }
      if (error instanceof AppError && error.code === "email_taken") {
        setServerError(t("auth.register.email_taken"));
        return;
      }
      if (error instanceof AppError && error.fields.length) {
        for (const field of error.fields) {
          if (["display_name", "email", "password"].includes(field.path)) {
            form.setError(field.path as "display_name" | "email" | "password", {
              message: field.code,
            });
          }
        }
        return;
      }
      serverErrorFor(error, t, setServerError);
    }
  };

  if (closed) {
    return (
      <main className="mx-auto grid min-h-full max-w-lg content-center gap-4 p-6 text-ink">
        <h1 className="font-display text-title-2">{t("auth.form.title.register")}</h1>
        <p role="status">{t("auth.register.closed")}</p>
        <a className="underline" href="/login">
          {t("auth.form.action.sign_in")}
        </a>
      </main>
    );
  }

  const displayName = form.register("display_name");
  const email = form.register("email");
  const password = form.register("password");
  const invite = form.register("invite_token");
  const timezone = form.register("timezone");
  const errors = form.formState.errors;

  return (
    <main className="grid min-h-full place-items-center p-4 text-ink">
      <section className="grid w-full max-w-md gap-4 rounded-lg border border-rule bg-surface p-6 shadow-rest">
        <h1 className="font-display text-title-2">{t("auth.form.title.register")}</h1>
        <form
          className="grid gap-4"
          aria-label={t("auth.form.title.register")}
          onSubmit={form.handleSubmit(submit)}
        >
          <label className="grid gap-1 font-medium" htmlFor="register-name">
            {t("auth.form.display_name")}
            <input
              {...displayName}
              id="register-name"
              autoComplete="name"
              maxLength={80}
              aria-invalid={Boolean(errors.display_name)}
              aria-describedby={errors.display_name ? "register-name-error" : undefined}
              className="min-h-11 rounded-md border border-rule-strong bg-sunken px-3 py-2 text-ink outline-focus-ring focus-visible:outline-2"
            />
          </label>
          {errors.display_name && (
            <p id="register-name-error" role="alert">
              {t(`error.field.${errors.display_name.message}` as never)}
            </p>
          )}
          <label className="grid gap-1 font-medium" htmlFor="register-email">
            {t("auth.form.email")}
            <input
              {...email}
              id="register-email"
              type="email"
              autoComplete="username"
              aria-invalid={Boolean(errors.email)}
              aria-describedby={errors.email ? "register-email-error" : undefined}
              className="min-h-11 rounded-md border border-rule-strong bg-sunken px-3 py-2 text-ink outline-focus-ring focus-visible:outline-2"
            />
          </label>
          {errors.email && (
            <p id="register-email-error" role="alert">
              {t(`error.field.${errors.email.message}` as never)}
            </p>
          )}
          <label className="grid gap-1 font-medium" htmlFor="register-password">
            {t("auth.form.password")}
            <input
              {...password}
              id="register-password"
              type="password"
              autoComplete="new-password"
              maxLength={128}
              aria-invalid={Boolean(errors.password)}
              aria-describedby={errors.password ? "register-password-error" : undefined}
              className="min-h-11 rounded-md border border-rule-strong bg-sunken px-3 py-2 text-ink outline-focus-ring focus-visible:outline-2"
            />
          </label>
          {errors.password && (
            <p id="register-password-error" role="alert">
              {t(`error.field.${errors.password.message}` as never)}
            </p>
          )}
          <label className="grid gap-1 font-medium" htmlFor="register-invite">
            {t("auth.form.invite_token")}
            <input
              {...invite}
              id="register-invite"
              autoComplete="off"
              className="min-h-11 rounded-md border border-rule-strong bg-sunken px-3 py-2 text-ink outline-focus-ring focus-visible:outline-2"
            />
          </label>
          <input {...timezone} type="hidden" />
          {serverError && <p role="alert">{serverError}</p>}
          <Button type="submit" isDisabled={form.formState.isSubmitting}>
            {t("auth.form.action.create_account")}
          </Button>
        </form>
        <a className="inline-flex min-h-11 items-center underline" href="/login">
          {t("auth.form.action.sign_in")}
        </a>
      </section>
    </main>
  );
}

function serverErrorFor(
  error: unknown,
  t: ReturnType<typeof useT>,
  setError: (value: string) => void,
) {
  if (!(error instanceof AppError)) {
    setError(t("error.internal_error.detail"));
    return;
  }
  if (error.code === "password_too_short" && typeof error.details.min === "number") {
    setError(t("auth.password.too_short", { min: error.details.min }));
    return;
  }
  if (error.code === "password_too_common") {
    setError(t("auth.password.too_common"));
    return;
  }
  setError(t(`error.${error.code}.detail` as MessageKey, error.details));
}
