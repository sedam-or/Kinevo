import { execFileSync } from "node:child_process";
import { mkdtemp, readFile, rm } from "node:fs/promises";
import { tmpdir } from "node:os";
import { join, resolve } from "node:path";
import { describe, expect, it } from "vitest";

const repoRoot = resolve(process.cwd(), "..");

describe("web_scaffold", () => {
  it("fe_types_generated_from_openapi", async () => {
    const schemaPath = resolve(repoRoot, "web/src/api/schema.d.ts");
    const generator = resolve(repoRoot, "web/node_modules/openapi-typescript/bin/cli.js");
    const tempDirectory = await mkdtemp(join(tmpdir(), "kinevo-openapi-"));
    const generatedPath = join(tempDirectory, "schema.d.ts");

    try {
      execFileSync(
        process.execPath,
        [generator, "../contracts/openapi.json", "-o", generatedPath],
        { cwd: resolve(repoRoot, "web") },
      );
      await expect(readFile(schemaPath, "utf8")).resolves.toBe(
        await readFile(generatedPath, "utf8"),
      );
    } finally {
      await rm(tempDirectory, { recursive: true, force: true });
    }
  });

  it("fe_13_no_inline_script_no_third_party", async () => {
    const index = await readFile(resolve(repoRoot, "web/dist/index.html"), "utf8");
    for (const script of index.matchAll(/<script\b([^>]*)>([\s\S]*?)<\/script>/gi)) {
      expect(script[1] ?? "").toMatch(/\bsrc=/i);
      expect((script[2] ?? "").trim()).toBe("");
    }
    expect(index).not.toMatch(/(?:src|href)=["']https?:\/\//i);
  });
});
