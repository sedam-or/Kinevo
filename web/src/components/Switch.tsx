import { useState } from "react";
import { Switch as AriaSwitch, type SwitchProps as AriaSwitchProps } from "react-aria-components";

type SwitchProps = Omit<AriaSwitchProps, "children"> & {
  label: string;
  onLabel?: string;
  offLabel?: string;
};

export function Switch({
  label,
  onLabel = "On",
  offLabel = "Off",
  isSelected,
  defaultSelected,
  onChange,
  ...props
}: SwitchProps) {
  const [draftSelected, setDraftSelected] = useState(defaultSelected ?? false);
  const selected = isSelected ?? draftSelected;
  return (
    <AriaSwitch
      {...props}
      aria-label={label}
      isSelected={selected}
      onChange={(next) => {
        if (isSelected === undefined) setDraftSelected(next);
        onChange?.(next);
      }}
      data-a11y-target="standard"
      className="group inline-flex min-h-11 min-w-11 items-center gap-3 text-ink"
    >
      <span className="relative inline-flex h-6 w-11 shrink-0 items-center rounded-full bg-rule-strong p-0.5 transition-colors group-data-[selected]:bg-accent">
        <span className="size-5 rounded-full bg-surface shadow-rest transition-transform group-data-[selected]:translate-x-5" />
      </span>
      <span>{label}</span>
      <span className="text-sm text-ink-muted" aria-hidden="true">
        {isSelected ? onLabel : offLabel}
      </span>
    </AriaSwitch>
  );
}
