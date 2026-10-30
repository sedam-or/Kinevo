import { render, screen } from "@testing-library/react";
import { describe, expect, it } from "vitest";
import { TextArea } from "./TextArea";

describe("components", () => {
  it("text_area_keeps_a_visible_label_and_character_counter", () => {
    render(<TextArea label="Notes" value="Draft" maxLength={200} description="Private notes" />);
    expect(screen.getByRole("textbox", { name: "Notes" })).toBeInTheDocument();
    expect(screen.getByText("5 / 200")).toBeInTheDocument();
  });
});
