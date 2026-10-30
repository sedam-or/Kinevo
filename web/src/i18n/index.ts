import i18next, { type i18n as I18nInstance } from "i18next";
import ICU from "i18next-icu";
import { initReactI18next, useTranslation } from "react-i18next";
import en from "./en.json";
import id from "./id.json";

export type Locale = "en" | "id";
export type MessageKey = keyof typeof en;
export type TermKey = Extract<MessageKey, `term.${string}`>;
export type TermOverrides = Partial<Record<TermKey, MessageKey>>;
export type Translation = (key: MessageKey, vars?: Record<string, unknown>) => string;

let accountTimeZone: string | undefined;

function normalizeLocale(locale: string | undefined): Locale {
  const language = locale?.toLowerCase().split(/[-_]/, 1)[0];
  return language === "id" ? "id" : "en";
}

function browserLocale(): Locale {
  if (typeof navigator === "undefined") return "en";
  const preferences = [...(navigator.languages ?? []), navigator.language];
  const supported = preferences.find((language) => /^(en|id)([-_]|$)/i.test(language));
  return normalizeLocale(supported);
}

function syncHtmlLanguage(instance: I18nInstance, locale = instance.resolvedLanguage): void {
  if (typeof document !== "undefined" && locale) {
    document.documentElement.lang = normalizeLocale(locale);
  }
}

i18next
  .use(ICU)
  .use(initReactI18next)
  .init({
    resources: {
      en: { translation: en },
      id: { translation: id },
    },
    lng: browserLocale(),
    fallbackLng: "en",
    supportedLngs: ["en", "id"],
    defaultNS: "translation",
    ns: ["translation"],
    returnNull: false,
    interpolation: { escapeValue: false },
    i18nFormat: { escapeVariables: false },
  })
  .then(() => syncHtmlLanguage(i18next));

i18next.on("languageChanged", (locale) => syncHtmlLanguage(i18next, locale));

export async function setAccountLocale(locale?: string, timeZone?: string): Promise<void> {
  const resolvedLocale = locale ? normalizeLocale(locale) : browserLocale();
  accountTimeZone = timeZone ?? accountTimeZone ?? Intl.DateTimeFormat().resolvedOptions().timeZone;
  await i18next.changeLanguage(resolvedLocale);
  syncHtmlLanguage(i18next, resolvedLocale);
}

export function getAccountTimeZone(): string {
  return accountTimeZone ?? Intl.DateTimeFormat().resolvedOptions().timeZone;
}

export function useT(termOverrides: TermOverrides = {}): Translation {
  const { t, i18n } = useTranslation();
  return (key, vars = {}) => {
    const messageKey = (key.startsWith("term.") ? termOverrides[key as TermKey] : undefined) ?? key;
    const locale = normalizeLocale(i18n.resolvedLanguage ?? i18n.language);
    if (
      import.meta.env.DEV &&
      locale === "id" &&
      i18n.getResource("id", "translation", messageKey) === undefined
    ) {
      return `⟦${messageKey}⟧`;
    }
    return String(t(messageKey, vars as never));
  };
}
