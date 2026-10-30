import type { FieldErrors, FieldValues, Resolver, ResolverError } from "react-hook-form";
import { z } from "zod";
import { zodKinds } from "../../lib/validation/zod-kinds";

export const loginSchema = z.object({
  email: zodKinds.email(),
  password: z.string().min(1, "required").max(128, "too_long"),
});

export const registerSchema = z.object({
  display_name: zodKinds.short_text({ min: 1, max: 80 }),
  email: zodKinds.email(),
  password: z.string().min(1, "required").max(128, "too_long"),
  timezone: zodKinds.iana_tz(),
  invite_token: z.string().optional(),
});

export const resetRequestSchema = z.object({ email: zodKinds.email() });
export const resetConfirmSchema = z.object({
  new_password: z.string().min(1, "required").max(128, "too_long"),
});
export const mfaSchema = z.object({ code: z.string().regex(/^\d{6}$/u, "digits_invalid") });
export const recoveryCodeSchema = z.object({
  code: z.string().regex(/^[ABCDEFGHJKMNPQRSTVWXYZ23456789*~]{10}$/u, "token_invalid"),
});

export type LoginValues = z.input<typeof loginSchema>;
export type RegisterValues = z.input<typeof registerSchema>;
export type ResetRequestValues = z.input<typeof resetRequestSchema>;
export type ResetConfirmValues = z.input<typeof resetConfirmSchema>;
export type MfaValues = z.input<typeof mfaSchema>;

export function resolverFor<T extends FieldValues>(schema: z.ZodType<T>): Resolver<T> {
  return async (values) => {
    const result = schema.safeParse(values);
    if (result.success) return { values: result.data, errors: {} };

    const errors: FieldErrors<T> = {};
    for (const issue of result.error.issues) {
      const field = issue.path[0];
      if (typeof field === "string" && errors[field as keyof typeof errors] === undefined) {
        errors[field as keyof typeof errors] = {
          type: "validate",
          message: issue.message,
        } as never;
      }
    }
    return { values: {}, errors } as ResolverError<T>;
  };
}
