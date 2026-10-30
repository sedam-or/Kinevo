import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import type { ReactNode } from "react";
import { describe, expect, it } from "vitest";
import { ErrorBoundary } from "./ErrorBoundary";

let shouldThrow = true;

function BrokenSection(): ReactNode {
  if (shouldThrow) {
    throw new Error("sensitive implementation details");
  }
  return <p>Section reloaded</p>;
}

describe("overlays", () => {
  it("overlays::fe_12_error_boundary_is_local", async () => {
    shouldThrow = true;
    const user = userEvent.setup();
    render(
      <>
        <ErrorBoundary
          onReload={() => {
            shouldThrow = false;
          }}
        >
          <BrokenSection />
        </ErrorBoundary>
        <header>Shell remains available</header>
      </>,
    );

    expect(screen.getByText("Shell remains available")).toBeInTheDocument();
    expect(screen.getByText("This section could not be loaded.")).toBeInTheDocument();
    expect(screen.queryByText("sensitive implementation details")).not.toBeInTheDocument();
    await user.click(screen.getByRole("button", { name: "Reload this section" }));
    expect(screen.getByText("Shell remains available")).toBeInTheDocument();
    expect(screen.getByText("Section reloaded")).toBeInTheDocument();
    expect(screen.queryByText("This section could not be loaded.")).not.toBeInTheDocument();
  });
});
