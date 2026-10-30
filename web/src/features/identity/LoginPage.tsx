import type { ReactNode } from "react";
import { useState } from "react";
import { useForm } from "react-hook-form";
import { AppError } from "../../api/errors";
import { useAuth } from "../../auth/AuthProvider";
import { safeNext } from "../../auth/guards";
import { Button } from "../../components/Button";
import { type MessageKey, useT } from "../../i18n";
import { MfaStep } from "./MfaStep";
import {
  type LoginValues,
  loginSchema,
  type ResetRequestValues,
  resetRequestSchema,
  resolverFor,
} from "./schemas";

type Credentials = { email: string; password: string };

export function LoginPage({
  next,
  onAuthenticated,
}: {
  next?: string | null;
  onAuthenticated?: (path: string) => void;
}) {
  const t = useT();
  const { manager } = useAuth();
  const [mode, setMode] = useState<"login" | "reset">("login");
  const [credentials, setCredentials] = useState<Credentials>();
  const [methods, setMethods] = useState<string[]>([]);
  const [mfaSubmitting, setMfaSubmitting] = useState(false);
  const [serverError, setServerError] = useState<string>();
  const [resetRequested, setResetRequested] = useState(false);
  const loginForm = useForm<LoginValues>({
    resolver: resolverFor(loginSchema),
    mode: "onBlur",
    defaultValues: { email: "", password: "" },
  });
  const resetForm = useForm<ResetRequestValues>({
    resolver: resolverFor(resetRequestSchema),
    mode: "onBlur",
    defaultValues: { email: "" },
  });

  const complete = (path?: string) => {
    const destination = safeNext(path ?? next);
    if (onAuthenticated) onAuthenticated(destination);
    else window.location.assign(destination);
  };

  const submitLogin = async (values: LoginValues) => {
    setServerError(undefined);
    try {
      const result = await manager.login(values);
      if (result.kind === "mfa_required") {
        setCredentials({ email: values.email, password: values.password });
        setMethods(result.methods);
        loginForm.reset({ email: values.email, password: "" });
        return;
      }
      loginForm.reset({ email: values.email, password: "" });
      setCredentials(undefined);
      complete(next ?? "/today");
    } catch (error) {
      loginForm.setValue("password", "");
      if (error instanceof AppError && error.fields.length) {
        for (const field of error.fields) {
          if (field.path === "email" || field.path === "password") {
            loginForm.setError(field.path, { message: field.code });
          }
        }
      } else {
        loginForm.setError("root", { message: messageFor(error, t, "login") });
      }
    }
  };

  const submitMfa = async (value: string, recovery: boolean) => {
    if (!credentials) return;
    setServerError(undefined);
    setMfaSubmitting(true);
    try {
      const result = await manager.login({
        ...credentials,
        ...(recovery ? { recovery_code: value } : { totp_code: value }),
      });
      if (result.kind === "mfa_required") {
        setMethods(result.methods);
        return;
      }
      setCredentials(undefined);
      complete(next ?? "/today");
    } catch (error) {
      setServerError(messageFor(error, t, "mfa"));
    } finally {
      setMfaSubmitting(false);
    }
  };

  const submitReset = async (values: ResetRequestValues) => {
    setServerError(undefined);
    try {
      await manager.requestPasswordReset(values.email);
      setResetRequested(true);
    } catch (error) {
      setServerError(messageFor(error, t, "reset"));
    }
  };

  if (credentials) {
    return (
      <main className="grid min-h-full place-items-center p-4 text-ink">
        <MfaStep
          email={credentials.email}
          methods={methods}
          {...(serverError ? { error: serverError } : {})}
          isSubmitting={mfaSubmitting}
          onSubmit={submitMfa}
          onCancel={() => {
            setCredentials(undefined);
            setServerError(undefined);
            loginForm.reset({ email: credentials.email, password: "" });
          }}
        />
      </main>
    );
  }

  if (mode === "reset") {
    if (resetRequested) {
      return (
        <AuthCard title={t("auth.form.title.reset_request")}>
          <p role="status">{t("auth.reset.requested")}</p>
          <a className="underline" href="/login">
            {t("auth.form.action.back_to_login")}
          </a>
        </AuthCard>
      );
    }
    const email = resetForm.register("email");
    const emailError = resetForm.formState.errors.email?.message;
    return (
      <AuthCard title={t("auth.form.title.reset_request")}>
        <form
          className="grid gap-4"
          aria-label={t("auth.form.title.reset_request")}
          onSubmit={resetForm.handleSubmit(submitReset)}
        >
          <label className="grid gap-1 font-medium" htmlFor="reset-request-email">
            {t("auth.form.email")}
            <input
              {...email}
              id="reset-request-email"
              type="email"
              autoComplete="username"
              aria-invalid={Boolean(emailError)}
              aria-describedby={emailError ? "reset-request-email-error" : undefined}
              className="min-h-11 rounded-md border border-rule-strong bg-sunken px-3 py-2 text-ink outline-focus-ring focus-visible:outline-2"
            />
          </label>
          {emailError && (
            <p id="reset-request-email-error" role="alert">
              {t(`error.field.${emailError}` as never)}
            </p>
          )}
          {serverError && <p role="alert">{serverError}</p>}
          <Button type="submit" isDisabled={resetForm.formState.isSubmitting}>
            {t("auth.form.action.send_reset")}
          </Button>
          <Button
            type="button"
            variant="quiet"
            onPress={() => {
              setMode("login");
              setServerError(undefined);
            }}
          >
            {t("auth.form.action.back_to_login")}
          </Button>
        </form>
      </AuthCard>
    );
  }

  const email = loginForm.register("email");
  const password = loginForm.register("password");
  const emailError = loginForm.formState.errors.email?.message;
  const passwordError = loginForm.formState.errors.password?.message;
  const rootError = loginForm.formState.errors.root?.message;

  return (
    <AuthCard title={t("auth.form.title.login")}>
      <p>{t("auth.form.description.login")}</p>
      <form
        className="grid gap-4"
        aria-label={t("auth.form.title.login")}
        onSubmit={loginForm.handleSubmit(submitLogin)}
      >
        <label className="grid gap-1 font-medium" htmlFor="login-email">
          {t("auth.form.email")}
          <input
            {...email}
            id="login-email"
            type="email"
            autoComplete="username"
            aria-invalid={Boolean(emailError)}
            aria-describedby={emailError ? "login-email-error" : undefined}
            className="min-h-11 rounded-md border border-rule-strong bg-sunken px-3 py-2 text-ink outline-focus-ring focus-visible:outline-2"
          />
        </label>
        {emailError && (
          <p id="login-email-error" role="alert">
            {t(`error.field.${emailError}` as never)}
          </p>
        )}
        <label className="grid gap-1 font-medium" htmlFor="login-password">
          {t("auth.form.password")}
          <input
            {...password}
            id="login-password"
            type="password"
            autoComplete="current-password"
            maxLength={128}
            aria-invalid={Boolean(passwordError)}
            aria-describedby={passwordError ? "login-password-error" : undefined}
            className="min-h-11 rounded-md border border-rule-strong bg-sunken px-3 py-2 text-ink outline-focus-ring focus-visible:outline-2"
          />
        </label>
        {passwordError && (
          <p id="login-password-error" role="alert">
            {t(`error.field.${passwordError}` as never)}
          </p>
        )}
        {rootError && <p role="alert">{rootError}</p>}
        <Button type="submit" isDisabled={loginForm.formState.isSubmitting}>
          {t("auth.form.action.sign_in")}
        </Button>
      </form>
      <div className="flex flex-wrap gap-3 text-sm">
        <Button
          type="button"
          variant="quiet"
          onPress={() => {
            setMode("reset");
            setServerError(undefined);
          }}
        >
          {t("auth.form.action.forgot_password")}
        </Button>
        <a className="inline-flex min-h-11 items-center underline" href="/register">
          {t("auth.form.action.create_account")}
        </a>
      </div>
    </AuthCard>
  );
}

