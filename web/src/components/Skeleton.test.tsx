import { act, render, screen } from "@testing-library/react";
import { afterEach, describe, expect, it, vi } from "vitest";
import { Skeleton } from "./Skeleton";

describe("overlays", () => {
  afterEach(() => vi.useRealTimers());

  it("shows an aria-hidden skeleton only after the delayed loading interval", async () => {
    vi.useFakeTimers();
    const { rerender } = render(<Skeleton isLoading={false} />);
    expect(screen.queryByTestId("loading-skeleton")).not.toBeInTheDocument();
    rerender(<Skeleton isLoading />);
    await act(async () => {
      await vi.advanceTimersByTimeAsync(399);
    });
    expect(screen.queryByTestId("loading-skeleton")).not.toBeInTheDocument();
    await act(async () => {
      await vi.advanceTimersByTimeAsync(1);
    });
    expect(screen.getByTestId("loading-skeleton")).toHaveAttribute("aria-hidden", "true");
  });

  it("overlays::reduced_motion_respected", async () => {
    vi.useFakeTimers();
    render(<Skeleton isLoading />);
    await act(async () => {
      await vi.runAllTimersAsync();
    });
    expect(screen.getByTestId("loading-skeleton").className).not.toMatch(/animate-/);
  });
});
