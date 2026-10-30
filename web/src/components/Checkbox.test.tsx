import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { describe, expect, it } from "vitest";
import { Checkbox } from "./Checkbox";

describe("components", () => {
  it("checkbox_has_a_visible_label_and_a_hit_target", async () => {
    const user = userEvent.setup();
    render(<Checkbox label="Remember this device" />);
    const control = screen.getByRole("checkbox", { name: "Remember this device" });
    expect(control.closest("label")).toHaveClass("min-h-11");
    await user.click(control);
    expect(control).toBeChecked();
  });
});
