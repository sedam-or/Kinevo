import { render, screen } from "@testing-library/react";
import { describe, expect, it } from "vitest";
import { TextField } from "../TextField";
import { useFormErrors } from "./use-form-errors";

const problem = {
  type: "about:blank",
  title: "Validation failed",
  status: 422,
  detail: "Check the submitted fields.",
  code: "validation_failed",
  request_id: "00000000-0000-7000-8000-000000000001",
  errors: [
    { path: "email", code: "email_invalid" },
    { path: "display_name", code: "required" },
  ],
};

describe("components", () => {
  it("components::fe_04_server_errors_map_all_at_once", () => {
    const mapped = useFormErrors(problem);
    render(
      <>
        <TextField label="Email" error={mapped.fieldErrors.email} />
        <TextField label="Display name" error={mapped.fieldErrors.display_name} />
      </>,
    );
    expect(screen.getByText("email_invalid")).toBeInTheDocument();
    expect(screen.getByText("required")).toBeInTheDocument();
    expect(screen.getByRole("textbox", { name: "Email" })).toHaveAttribute("aria-describedby");
    expect(screen.getByRole("textbox", { name: "Display name" })).toHaveAttribute(
      "aria-describedby",
    );
  });
});
