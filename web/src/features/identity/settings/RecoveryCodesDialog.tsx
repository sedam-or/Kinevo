import { useState } from "react";
import { Dialog as AriaDialog, Heading, Modal, ModalOverlay } from "react-aria-components";
import { Button } from "../../../components/Button";
import { Checkbox } from "../../../components/Checkbox";
import { useT } from "../../../i18n";

export function RecoveryCodesDialog({ codes, onClose }: { codes: string[]; onClose: () => void }) {
  const t = useT();
  const [saved, setSaved] = useState(false);
  const [copyStatus, setCopyStatus] = useState("");

  async function copyCodes() {
    try {
      await navigator.clipboard.writeText(codes.join("\n"));
      setCopyStatus(t("settings.recovery.copied"));
    } catch {
      setCopyStatus(t("action.copy_unavailable"));
    }
  }

  function downloadCodes() {
    const blob = new Blob([codes.join("\n")], { type: "text/plain;charset=utf-8" });
    const href = URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.href = href;
    link.download = "kinevo-recovery-codes.txt";
    link.click();
    URL.revokeObjectURL(href);
  }

  return (
    <ModalOverlay
      isOpen
      isDismissable={false}
      isKeyboardDismissDisabled
      className="fixed inset-0 z-50 grid place-items-center bg-ink/40 p-4"
    >
      <Modal className="max-h-[calc(100dvh-2rem)] w-full max-w-xl overflow-auto rounded-lg border border-rule bg-surface p-6 text-ink shadow-overlay">
        <AriaDialog
          aria-labelledby="recovery-codes-heading"
          aria-describedby="recovery-codes-description"
          className="grid gap-4"
        >
          <Heading id="recovery-codes-heading" slot="title" className="font-display text-title-2">
            {t("settings.recovery.heading")}
          </Heading>
          <p id="recovery-codes-description" className="text-sm text-ink-muted">
            {t("auth.totp.recovery_codes")}
          </p>
          <ol className="grid grid-cols-1 gap-2 rounded-md border border-rule bg-sunken p-4 font-mono sm:grid-cols-2">
            {codes.map((code) => (
              <li key={code}>{code}</li>
            ))}
          </ol>
          <div className="flex flex-wrap gap-2">
            <Button variant="secondary" onPress={() => void copyCodes()}>
              {t("settings.recovery.copy")}
            </Button>
            <Button variant="secondary" onPress={downloadCodes}>
              {t("settings.recovery.download")}
            </Button>
          </div>
          {copyStatus && <p role="status">{copyStatus}</p>}
          <Checkbox
            label={t("settings.recovery.saved_checkbox")}
            isSelected={saved}
            onChange={setSaved}
          />
          <div className="flex justify-end">
            <Button isDisabled={!saved} onPress={onClose}>
              {t("settings.recovery.done")}
            </Button>
          </div>
        </AriaDialog>
      </Modal>
    </ModalOverlay>
  );
}