function AuthCard({ title, children }: { title: string; children: ReactNode }) {
  return (
    <main className="grid min-h-full place-items-center p-4 text-ink">
      <section className="grid w-full max-w-md gap-4 rounded-lg border border-rule bg-surface p-6 shadow-rest">
        <h1 className="font-display text-title-2">{title}</h1>
        {children}
      </section>
    </main>
  );
}

function messageFor(
  error: unknown,
  t: ReturnType<typeof useT>,
  mode: "login" | "mfa" | "reset",
): string {
  if (!(error instanceof AppError)) return t("error.internal_error.detail");
  if (error.code === "invalid_credentials") return t("auth.login.invalid");
  if (error.code === "invalid_mfa_code") return t("auth.login.mfa_invalid");
  if (error.code === "account_locked" && typeof error.details.minutes === "number") {
    return t("auth.login.locked", { minutes: error.details.minutes });
  }
  if (error.code === "rate_limited" && typeof error.details.retry_after_seconds === "number") {
    return t("auth.login.rate_limited", { seconds: error.details.retry_after_seconds });
  }
  if (error.code === "password_too_short" && typeof error.details.min === "number") {
    return t("auth.password.too_short", { min: error.details.min });
  }
  if (error.code === "password_too_common") return t("auth.password.too_common");
  if (error.code === "reset_token_invalid" && mode === "reset") return t("auth.reset.invalid");
  const key = `error.${error.code}.detail` as MessageKey;
  return t(key, error.details);
}
