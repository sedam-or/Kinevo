export function ProgressBar({
  label,
  value,
  maxValue = 100,
}: {
  label: string;
  value: number;
  maxValue?: number;
}) {
  const current = Math.min(maxValue, Math.max(0, value));
  const percentage = maxValue > 0 ? (current / maxValue) * 100 : 0;
  return (
    <div className="grid gap-1">
      <span className="text-sm">{label}</span>
      <div
        role="progressbar"
        aria-label={label}
        aria-valuemin={0}
        aria-valuemax={maxValue}
        aria-valuenow={current}
        className="h-2 overflow-hidden rounded-full bg-sunken"
      >
        <div className="h-full rounded-full bg-accent" style={{ width: `${percentage}%` }} />
      </div>
    </div>
  );
}
