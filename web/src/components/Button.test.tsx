import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { describe, expect, it } from "vitest";
import { Button } from "./Button";

describe("components", () => {
  it("components::ds_button_states", async () => {
    const user = userEvent.setup();
    render(
      <div>
        <Button variant="primary">Primary</Button>
        <Button variant="secondary">Secondary</Button>
        <Button variant="quiet">Quiet</Button>
        <Button variant="danger">Danger</Button>
        <Button variant="primary" isDisabled>
          Disabled
        </Button>
        <Button variant="primary" isLoading>
          Save changes
        </Button>
        <Button variant="danger" error="The action is unavailable">
          Error state
        </Button>
        <Button variant="quiet" isSelected>
          Selected
        </Button>
      </div>,
    );

    for (const variant of ["primary", "secondary", "quiet", "danger"] as const) {
      expect(
        screen.getByRole("button", { name: variant.charAt(0).toUpperCase() + variant.slice(1) }),
      ).toHaveAttribute("data-variant", variant);
    }

    const primary = screen.getByRole("button", { name: "Primary" });
    await user.tab();
    expect(primary).toHaveAttribute("data-focus-visible");
    await user.hover(primary);
    expect(primary).toHaveAttribute("data-hovered");
    await user.pointer({ target: primary, keys: "[MouseLeft>]" });
    expect(primary).toHaveAttribute("data-pressed");
    await user.pointer({ target: primary, keys: "[/MouseLeft]" });

    expect(screen.getByRole("button", { name: "Disabled" })).toBeDisabled();
    expect(screen.getByRole("button", { name: "Save changes" })).toHaveAttribute(
      "aria-busy",
      "true",
    );
    expect(screen.getByRole("button", { name: "Save changes" })).toHaveTextContent("Save changes");
    expect(screen.getByRole("button", { name: "Error state" })).toHaveAttribute("aria-describedby");
    expect(screen.getByRole("button", { name: "Selected" })).toHaveAttribute(
      "aria-pressed",
      "true",
    );
  });
});
