import qrcode from "qrcode-generator";
import { type FormEvent, useEffect, useState } from "react";
import { api } from "../../../api/client";
import type { components } from "../../../api/schema";
import { Button } from "../../../components/Button";
import { TextField } from "../../../components/TextField";
import { useT } from "../../../i18n";
import { RecoveryCodesDialog } from "./RecoveryCodesDialog";
import { totpCodeSchema } from "./schemas";

type AccountProfile = components["schemas"]["AccountProfile"];
type Problem = components["schemas"]["Problem"];
type Enrolment = components["schemas"]["TotpEnrolmentResponse"];

export function TotpPanel() {
  const t = useT();
  const [account, setAccount] = useState<AccountProfile>();
  const [enrolment, setEnrolment] = useState<Enrolment>();
  const [recoveryCodes, setRecoveryCodes] = useState<string[]>();
  const [code, setCode] = useState("");
  const [password, setPassword] = useState("");
  const [problem, setProblem] = useState<Problem>();
  const [notice, setNotice] = useState("");
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    let active = true;
    void api.GET("/api/v1/account").then((result) => {
      if (!active) return;
      if (result.error) setProblem(result.error);
      else setAccount(result.data);
    });
    return () => {
      active = false;
    };
  }, []);

  async function beginEnrolment() {
    setBusy(true);
    setProblem(undefined);
    setNotice("");
    const result = await api.POST("/api/v1/account/totp/enroll", {
      params: { header: { "Idempotency-Key": crypto.randomUUID() } },
    });
    setBusy(false);
    if (result.error) setProblem(result.error);
    else setEnrolment(result.data);
  }

  async function confirmEnrolment(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const parsed = totpCodeSchema.safeParse({ code });
    if (!parsed.success || !enrolment) {
      setProblem(problemFor("invalid_mfa_code", 422));
      return;
    }
    setBusy(true);
    setProblem(undefined);
    const result = await api.POST("/api/v1/account/totp/confirm", {
      params: { header: { "Idempotency-Key": crypto.randomUUID() } },
      body: parsed.data,
    });
    setBusy(false);
    if (result.error) {
      setProblem(result.error);
      return;
    }
    setAccount((current) => (current ? { ...current, totp_enabled: true } : current));
    setNotice(t("auth.totp.enrolled"));
    setRecoveryCodes(enrolment.recovery_codes);
    setEnrolment(undefined);
    setCode("");
  }

  async function disableTotp(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const parsed = totpCodeSchema.safeParse({ code });
    if (!parsed.success || !password) {
      setProblem(problemFor("invalid_mfa_code", 422));
      return;
    }
    setBusy(true);
    setProblem(undefined);
    const result = await api.DELETE("/api/v1/account/totp", {
      params: { header: { "Idempotency-Key": crypto.randomUUID() } },
      body: { password, code: parsed.data.code },
    });
    setBusy(false);
    if (result.error) {
      setProblem(result.error);
      return;
    }
    setAccount((current) => (current ? { ...current, totp_enabled: false } : current));
    setPassword("");
    setCode("");
    setNotice(t("settings.security.totp_disabled"));
  }

  function clearRecoveryCodes() {
    setRecoveryCodes(undefined);
    setEnrolment(undefined);
    setCode("");
  }

  if (!account) {
    return (
      <section aria-labelledby="totp-heading" className="grid gap-3">
        <h2 id="totp-heading" className="font-display text-title-2">
          {t("settings.security.two_step")}
        </h2>
        {problem ? (
          <p role="alert">{errorMessage(problem, t)}</p>
        ) : (
          <p role="status">{t("state.loading")}</p>
        )}
      </section>
    );
  }

  return (
    <section aria-labelledby="totp-heading" className="grid gap-4">
      <h2 id="totp-heading" className="font-display text-title-2">
        {t("settings.security.two_step")}
      </h2>
      {notice && (
        <p role="status" aria-live="polite">
          {notice}
        </p>
      )}
      {!account.totp_enabled && !enrolment && (
        <div>
          <Button onPress={() => void beginEnrolment()} isLoading={busy}>
            {t("settings.security.totp_enable")}
          </Button>
        </div>
      )}
      {enrolment && (
        <div className="grid max-w-xl gap-4">
          <p>{t("settings.security.totp_scan")}</p>
          <QrCode uri={enrolment.otpauth_uri} />
          <p>{t("settings.security.totp_secret")}</p>
          <p className="font-mono">{formatSecret(enrolment.otpauth_uri)}</p>
          <form className="grid gap-4" onSubmit={(event) => void confirmEnrolment(event)}>
            <TextField
              label={t("auth.form.authentication_code")}
              type="text"
              inputMode="numeric"
              autoComplete="one-time-code"
              value={code}
              onChange={setCode}
              maxLength={6}
            />
            <div className="flex flex-wrap gap-2">
              <Button type="submit" isLoading={busy}>
                {t("settings.security.totp_confirm")}
              </Button>
              <Button variant="secondary" onPress={clearRecoveryCodes}>
                {t("auth.form.action.cancel")}
              </Button>
            </div>
          </form>
        </div>
      )}
      {account.totp_enabled && !recoveryCodes && (
        <form className="grid max-w-xl gap-4" onSubmit={(event) => void disableTotp(event)}>
          <p>{t("settings.security.totp_enabled")}</p>
          <TextField
            label={t("settings.password.current")}
            type="password"
            autoComplete="current-password"
            value={password}
            onChange={setPassword}
          />
          <TextField
            label={t("auth.form.authentication_code")}
            type="text"
            inputMode="numeric"
            autoComplete="one-time-code"
            value={code}
            onChange={setCode}
            maxLength={6}
          />
          <div>
            <Button type="submit" variant="danger" isLoading={busy}>
              {t("settings.security.totp_disable")}
            </Button>
          </div>
        </form>
      )}
      {problem && <p role="alert">{errorMessage(problem, t)}</p>}
      {recoveryCodes && <RecoveryCodesDialog codes={recoveryCodes} onClose={clearRecoveryCodes} />}
    </section>
  );
}

