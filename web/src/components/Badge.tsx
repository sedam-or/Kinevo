import type { ReactNode } from "react";

export type BadgeVariant = "neutral" | "info" | "success" | "warning" | "danger";

const variants: Record<BadgeVariant, string> = {
  neutral: "bg-sunken text-ink",
  info: "bg-info/10 text-info",
  success: "bg-success/10 text-success",
  warning: "bg-warning/10 text-warning",
  danger: "bg-danger/10 text-danger",
};

export function Badge({
  children,
  icon,
  variant = "neutral",
}: {
  children: ReactNode;
  icon: ReactNode;
  variant?: BadgeVariant;
}) {
  return (
    <span
      className={`inline-flex min-h-6 items-center gap-1 rounded-full px-2 py-0.5 text-sm ${variants[variant]}`}
    >
      {icon}
      <span>{children}</span>
    </span>
  );
}
