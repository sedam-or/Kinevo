import { z } from "zod";

const supportedTimeZones =
  typeof Intl.supportedValuesOf === "function" ? Intl.supportedValuesOf("timeZone") : [];

export function profileTimeZones(current?: string): string[] {
  const values = new Set(supportedTimeZones);
  if (current) values.add(current);
  return [...values].sort((left, right) => left.localeCompare(right));
}

export const profileSchema = z.object({
  display_name: z.string().min(1).max(80),
  locale: z.enum(["en", "id"]),
  timezone: z.string().refine((value) => {
    try {
      new Intl.DateTimeFormat("en", { timeZone: value });
      return true;
    } catch {
      return false;
    }
  }),
  week_start: z.number().int().min(1).max(7),
});

export const passwordChangeSchema = z.object({
  current_password: z.string().min(1),
  new_password: z.string().min(1).max(128),
});

export const totpCodeSchema = z.object({ code: z.string().regex(/^\d{6}$/u) });

export type ProfilePatch = z.infer<typeof profileSchema>;
