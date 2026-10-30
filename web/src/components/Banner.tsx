import type { ReactNode } from "react";
import { Button } from "./Button";

export type BannerVariant = "info" | "warning" | "danger" | "success";

const variants: Record<BannerVariant, { role: "status" | "alert"; className: string }> = {
  info: { role: "status", className: "border-info bg-info/10" },
  warning: { role: "status", className: "border-warning bg-warning/10" },
  danger: { role: "alert", className: "border-danger bg-danger/10" },
  success: { role: "status", className: "border-success bg-success/10" },
};

export type BannerProps = {
  variant: BannerVariant;
  title: string;
  children: ReactNode;
  isCritical?: boolean;
  isDismissable?: boolean;
  onDismiss?: () => void;
};

export function Banner({
  variant,
  title,
  children,
  isCritical = false,
  isDismissable = false,
  onDismiss,
}: BannerProps) {
  const style = variants[variant];
  return (
    <div
      role={style.role}
      className={`flex items-start justify-between gap-4 border-l-4 p-4 text-ink ${style.className}`}
    >
      <div className="grid gap-1">
        <h2 className="font-semibold">{title}</h2>
        <div>{children}</div>
      </div>
      {isDismissable && !isCritical && onDismiss && (
        <Button variant="quiet" aria-label="Dismiss banner" onPress={onDismiss} className="px-2">
          ×
        </Button>
      )}
    </div>
  );
}
