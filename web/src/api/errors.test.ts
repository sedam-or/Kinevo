import { readFileSync } from "node:fs";
import { resolve } from "node:path";
import { act, renderHook } from "@testing-library/react";
import { describe, expect, it } from "vitest";
import { checkErrorMessages } from "../../scripts/check-i18n";
import { parseErrorRegistry } from "../../scripts/gen-error-codes";
import { setAccountLocale } from "../i18n";
import en from "../i18n/en.json";
import id from "../i18n/id.json";
import { errorCodeMetadata, fieldErrorCodes } from "./error-codes";
import { mapProblem, useErrorMessage } from "./errors";

const registryPath = resolve(process.cwd(), "../spec/standards/error-handling.md");
const requestId = "00000000-0000-4000-8000-000000000048";

function problem(code: string, status: number, extra: Record<string, unknown> = {}): Response {
  return new Response(
    JSON.stringify({
      type: "about:blank",
      title: "Server supplied title must not be displayed",
      status,
      detail: "Server supplied detail must not be displayed",
      code,
      request_id: requestId,
      ...extra,
    }),
    { status, headers: { "content-type": "application/problem+json" } },
  );
}

describe("errors", () => {
  it("errors::err_01_ts_table_matches_registry", () => {
    expect(errorCodeMetadata).toEqual(
      parseErrorRegistry(readFileSync(registryPath, "utf8")).apiCodes,
    );
  });

  it("errors::uxs_01_error_messages_complete", () => {
    const registry = parseErrorRegistry(readFileSync(registryPath, "utf8"));

    expect(errorCodeMetadata).toEqual(registry.apiCodes);
    expect([...fieldErrorCodes]).toEqual(registry.fieldCodes);
    expect(checkErrorMessages(en, id, registry)).toEqual([]);
    expect(checkErrorMessages({}, {}, registry)).toContain(
      "Missing error message: error.policy_value_invalid.title in en",
    );
  });

  it("errors::fe_03_validation_maps_all_fields", async () => {
    const error = await mapProblem(
      problem("validation_failed", 422, {
        errors: [
          { path: "email", code: "email_invalid", details: { reason: "format" } },
          { path: "password", code: "too_short", details: { min: 12 } },
        ],
        unexpected: "must be dropped",
      }),
    );

    expect(error.fields).toEqual([
      { path: "email", code: "email_invalid", details: { reason: "format" } },
      { path: "password", code: "too_short", details: { min: 12 } },
    ]);
    expect(error.details).toEqual({});
    expect(error.requestId).toBe(requestId);
    expect("detail" in error).toBe(false);
  });

  it("errors::unknown_code_falls_back", async () => {
    await act(async () => setAccountLocale("en", "UTC"));
    const error = await mapProblem(problem("unknown_future_code", 418));
    const { result } = renderHook(() => useErrorMessage(error));

    expect(error.code).toBe("unknown_future_code");
    expect(error.requestId).toBe(requestId);
    expect(result.current).toEqual({
      title: en["error.internal_error.title"],
      detail: en["error.internal_error.detail"],
    });
    expect(result.current.detail).not.toContain("Server supplied");
  });

  it("errors::network_error_local_code", async () => {
    const error = await mapProblem(Promise.reject(new TypeError("secret request URL")));

    expect(error.code).toBe("network_error");
    expect(error.retry).toBe("backoff");
    expect(error.status).toBe(0);
    expect(error.requestId).toBeUndefined();
  });

  it("errors::details_feed_icu_arguments", async () => {
    await act(async () => setAccountLocale("en", "UTC"));
    const error = await mapProblem(
      problem("policy_value_invalid", 422, { details: { bounds: { min: 10, max: 128 } } }),
    );
    const { result } = renderHook(() => useErrorMessage(error));

    expect(error.details).toEqual({ bounds: { min: 10, max: 128 } });
    expect(result.current.detail).toContain("10");
    expect(result.current.detail).toContain("128");
  });
});
