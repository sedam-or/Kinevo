import { readdirSync, readFileSync } from "node:fs";
import { dirname, relative, resolve } from "node:path";
import { fileURLToPath } from "node:url";
import IntlMessageFormat from "intl-messageformat";
import ts from "typescript";
import { type ErrorRegistry, parseErrorRegistry } from "./gen-error-codes.ts";

export type Messages = Record<string, string>;

type AllowRule = { pattern: string; reason: string; expression: RegExp };

const scriptDirectory = dirname(fileURLToPath(import.meta.url));
const webRoot = resolve(scriptDirectory, "..");

export function catalogueKeysFromUxStates(markdown: string): string[] {
  const keys = new Set<string>();
  const catalogueSections = new Set([2, 3, 4, 5, 6, 7, 8, 9, 10]);
  const seenSections = new Set<number>();
  let section: number | undefined;
  let sectionHasHeader = false;
  let sectionKeyCount = 0;

  function finishSection(): void {
    if (section === undefined || !catalogueSections.has(section)) return;
    if (!sectionHasHeader || sectionKeyCount === 0) {
      throw new Error(`Unrecognised or empty UX catalogue table in section ${section}`);
    }
  }

  for (const line of markdown.split("\n")) {
    const heading = /^## (\d+)\./.exec(line);
    if (heading) {
      finishSection();
      section = Number(heading[1]);
      if (catalogueSections.has(section)) seenSections.add(section);
      sectionHasHeader = false;
      sectionKeyCount = 0;
      continue;
    }
    if (section === undefined || !catalogueSections.has(section)) continue;
    if (!line.trimStart().startsWith("|")) continue;
    const cells = line.split("|").map((cell) => cell.trim());
    if (cells.length !== 7) {
      throw new Error(`Unrecognised UX table row in section ${section}: ${line}`);
    }
    const keyCell = cells[1]?.replaceAll("`", "").trim();
    if (keyCell === "Key") {
      const headers = cells.slice(1, 6);
      if (headers.join("|") !== "Key|Element|EN|ID|Actions") {
        throw new Error(`Unrecognised UX table header in section ${section}: ${line}`);
      }
      sectionHasHeader = true;
      continue;
    }
    if (keyCell?.startsWith("---")) continue;
    if (!sectionHasHeader || !keyCell) {
      throw new Error(`UX catalogue row precedes a recognised header in section ${section}`);
    }

    const parts = keyCell.split(/\s+\/\s+/);
    const expanded: string[] = [];
    for (const part of parts) {
      const key = part.trim();
      if (!key) continue;
      if (key.startsWith(".")) {
        const previous = expanded.at(-1);
        const parent = previous?.slice(0, previous.lastIndexOf("."));
        if (!parent) throw new Error(`Cannot expand abbreviated UX key ${keyCell}`);
        expanded.push(`${parent}${key}`);
      } else {
        expanded.push(key);
      }
    }

    for (const key of expanded) {
      if (!/^[a-z][a-zA-Z0-9_.-]+$/.test(key)) {
        throw new Error(`Unrecognised UX key table cell: ${keyCell}`);
      }
      keys.add(key);
      sectionKeyCount += 1;
    }
  }

  finishSection();
  for (const requiredSection of catalogueSections) {
    if (!seenSections.has(requiredSection)) {
      throw new Error(`Missing UX catalogue section ${requiredSection}`);
    }
  }

  return [...keys].sort();
}

export function checkCatalogues(en: Messages, id: Messages, uxStatesMarkdown: string): string[] {
  const findings: string[] = [];
  const enKeys = new Set(Object.keys(en));
  const idKeys = new Set(Object.keys(id));

  for (const key of [...new Set([...enKeys, ...idKeys])].sort()) {
    if (!enKeys.has(key)) findings.push(`Missing en message: ${key}`);
    if (!idKeys.has(key)) findings.push(`Missing id message: ${key}`);
  }

  let requiredKeys: string[];
  try {
    requiredKeys = catalogueKeysFromUxStates(uxStatesMarkdown);
  } catch (error) {
    findings.push(`Could not parse ux-states.md: ${errorMessage(error)}`);
    return findings;
  }

  for (const key of requiredKeys) {
    if (!enKeys.has(key)) findings.push(`Missing UX state in en: ${key}`);
    if (!idKeys.has(key)) findings.push(`Missing UX state in id: ${key}`);
  }

  for (const key of [
    "term.goal",
    "term.milestone",
    "term.task",
    "term.workspace",
    "term.hard_landscape",
    "term.focus_session",
  ]) {
    if (!enKeys.has(key)) findings.push(`Missing terminology message in en: ${key}`);
    if (!idKeys.has(key)) findings.push(`Missing terminology message in id: ${key}`);
  }

  return findings;
}

