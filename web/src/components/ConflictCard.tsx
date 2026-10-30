import type { ReactNode } from "react";
import { Button } from "./Button";

export function ConflictCard({
  yourChange,
  currentVersion,
  onKeepMine,
  onUseCurrent,
}: {
  yourChange: ReactNode;
  currentVersion: ReactNode;
  onKeepMine: () => void;
  onUseCurrent: () => void;
}) {
  return (
    <section
      aria-labelledby="conflict-heading"
      className="grid gap-3 rounded-lg border border-warning bg-surface p-4 text-ink"
    >
      <h2 id="conflict-heading" className="font-semibold">
        This changed elsewhere
      </h2>
      <div className="grid gap-3 sm:grid-cols-2">
        <div>
          <h3 className="text-sm font-semibold">Your change</h3>
          <div>{yourChange}</div>
        </div>
        <div>
          <h3 className="text-sm font-semibold">Current version</h3>
          <div>{currentVersion}</div>
        </div>
      </div>
      <div className="flex flex-wrap gap-2">
        <Button onPress={onKeepMine}>Keep my change</Button>
        <Button variant="secondary" onPress={onUseCurrent}>
          Use current version
        </Button>
      </div>
    </section>
  );
}