function QrCode({ uri }: { uri: string }) {
  const t = useT();
  const qr = qrcode(0, "M");
  qr.addData(uri);
  qr.make();
  const size = qr.getModuleCount();
  const cells = Array.from({ length: size * size }, (_, index) => ({
    row: Math.floor(index / size),
    column: index % size,
  }));
  const darkModules = cells
    .filter(({ row, column }) => qr.isDark(row, column))
    .map(({ row, column }) => `M${column} ${row}h1v1h-1z`)
    .join("");
  return (
    <svg
      viewBox={`0 0 ${size} ${size}`}
      width="160"
      height="160"
      shapeRendering="crispEdges"
      role="img"
      aria-label={t("settings.security.totp_qr")}
      className="w-fit rounded-md border border-rule bg-white p-2"
    >
      <rect width={size} height={size} fill="white" />
      <path d={darkModules} fill="black" />
    </svg>
  );
}

function formatSecret(uri: string): string {
  try {
    const secret = new URL(uri).searchParams.get("secret")?.toUpperCase();
    return secret ? (secret.match(/.{1,4}/gu)?.join("-") ?? secret) : "";
  } catch {
    return "";
  }
}

function errorMessage(problem: Problem, t: ReturnType<typeof useT>): string {
  if (problem.code === "totp_required_by_policy")
    return t("settings.security.totp_required_by_policy");
  if (problem.code === "current_password_incorrect")
    return t("error.current_password_incorrect.title");
  if (problem.code === "invalid_mfa_code") return t("auth.login.mfa_invalid");
  return t("error.internal_error.title");
}

function problemFor(code: string, status: number): Problem {
  return {
    type: "about:blank",
    title: code,
    status,
    detail: code,
    code,
    request_id: "00000000-0000-7000-8000-000000000000",
  };
}