export function checkErrorMessages(en: Messages, id: Messages, registry: ErrorRegistry): string[] {
  const findings: string[] = [];

  function requireMessage(key: string): void {
    if (!(key in en)) findings.push(`Missing error message: ${key} in en`);
    if (!(key in id)) findings.push(`Missing error message: ${key} in id`);
    for (const [locale, messages] of [
      ["en", en],
      ["id", id],
    ] as const) {
      const message = messages[key];
      if (message !== undefined && message.length > 140) {
        findings.push(`Error message exceeds 140 characters: ${key} in ${locale}`);
      }
    }
  }

  for (const code of Object.keys(registry.apiCodes)) {
    const titleKey = `error.${code}.title`;
    const detailKey = `error.${code}.detail`;
    requireMessage(titleKey);
    requireMessage(detailKey);
    if (en[titleKey] !== undefined && en[titleKey] !== registry.apiTitles[code]) {
      findings.push(`English error title differs from registry: ${code}`);
    }
  }

  for (const code of registry.fieldCodes) requireMessage(`error.field.${code}`);
  for (const code of ["network_error"]) {
    requireMessage(`error.${code}.title`);
    requireMessage(`error.${code}.detail`);
  }
  for (const code of registry.processCodes) {
    for (const suffix of ["title", "detail"]) {
      const key = `error.${code}.${suffix}`;
      if (key in en || key in id) findings.push(`Process-level code has a client message: ${key}`);
    }
  }

  return findings.sort();
}

export function checkIcu(messages: Messages, locale: string): string[] {
  const findings: string[] = [];

  for (const [key, message] of Object.entries(messages)) {
    try {
      new IntlMessageFormat(message, locale);
    } catch (error) {
      findings.push(`Invalid ICU message ${key} (${locale}): ${errorMessage(error)}`);
      continue;
    }

    const rules = pluralCategories(message);
    for (const rule of rules) {
      const allowed = new Set(
        new Intl.PluralRules(locale, { type: rule.type }).resolvedOptions().pluralCategories,
      );
      if (!rule.categories.has("other")) {
        findings.push(`Plural message ${key} (${locale}) has no other case`);
      }
      for (const category of rule.categories) {
        if (category.startsWith("=")) continue;
        if (!allowed.has(category as Intl.LDMLPluralRule)) {
          findings.push(
            `Plural message ${key} (${locale}) uses unsupported ${rule.type} case ${category}`,
          );
        }
      }
    }
  }

  return findings;
}

export function findLiteralJsxText(source: string): { line: number; text: string }[] {
  const sourceFile = ts.createSourceFile(
    "literal-text.tsx",
    source,
    ts.ScriptTarget.Latest,
    true,
    ts.ScriptKind.TSX,
  );
  const findings: { line: number; text: string }[] = [];

  function visit(node: ts.Node): void {
    if (ts.isJsxText(node)) {
      const text = node.text.replace(/\s+/g, " ").trim();
      if (/[\p{L}\p{N}]/u.test(text) && !isAriaHidden(node.parent)) {
        const line = sourceFile.getLineAndCharacterOfPosition(node.getStart(sourceFile)).line + 1;
        findings.push({ line, text });
      }
    }
    ts.forEachChild(node, visit);
  }

  visit(sourceFile);
  return findings;
}

