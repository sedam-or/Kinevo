import {
  createContext,
  type ReactNode,
  useCallback,
  useContext,
  useEffect,
  useRef,
  useState,
} from "react";
import { type MessageKey, useT } from "../i18n";
import { Button } from "./Button";

type ToastKind = "success" | "info" | "error";
type ToastItem = { id: number; kind: ToastKind; messageKey: MessageKey; requestId?: string };
type ToastInput = { messageKey: MessageKey; requestId: string };
type ToastApi = {
  success: (messageKey: MessageKey) => void;
  info: (messageKey: MessageKey) => void;
  error: (input: ToastInput) => void;
};

const ToastContext = createContext<ToastApi | undefined>(undefined);
const toastDuration = 6000;
const toastLimit = 3;

export function useToast() {
  const toast = useContext(ToastContext);
  if (!toast) throw new Error("useToast must be used inside ToastProvider");
  return toast;
}

export function ToastProvider({ children }: { children: ReactNode }) {
  const [items, setItems] = useState<ToastItem[]>([]);
  const sequence = useRef(0);

  const enqueue = useCallback((kind: ToastKind, messageKey: MessageKey, requestId?: string) => {
    const item = { id: ++sequence.current, kind, messageKey, ...(requestId ? { requestId } : {}) };
    setItems((current) => {
      if (current.length < toastLimit) return [...current, item];
      const discardIndex = current.findIndex((queued) => queued.kind !== "error");
      if (discardIndex < 0) return current;
      return [...current.slice(0, discardIndex), ...current.slice(discardIndex + 1), item];
    });
  }, []);

  const dismiss = useCallback((id: number) => {
    setItems((current) => current.filter((item) => item.id !== id));
  }, []);

  const api: ToastApi = {
    success: (messageKey) => enqueue("success", messageKey),
    info: (messageKey) => enqueue("info", messageKey),
    error: ({ messageKey, requestId }) => enqueue("error", messageKey, requestId),
  };
  const t = useT();

  return (
    <ToastContext.Provider value={api}>
      {children}
      <section
        aria-label={t("action.notifications")}
        className="fixed right-4 top-4 z-[60] grid w-[min(24rem,calc(100vw-2rem))] gap-2"
      >
        {items.map((item) => (
          <ToastMessage key={item.id} item={item} onDismiss={dismiss} />
        ))}
      </section>
    </ToastContext.Provider>
  );
}

function ToastMessage({ item, onDismiss }: { item: ToastItem; onDismiss: (id: number) => void }) {
  const t = useT();
  const messageRef = useRef<HTMLElement>(null);
  const [remaining, setRemaining] = useState(toastDuration);
  const timer = useRef<ReturnType<typeof setTimeout> | undefined>(undefined);
  const startedAt = useRef(0);
  const paused = useRef(false);
  const hovering = useRef(false);
  const focused = useRef(false);

  const schedule = useCallback(
    (delay: number) => {
      if (item.kind === "error" || paused.current) return;
      startedAt.current = Date.now();
      timer.current = setTimeout(() => onDismiss(item.id), delay);
    },
    [item.id, item.kind, onDismiss],
  );

  useEffect(() => {
    schedule(remaining);
    return () => {
      if (timer.current) clearTimeout(timer.current);
    };
  }, [remaining, schedule]);

  const pause = useCallback(() => {
    if (item.kind === "error" || paused.current) return;
    paused.current = true;
    if (timer.current) clearTimeout(timer.current);
    setRemaining((time) => Math.max(0, time - (Date.now() - startedAt.current)));
  }, [item.kind]);

  const resume = useCallback(() => {
    if (!paused.current) return;
    if (hovering.current || focused.current) return;
    paused.current = false;
    schedule(remaining);
  }, [remaining, schedule]);

  useEffect(() => {
    const element = messageRef.current;
    if (!element) return;

    const onMouseEnter = () => {
      hovering.current = true;
      pause();
    };
    const onMouseLeave = () => {
      hovering.current = false;
      resume();
    };
    const onFocus = () => {
      focused.current = true;
      pause();
    };
    const onBlur = (event: FocusEvent) => {
      if (!event.relatedTarget || !element.contains(event.relatedTarget as Node)) {
        focused.current = false;
        resume();
      }
    };

    element.addEventListener("mouseenter", onMouseEnter);
    element.addEventListener("mouseleave", onMouseLeave);
    element.addEventListener("focusin", onFocus);
    element.addEventListener("focusout", onBlur);
    return () => {
      element.removeEventListener("mouseenter", onMouseEnter);
      element.removeEventListener("mouseleave", onMouseLeave);
      element.removeEventListener("focusin", onFocus);
      element.removeEventListener("focusout", onBlur);
    };
  }, [pause, resume]);
  const role = item.kind === "error" ? "alert" : "status";
  const style =
    item.kind === "error"
      ? "border-danger"
      : item.kind === "success"
        ? "border-success"
        : "border-info";

  return (
    <section
      ref={messageRef}
      role={role}
      className={`grid gap-1 rounded-md border-l-4 bg-surface p-4 text-ink shadow-raised ${style}`}
    >
      <div className="flex items-start justify-between gap-3">
        <span>
          {t(item.messageKey, item.requestId ? { request_id: item.requestId } : undefined)}
        </span>
        <Button
          variant="quiet"
          className="min-h-6 min-w-6 px-2 py-1 text-sm"
          aria-label={t("action.dismiss_notification")}
          onPress={() => onDismiss(item.id)}
        >
          <span aria-hidden="true">×</span>
        </Button>
      </div>
      {item.requestId && (
        <span className="text-xs text-ink-muted">
          {t("feedback.section_error.reference", { request_id: item.requestId })}
        </span>
      )}
    </section>
  );
}
