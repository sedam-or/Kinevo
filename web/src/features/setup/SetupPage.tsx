import { useEffect, useState } from "react";
import { AppError } from "../../api/errors";
import { useAuth } from "../../auth/AuthProvider";
import { Button } from "../../components/Button";
import { setAccountLocale, useT } from "../../i18n";
import { DonePage } from "./DonePage";
import { ModulesStep } from "./ModulesStep";
import { RulesStep } from "./RulesStep";
import { type SetupAccountValues, setupAccountSchema } from "./schemas";
import { TemplateStep } from "./TemplateStep";
import { TokenStep } from "./TokenStep";
import { useWizard } from "./useWizard";

export function SetupPage({
  onComplete = defaultComplete,
}: {
  onComplete?: (path: string) => void;
}) {
  const t = useT();
  const { manager } = useAuth();
  const [initialToken] = useState(
    () => new URLSearchParams(window.location.search).get("token") ?? undefined,
  );
  const wizard = useWizard(initialToken, manager);
  const [token, setToken] = useState("");
  const [step, setStep] = useState<1 | 2 | 3>(1);
  const [templateId, setTemplateId] = useState("personal");
  const [locale, setLocale] = useState<"en" | "id">(browserLocale());
  const [baseUrl, setBaseUrl] = useState(() => window.location.origin);
  const [modules, setModules] = useState<Record<string, boolean>>({});
  const [policies, setPolicies] = useState<Record<string, unknown>>({});
  const [account, setAccount] = useState<SetupAccountValues>({
    email: "",
    password: "",
    timezone: browserTimezone(),
  });
  const [failedModule, setFailedModule] = useState<string>();
  const [finished, setFinished] = useState(false);

  useEffect(() => {
    if (!wizard.wizard) return;
    setStep(wizard.wizard.step);
    setTemplateId(wizard.wizard.template_id);
    setLocale(wizard.wizard.step === 1 ? browserLocale() : wizard.wizard.locale);
    setBaseUrl(wizard.wizard.base_url ?? window.location.origin);
    setModules(wizard.wizard.modules);
    setPolicies(wizard.wizard.policies);
    if (wizard.wizard.step === 1) void setAccountLocale(browserLocale());
  }, [wizard.wizard]);

  useEffect(() => {
    if (!finished) return;
    const timer = window.setTimeout(() => onComplete("/today"), 0);
    return () => window.clearTimeout(timer);
  }, [finished, onComplete]);

  const changeStep = async (
    next: 1 | 2 | 3,
    patch: {
      template_id?: string;
      locale?: "en" | "id";
      base_url?: string;
      modules?: Record<string, boolean>;
    },
  ) => {
    const previous = step;
    setStep(next);
    try {
      await wizard.patch({ step: next, ...patch });
    } catch {
      setStep(previous);
    }
  };

  const finish = async (values: SetupAccountValues) => {
    const valid = setupAccountSchema.safeParse(values);
    if (!valid.success) return;
    await wizard.patch({ step: 3, policies });
    const { display_name, ...required } = valid.data;
    await wizard.finish({
      ...required,
      ...(display_name?.trim() ? { display_name: display_name.trim() } : {}),
    });
    setFinished(true);
  };

  const errorText = messageForWizardError(wizard.error, t);
  const locked = wizard.error?.code === "rate_limited";
  const dependencyMissing = Array.isArray(wizard.error?.details.missing)
    ? wizard.error.details.missing
    : [];

  return (
    <main className="mx-auto grid min-h-screen w-full max-w-2xl content-start gap-6 p-4 text-ink sm:p-8">
      <header className="grid gap-2">
        <h1 className="font-display text-title-1">{t("setup.heading")}</h1>
        {wizard.wizard && !finished && (
          <p className="text-sm text-ink-muted">
            {t("setup.progress", { current: step, total: 3 })}
          </p>
        )}
      </header>

      {wizard.loading && !wizard.status ? (
        <p role="status" aria-busy="true">
          {t("state.loading")}
        </p>
      ) : wizard.status?.setup_completed ? (
        <section role="status" aria-labelledby="setup-completed-heading" className="grid gap-3">
          <h2 id="setup-completed-heading" className="font-display text-title-2">
            {t("error.setup_already_completed.title")}
          </h2>
        </section>
      ) : finished ? (
        <DonePage />
      ) : !wizard.wizard ? (
        <TokenStep
          value={token}
          {...(errorText ? { error: errorText } : {})}
          isLoading={wizard.loading}
          onChange={setToken}
          onSubmit={() => {
            setToken("");
            void wizard.start(token);
          }}
        />
      ) : (
        <>
          {locked && (
            <p role="alert" className="rounded-md border border-danger p-3 text-danger">
              {errorText}
            </p>
          )}
          {step === 1 && (
            <TemplateStep
              templates={wizard.status?.templates ?? []}
              templateId={templateId}
              locale={locale}
              baseUrl={baseUrl}
              onTemplateChange={setTemplateId}
              onLocaleChange={(value) => {
                setLocale(value);
                void setAccountLocale(value);
              }}
              onBaseUrlChange={setBaseUrl}
            />
          )}
          {step === 2 && (
            <ModulesStep
              fields={wizard.wizard.module_form ?? []}
              templateId={templateId}
              values={modules}
              dependencyMissing={dependencyMissing}
              {...(failedModule ? { failedModule } : {})}
              onChange={(key, enabled) => {
                setFailedModule(key);
                setModules((current) => ({ ...current, [key]: enabled }));
              }}
            />
          )}
          {step === 3 && (
            <RulesStep
              fields={wizard.wizard.policy_form ?? []}
              policies={policies}
              account={account}
              {...(wizard.error ? { error: wizard.error } : {})}
              isLoading={wizard.loading}
              onPoliciesChange={setPolicies}
              onAccountChange={setAccount}
              onFinish={finish}
            />
          )}
          <nav
            aria-label={t("setup.navigation")}
            className="flex items-center justify-between gap-3"
          >
            <Button
              variant="secondary"
              isDisabled={step === 1 || wizard.loading}
              onPress={() => void changeStep((step - 1) as 1 | 2 | 3, {})}
            >
              {t("setup.action.back")}
            </Button>
            {step < 3 && (
              <Button
                isLoading={wizard.loading}
                onPress={() =>
                  step === 1
                    ? void changeStep(2, {
                        template_id: templateId,
                        locale,
                        base_url: baseUrl,
                      })
                    : void changeStep(3, { modules })
                }
              >
                {t("setup.action.next")}
              </Button>
            )}
          </nav>
        </>
      )}
    </main>
  );
}

function defaultComplete(path: string): void {
  window.location.assign(path);
}

function messageForWizardError(
  error: AppError | undefined,
  t: ReturnType<typeof useT>,
): string | undefined {
  if (!error) return undefined;
  if (error.code === "setup_token_invalid") return t("setup.token.invalid");
  if (error.code === "rate_limited") {
    const seconds = error.details.retry_after_seconds;
    return typeof seconds === "number"
      ? t("state.rate_limited", { seconds })
      : t("error.rate_limited.title");
  }
  return t("error.service_unavailable.title");
}

function browserLocale(): "en" | "id" {
  const preferences = [...(navigator.languages ?? []), navigator.language];
  return preferences.some((language) => /^id(?:[-_]|$)/i.test(language)) ? "id" : "en";
}

function browserTimezone(): string {
  try {
    return Intl.DateTimeFormat().resolvedOptions().timeZone || "UTC";
  } catch {
    return "UTC";
  }
}
