import { useId, useState } from "react";
import {
  TextArea as AriaTextArea,
  TextField as AriaTextField,
  type TextFieldProps as AriaTextFieldProps,
} from "react-aria-components";
import { FieldRow } from "./form/FieldRow";

type TextAreaProps = Omit<
  AriaTextFieldProps,
  "children" | "className" | "defaultValue" | "onChange" | "value"
> & {
  label: string;
  description?: string | undefined;
  error?: string | undefined;
  maxLength?: number | undefined;
  value?: string | undefined;
  defaultValue?: string | undefined;
  onChange?: (value: string) => void;
};

export function TextArea({
  label,
  description,
  error,
  maxLength,
  value,
  defaultValue,
  onChange,
  isDisabled,
  ...props
}: TextAreaProps) {
  const id = useId();
  const [draft, setDraft] = useState(value ?? defaultValue ?? "");
  const current = value ?? draft;

  return (
    <AriaTextField
      {...props}
      aria-label={label}
      {...(isDisabled !== undefined ? { isDisabled } : {})}
      isInvalid={Boolean(error)}
      {...(value !== undefined ? { value } : {})}
      {...(defaultValue !== undefined ? { defaultValue } : {})}
      onChange={(next) => {
        setDraft(next);
        onChange?.(next);
      }}
    >
      <FieldRow
        label={label}
        controlId={id}
        description={description}
        error={error}
        counter={
          maxLength ? { current: Array.from(current).length, maximum: maxLength } : undefined
        }
      >
        {(describedBy) => (
          <AriaTextArea
            id={id}
            maxLength={maxLength}
            data-a11y-target="standard"
            {...(describedBy ? { "aria-describedby": describedBy } : {})}
            className="min-h-24 w-full resize-y rounded-md border border-rule-strong bg-sunken px-3 py-2 text-base text-ink shadow-inset-field outline-focus-ring placeholder:text-ink-subtle focus-visible:outline-2 focus-visible:outline-offset-2 data-[invalid]:border-danger disabled:cursor-not-allowed disabled:text-ink-muted"
          />
        )}
      </FieldRow>
    </AriaTextField>
  );
}