function findLiteralJsxAttributes(source: string): { line: number; text: string }[] {
  const sourceFile = ts.createSourceFile(
    "literal-attributes.tsx",
    source,
    ts.ScriptTarget.Latest,
    true,
    ts.ScriptKind.TSX,
  );
  const visibleAttributes = new Set([
    "aria-label",
    "aria-description",
    "alt",
    "description",
    "label",
    "offLabel",
    "onLabel",
    "placeholder",
    "title",
  ]);
  const findings: { line: number; text: string }[] = [];

  function visit(node: ts.Node): void {
    if (ts.isJsxAttribute(node) && visibleAttributes.has(node.name.getText())) {
      const value = node.initializer;
      if (value && ts.isStringLiteral(value) && value.text.trim()) {
        const line = sourceFile.getLineAndCharacterOfPosition(value.getStart(sourceFile)).line + 1;
        findings.push({ line, text: value.text });
      }
    }
    ts.forEachChild(node, visit);
  }

  visit(sourceFile);
  return findings;
}

export function validateI18n(root = webRoot): string[] {
  const en = readJson(resolve(root, "src/i18n/en.json"));
  const id = readJson(resolve(root, "src/i18n/id.json"));
  const uxStates = readFileSync(resolve(root, "../spec/standards/ux-states.md"), "utf8");
  const errorRegistry = parseErrorRegistry(
    readFileSync(resolve(root, "../spec/standards/error-handling.md"), "utf8"),
  );
  const findings = [
    ...checkCatalogues(en, id, uxStates),
    ...checkErrorMessages(en, id, errorRegistry),
    ...checkIcu(en, "en"),
    ...checkIcu(id, "id"),
  ];
  const allowed = readAllowList(readFileSync(resolve(root, "scripts/i18n.allow"), "utf8"));
  const used = new Set<string>();
  const sourceRoot = resolve(root, "src");

  for (const file of sourceFiles(sourceRoot)) {
    const source = readFileSync(file, "utf8");
    const relativeFile = relative(root, file);
    const sourceFile = ts.createSourceFile(
      file,
      source,
      ts.ScriptTarget.Latest,
      true,
      file.endsWith(".tsx") ? ts.ScriptKind.TSX : ts.ScriptKind.TS,
    );
    collectTranslationCalls(sourceFile, used);

    for (const literal of findLiteralJsxText(source)) {
      findings.push(`${relativeFile}:${literal.line}: literal JSX text "${literal.text}"`);
    }
    for (const literal of findLiteralJsxAttributes(source)) {
      findings.push(`${relativeFile}:${literal.line}: literal JSX label "${literal.text}"`);
    }

    if (/\bt\s*\(\s*["'][^"']+["']\s*\)\s*\+\s*(?:t\s*\(|["'])/.test(source)) {
      findings.push(
        `${relativeFile}: concatenate complete messages with ICU arguments instead of translated fragments`,
      );
    }
  }

  for (const key of used) {
    if (!(key in en)) findings.push(`Unknown message key used in src: ${key}`);
    if (!(key in id)) findings.push(`Missing id message used in src: ${key}`);
  }

  for (const key of Object.keys(en)) {
    if (used.has(key)) continue;
    if (!allowed.some((rule) => rule.expression.test(key))) {
      findings.push(`Unused message has no reason in scripts/i18n.allow: ${key}`);
    }
  }

  return findings.sort();
}

function collectTranslationCalls(sourceFile: ts.SourceFile, used: Set<string>): void {
  function visit(node: ts.Node): void {
    if (ts.isCallExpression(node)) {
      const callee = node.expression;
      const isTranslationCall =
        (ts.isIdentifier(callee) && callee.text === "t") ||
        (ts.isPropertyAccessExpression(callee) && callee.name.text === "t");
      const key = node.arguments[0];
      if (
        isTranslationCall &&
        key &&
        (ts.isStringLiteral(key) || ts.isNoSubstitutionTemplateLiteral(key))
      ) {
        used.add(key.text);
      }
    }
    ts.forEachChild(node, visit);
  }

  visit(sourceFile);
}

function sourceFiles(directory: string): string[] {
  const files: string[] = [];
  for (const entry of readdirSync(directory, { withFileTypes: true })) {
    const path = resolve(directory, entry.name);
    if (entry.isDirectory()) {
      if (entry.name === "test" || entry.name === "__tests__") continue;
      files.push(...sourceFiles(path));
    } else if (/\.(tsx?|jsx?)$/.test(entry.name) && !/\.test\.[jt]sx?$/.test(entry.name)) {
      files.push(path);
    }
  }
  return files.sort();
}

function readAllowList(text: string): AllowRule[] {
  return text
    .split("\n")
    .map((line) => line.trim())
    .filter((line) => line && !line.startsWith("#"))
    .map((line) => {
      const separator = line.indexOf(" — ");
      if (separator < 0) throw new Error(`Allow-list entry needs a reason: ${line}`);
      const pattern = line.slice(0, separator).trim();
      const reason = line.slice(separator + 3).trim();
      if (!reason) throw new Error(`Allow-list entry needs a reason: ${line}`);
      const expression = new RegExp(`^${pattern.split("*").map(escapeRegExp).join(".*")}$`);
      return { pattern, reason, expression };
    });
}

function pluralCategories(
  message: string,
): { type: "cardinal" | "ordinal"; categories: Set<string> }[] {
  const rules: { type: "cardinal" | "ordinal"; categories: Set<string> }[] = [];

  function scan(text: string): void {
    for (let index = 0; index < text.length; index += 1) {
      if (text[index] !== "{") continue;
      const end = matchingBrace(text, index);
      if (end < 0) return;
      const expression = text.slice(index + 1, end);
      const parts = splitTopLevel(expression, ",", 3);
      const type = parts[1]?.trim();
      if ((type === "plural" || type === "selectordinal") && parts[2]) {
        const categories = optionCategories(parts[2]);
        rules.push({ type: type === "plural" ? "cardinal" : "ordinal", categories });
      }
      scan(expression);
      index = end;
    }
  }

  scan(message);
  return rules;
}

function optionCategories(style: string): Set<string> {
  const categories = new Set<string>();
  let index = 0;

  while (index < style.length) {
    while (/\s/.test(style[index] ?? "") || style[index] === ",") index += 1;
    if (index >= style.length) break;
    const selector = /^(=?[\w-]+)/.exec(style.slice(index));
    if (!selector) {
      index += 1;
      continue;
    }
    const key = selector[1] ?? "";
    if (!key) {
      index += 1;
      continue;
    }
    index += selector[0].length;
    while (/\s/.test(style[index] ?? "")) index += 1;
    if (style[index] !== "{") continue;
    categories.add(key);
    const end = matchingBrace(style, index);
    if (end < 0) break;
    index = end + 1;
  }

  return categories;
}

function splitTopLevel(input: string, separator: string, maxParts: number): string[] {
  const parts: string[] = [];
  let start = 0;
  let depth = 0;
  for (let index = 0; index < input.length && parts.length < maxParts - 1; index += 1) {
    if (input[index] === "{") depth += 1;
    if (input[index] === "}") depth -= 1;
    if (input[index] === separator && depth === 0) {
      parts.push(input.slice(start, index));
      start = index + 1;
    }
  }
  parts.push(input.slice(start));
  return parts;
}

function matchingBrace(input: string, start: number): number {
  let depth = 0;
  for (let index = start; index < input.length; index += 1) {
    if (input[index] === "{") depth += 1;
    if (input[index] === "}") {
      depth -= 1;
      if (depth === 0) return index;
    }
  }
  return -1;
}

function isAriaHidden(node: ts.Node): boolean {
  const attributes = ts.isJsxElement(node)
    ? node.openingElement.attributes
    : ts.isJsxSelfClosingElement(node)
      ? node.attributes
      : undefined;
  if (!attributes) return false;
  return attributes.properties.some(
    (attribute) =>
      ts.isJsxAttribute(attribute) &&
      attribute.name.getText() === "aria-hidden" &&
      attribute.initializer?.getText() === '"true"',
  );
}

function readJson(path: string): Messages {
  return JSON.parse(readFileSync(path, "utf8")) as Messages;
}

function errorMessage(error: unknown): string {
  return error instanceof Error ? error.message : String(error);
}

function escapeRegExp(value: string): string {
  return value.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
}

const invokedPath = process.argv[1] ? resolve(process.argv[1]) : "";
if (invokedPath === fileURLToPath(import.meta.url)) {
  const findings = validateI18n();
  if (findings.length) {
    for (const finding of findings) process.stderr.write(`${finding}\n`);
    process.exitCode = 1;
  } else {
    const en = readJson(resolve(webRoot, "src/i18n/en.json"));
    process.stdout.write(`check-i18n: ${Object.keys(en).length} messages, no findings\n`);
  }
}
