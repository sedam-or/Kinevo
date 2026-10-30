import { readFileSync } from "node:fs";
import { resolve } from "node:path";
import { describe, expect, it } from "vitest";
import { zodKinds } from "./zod-kinds";

type Fixture = {
  kind: string;
  params: Record<string, unknown>;
  valid: Array<{ input: string; normalised: string }>;
  invalid: Array<{ input: string; code: string }>;
};

const repoRoot = resolve(process.cwd(), "..");
const kindNames = ["email", "short_text", "key", "https_url", "iana_tz"] as const;

describe("validation", () => {
  it("components::tst_03_zod_matches_shared_fixtures", () => {
    for (const kind of kindNames) {
      const fixture = JSON.parse(
        readFileSync(resolve(repoRoot, `spec/fixtures/validation/${kind}.json`), "utf8"),
      ) as Fixture;
      const schema = zodKinds[kind](fixture.params);

      for (const example of fixture.valid) {
        const result = schema.safeParse(example.input);
        expect(result.success, `${kind} should accept ${JSON.stringify(example.input)}`).toBe(true);
        if (result.success) expect(result.data).toBe(example.normalised);
      }
      for (const example of fixture.invalid) {
        expect(
          schema.safeParse(example.input).success,
          `${kind} should reject ${JSON.stringify(example.input)}`,
        ).toBe(false);
      }
    }
  });

  it("url_normalization_removes_only_the_origin_root_slash", () => {
    const schema = zodKinds.https_url();
    expect(schema.parse("https://example.test/")).toBe("https://example.test");
    expect(schema.parse("https://example.test/path/")).toBe("https://example.test/path/");
  });
});
