import type { ReactNode } from "react";

export type FieldCounter = { current: number; maximum: number };

type FieldRowProps = {
  label: string;
  controlId: string;
  description?: string | undefined;
  error?: string | undefined;
  counter?: FieldCounter | undefined;
  children: (describedBy: string | undefined) => ReactNode;
};

export function FieldRow({
  label,
  controlId,
  description,
  error,
  counter,
  children,
}: FieldRowProps) {
  const ids = [
    description ? `${controlId}-hint` : undefined,
    error ? `${controlId}-error` : undefined,
    counter ? `${controlId}-counter` : undefined,
  ].filter(Boolean);

  return (
    <div className="grid min-w-0 gap-1">
      <label id={`${controlId}-label`} className="font-medium text-ink" htmlFor={controlId}>
        {label}
      </label>
      {children(ids.length ? ids.join(" ") : undefined)}
      {description && <FieldHint id={`${controlId}-hint`}>{description}</FieldHint>}
      {error && <FieldError id={`${controlId}-error`}>{error}</FieldError>}
      {counter && (
        <p id={`${controlId}-counter`} className="text-right text-xs text-ink-muted">
          {counter.current} / {counter.maximum}
        </p>
      )}
    </div>
  );
}

export function FieldHint({ id, children }: { id: string; children: ReactNode }) {
  return (
    <p id={id} className="text-sm text-ink-muted">
      {children}
    </p>
  );
}

export function FieldError({ id, children }: { id: string; children: ReactNode }) {
  return (
    <p id={id} className="flex items-start gap-1 text-sm text-danger" role="alert">
      <svg
        aria-hidden="true"
        className="mt-0.5 size-4 shrink-0"
        viewBox="0 0 16 16"
        fill="currentColor"
      >
        <path d="M8 1.5a6.5 6.5 0 1 0 0 13 6.5 6.5 0 0 0 0-13Zm0 9.75a.875.875 0 1 1 0 1.75.875.875 0 0 1 0-1.75Zm-.75-6h1.5v4.5h-1.5v-4.5Z" />
      </svg>
      <span>{children}</span>
    </p>
  );
}
