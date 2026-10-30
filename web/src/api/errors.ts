import { type MessageKey, useT } from "../i18n";
import { errorCodeMetadata } from "./error-codes";

export type RetryClass = (typeof errorCodeMetadata)[keyof typeof errorCodeMetadata]["retry"];
export type ErrorDetailScalar = string | number | boolean | string[];
export type ErrorBounds = { min?: number; max?: number };
export type ErrorDetails = Record<string, ErrorDetailScalar | ErrorBounds>;

export type FieldError = {
  path: string;
  code: string;
  details: ErrorDetails;
};

export class AppError {
  constructor(
    readonly code: string,
    readonly status: number,
    readonly requestId: string | undefined,
    readonly fields: FieldError[],
    readonly details: ErrorDetails,
    readonly retry: RetryClass | "backoff",
  ) {}
}

const networkError = () => new AppError("network_error", 0, undefined, [], {}, "backoff");

export function mapProblem(response: Response): Promise<AppError>;
export function mapProblem(response: Promise<Response>): Promise<AppError>;
export async function mapProblem(input: Response | Promise<Response>): Promise<AppError> {
  let response: Response;
  try {
    response = await input;
  } catch {
    return networkError();
  }
  if (!(response instanceof Response)) return networkError();

  let body: unknown;
  try {
    body = await response.json();
  } catch {
    body = undefined;
  }

  const problem = isRecord(body) ? body : {};
  const code = typeof problem.code === "string" && problem.code ? problem.code : "internal_error";
  const knownCode = Object.hasOwn(errorCodeMetadata, code);
  const retry = knownCode
    ? errorCodeMetadata[code as keyof typeof errorCodeMetadata].retry
    : errorCodeMetadata.internal_error.retry;
  const fields = Array.isArray(problem.errors)
    ? problem.errors.flatMap((field) => {
        if (!isRecord(field) || typeof field.path !== "string" || typeof field.code !== "string") {
          return [];
        }
        return [
          {
            path: field.path,
            code: field.code,
            details: sanitizeDetails(field.details),
          },
        ];
      })
    : [];

  return new AppError(
    code,
    response.status,
    typeof problem.request_id === "string" && problem.request_id ? problem.request_id : undefined,
    fields,
    sanitizeDetails(problem.details),
    retry,
  );
}

export function useErrorMessage(error: AppError): { title: string; detail: string } {
  const t = useT();
  const code = Object.hasOwn(errorCodeMetadata, error.code) ? error.code : "internal_error";
  const prefix = `error.${code}`;
  const variables: Record<string, unknown> = { ...error.details };
  const bounds = error.details.bounds;
  if (isRecord(bounds)) {
    if (typeof bounds.min === "number" && Number.isFinite(bounds.min)) variables.min = bounds.min;
    if (typeof bounds.max === "number" && Number.isFinite(bounds.max)) variables.max = bounds.max;
  }

  return {
    title: t(`${prefix}.title` as MessageKey),
    detail: t(`${prefix}.detail` as MessageKey, variables),
  };
}

function sanitizeDetails(value: unknown): ErrorDetails {
  if (!isRecord(value)) return {};
  const details: ErrorDetails = {};
  for (const [key, entry] of Object.entries(value)) {
    if (typeof entry === "string" || typeof entry === "boolean") {
      details[key] = entry;
    } else if (typeof entry === "number" && Number.isFinite(entry)) {
      details[key] = entry;
    } else if (
      Array.isArray(entry) &&
      entry.every((item): item is string => typeof item === "string")
    ) {
      details[key] = entry;
    } else if (key === "bounds" && isRecord(entry)) {
      const bounds: ErrorBounds = {};
      if (typeof entry.min === "number" && Number.isFinite(entry.min)) bounds.min = entry.min;
      if (typeof entry.max === "number" && Number.isFinite(entry.max)) bounds.max = entry.max;
      if (Object.keys(bounds).length) details.bounds = bounds;
    }
  }
  return details;
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === "object" && value !== null && !Array.isArray(value);
}
