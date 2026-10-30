import type { ReactNode } from "react";
import { Button, type ButtonProps } from "./Button";

type IconButtonProps = Omit<ButtonProps, "children" | "variant"> & {
  "aria-label": string;
  icon: ReactNode;
};

export function IconButton({ icon, ...props }: IconButtonProps) {
  return (
    <Button {...props} variant="quiet" className="p-2">
      <span aria-hidden="true" className="inline-flex size-5 items-center justify-center">
        {icon}
      </span>
    </Button>
  );
}
