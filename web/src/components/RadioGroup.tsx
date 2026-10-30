import { useId } from "react";
import {
  Radio as AriaRadio,
  RadioGroup as AriaRadioGroup,
  type RadioGroupProps as AriaRadioGroupProps,
} from "react-aria-components";
import { FieldRow } from "./form/FieldRow";

type RadioOption = { value: string; label: string };
type RadioGroupProps = Omit<AriaRadioGroupProps, "children" | "className"> & {
  label: string;
  options: RadioOption[];
  description?: string;
  error?: string;
};

export function RadioGroup({ label, options, description, error, ...props }: RadioGroupProps) {
  const id = useId();
  return (
    <AriaRadioGroup
      {...props}
      id={id}
      aria-labelledby={`${id}-label`}
      {...([description ? `${id}-hint` : undefined, error ? `${id}-error` : undefined]
        .filter(Boolean)
        .join(" ")
        ? {
            "aria-describedby": [
              description ? `${id}-hint` : undefined,
              error ? `${id}-error` : undefined,
            ]
              .filter(Boolean)
              .join(" "),
          }
        : {})}
      isInvalid={Boolean(error)}
      className="grid gap-2"
    >
      <FieldRow label={label} controlId={id} description={description} error={error}>
        {(describedBy) => (
          <div aria-describedby={describedBy} className="grid gap-1">
            {options.map((option) => (
              <AriaRadio
                key={option.value}
                value={option.value}
                data-a11y-target="standard"
                className="group inline-flex min-h-11 min-w-11 items-center gap-3 text-ink"
              >
                <span className="inline-flex size-6 shrink-0 items-center justify-center rounded-full border border-rule-strong bg-surface group-data-[selected]:border-accent">
                  <span className="size-3 rounded-full bg-accent opacity-0 group-data-[selected]:opacity-100" />
                </span>
                <span>{option.label}</span>
              </AriaRadio>
            ))}
          </div>
        )}
      </FieldRow>
    </AriaRadioGroup>
  );
}
