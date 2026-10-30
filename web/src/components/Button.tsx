import { forwardRef, type ReactNode, useCallback, useId } from "react";
import { Button as AriaButton, type ButtonProps as AriaButtonProps } from "react-aria-components";

export type ButtonVariant = "primary" | "secondary" | "quiet" | "danger";

export type ButtonProps = Omit<AriaButtonProps, "children" | "className"> & {
  children: ReactNode;
  className?: string;
  variant?: ButtonVariant;
  isLoading?: boolean;
  isSelected?: boolean;
  error?: string;
};

const variants: Record<ButtonVariant, string> = {
  primary: "bg-accent text-accent-ink shadow-edge-primary hover:bg-accent/90",
  secondary: "border-rule-strong bg-surface text-ink shadow-rest hover:bg-sunken",
  quiet: "bg-transparent text-ink hover:bg-sunken",
  danger: "bg-danger text-paper shadow-rest hover:brightness-95",
};

export const Button = forwardRef<HTMLButtonElement, ButtonProps>(function Button(
  {
    children,
    variant = "primary",
    isLoading = false,
    isSelected = false,
    isDisabled = false,
    error,
    className,
    "aria-describedby": describedBy,
    ...props
  },
  forwardedRef,
) {
  const errorId = useId();
  const describedByIds = [describedBy, error ? errorId : undefined].filter(Boolean).join(" ");
  const buttonRef = useCallback(
    (element: HTMLButtonElement | null) => {
      if (typeof forwardedRef === "function") forwardedRef(element);
      else if (forwardedRef) forwardedRef.current = element;
      if (!element) return;
      if (isLoading) element.setAttribute("aria-busy", "true");
      else element.removeAttribute("aria-busy");
    },
    [forwardedRef, isLoading],
  );

  return (
    <AriaButton
      {...props}
      ref={buttonRef}
      isDisabled={isDisabled || isLoading}
      {...(isSelected ? { "aria-pressed": true, "data-selected": "" } : {})}
      {...(describedByIds ? { "aria-describedby": describedByIds } : {})}
      data-variant={variant}
      data-error={error ? "true" : undefined}
      {...(variant !== "primary" ? { "data-a11y-target": "standard" } : {})}
      className={`inline-flex min-h-11 min-w-11 items-center justify-center gap-2 rounded-md border border-transparent px-4 py-2 font-medium transition-[color,background-color,box-shadow,transform] duration-fast ease-standard focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus-ring data-[pressed]:translate-y-px data-[selected]:ring-2 data-[selected]:ring-focus-ring disabled:cursor-not-allowed disabled:bg-sunken disabled:text-ink-muted ${variants[variant]} ${className ?? ""}`}
    >
      {isLoading && (
        <svg aria-hidden="true" className="size-4 animate-spin" viewBox="0 0 16 16" fill="none">
          <circle cx="8" cy="8" r="6" stroke="currentColor" strokeWidth="2" opacity=".25" />
          <path d="M14 8a6 6 0 0 0-6-6" stroke="currentColor" strokeWidth="2" />
        </svg>
      )}
      {children}
      {error && (
        <span id={errorId} className="sr-only" aria-hidden="true">
          {error}
        </span>
      )}
    </AriaButton>
  );
});
