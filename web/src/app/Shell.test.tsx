import { fireEvent, render, screen } from "@testing-library/react";
import { describe, expect, it, vi } from "vitest";
import { ErrorBoundary } from "../components/ErrorBoundary";
import { Chords } from "../features/shell/Chords";
import { ModuleGate } from "../features/shell/ModuleGate";
import type { ModuleItem } from "../features/shell/useModules";
import { Shell } from "./Shell";

const identity: ModuleItem = {
  key: "identity",
  kind: "core",
  enabled: true,
  version: 1,
  nav: [{ key: "accounts", label_key: "nav.admin.accounts", route: "/admin/accounts", order: 1 }],
  settings_panels: [],
};

const disabledCanvas: ModuleItem = {
  key: "canvas",
  kind: "toggleable",
  enabled: false,
  version: 1,
  nav: [],
  settings_panels: [],
};

const adminItems = [
  ["modules", "nav.admin.modules", "admin.modules.manage"],
  ["policies", "nav.admin.policies", "admin.policies.instance.read"],
  ["audit", "nav.admin.audit", "admin.audit.read"],
  ["jobs", "nav.admin.jobs", "admin.jobs.read"],
  ["secrets", "nav.admin.secrets", "admin.secrets.manage"],
] as const;

const admin: ModuleItem = {
  key: "admin",
  kind: "core",
  enabled: true,
  version: 1,
  nav: adminItems.map(([key, label_key], order) => ({
    key,
    label_key,
    route: `/admin/${key}`,
    order,
  })),
  settings_panels: [],
};

function BrokenFeature(): never {
  throw new Error("synthetic feature failure");
}

function renderShell(modules: ModuleItem[]) {
  return render(
    <Shell modules={modules} currentPath="/today">
      <h1>Today</h1>
    </Shell>,
  );
}

describe("shell", () => {
  it("shell::mod_01_nav_only_from_response", () => {
    renderShell([identity]);

    expect(screen.getByRole("link", { name: "Accounts" })).toHaveAttribute(
      "href",
      "/admin/accounts",
    );
    expect(screen.queryByRole("link", { name: "Canvas" })).not.toBeInTheDocument();
  });

  it("shell::mod_09_unknown_module_ignored", () => {
    const unknown: ModuleItem = {
      key: "not_compiled",
      kind: "toggleable",
      enabled: true,
      version: 1,
      nav: [{ key: "surprise", label_key: "nav.surprise", route: "/surprise", order: 1 }],
      settings_panels: [],
    };

    expect(() => renderShell([identity, unknown])).not.toThrow();
    expect(screen.queryByRole("link", { name: "nav.surprise" })).not.toBeInTheDocument();
  });

  it("shell::fe_01_disabled_route_shows_state", () => {
    render(
      <ModuleGate module="canvas" modules={[disabledCanvas]}>
        <p>Canvas content</p>
      </ModuleGate>,
    );

    expect(screen.getByText("Canvas is turned off on this server.")).toBeInTheDocument();
    expect(screen.getByRole("link", { name: "Today" })).toHaveAttribute("href", "/today");
    expect(screen.queryByText("Canvas content")).not.toBeInTheDocument();
  });

  it("shell::fe_01_forbidden_route_shows_state", () => {
    render(
      <ModuleGate module="identity" modules={[identity]} forbidden>
        <p>Protected content</p>
      </ModuleGate>,
    );

    expect(
      screen.getByRole("heading", { name: "You don't have access to this." }),
    ).toBeInTheDocument();
    expect(screen.getByRole("link", { name: "Today" })).toHaveAttribute("href", "/today");
    expect(screen.queryByText("Protected content")).not.toBeInTheDocument();
  });

  it("shell::admin_items_follow_permissions", () => {
    const ownerIdentity: ModuleItem = { ...identity, nav: [] };
    const ownerAdmin: ModuleItem = {
      ...admin,
      nav: admin.nav.filter((item) => item.key === "policies"),
    };
    const { rerender } = renderShell([ownerIdentity, ownerAdmin]);

    expect(screen.getByRole("link", { name: "Policies" })).toBeInTheDocument();
    expect(screen.queryByRole("link", { name: "Modules" })).not.toBeInTheDocument();
    expect(screen.queryByRole("link", { name: "Accounts" })).not.toBeInTheDocument();

    rerender(
      <Shell modules={[identity, admin]} currentPath="/today">
        <h1>Today</h1>
      </Shell>,
    );
    for (const [, labelKey] of adminItems) {
      const label = labelKey.slice(labelKey.lastIndexOf(".") + 1);
      expect(
        screen.getByRole("link", { name: label[0]?.toUpperCase() + label.slice(1) }),
      ).toBeInTheDocument();
    }
    expect(screen.getByRole("link", { name: "Accounts" })).toBeInTheDocument();
  });

  it("shell::fe_11_chords_not_in_inputs", () => {
    const navigate = vi.fn();
    render(
      <Chords onNavigate={navigate}>
        <input aria-label="Search" />
        <p>Page</p>
      </Chords>,
    );

    fireEvent.keyDown(document, { key: "g" });
    fireEvent.keyDown(document, { key: "t" });
    expect(navigate).toHaveBeenCalledWith("/today");

    navigate.mockClear();
    const input = screen.getByRole("textbox", { name: "Search" });
    fireEvent.keyDown(input, { key: "g" });
    fireEvent.keyDown(input, { key: "t" });
    expect(navigate).not.toHaveBeenCalled();
  });

  it("shell::fe_12_feature_error_is_local", () => {
    render(
      <>
        <ErrorBoundary>
          <BrokenFeature />
        </ErrorBoundary>
        <p>Other route remains available</p>
      </>,
    );

    expect(screen.getByText("Other route remains available")).toBeInTheDocument();
    expect(screen.getByText("This section could not be loaded.")).toBeInTheDocument();
  });
});
