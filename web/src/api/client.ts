import createClient from "openapi-fetch";
import { authManager } from "../auth/AuthManager";
import type { paths } from "./schema";

export const api = createClient<paths>({
  baseUrl: "/",
  fetch: (request) => authManager.fetch(request),
});
