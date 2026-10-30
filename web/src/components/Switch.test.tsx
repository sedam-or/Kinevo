import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { describe, expect, it, vi } from "vitest";
import { Switch } from "./Switch";

describe("components", () => {
  it("switch_shows_an_on_or_off_label_and_is_operable", async () => {
    const user = userEvent.setup();
    const onChange = vi.fn();
    render(<Switch label="Canvas" onLabel="On" offLabel="Off" isSelected onChange={onChange} />);
    const control = screen.getByRole("switch", { name: "Canvas" });
    expect(control).toBeChecked();
    expect(screen.getByText("On")).toBeVisible();
    await user.click(control);
    expect(onChange).toHaveBeenCalledWith(false);
  });
});
