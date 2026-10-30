import { type ReactNode, useState } from "react";
import {
  Dialog as AriaDialog,
  DialogTrigger,
  Heading,
  Modal,
  ModalOverlay,
} from "react-aria-components";
import { useAuth } from "../auth/AuthProvider";
import { Button } from "../components/Button";
import { ErrorBoundary } from "../components/ErrorBoundary";
import { Chords } from "../features/shell/Chords";
import { MoreSheet } from "../features/shell/MoreSheet";
import { Nav, navigationFromModules } from "../features/shell/Nav";
import type { ModuleItem } from "../features/shell/useModules";
import { useT } from "../i18n";

export function Shell({
  modules,
  currentPath,
  onNavigate,
  children,
}: {
  modules: ModuleItem[];
  currentPath: string;
  onNavigate?: (path: string) => void;
  children: ReactNode;
}) {
  const t = useT();
  const { manager } = useAuth();
  const [helpOpen, setHelpOpen] = useState(false);
  const navigate = onNavigate ?? ((path: string) => window.location.assign(path));
  const entries = navigationFromModules(modules);
  const mobileEntries = pickMobileEntries(entries);
  const mobileKeys = new Set(mobileEntries.map((entry) => entry.key));
  const moreEntries = entries.filter(
    (entry) => entry.group === "settings" || entry.group === "admin" || !mobileKeys.has(entry.key),
  );

  return (
    <Chords onNavigate={navigate} onHelp={() => setHelpOpen(true)}>
      <a className="skip-link" href="#main-content">
        {t("shell.skip_to_main")}
      </a>
      <div className="min-h-screen overflow-x-clip text-ink">
        <header className="flex min-h-14 items-center justify-between border-b border-rule bg-surface px-4 md:pl-64">
          <a href="/today" className="font-display font-semibold text-ink no-underline">
            {t("app.name")}
          </a>
          <div className="flex items-center gap-2">
            <details className="relative">
              <summary className="flex min-h-11 cursor-pointer list-none items-center rounded-md px-3 py-2 text-ink hover:bg-sunken">
                {t("nav.account")}
              </summary>
              <div className="absolute right-0 top-full z-30 grid min-w-48 gap-2 rounded-md border border-rule bg-surface p-2 shadow-overlay">
                <a
                  className="min-h-11 rounded-md px-3 py-2 text-ink hover:bg-sunken"
                  href="/settings/profile"
                  onClick={(event) => {
                    if (event.button === 0 && !event.metaKey && !event.ctrlKey && onNavigate) {
                      event.preventDefault();
                      onNavigate("/settings/profile");
                    }
                  }}
                >
                  {t("settings.profile")}
                </a>
                <Button
                  variant="quiet"
                  onPress={() => {
                    void manager
                      .logout()
                      .catch(() => undefined)
                      .then(() => navigate("/login"));
                  }}
                >
                  {t("auth.form.action.sign_out")}
                </Button>
              </div>
            </details>
            <DialogTrigger isOpen={helpOpen} onOpenChange={setHelpOpen}>
              <Button
                variant="quiet"
                aria-label={t("nav.help_shortcuts")}
                className="min-h-11 px-3"
              >
                {t("nav.help_shortcuts")}
              </Button>
              <ModalOverlay
                isDismissable
                className="fixed inset-0 z-50 grid place-items-center bg-ink/40 p-4"
              >
                <Modal className="w-full max-w-lg rounded-lg border border-rule bg-surface p-6 text-ink shadow-overlay">
                  <AriaDialog aria-labelledby="shortcut-heading" className="grid gap-4">
                    <Heading
                      id="shortcut-heading"
                      slot="title"
                      className="font-display text-title-2"
                    >
                      {t("nav.help_shortcuts")}
                    </Heading>
                    <ul className="grid gap-2">
                      <li>{t("nav.shortcut.today")}</li>
                      <li>{t("nav.shortcut.help")}</li>
                    </ul>
                    <Button variant="secondary" onPress={() => setHelpOpen(false)}>
                      {t("action.close_dialog")}
                    </Button>
                  </AriaDialog>
                </Modal>
              </ModalOverlay>
            </DialogTrigger>
          </div>
        </header>
        <aside className="fixed inset-y-14 left-0 hidden w-60 overflow-y-auto border-r border-rule bg-surface p-3 md:block">
          <Nav
            entries={entries}
            currentPath={currentPath}
            className="grid gap-5"
            onNavigate={navigate}
          />
        </aside>
        <main
          id="main-content"
          className="mx-auto grid w-full max-w-7xl gap-6 p-4 pb-24 md:ml-60 md:w-[calc(100%-15rem)] md:p-6"
        >
          <ErrorBoundary>{children}</ErrorBoundary>
        </main>
        <div className="fixed inset-x-0 bottom-0 z-20 flex items-center justify-between gap-2 border-t border-rule bg-surface px-2 py-1 md:hidden">
          <Nav
            entries={mobileEntries}
            currentPath={currentPath}
            groups={["now", "plan", "think", "reflect"]}
            className="flex min-w-0 flex-1 items-center justify-around gap-1"
            onNavigate={navigate}
          />
          <MoreSheet entries={moreEntries} onNavigate={navigate} />
        </div>
      </div>
    </Chords>
  );
}

function pickMobileEntries(entries: ReturnType<typeof navigationFromModules>) {
  const primary = entries.find((entry) => entry.key === "today");
  const groups = ["plan", "think", "reflect"] as const;
  return [primary, ...groups.map((group) => entries.find((entry) => entry.group === group))].filter(
    (entry): entry is NonNullable<typeof entry> => entry !== undefined,
  );
}
