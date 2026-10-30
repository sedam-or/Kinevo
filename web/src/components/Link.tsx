import type { ReactNode } from "react";
import { Link as AriaLink, type LinkProps as AriaLinkProps } from "react-aria-components";

type LinkProps = Omit<AriaLinkProps, "children" | "className" | "href" | "target" | "rel"> & {
  href: string;
  children: ReactNode;
  openInNewTab?: boolean;
};

export function Link({ href, children, openInNewTab = false, ...props }: LinkProps) {
  return (
    <AriaLink
      {...props}
      href={href}
      {...(openInNewTab ? { target: "_blank", rel: "noopener noreferrer" } : {})}
      data-a11y-target="standard"
      className="inline-flex min-h-6 items-center gap-1 text-accent underline decoration-1 underline-offset-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus-ring"
    >
      {children}
      {openInNewTab && <span aria-hidden="true">↗</span>}
    </AriaLink>
  );
}
