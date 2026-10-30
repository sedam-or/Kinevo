import type { ReactNode } from "react";
import { useEffect, useRef } from "react";

export function Chords({
  children,
  onNavigate,
  onHelp,
}: {
  children: ReactNode;
  onNavigate: (path: string) => void;
  onHelp?: () => void;
}) {
  const pending = useRef(false);

  useEffect(() => {
    let reset: ReturnType<typeof setTimeout> | undefined;
    const onKeyDown = (event: KeyboardEvent) => {
      const target = event.target;
      if (
        target instanceof HTMLElement &&
        (target.matches("input, textarea, select, [contenteditable='true'], [role='textbox']") ||
          target.isContentEditable)
      ) {
        pending.current = false;
        return;
      }
      if (event.altKey || event.ctrlKey || event.metaKey) return;
      if (event.key === "?") {
        onHelp?.();
        pending.current = false;
        return;
      }
      if (pending.current && event.key.toLowerCase() === "t") {
        event.preventDefault();
        pending.current = false;
        onNavigate("/today");
        return;
      }
      pending.current = event.key.toLowerCase() === "g";
      if (reset) clearTimeout(reset);
      if (pending.current) {
        reset = setTimeout(() => {
          pending.current = false;
        }, 1200);
      }
    };
    document.addEventListener("keydown", onKeyDown);
    return () => {
      document.removeEventListener("keydown", onKeyDown);
      if (reset) clearTimeout(reset);
    };
  }, [onHelp, onNavigate]);

  return children;
}
