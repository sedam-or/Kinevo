import { useId } from "react";
import {
  Button as AriaButton,
  NumberField as AriaNumberField,
  type NumberFieldProps as AriaNumberFieldProps,
  Group,
  Input,
} from "react-aria-components";
import { FieldRow } from "./form/FieldRow";

type NumberFieldProps = Omit<AriaNumberFieldProps, "children" | "className"> & {
  label: string;
  description?: string;
  error?: string;
};

export function NumberField({
  label,
  description,
  error,
  minValue,
  maxValue,
  ...props
}: NumberFieldProps) {
  const id = useId();
  return (
    <AriaNumberField {...props} aria-label={label} isInvalid={Boolean(error)} className="min-w-0">
      <FieldRow label={label} controlId={id} description={description} error={error}>
        {(describedBy) => (
          <Group
            data-a11y-target="standard"
            className="flex min-h-11 items-stretch overflow-hidden rounded-md border border-rule-strong bg-sunken shadow-inset-field focus-within:outline-2 focus-within:outline-focus-ring"
          >
            <AriaButton
              aria-label={`Decrease ${label}`}
              data-a11y-target="standard"
              slot="decrement"
              className="min-w-11 border-r border-rule px-3 text-ink hover:bg-surface disabled:text-ink-muted"
            >
              −
            </AriaButton>
            <Input
              id={id}
              role="spinbutton"
              {...(minValue !== undefined ? { min: minValue, "aria-valuemin": minValue } : {})}
              {...(maxValue !== undefined ? { max: maxValue, "aria-valuemax": maxValue } : {})}
              {...(describedBy ? { "aria-describedby": describedBy } : {})}
              className="min-w-0 flex-1 bg-transparent px-3 text-center text-base text-ink"
            />
            <AriaButton
              aria-label={`Increase ${label}`}
              data-a11y-target="standard"
              slot="increment"
              className="min-w-11 border-l border-rule px-3 text-ink hover:bg-surface disabled:text-ink-muted"
            >
              +
            </AriaButton>
          </Group>
        )}
      </FieldRow>
    </AriaNumberField>
  );
}
