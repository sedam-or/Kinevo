import { fireEvent, render, screen } from "@testing-library/react";
import { describe, expect, it, vi } from "vitest";
import { ErrorCard } from "./ErrorCard";

describe("error feedback", () => {
  it("shows a safe message and copies only the request id after a user gesture", async () => {
    const writeText = vi.fn().mockResolvedValue(undefined);
    Object.defineProperty(navigator, "clipboard", { configurable: true, value: { writeText } });
    render(
      <ErrorCard
        error={{
          code: "internal.error",
          detail: "private stack detail",
          request_id: "request-123",
          status: 500,
          title: "Untrusted server title",
          type: "about:blank",
        }}
        onRetry={() => undefined}
      />,
    );

    expect(screen.getByText("This section could not be loaded.")).toBeInTheDocument();
    expect(screen.getByText("Request reference: request-123")).toBeInTheDocument();
    expect(screen.queryByText("private stack detail")).not.toBeInTheDocument();
    expect(screen.queryByText("Untrusted server title")).not.toBeInTheDocument();
    fireEvent.click(screen.getByRole("button", { name: "Copy request id" }));
    expect(writeText).toHaveBeenCalledWith("request-123");
  });
});
