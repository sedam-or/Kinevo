import {
  type AnyRoute,
  createRootRoute,
  createRoute,
  createRouter,
  Outlet,
  redirect,
  useRouterState,
} from "@tanstack/react-router";
import { Suspense } from "react";
import { authManager } from "../auth/AuthManager";
import { isPublicAuthPath, redirectIfAnonymous } from "../auth/guards";
import { LoginPage } from "../features/identity/LoginPage";
import { RegisterPage } from "../features/identity/RegisterPage";
import { ResetPage } from "../features/identity/ResetPage";
import { SessionRevokedPage } from "../features/identity/SessionRevokedPage";
import { SetupPage } from "../features/setup/SetupPage";
import { modulePageRoutes, RegisteredModulePage } from "../features/shell/routes";
import { useModules } from "../features/shell/useModules";
import { TodayPage } from "../features/today/TodayPage";
import { useT } from "../i18n";
import { Shell } from "./Shell";

const rootRoute = createRootRoute({
  beforeLoad: async ({ location }) => {
    if (isPublicAuthPath(location.pathname)) {
      await authManager.restore();
      return;
    }
    const target = await redirectIfAnonymous(authManager, location.href);
    if (target) throw redirect({ href: target });
  },
  component: RootLayout,
});

const indexRoute = createRoute({
  getParentRoute: () => rootRoute,
  path: "/",
  beforeLoad: () => {
    throw redirect({ href: "/today" });
  },
  component: Outlet,
});

const todayRoute = createRoute({
  getParentRoute: () => rootRoute,
  path: "/today",
  component: TodayPage,
});

const loginRoute = createRoute({
  getParentRoute: () => rootRoute,
  path: "/login",
  component: () => (
    <LoginPage
      next={new URLSearchParams(window.location.search).get("next")}
      onAuthenticated={navigateTo}
    />
  ),
});

const registerRoute = createRoute({
  getParentRoute: () => rootRoute,
  path: "/register",
  component: () => (
    <RegisterPage
      {...(new URLSearchParams(window.location.search).get("invite")
        ? { inviteToken: new URLSearchParams(window.location.search).get("invite") as string }
        : {})}
      onAuthenticated={navigateTo}
    />
  ),
});

const resetRoute = createRoute({
  getParentRoute: () => rootRoute,
  path: "/reset/$token",
  component: function ResetRoute() {
    const { token } = resetRoute.useParams();
    return <ResetPage token={token} />;
  },
});

const revokedRoute = createRoute({
  getParentRoute: () => rootRoute,
  path: "/session/revoked",
  component: SessionRevokedPage,
});

const setupRoute = createRoute({
  getParentRoute: () => rootRoute,
  path: "/setup",
  component: () => <SetupPage onComplete={navigateTo} />,
});

const moduleRoutes = modulePageRoutes.map(({ path, module, titleKey }) =>
  createRoute({
    getParentRoute: () => rootRoute,
    path: path as never,
    component: function ModuleRoute() {
      const modules = useModules();
      return (
        <Suspense fallback={<div role="status" aria-busy="true" />}>
          <RegisteredModulePage
            module={module}
            titleKey={titleKey}
            modules={modules.data?.items ?? []}
          />
        </Suspense>
      );
    },
  }),
);

const routeTree = rootRoute.addChildren([
  indexRoute,
  loginRoute,
  registerRoute,
  resetRoute,
  revokedRoute,
  setupRoute,
  todayRoute,
  ...(moduleRoutes as unknown as AnyRoute[]),
]);

export const router = createRouter({ routeTree });

function navigateTo(path: string) {
  void router.navigate({ href: path });
}

function RootLayout() {
  const pathname = useRouterState({ select: (state) => state.location.pathname });
  if (isPublicAuthPath(pathname)) {
    return <Outlet />;
  }
  return <ModuleShell />;
}

function ModuleShell() {
  const t = useT();
  const pathname = useRouterState({ select: (state) => state.location.pathname });
  const modules = useModules();
  if (modules.isPending) {
    return (
      <main id="main-content" aria-busy="true">
        {t("state.loading")}
      </main>
    );
  }
  if (modules.isError) {
    return (
      <main id="main-content" role="alert">
        {t("error.service_unavailable.title")}
      </main>
    );
  }
  return (
    <Shell
      modules={modules.data.items}
      currentPath={pathname}
      onNavigate={(path) => void router.navigate({ href: path })}
    >
      <Outlet />
    </Shell>
  );
}

declare module "@tanstack/react-router" {
  interface Register {
    router: typeof router;
  }
}
