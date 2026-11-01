import { useState } from "react";
import {
  Dialog as AriaDialog,
  DialogTrigger,
  Heading,
  Modal,
  ModalOverlay,
} from "react-aria-components";
import { api } from "../../../api/client";
import { Button } from "../../../components/Button";
import { TextField } from "../../../components/TextField";
import { useT } from "../../../i18n";

const deletePhrase = "delete my account";

export function DeleteDialog({ onDeleted }: { onDeleted: () => void }) {
  const t = useT();
  const [isOpen, setIsOpen] = useState(false);
  const [password, setPassword] = useState("");
  const [phrase, setPhrase] = useState("");
  const [error, setError] = useState("");
  const [busy, setBusy] = useState(false);

  function clearFields() {
    setPassword("");
    setPhrase("");
    setError("");
  }

  function changeOpen(open: boolean) {
    setIsOpen(open);
    if (!open) clearFields();
  }

  async function deleteAccount() {
    if (phrase !== deletePhrase || !password) return;
    setBusy(true);
    setError("");
    try {
      const result = await api.DELETE("/api/v1/account", {
        params: { header: { "Idempotency-Key": crypto.randomUUID() } },
        body: { password, confirm: deletePhrase },
      });
      if (result.error) {
        if (result.error.code === "last_admin") setError(t("data.delete.last_admin"));
        else if (result.error.code === "current_password_incorrect") {
          setError(t("error.current_password_incorrect.title"));
        } else setError(t("error.internal_error.title"));
        return;
      }
      clearFields();
      changeOpen(false);
      onDeleted();
    } catch {
      setError(t("error.internal_error.title"));
    } finally {
      setBusy(false);
    }
  }

  return (
    <section aria-labelledby="delete-heading" className="grid gap-4">
      <h2 id="delete-heading" className="font-display text-title-2">
        {t("data.delete.heading")}
      </h2>
      <p>{t("data.delete.description")}</p>
      <DialogTrigger isOpen={isOpen} onOpenChange={changeOpen}>
        <Button variant="danger" onPress={() => setIsOpen(true)}>
          {t("data.delete.button")}
        </Button>
        <ModalOverlay
          isDismissable
          className="fixed inset-0 z-50 grid place-items-center bg-ink/40 p-4"
        >
          <Modal className="w-full max-w-lg rounded-lg border border-rule bg-surface p-6 text-ink shadow-overlay">
            <AriaDialog
              role="alertdialog"
              aria-labelledby="delete-account-heading"
              aria-describedby="delete-account-description"
              className="grid gap-4"
            >
              <Heading
                id="delete-account-heading"
                slot="title"
                className="font-display text-title-2"
              >
                {t("data.delete.button")}
              </Heading>
              <p id="delete-account-description">
                {t("data.delete.confirm", { phrase: deletePhrase })}
              </p>
              <TextField
                label={t("settings.password.current")}
                type="password"
                autoComplete="current-password"
                value={password}
                onChange={setPassword}
              />
              <TextField
                label={t("data.delete.phrase_label", { phrase: deletePhrase })}
                value={phrase}
                onChange={setPhrase}
              />
              {error && <p role="alert">{error}</p>}
              <div className="flex justify-end gap-2">
                <Button variant="secondary" autoFocus onPress={() => changeOpen(false)}>
                  {t("auth.form.action.cancel")}
                </Button>
                <Button
                  variant="danger"
                  isLoading={busy}
                  isDisabled={phrase !== deletePhrase || !password}
                  onPress={() => void deleteAccount()}
                >
                  {t("data.delete.button")}
                </Button>
              </div>
            </AriaDialog>
          </Modal>
        </ModalOverlay>
      </DialogTrigger>
    </section>
  );
}
