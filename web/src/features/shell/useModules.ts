import { useQuery } from "@tanstack/react-query";
import { api } from "../../api/client";

export type ModuleNavItem = {
  key: string;
  label_key: string;
  route: string;
  order: number;
};

export type ModuleSettingsPanel = {
  key: string;
  label_key: string;
};

export type ModuleItem = {
  key: string;
  kind: "core" | "toggleable";
  enabled: boolean;
  version: number;
  nav: ModuleNavItem[];
  settings_panels: ModuleSettingsPanel[];
};

export type ModuleList = { items: ModuleItem[] };

export function useModules() {
  return useQuery({
    queryKey: ["modules"],
    queryFn: async (): Promise<ModuleList> => {
      const result = await api.GET("/api/v1/modules");
      if (result.error) {
        const code =
          typeof result.error === "object" &&
          result.error !== null &&
          "code" in result.error &&
          typeof result.error.code === "string"
            ? result.error.code
            : "modules_unavailable";
        throw new Error(code);
      }
      return parseModuleList(result.data);
    },
  });
}

function parseModuleList(value: unknown): ModuleList {
  if (!isRecord(value) || !Array.isArray(value.items)) return { items: [] };
  return { items: value.items.flatMap(parseModuleItem) };
}

function parseModuleItem(value: unknown): ModuleItem[] {
  if (
    !isRecord(value) ||
    typeof value.key !== "string" ||
    (value.kind !== "core" && value.kind !== "toggleable") ||
    typeof value.enabled !== "boolean" ||
    typeof value.version !== "number"
  ) {
    return [];
  }

  return [
    {
      key: value.key,
      kind: value.kind,
      enabled: value.enabled,
      version: value.version,
      nav: Array.isArray(value.nav) ? value.nav.flatMap(parseNavItem) : [],
      settings_panels: Array.isArray(value.settings_panels)
        ? value.settings_panels.flatMap(parseSettingsPanel)
        : [],
    },
  ];
}

function parseNavItem(value: unknown): ModuleNavItem[] {
  if (
    !isRecord(value) ||
    typeof value.key !== "string" ||
    typeof value.label_key !== "string" ||
    typeof value.route !== "string" ||
    typeof value.order !== "number"
  ) {
    return [];
  }
  return [{ key: value.key, label_key: value.label_key, route: value.route, order: value.order }];
}

function parseSettingsPanel(value: unknown): ModuleSettingsPanel[] {
  if (!isRecord(value) || typeof value.key !== "string" || typeof value.label_key !== "string") {
    return [];
  }
  return [{ key: value.key, label_key: value.label_key }];
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === "object" && value !== null && !Array.isArray(value);
}
