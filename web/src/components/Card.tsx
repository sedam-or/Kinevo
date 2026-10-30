import type { ReactNode } from "react";

type SectionProps = {
  title: string;
  children: ReactNode;
  description?: string;
  className?: string;
};

export function Card({ title, description, children, className }: SectionProps) {
  return (
    <section
      aria-label={title}
      className={`grid gap-3 rounded-lg border border-rule bg-surface p-4 text-ink shadow-rest ${className ?? ""}`}
    >
      <div className="grid gap-1">
        <h2 className="font-semibold">{title}</h2>
        {description && <p className="text-sm text-ink-muted">{description}</p>}
      </div>
      {children}
    </section>
  );
}

export function Section({ title, description, children, className }: SectionProps) {
  return (
    <section aria-label={title} className={`grid gap-3 text-ink ${className ?? ""}`}>
      <div className="grid gap-1">
        <h2 className="font-semibold">{title}</h2>
        {description && <p className="text-sm text-ink-muted">{description}</p>}
      </div>
      {children}
    </section>
  );
}
