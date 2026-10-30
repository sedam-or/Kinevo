import { render, screen } from "@testing-library/react";
import { describe, expect, it } from "vitest";
import { TextField } from "./TextField";

describe("components", () => {
  it("components::ds_field_error_linked", () => {
    render(
      <TextField
        label="Email address"
        type="email"
        autoComplete="email"
        description="Used for account access"
        error="Enter a valid email address"
        maxLength={254}
      />,
    );

    const input = screen.getByRole("textbox", { name: "Email address" });
    const describedBy = input.getAttribute("aria-describedby") ?? "";
    expect(input).toHaveAttribute("autocomplete", "email");
    expect(describedBy).toContain(screen.getByText("Used for account access").id);
    expect(describedBy).toContain(screen.getByText("Enter a valid email address").id);
    expect(
      screen
        .getByText("Enter a valid email address")
        .parentElement?.querySelector("[aria-hidden='true']"),
    ).not.toBeNull();
  });
});
