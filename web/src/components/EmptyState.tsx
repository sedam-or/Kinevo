import type { ReactNode } from "react";

export function EmptyState({
  headline,
  body,
  action,
}: {
  headline: string;
  body: string;
  action: ReactNode;
}) {
  return (
    <section className="grid justify-items-start gap-3 rounded-lg border border-dashed border-rule p-6">
      <h2 className="font-display text-title-2 text-ink">{headline}</h2>
      <p className="max-w-prose text-ink-muted">{body}</p>
      {action}
    </section>
  );
}
