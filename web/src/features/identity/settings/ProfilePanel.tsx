import { type FormEvent, useCallback, useEffect, useState } from "react";
import { api } from "../../../api/client";
import type { components } from "../../../api/schema";
import { Button } from "../../../components/Button";
import { ConflictCard } from "../../../components/ConflictCard";
import { useFormErrors } from "../../../components/form/use-form-errors";
import { Select, type SelectItem } from "../../../components/Select";
import { TextField } from "../../../components/TextField";
import type { MessageKey } from "../../../i18n";
import { setAccountLocale, useT } from "../../../i18n";
import { type ProfilePatch, profileSchema, profileTimeZones } from "./schemas";

type AccountProfile = components["schemas"]["AccountProfile"];
type Problem = components["schemas"]["Problem"];

const localeOptions: SelectItem[] = [
  { id: "en", label: "English" },
  { id: "id", label: "Bahasa Indonesia" },
];

export function ProfilePanel() {
  const t = useT();
  const [account, setAccount] = useState<AccountProfile>();
  const [latest, setLatest] = useState<AccountProfile>();
  const [draft, setDraft] = useState<ProfilePatch>();
  const [problem, setProblem] = useState<Problem>();
  const [clientErrors, setClientErrors] = useState<Record<string, string>>({});
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [conflict, setConflict] = useState(false);
  const [saved, setSaved] = useState(false);
  const { fieldErrors } = useFormErrors(problem);

  const loadProfile = useCallback(async (preserveDraft: boolean) => {
    setLoading(true);
    setProblem(undefined);
    const result = await api.GET("/api/v1/account");
    setLoading(false);
    if (result.error) {
      setProblem(result.error);
      return;
    }
    setAccount(result.data);
    setLatest(result.data);
    if (!preserveDraft) setDraft(profileFrom(result.data));
  }, []);

  useEffect(() => {
    void loadProfile(false);
  }, [loadProfile]);

  if (loading && !draft) return <p role="status">{t("state.loading")}</p>;
  if (!draft || !account) {
    return (
      <section aria-labelledby="profile-error-title" className="grid gap-3">
        <h2 id="profile-error-title" className="font-semibold">
          {t("settings.profile")}
        </h2>
        <p role="alert">
          {problem ? errorTitle(problem, t) : t("error.service_unavailable.title")}
        </p>
        <Button variant="secondary" onPress={() => void loadProfile(false)}>
          {t("action.reload_section")}
        </Button>
      </section>
    );
  }

  const update = <K extends keyof ProfilePatch>(key: K, value: ProfilePatch[K]) => {
    setSaved(false);
    setClientErrors((current) => ({ ...current, [key]: "" }));
    setDraft((current) => (current ? { ...current, [key]: value } : current));
  };

  async function save(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (!account) return;
    setProblem(undefined);
    setConflict(false);
    setSaved(false);
    const parsed = profileSchema.safeParse(draft);
    if (!parsed.success) {
      setClientErrors(
        Object.fromEntries(parsed.error.issues.map((issue) => [String(issue.path[0]), issue.code])),
      );
      return;
    }

    setSaving(true);
    const result = await api.PATCH("/api/v1/account", {
      params: { header: { "If-Match": `"${account.version}"` } },
      body: parsed.data,
    } as never);
    setSaving(false);
    if (result.error) {
      setProblem(result.error as Problem);
      if (result.error.code === "version_conflict") {
        setConflict(true);
        await loadProfile(true);
      }
      return;
    }

    setAccount(result.data as AccountProfile);
    setLatest(result.data as AccountProfile);
    setDraft(profileFrom(result.data as AccountProfile));
    setClientErrors({});
    setSaved(true);
    await setAccountLocale(parsed.data.locale, parsed.data.timezone);
  }

  const fieldError = (key: keyof ProfilePatch) =>
    fieldErrors[key]
      ? fieldMessage(fieldErrors[key], t)
      : clientErrors[key]
        ? fieldMessage(clientErrors[key], t)
        : undefined;
  const localeError = fieldError("locale");
  const timeZoneError = fieldError("timezone");
  const weekStartError = fieldError("week_start");
  const timeZones = profileTimeZones(draft.timezone);

  return (
    <section aria-labelledby="profile-heading" className="grid gap-4">
      <h2 id="profile-heading" className="font-display text-title-2">
        {t("settings.profile")}
      </h2>
      {conflict && latest && (
        <ConflictCard
          yourChange={<p>{draft.display_name}</p>}
          currentVersion={<p>{latest.display_name}</p>}
          onKeepMine={() => {
            setAccount(latest);
            setConflict(false);
            setProblem(undefined);
          }}
          onUseCurrent={() => {
            setAccount(latest);
            setDraft(profileFrom(latest));
            setConflict(false);
            setProblem(undefined);
          }}
        />
      )}
      <form className="grid max-w-2xl gap-4" noValidate onSubmit={(event) => void save(event)}>
        <TextField
          label={t("auth.form.display_name")}
          value={draft.display_name}
          onChange={(value) => update("display_name", value)}
          maxLength={80}
          error={fieldError("display_name")}
          autoComplete="nickname"
        />
        <Select
          label={t("settings.language")}
          items={localeOptions.map((option) => ({
            ...option,
            label:
              option.id === "en"
                ? t("settings.language.english")
                : t("settings.language.indonesian"),
          }))}
          selectedKey={draft.locale}
          onSelectionChange={(value) => {
            if (value === "en" || value === "id") update("locale", value);
          }}
          {...(localeError ? { error: localeError } : {})}
        />
        <Select
          label={t("settings.timezone")}
          items={timeZones.map((timezone) => ({ id: timezone, label: timezone }))}
          selectedKey={draft.timezone}
          onSelectionChange={(value) => {
            if (value) update("timezone", value);
          }}
          {...(timeZoneError ? { error: timeZoneError } : {})}
        />
        <Select
          label={t("settings.week_start")}
          items={weekStartOptions(t)}
          selectedKey={String(draft.week_start)}
          onSelectionChange={(value) => {
            const day = Number(value);
            if (Number.isInteger(day) && day >= 1 && day <= 7) update("week_start", day);
          }}
          {...(weekStartError ? { error: weekStartError } : {})}
        />
        {problem && !conflict && <p role="alert">{errorTitle(problem, t)}</p>}
        {saved && <p role="status">{t("settings.profile.saved")}</p>}
        <div>
          <Button type="submit" isLoading={saving}>
            {t("settings.profile.save")}
          </Button>
        </div>
      </form>
    </section>
  );
}

function profileFrom(account: AccountProfile): ProfilePatch {
  return {
    display_name: account.display_name,
    locale: account.locale === "id" ? "id" : "en",
    timezone: account.timezone,
    week_start: account.week_start,
  };
}

function weekStartOptions(t: ReturnType<typeof useT>): SelectItem[] {
  return Array.from({ length: 7 }, (_, index) => index + 1).map((day) => ({
    id: String(day),
    label: t(`settings.week_start.${day}` as MessageKey),
  }));
}

function fieldMessage(code: string, t: ReturnType<typeof useT>): string {
  if (code === "too_small") return t("settings.field.required");
  if (code === "too_big") return t("settings.field.too_long", { max: 80 });
  return t("settings.field.invalid");
}

function errorTitle(problem: Problem, t: ReturnType<typeof useT>): string {
  if (problem.code === "timezone_invalid") return t("settings.field.invalid");
  return t("error.internal_error.title");
}
