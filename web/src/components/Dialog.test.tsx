import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { describe, expect, it } from "vitest";
import { Button } from "./Button";
import { Dialog } from "./Dialog";
import { Sheet } from "./Sheet";

describe("overlays", () => {
  it("overlays::dialog_focus_trap_and_restore", async () => {
    const user = userEvent.setup();
    render(
      <Dialog trigger={<Button>Open dialog</Button>} title="Edit item" description="Review changes">
        <Button variant="secondary">First action</Button>
        <Button variant="secondary">Last action</Button>
      </Dialog>,
    );

    const trigger = screen.getByRole("button", { name: "Open dialog" });
    await user.click(trigger);
    const dialog = screen.getByRole("dialog", { name: "Edit item" });
    expect(dialog).toHaveAccessibleDescription("Review changes");

    await user.keyboard("{Shift>}{Tab}{/Shift}");
    expect(screen.getByRole("button", { name: "Last action" })).toHaveFocus();
    await user.keyboard("{Tab}");
    expect(trigger).not.toHaveFocus();
    await user.keyboard("{Escape}");
    await waitFor(() => expect(trigger).toHaveFocus());
  });

  it("overlays::dialog_not_dismissible_while_saving", async () => {
    const user = userEvent.setup();
    render(
      <Dialog
        trigger={<Button>Save changes</Button>}
        title="Saving item"
        description="Wait for the save to finish"
        isDismissable={false}
      >
        Saving…
      </Dialog>,
    );
    await user.click(screen.getByRole("button", { name: "Save changes" }));
    await user.keyboard("{Escape}");
    expect(screen.getByRole("dialog", { name: "Saving item" })).toBeInTheDocument();
  });

  it("opens the side sheet with a labelled and described modal", async () => {
    const user = userEvent.setup();
    render(
      <Sheet trigger={<Button>Open details</Button>} title="Details" description="More information">
        Sheet content
      </Sheet>,
    );
    await user.click(screen.getByRole("button", { name: "Open details" }));
    expect(screen.getByRole("dialog", { name: "Details" })).toHaveAccessibleDescription(
      "More information",
    );
    expect(screen.getByText("Sheet content")).toBeInTheDocument();
  });
});
