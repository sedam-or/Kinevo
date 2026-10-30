import { HttpResponse, http } from "msw";
import type { components } from "../../api/schema";

const account: components["schemas"]["AccountProfile"] = {
  id: "00000000-0000-7000-8000-000000000049",
  email: "person@example.test",
  display_name: "Test person",
  locale: "en",
  timezone: "Etc/UTC",
  totp_enabled: false,
  is_instance_admin: false,
  version: 1,
  week_start: 1,
};

export const authOpenApiFixtures = {
  login: {
    access_token: "access_token_for_tests_only",
    account,
    expires_in: 600,
    mfa_enrol_required: false,
  } satisfies components["schemas"]["LoginResponse"],
  refresh: {
    access_token: "refreshed_access_token_for_tests_only",
    expires_in: 600,
  } satisfies components["schemas"]["RefreshResponse"],
  invalidCredentials: {
    type: "about:blank",
    title: "Email or password is incorrect.",
    status: 401,
    detail: "Email or password is incorrect.",
    code: "invalid_credentials",
    request_id: "00000000-0000-7000-8000-000000000001",
  } satisfies components["schemas"]["Problem"],
  mfaRequired: {
    type: "about:blank",
    title: "Additional verification required.",
    status: 401,
    detail: "Additional verification required.",
    code: "mfa_required",
    request_id: "00000000-0000-7000-8000-000000000002",
    details: { methods: ["totp", "recovery_code"] },
  } satisfies components["schemas"]["Problem"],
  invalidMfaCode: {
    type: "about:blank",
    title: "The code is invalid.",
    status: 401,
    detail: "The code is invalid.",
    code: "invalid_mfa_code",
    request_id: "00000000-0000-7000-8000-000000000003",
  } satisfies components["schemas"]["Problem"],
  registrationClosed: {
    type: "about:blank",
    title: "Registration is closed.",
    status: 403,
    detail: "Registration is closed.",
    code: "registration_closed",
    request_id: "00000000-0000-7000-8000-000000000004",
  } satisfies components["schemas"]["Problem"],
};

export const authHandlers = {
  login: http.post("/api/v1/auth/login", () => HttpResponse.json(authOpenApiFixtures.login)),
  refresh: http.post("/api/v1/auth/refresh", () => HttpResponse.json(authOpenApiFixtures.refresh)),
  invalidCredentials: http.post("/api/v1/auth/login", () =>
    HttpResponse.json(authOpenApiFixtures.invalidCredentials, { status: 401 }),
  ),
  registrationClosed: http.post("/api/v1/auth/register", () =>
    HttpResponse.json(authOpenApiFixtures.registrationClosed, { status: 403 }),
  ),
  resetRequested: http.post(
    "/api/v1/auth/password-reset/request",
    () => new HttpResponse(null, { status: 202 }),
  ),
  resetConfirmed: http.post(
    "/api/v1/auth/password-reset/confirm",
    () => new HttpResponse(null, { status: 204 }),
  ),
};
