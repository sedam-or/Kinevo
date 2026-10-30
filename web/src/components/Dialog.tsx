import type { ReactNode } from "react";
import {
  Dialog as AriaDialog,
  DialogTrigger as AriaDialogTrigger,
  Heading,
  Modal,
  ModalOverlay,
} from "react-aria-components";
import { useT } from "../i18n";
import { Button } from "./Button";

export type DialogProps = {
  trigger: ReactNode;
  title: string;
  description: string;
  children: ReactNode;
  isDismissable?: boolean;
  panelClassName?: string;
};

export function Dialog({
  trigger,
  title,
  description,
  children,
  isDismissable = true,
  panelClassName,
}: DialogProps) {
  const t = useT();
  return (
    <AriaDialogTrigger>
      {trigger}
      <ModalOverlay
        isDismissable={isDismissable}
        isKeyboardDismissDisabled={!isDismissable}
        className="fixed inset-0 z-50 grid place-items-center bg-ink/40 p-4 data-[entering]:opacity-0 data-[exiting]:opacity-0 motion-safe:transition-opacity motion-safe:duration-fast"
      >
        <Modal
          className={`max-h-[calc(100dvh-2rem)] w-full ${panelClassName ? "" : "max-w-xl"} overflow-auto rounded-lg border border-rule bg-surface p-6 text-ink shadow-overlay focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus-ring ${panelClassName ?? ""}`}
        >
          <AriaDialog className="grid gap-4" aria-describedby="dialog-description">
            <div className="flex items-start justify-between gap-4">
              <div className="grid gap-1">
                <Heading slot="title" className="font-display text-title-2">
                  {title}
                </Heading>
                <p id="dialog-description" className="text-sm text-ink-muted">
                  {description}
                </p>
              </div>
              {isDismissable && (
                <Button
                  slot="close"
                  variant="quiet"
                  aria-label={t("action.close_dialog")}
                  className="px-3"
                >
                  <span aria-hidden="true">×</span>
                </Button>
              )}
            </div>
            <div>{children}</div>
          </AriaDialog>
        </Modal>
      </ModalOverlay>
    </AriaDialogTrigger>
  );
}
