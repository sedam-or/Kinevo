import { describe, expect, it } from "vitest";
import { safeNext } from "./guards";

describe("auth", () => {
  it("safe_next_only_relative", () => {
    expect(safeNext("//evil.test/path")).toBe("/today");
    expect(safeNext("https://evil.test/path")).toBe("/today");
    expect(safeNext("/tasks?filter=today#list")).toBe("/tasks?filter=today#list");
  });
});
