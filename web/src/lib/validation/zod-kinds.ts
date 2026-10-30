import { z } from "zod";

type ValidationParams = Record<string, unknown>;

const forbiddenInvisible = /[\u200b-\u200f\u202a-\u202e\u2066-\u2069]/u;
const withoutInvisible = (value: string) =>
  value.replace(/[\u200b-\u200f\u202a-\u202e\u2066-\u2069]/gu, "");

function hasForbiddenControls(value: string): boolean {
  for (const character of value) {
    const codePoint = character.codePointAt(0) ?? 0;
    if (
      codePoint <= 0x1f ||
      (codePoint >= 0x7f && codePoint <= 0x9f) ||
      (codePoint >= 0xd800 && codePoint <= 0xdfff) ||
      codePoint === 0xfffe ||
      codePoint === 0xffff
    ) {
      return true;
    }
  }
  return false;
}

function schema<T>(
  normalise: (input: string, params: ValidationParams) => { value: T; error?: string },
) {
  return (params: ValidationParams = {}) =>
    z.string().transform((input, context) => {
      const result = normalise(input, params);
      if (result.error) context.addIssue({ code: "custom", message: result.error });
      return result.value;
    });
}

const email = schema<string>((input) => {
  const value = input.trim().normalize("NFC").toLowerCase();
  if (
    !value ||
    value.length > 254 ||
    hasForbiddenControls(value) ||
    forbiddenInvisible.test(value) ||
    /\s/u.test(value) ||
    /["(),:;<>]/u.test(value) ||
    value.includes("\\") ||
    value.includes("[") ||
    value.includes("]")
  )
    return { value, error: "email_invalid" };

  const parts = value.split("@");
  if (parts.length !== 2) return { value, error: "email_invalid" };
  const [local, domain] = parts;
  const labels = domain?.split(".") ?? [];
  if (
    !local ||
    local.length > 64 ||
    local.startsWith(".") ||
    local.endsWith(".") ||
    local.includes("..") ||
    !domain ||
    !domain.includes(".") ||
    labels.some(
      (label) => !label || label.length > 63 || label.startsWith("-") || label.endsWith("-"),
    ) ||
    labels.some((label) => !/^[\p{L}\p{N}-]+$/u.test(label))
  )
    return { value, error: "email_invalid" };
  return { value };
});

const shortText = schema<string>((input, params) => {
  const value = withoutInvisible(input).normalize("NFC").trim();
  const min = typeof params.min === "number" ? params.min : 1;
  const max = typeof params.max === "number" ? params.max : Number.POSITIVE_INFINITY;
  if (hasForbiddenControls(value) || /[\r\n]/u.test(value))
    return { value, error: "invalid_characters" };
  if (!value && min > 0) return { value, error: "required" };
  const length = Array.from(value).length;
  if (length < min) return { value, error: "too_short" };
  if (length > max) return { value, error: "too_long" };
  return { value };
});

const key = schema<string>((input, params) => {
  const value = input.trim().toLowerCase();
  const pattern = typeof params.pattern === "string" ? params.pattern : "";
  if (hasForbiddenControls(value) || forbiddenInvisible.test(value) || !pattern)
    return { value, error: "key_invalid" };
  try {
    return new RegExp(pattern, "u").test(value) ? { value } : { value, error: "key_invalid" };
  } catch {
    return { value, error: "key_invalid" };
  }
});

const httpsUrl = schema<string>((input) => {
  const value = input.trim();
  if (
    value.length > 2048 ||
    hasForbiddenControls(value) ||
    forbiddenInvisible.test(value) ||
    value.includes("#")
  ) {
    return { value, error: "url_invalid" };
  }
  try {
    const url = new URL(value);
    const host = url.hostname.toLowerCase();
    const ipv4Loopback =
      /^127(?:\.\d{1,3}){3}$/u.test(host) && host.split(".").every((part) => Number(part) <= 255);
    const loopback =
      host === "localhost" || host.endsWith(".localhost") || ipv4Loopback || host === "[::1]";
    if (
      !url.hostname ||
      url.username ||
      url.password ||
      (url.protocol !== "https:" && !(url.protocol === "http:" && loopback))
    ) {
      return { value, error: "url_invalid" };
    }
    url.hash = "";
    const normalised = url.pathname === "/" ? `${url.origin}${url.search}` : url.toString();
    return { value: normalised };
  } catch {
    return { value, error: "url_invalid" };
  }
});

const ianaTz = schema<string>((input) => {
  const value = input.trim();
  try {
    new Intl.DateTimeFormat("en", { timeZone: value });
    return { value };
  } catch {
    return { value, error: "timezone_invalid" };
  }
});

export const zodKinds = { email, short_text: shortText, key, https_url: httpsUrl, iana_tz: ianaTz };
