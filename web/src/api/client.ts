import createClient from "openapi-fetch";
import { authManager } from "../auth/AuthManager";
import type { paths } from "./schema";

export const api = createClient<paths>({
  baseUrl: new URL("/", typeof window === "undefined" ? "http://localhost" : window.location.href)
    .href,
  fetch: (request) => authManager.fetch(request),
});
