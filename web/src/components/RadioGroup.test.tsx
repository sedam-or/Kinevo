import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { describe, expect, it } from "vitest";
import { RadioGroup } from "./RadioGroup";

describe("components", () => {
  it("radio_group_exposes_its_label_and_selected_option", async () => {
    const user = userEvent.setup();
    render(
      <RadioGroup
        label="Layout"
        defaultValue="comfortable"
        options={[
          { value: "comfortable", label: "Comfortable" },
          { value: "compact", label: "Compact" },
        ]}
      />,
    );
    expect(screen.getByRole("radiogroup", { name: "Layout" })).toBeInTheDocument();
    const compact = screen.getByRole("radio", { name: "Compact" });
    await user.click(compact);
    expect(compact).toBeChecked();
  });
});
