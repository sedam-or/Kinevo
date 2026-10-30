import { render, screen } from "@testing-library/react";
import { describe, expect, it } from "vitest";
import { IconButton } from "./IconButton";

describe("components", () => {
  it("icon_button_requires_an_accessible_name", () => {
    render(<IconButton aria-label="Close panel" icon={<span aria-hidden="true">×</span>} />);
    expect(screen.getByRole("button", { name: "Close panel" })).toBeInTheDocument();
    expect(screen.queryByText("×")).toHaveAttribute("aria-hidden", "true");
  });
});
