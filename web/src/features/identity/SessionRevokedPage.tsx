import { useT } from "../../i18n";

export function SessionRevokedPage() {
  const t = useT();
  return (
    <main className="mx-auto grid min-h-full max-w-lg content-center gap-4 p-6 text-ink">
      <h1 className="font-display text-title-2">{t("auth.form.title.revoked")}</h1>
      <p>{t("state.session.revoked")}</p>
      <a className="inline-flex min-h-11 items-center underline" href="/login">
        {t("auth.form.action.sign_in")}
      </a>
    </main>
  );
}
