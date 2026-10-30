import { lazy } from "react";
import type { MessageKey } from "../../i18n";

export const RegisteredModulePage = lazy(() =>
  import("./ModuleGate").then(({ RegisteredModulePage: ModulePage }) => ({ default: ModulePage })),
);

export const moduleRouteRegistry = {
  identity: ["/admin/accounts", "/settings/profile", "/settings/security", "/settings/data"],
  admin: ["/admin/modules", "/admin/policies", "/admin/audit", "/admin/jobs", "/admin/secrets"],
  canvas: ["/think/canvas"],
} as const satisfies Record<string, readonly string[]>;

export const modulePageRoutes = [
  { path: "/admin/accounts", module: "identity", titleKey: "nav.admin.accounts" },
  { path: "/settings/profile", module: "identity", titleKey: "settings.profile" },
  { path: "/settings/security", module: "identity", titleKey: "settings.security" },
  { path: "/settings/data", module: "identity", titleKey: "settings.data" },
  { path: "/admin/modules", module: "admin", titleKey: "nav.admin.modules" },
  { path: "/admin/policies", module: "admin", titleKey: "nav.admin.policies" },
  { path: "/admin/audit", module: "admin", titleKey: "nav.admin.audit" },
  { path: "/admin/jobs", module: "admin", titleKey: "nav.admin.jobs" },
  { path: "/admin/secrets", module: "admin", titleKey: "nav.admin.secrets" },
  { path: "/think/canvas", module: "canvas", titleKey: "module.canvas.name" },
] as const satisfies readonly {
  path: string;
  module: RegisteredModuleKey;
  titleKey: MessageKey;
}[];

export type RegisteredModuleKey = keyof typeof moduleRouteRegistry;

export function registeredModuleKey(key: string): key is RegisteredModuleKey {
  return Object.hasOwn(moduleRouteRegistry, key);
}

export function isRegisteredModuleRoute(module: string, route: string): boolean {
  if (!registeredModuleKey(module)) return false;
  const routes: readonly string[] = moduleRouteRegistry[module];
  return routes.includes(route);
}
