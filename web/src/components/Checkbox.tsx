import {
  Checkbox as AriaCheckbox,
  type CheckboxProps as AriaCheckboxProps,
} from "react-aria-components";

type CheckboxProps = Omit<AriaCheckboxProps, "children"> & { label: string };

export function Checkbox({ label, ...props }: CheckboxProps) {
  return (
    <AriaCheckbox
      {...props}
      data-a11y-target="standard"
      className="group inline-flex min-h-11 min-w-11 items-center gap-3 text-ink"
    >
      <span className="inline-flex size-6 shrink-0 items-center justify-center rounded border border-rule-strong bg-surface text-accent-ink group-data-[selected]:border-accent group-data-[selected]:bg-accent">
        <svg
          aria-hidden="true"
          viewBox="0 0 16 16"
          className="size-4 opacity-0 group-data-[selected]:opacity-100"
          fill="none"
        >
          <path d="m3 8 3.2 3.2L13 4.5" stroke="currentColor" strokeWidth="2" />
        </svg>
      </span>
      <span>{label}</span>
    </AriaCheckbox>
  );
}
