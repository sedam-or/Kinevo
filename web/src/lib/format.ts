import { useTranslation } from "react-i18next";
import { getAccountTimeZone } from "../i18n";

export function useFormat(): {
  date: (instant: string, options?: Intl.DateTimeFormatOptions) => string;
  time: (instant: string) => string;
  number: (value: number) => string;
} {
  const { i18n } = useTranslation();
  const locale = i18n.resolvedLanguage ?? i18n.language ?? "en";

  return {
    date: (instant, options = { dateStyle: "medium" }) =>
      new Intl.DateTimeFormat(locale, { ...options, timeZone: getAccountTimeZone() }).format(
        new Date(instant),
      ),
    time: (instant) =>
      new Intl.DateTimeFormat(locale, {
        timeStyle: "short",
        timeZone: getAccountTimeZone(),
      }).format(new Date(instant)),
    number: (value) => new Intl.NumberFormat(locale).format(value),
  };
}
