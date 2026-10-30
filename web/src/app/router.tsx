import { createRootRoute, createRoute, createRouter, Outlet } from "@tanstack/react-router";
import { useT } from "../i18n";

const rootRoute = createRootRoute({
  component: Outlet,
});

const indexRoute = createRoute({
  getParentRoute: () => rootRoute,
  path: "/",
  component: function HomePage() {
    const t = useT();
    return (
      <main>
        <h1>{t("app.name")}</h1>
      </main>
    );
  },
});

const routeTree = rootRoute.addChildren([indexRoute]);

export const router = createRouter({ routeTree });

declare module "@tanstack/react-router" {
  interface Register {
    router: typeof router;
  }
}
