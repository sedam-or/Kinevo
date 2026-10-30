import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { describe, expect, it } from "vitest";
import { Tabs } from "./Tabs";

describe("tabs", () => {
  it("moves focus and selection with arrow keys", async () => {
    const user = userEvent.setup();
    render(
      <Tabs
        label="Settings sections"
        items={[
          { id: "profile", label: "Profile", content: "Profile settings" },
          { id: "security", label: "Security", content: "Security settings" },
        ]}
      />,
    );
    const profile = screen.getByRole("tab", { name: "Profile" });
    await user.click(profile);
    await user.keyboard("{ArrowRight}");
    expect(screen.getByRole("tab", { name: "Security" })).toHaveFocus();
    expect(screen.getByRole("tabpanel")).toHaveTextContent("Security settings");
  });
});
