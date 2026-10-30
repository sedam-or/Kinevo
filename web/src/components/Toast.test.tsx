import { act, fireEvent, render, screen } from "@testing-library/react";
import { afterEach, describe, expect, it, vi } from "vitest";
import { ToastProvider, useToast } from "./Toast";

function ToastActions() {
  const toast = useToast();
  return (
    <>
      <button type="button" onClick={() => toast.success("Saved")}>
        Success
      </button>
      <button
        type="button"
        onClick={() => toast.error({ messageKey: "Failed", requestId: "request-1" })}
      >
        Error
      </button>
      <button type="button" onClick={() => toast.success("Another")}>
        Another success
      </button>
      <button type="button" onClick={() => toast.success("Last")}>
        Last success
      </button>
    </>
  );
}

describe("overlays", () => {
  afterEach(() => vi.useRealTimers());

  it("overlays::toast_queue_and_persistence", async () => {
    vi.useFakeTimers();
    render(
      <ToastProvider>
        <ToastActions />
      </ToastProvider>,
    );
    fireEvent.click(screen.getByRole("button", { name: "Error" }));
    fireEvent.click(screen.getByRole("button", { name: "Success" }));
    fireEvent.click(screen.getByRole("button", { name: "Another success" }));
    fireEvent.click(screen.getByRole("button", { name: "Last success" }));

    expect(screen.getAllByRole("alert")).toHaveLength(1);
    expect(screen.getAllByRole("status")).toHaveLength(2);
    await act(async () => {
      await vi.advanceTimersByTimeAsync(6100);
    });
    expect(screen.getByRole("alert")).toHaveTextContent("Failed");
    expect(screen.queryByRole("status")).not.toBeInTheDocument();
  });

  it("overlays::toast_pauses_on_hover_focus", async () => {
    vi.useFakeTimers();
    render(
      <ToastProvider>
        <ToastActions />
      </ToastProvider>,
    );
    fireEvent.click(screen.getByRole("button", { name: "Success" }));
    const toast = screen.getByRole("status");
    await act(async () => {
      await vi.advanceTimersByTimeAsync(3000);
    });
    fireEvent.mouseEnter(toast);
    await act(async () => {
      await vi.advanceTimersByTimeAsync(6100);
    });
    expect(toast).toBeInTheDocument();
    fireEvent.mouseLeave(toast);
    await act(async () => {
      await vi.advanceTimersByTimeAsync(1000);
    });
    const dismiss = screen.getByRole("button", { name: "Dismiss notification" });
    fireEvent.focus(dismiss);
    await act(async () => {
      await vi.advanceTimersByTimeAsync(6100);
    });
    expect(toast).toBeInTheDocument();
    fireEvent.blur(dismiss, { relatedTarget: document.body });
    await act(async () => {
      await vi.advanceTimersByTimeAsync(2100);
    });
    expect(screen.queryByRole("status")).not.toBeInTheDocument();
  });
});
