import { Button } from "../../components/Button";
import { TextField } from "../../components/TextField";
import { useT } from "../../i18n";

export function TokenStep({
  value,
  error,
  isLoading,
  onChange,
  onSubmit,
}: {
  value: string;
  error?: string;
  isLoading: boolean;
  onChange: (value: string) => void;
  onSubmit: () => void;
}) {
  const t = useT();
  return (
    <section className="grid gap-4" aria-labelledby="setup-token-heading">
      <h2 id="setup-token-heading" className="font-display text-title-2">
        {t("setup.token.heading")}
      </h2>
      <TextField
        label={t("setup.token.label")}
        type="password"
        autoComplete="off"
        value={value}
        onChange={onChange}
        error={error}
      />
      <Button onPress={onSubmit} isLoading={isLoading}>
        {t("setup.action.continue")}
      </Button>
    </section>
  );
}
