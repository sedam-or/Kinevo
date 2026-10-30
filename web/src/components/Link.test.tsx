import { render, screen } from "@testing-library/react";
import { describe, expect, it } from "vitest";
import { Link } from "./Link";

describe("components", () => {
  it("links_are_descriptive_and_open_new_tabs_only_when_requested", () => {
    const { rerender } = render(<Link href="https://example.test/help">Read the help guide</Link>);
    expect(screen.getByRole("link", { name: "Read the help guide" })).not.toHaveAttribute("target");

    rerender(
      <Link href="https://example.test/help" openInNewTab>
        Read the help guide
      </Link>,
    );
    expect(screen.getByRole("link", { name: /Read the help guide/ })).toHaveAttribute(
      "target",
      "_blank",
    );
    expect(screen.getByRole("link", { name: /Read the help guide/ })).toHaveAttribute(
      "rel",
      "noopener noreferrer",
    );
  });
});
