import {
  createRootRoute,
  createRoute,
  createRouter,
  Outlet,
  redirect,
} from "@tanstack/react-router";
import { authManager } from "../auth/AuthManager";
import { isPublicAuthPath, redirectIfAnonymous } from "../auth/guards";
import { LoginPage } from "../features/identity/LoginPage";
import { RegisterPage } from "../features/identity/RegisterPage";
import { ResetPage } from "../features/identity/ResetPage";
import { SessionRevokedPage } from "../features/identity/SessionRevokedPage";
import { useT } from "../i18n";

const rootRoute = createRootRoute({
  beforeLoad: async ({ location }) => {
    if (isPublicAuthPath(location.pathname)) {
      await authManager.restore();
      return;
    }
    const target = await redirectIfAnonymous(authManager, location.href);
    if (target) throw redirect({ href: target });
  },
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

const routeTree = rootRoute.addChildren([
  indexRoute,
  loginRoute,
  registerRoute,
  resetRoute,
  revokedRoute,
]);

export const router = createRouter({ routeTree });

function navigateTo(path: string) {
  void router.navigate({ href: path });
}

declare module "@tanstack/react-router" {
  interface Register {
    router: typeof router;
  }
}
