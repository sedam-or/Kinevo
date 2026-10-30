import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { describe, expect, it, vi } from "vitest";
import { Select } from "./Select";

describe("components", () => {
  it("select_supports_keyboard_selection_and_visible_label", async () => {
    const user = userEvent.setup();
    const onSelectionChange = vi.fn();
    render(
      <Select
        label="Theme"
        items={[
          { id: "light", label: "Light" },
          { id: "dark", label: "Dark" },
        ]}
        selectedKey="light"
        onSelectionChange={onSelectionChange}
      />,
    );
    await user.click(screen.getByRole("button", { name: /Theme/ }));
    await user.click(await screen.findByRole("option", { name: "Dark" }));
    expect(onSelectionChange).toHaveBeenCalledWith("dark");
  });
});
