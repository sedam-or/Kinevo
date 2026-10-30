import { readFileSync, writeFileSync } from "node:fs";
import { dirname, resolve } from "node:path";
import { fileURLToPath } from "node:url";

export type RetryClass = "never" | "after_change" | "backoff" | "reauth";

export type ErrorCodeMetadata = { status: number; retry: RetryClass };

export type ErrorRegistry = {
  apiCodes: Record<string, ErrorCodeMetadata>;
  apiTitles: Record<string, string>;
  fieldCodes: string[];
  processCodes: string[];
};

const retryClasses: Record<string, RetryClass> = {
  N: "never",
  C: "after_change",
  B: "backoff",
  R: "reauth",
};

const scriptDirectory = dirname(fileURLToPath(import.meta.url));
const repositoryRoot = resolve(scriptDirectory, "../..");
const registryPath = resolve(repositoryRoot, "spec/standards/error-handling.md");
const outputPath = resolve(repositoryRoot, "web/src/api/error-codes.ts");
const registryRow = /^\| `([a-z0-9_]+)` \| (\d{3}) \| ([NCBR]) \|/;
const registrySections = new Set(["2.1", "2.2", "2.3", "2.4", "2.5", "2.6", "2.7", "2.8"]);

export function parseErrorRegistry(markdown: string): ErrorRegistry {
  const apiCodes: Record<string, ErrorCodeMetadata> = {};
  const apiTitles: Record<string, string> = {};
  const sectionsWithRows = new Set<string>();
  let activeSection: string | undefined;
  let tableHeaderSeen = false;

  for (const line of markdown.split("\n")) {
    const heading = /^### (2\.\d+)\b/.exec(line);
    if (heading) {
      activeSection = registrySections.has(heading[1] ?? "") ? (heading[1] ?? "") : undefined;
      tableHeaderSeen = false;
      continue;
    }
    if (!activeSection || !line.trimStart().startsWith("|")) continue;

    const cells = line.split("|").map((cell) => cell.trim());
    if (cells[1] === "Code" && cells[2] === "HTTP" && cells[3] === "Retry") {
      tableHeaderSeen = true;
      continue;
    }

    const row = registryRow.exec(line);
    if (!row) {
      if (/^\| `[^`]+` \|/.test(line)) {
        throw new Error(`Unrecognised error registry row in section ${activeSection}: ${line}`);
      }
      continue;
    }
    if (!tableHeaderSeen) {
      throw new Error(`Error registry row precedes a recognised table header: ${line}`);
    }
    if (cells.length !== 8) {
      throw new Error(`Unrecognised error registry table row: ${line}`);
    }

    const code = row[1] ?? "";
    const status = Number(row[2]);
    const retry = retryClasses[row[3] ?? ""];
    const title = cells[4]?.trim();
    if (!code || !Number.isInteger(status) || !retry || !title) {
      throw new Error(`Incomplete error registry row: ${line}`);
    }
    if (Object.hasOwn(apiCodes, code)) {
      throw new Error(`Duplicate error registry code: ${code}`);
    }

    apiCodes[code] = { status, retry };
    apiTitles[code] = title;
    sectionsWithRows.add(activeSection);
  }

  for (const section of ["2.1", "2.3", "2.4", "2.5", "2.6", "2.7", "2.8"]) {
    if (!sectionsWithRows.has(section)) {
      throw new Error(`Missing error registry table in section ${section}`);
    }
  }

  const fieldSectionStart = markdown.indexOf("### 2.2 Field-level validation codes");
  const fieldSectionEnd = markdown.indexOf("### 2.3", fieldSectionStart);
  if (fieldSectionStart < 0 || fieldSectionEnd < 0) {
    throw new Error("Field-level error registry section is missing or unrecognised");
  }
  const fieldSection = markdown.slice(fieldSectionStart, fieldSectionEnd);
  const fieldList = fieldSection
    .split("\n")
    .map((line) => line.trim())
    .find((line) => line && !line.startsWith("###"));
  const codeList = fieldList?.split(". `details`")[0];
  if (!codeList) throw new Error("Field-level error code list is missing");
  const fieldCodes = [...codeList.matchAll(/`([a-z0-9_]+)`/g)].map((match) => match[1] ?? "");
  if (!fieldCodes.length || new Set(fieldCodes).size !== fieldCodes.length) {
    throw new Error("Field-level error code list is empty or contains duplicates");
  }

  const processSectionStart = markdown.indexOf("### 2.9 Non-HTTP (process-level) codes");
  if (processSectionStart < 0) throw new Error("Process-level error registry section is missing");
  const processSection = markdown.slice(processSectionStart).split("\n").slice(1).join("\n");
  const processCodes = [...processSection.matchAll(/`([a-z0-9_]+)`/g)].map(
    (match) => match[1] ?? "",
  );
  if (!processCodes.length || new Set(processCodes).size !== processCodes.length) {
    throw new Error("Process-level error code list is empty or contains duplicates");
  }
  if (processCodes.some((code) => Object.hasOwn(apiCodes, code))) {
    throw new Error("Process-level error codes must not be emitted as API errors");
  }

  return {
    apiCodes: Object.fromEntries(
      Object.entries(apiCodes).sort(([left], [right]) => left.localeCompare(right)),
    ),
    apiTitles: Object.fromEntries(
      Object.entries(apiTitles).sort(([left], [right]) => left.localeCompare(right)),
    ),
    fieldCodes: fieldCodes.sort(),
    processCodes: processCodes.sort(),
  };
}

export function renderErrorCodeTable(registry: ErrorRegistry): string {
  const metadata = Object.entries(registry.apiCodes)
    .sort(([left], [right]) => left.localeCompare(right))
    .map(
      ([code, value]) =>
        `  ${code}: { status: ${value.status}, retry: ${JSON.stringify(value.retry)} },`,
    )
    .join("\n");
  const fieldCodes = [...registry.fieldCodes]
    .sort()
    .map((code) => `  ${JSON.stringify(code)},`)
    .join("\n");

  return [
    "// Generated by `pnpm --dir web gen:error-codes`; do not edit by hand.",
    "export const errorCodeMetadata = {",
    metadata,
    "} as const;",
    "",
    "export const fieldErrorCodes = [",
    fieldCodes,
    "] as const;",
    "",
  ].join("\n");
}

export function generateErrorCodeTable(markdown: string): string {
  return renderErrorCodeTable(parseErrorRegistry(markdown));
}

function run(): void {
  const expected = generateErrorCodeTable(readFileSync(registryPath, "utf8"));
  if (process.argv.includes("--check")) {
    let actual: string;
    try {
      actual = readFileSync(outputPath, "utf8");
    } catch {
      process.stderr.write(
        "gen-error-codes: generated table is missing; run pnpm gen:error-codes\n",
      );
      process.exitCode = 1;
      return;
    }
    if (actual !== expected) {
      process.stderr.write(
        "gen-error-codes: error-codes.ts is out of date; run pnpm gen:error-codes\n",
      );
      process.exitCode = 1;
      return;
    }
    process.stdout.write("gen-error-codes: registry table is current\n");
    return;
  }

  writeFileSync(outputPath, expected);
  process.stdout.write("gen-error-codes: generated error-codes.ts\n");
}

if (process.argv[1] && resolve(process.argv[1]) === fileURLToPath(import.meta.url)) run();
