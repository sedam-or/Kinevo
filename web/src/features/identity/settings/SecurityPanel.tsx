import { PasswordForm } from "./PasswordForm";
import { SessionsPanel } from "./SessionsPanel";
import { TotpPanel } from "./TotpPanel";

export function SecurityPanel() {
  return (
    <div className="grid gap-8">
      <PasswordForm />
      <TotpPanel />
      <SessionsPanel />
    </div>
  );
}
