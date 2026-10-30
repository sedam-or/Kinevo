import { useId } from "react";
import {
  Select as AriaSelect,
  Button,
  ListBox,
  ListBoxItem,
  Popover,
  SelectValue,
} from "react-aria-components";
import { FieldRow } from "./form/FieldRow";

export type SelectItem = { id: string; label: string };

type SelectProps = {
  label: string;
  items: SelectItem[];
  selectedKey?: string | null;
  defaultSelectedKey?: string;
  onSelectionChange?: (key: string | null) => void;
  description?: string;
  error?: string;
  isDisabled?: boolean;
};

export function Select({
  label,
  items,
  selectedKey,
  defaultSelectedKey,
  onSelectionChange,
  description,
  error,
  isDisabled,
}: SelectProps) {
  const id = useId();
  return (
    <AriaSelect
      aria-label={label}
      {...(isDisabled !== undefined ? { isDisabled } : {})}
      isInvalid={Boolean(error)}
      {...(selectedKey !== undefined ? { selectedKey } : {})}
      {...(defaultSelectedKey !== undefined ? { defaultSelectedKey } : {})}
      onSelectionChange={(key) => onSelectionChange?.(key === null ? null : String(key))}
    >
      <FieldRow label={label} controlId={id} description={description} error={error}>
        {(describedBy) => (
          <>
            <Button
              id={id}
              aria-labelledby={`${id}-label ${id}-value`}
              {...(describedBy ? { "aria-describedby": describedBy } : {})}
              data-a11y-target="standard"
              className="flex min-h-11 w-full items-center justify-between rounded-md border border-rule-strong bg-sunken px-3 py-2 text-left text-base text-ink shadow-inset-field outline-focus-ring focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus-ring disabled:cursor-not-allowed disabled:text-ink-muted"
            >
              <SelectValue id={`${id}-value`} />
              <span aria-hidden="true">⌄</span>
            </Button>
            <Popover className="max-h-64 min-w-[var(--trigger-width)] overflow-auto rounded-md border border-rule bg-surface p-1 text-ink shadow-overlay">
              <ListBox items={items} className="outline-none">
                {(item) => (
                  <ListBoxItem
                    id={item.id}
                    textValue={item.label}
                    className="min-h-11 cursor-default rounded px-3 py-2 outline-none data-[focused]:bg-sunken data-[selected]:font-semibold"
                  >
                    {item.label}
                  </ListBoxItem>
                )}
              </ListBox>
            </Popover>
          </>
        )}
      </FieldRow>
    </AriaSelect>
  );
}
