import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { describe, expect, it } from "vitest";
import { NumberField } from "./NumberField";

describe("components", () => {
  it("number_field_is_labelled_and_uses_supplied_bounds", async () => {
    const user = userEvent.setup();
    render(<NumberField label="Sessions" defaultValue={2} minValue={0} maxValue={3} />);
    const input = screen.getByRole("spinbutton", { name: "Sessions" });
    expect(input).toHaveValue("2");
    expect(input).toHaveAttribute("min", "0");
    expect(input).toHaveAttribute("max", "3");
    await user.click(screen.getByRole("button", { name: "Increase Sessions" }));
    expect(input).toHaveValue("3");
  });
});
