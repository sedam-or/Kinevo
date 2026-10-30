import { render, screen } from "@testing-library/react";
import { afterEach, describe, expect, it, vi } from "vitest";
import { App } from "../app/providers";

afterEach(() => {
  vi.restoreAllMocks();
});

describe("web_scaffold", () => {
  it("app_mounts", async () => {
    const error = vi.spyOn(console, "error").mockImplementation(() => {});

    render(<App />);
    expect(error).not.toHaveBeenCalled();
    await expect(screen.findByRole("main")).resolves.toBeInTheDocument();
  });
});
