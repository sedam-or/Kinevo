import { useDelayedLoading } from "../hooks/useDelayedLoading";

export function Skeleton({ isLoading }: { isLoading: boolean }) {
  const isVisible = useDelayedLoading(isLoading, 400);
  if (!isVisible) return null;

  return (
    <div aria-hidden="true" data-testid="loading-skeleton" className="grid gap-2">
      <span className="h-4 w-2/3 rounded bg-sunken" />
      <span className="h-4 w-full rounded bg-sunken" />
      <span className="h-4 w-5/6 rounded bg-sunken" />
    </div>
  );
}
