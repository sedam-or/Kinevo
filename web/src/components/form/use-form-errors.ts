import type { components } from "../../api/schema";

export type AppError = components["schemas"]["Problem"];

export function useFormErrors(problem: AppError | undefined): {
  fieldErrors: Record<string, string>;
  formError?: string;
} {
  if (!problem) return { fieldErrors: {} };

  const fieldErrors: Record<string, string> = {};
  for (const error of problem.errors ?? []) {
    fieldErrors[error.path] = error.code;
  }

  return problem.errors?.length ? { fieldErrors } : { fieldErrors, formError: problem.code };
}
