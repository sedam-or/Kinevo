import { mkdir, readFile, writeFile } from "node:fs/promises";
import { dirname, resolve } from "node:path";
import { fileURLToPath } from "node:url";
import { formatHex, parse } from "culori";

type ColorValue = {
  colorSpace: "oklch";
  components: [number, number, number];
  alpha: number;
};

type Token<TType extends string, TValue> = {
  $type: TType;
  $value: TValue;
};

type Dimension = { value: number; unit: string };
type ShadowLayer = {
  color: ColorValue;
  offsetX: Dimension;
  offsetY: Dimension;
  blur: Dimension;
  spread: Dimension;
  inset: boolean;
};

type TokenDocument = {
  color: Record<"light" | "dark", Record<string, Token<"color", ColorValue>>>;
  typography: {
    family: Record<string, Token<"fontFamily", string[]>>;
    size: Record<string, Token<"dimension", Dimension>>;
    lineHeight: Record<string, Token<"dimension", Dimension>>;
    weight: Record<string, Token<"fontWeight", number>>;
    numeric: Record<string, Token<"string", string>>;
  };
  spacing: Record<string, Token<"dimension", Dimension>>;
  radius: Record<string, Token<"dimension", Dimension>>;
  breakpoint: { lg: Token<"dimension", Dimension> };
  elevation: Record<
    "light" | "dark",
    Record<string, Token<"shadow", ShadowLayer[]> | Token<"string", string>>
  >;
  motion: {
    duration: Record<string, Token<"duration", string>>;
    easing: Record<string, Token<"cubicBezier", [number, number, number, number]>>;
  };
};

const webRoot = resolve(dirname(fileURLToPath(import.meta.url)), "..");
const sourcePath = resolve(webRoot, "src/styles/tokens.json");
const cssPath = resolve(webRoot, "src/styles/tokens.css");
const typesPath = resolve(webRoot, "src/styles/tokens.d.ts");
const tokens = JSON.parse(await readFile(sourcePath, "utf8")) as TokenDocument;

function number(value: number): string {
  return String(Object.is(value, -0) ? 0 : value);
}

function oklch(value: ColorValue): string {
  if (value.colorSpace !== "oklch") {
    throw new Error(`Expected an OKLCH token, received ${value.colorSpace}`);
  }
  const components = value.components.map(number).join(" ");
  return value.alpha === 1
    ? `oklch(${components})`
    : `oklch(${components} / ${number(value.alpha)})`;
}

function hex(value: ColorValue): string {
  const color = parse(oklch(value));
  if (!color) {
    throw new Error(`Could not parse color ${oklch(value)}`);
  }
  return formatHex(color);
}

function dimension(value: Dimension): string {
  return `${number(value.value)}${value.unit}`;
}

function shadowLayer(value: ShadowLayer): string {
  const inset = value.inset ? "inset " : "";
  return `${inset}${dimension(value.offsetX)} ${dimension(value.offsetY)} ${dimension(value.blur)} ${dimension(value.spread)} ${oklch(value.color)}`;
}

function serializeElevation(
  value: Token<"shadow", ShadowLayer[]> | Token<"string", string>,
): string {
  return value.$type === "shadow" ? value.$value.map(shadowLayer).join(", ") : value.$value;
}

function declaration(name: string, value: string): string {
  return `  ${name}: ${value};`;
}

function required<T>(values: Record<string, T>, name: string): T {
  const value = values[name];
  if (!value) {
    throw new Error(`Missing design token ${name}`);
  }
  return value;
}

function sharedDeclarations(): string[] {
  const output: string[] = [];
  for (const [name, token] of Object.entries(tokens.typography.family)) {
    const families = token.$value.map((family) => (family.includes(" ") ? `"${family}"` : family));
    output.push(declaration(`--token-font-family-${name}`, families.join(", ")));
  }
  for (const [name, token] of Object.entries(tokens.typography.size)) {
    if (name !== "display-wide") {
      output.push(declaration(`--token-font-size-${name}`, dimension(token.$value)));
    }
  }
  for (const [name, token] of Object.entries(tokens.typography.lineHeight)) {
    if (name !== "display-wide") {
      output.push(declaration(`--token-line-height-${name}`, dimension(token.$value)));
    }
  }
  for (const [name, token] of Object.entries(tokens.typography.weight)) {
    output.push(declaration(`--token-font-weight-${name}`, number(token.$value)));
  }
  for (const [name, token] of Object.entries(tokens.typography.numeric)) {
    output.push(declaration(`--token-font-variant-numeric-${name}`, token.$value));
  }
  for (const [name, token] of Object.entries(tokens.spacing)) {
    output.push(declaration(`--token-space-${name}`, dimension(token.$value)));
  }
  for (const [name, token] of Object.entries(tokens.radius)) {
    output.push(declaration(`--token-radius-${name}`, dimension(token.$value)));
  }
  for (const [name, token] of Object.entries(tokens.motion.duration)) {
    output.push(declaration(`--token-motion-${name}`, token.$value));
  }
  for (const [name, token] of Object.entries(tokens.motion.easing)) {
    output.push(
      declaration(`--token-easing-${name}`, `cubic-bezier(${token.$value.map(number).join(", ")})`),
    );
  }
  return output;
}

function themeDeclarations(theme: "light" | "dark"): string[] {
  const output = [`  color-scheme: ${theme};`];
  for (const [name, token] of Object.entries(tokens.color[theme])) {
    output.push(declaration(`--token-color-${name}`, oklch(token.$value)));
  }
  for (const [name, token] of Object.entries(tokens.elevation[theme])) {
    output.push(declaration(`--token-${name}`, serializeElevation(token)));
  }
  return output;
}

function rule(selector: string, declarations: string[]): string {
  return `${selector} {\n${declarations.join("\n")}\n}`;
}

function themeBlock(theme: "light" | "dark", selector: string): string {
  return rule(selector, [...themeDeclarations(theme), ...sharedDeclarations()]);
}

function fallbackBlock(theme: "light" | "dark", selector: string): string {
  const declarations = Object.entries(tokens.color[theme]).map(([name, token]) =>
    declaration(`--token-color-${name}`, hex(token.$value)),
  );
  return rule(selector, declarations);
}

function tailwindTheme(): string {
  const entries: string[] = [];
  for (const name of Object.keys(tokens.color.light)) {
    entries.push(declaration(`--color-${name}`, `var(--token-color-${name})`));
  }
  for (const [name] of Object.entries(tokens.typography.family)) {
    entries.push(declaration(`--font-${name}`, `var(--token-font-family-${name})`));
  }
  for (const [name] of Object.entries(tokens.typography.size)) {
    if (name !== "display-wide" && name !== "display") {
      const scaleName = name.startsWith("text-") ? name.slice("text-".length) : name;
      entries.push(declaration(`--text-${scaleName}`, `var(--token-font-size-${name})`));
      entries.push(
        declaration(`--text-${scaleName}--line-height`, `var(--token-line-height-${name})`),
      );
    }
  }
  entries.push(declaration("--text-display", "var(--token-font-size-display)"));
  entries.push(declaration("--text-display--line-height", "var(--token-line-height-display)"));
  for (const [name] of Object.entries(tokens.typography.weight)) {
    entries.push(declaration(`--font-weight-${name}`, `var(--token-font-weight-${name})`));
  }
  for (const [name] of Object.entries(tokens.spacing)) {
    entries.push(declaration(`--spacing-${name}`, `var(--token-space-${name})`));
  }
  for (const [name] of Object.entries(tokens.radius)) {
    entries.push(declaration(`--radius-${name}`, `var(--token-radius-${name})`));
  }
  for (const name of Object.keys(tokens.elevation.light)) {
    if (name !== "pressed-transform" && name !== "pressed-shadow") {
      const shadowName = name.startsWith("shadow-") ? name.slice("shadow-".length) : name;
      entries.push(declaration(`--shadow-${shadowName}`, `var(--token-${name})`));
    }
  }
  for (const [name] of Object.entries(tokens.motion.duration)) {
    entries.push(declaration(`--duration-${name}`, `var(--token-motion-${name})`));
  }
  for (const [name] of Object.entries(tokens.motion.easing)) {
    entries.push(declaration(`--ease-${name}`, `var(--token-easing-${name})`));
  }
  return `@theme inline {\n${entries.join("\n")}\n}`;
}

function generateCss(): string {
  const breakpoint = dimension(tokens.breakpoint.lg.$value);
  const displaySize = required(tokens.typography.size, "display-wide");
  const displayLineHeight = required(tokens.typography.lineHeight, "display-wide");
  return [
    "/* Generated by `pnpm tokens`; edit tokens.json instead. */",
    themeBlock("light", ':root, :root[data-theme="light"]'),
    themeBlock("dark", ':root[data-theme="dark"]'),
    "@media (prefers-color-scheme: dark) {\n  :root:not([data-theme]) {\n" +
      [...themeDeclarations("dark"), ...sharedDeclarations()]
        .map((line) => `  ${line}`)
        .join("\n") +
      "\n  }\n}",
    `@media (min-width: ${breakpoint}) {\n  :root, :root[data-theme="light"], :root[data-theme="dark"] {\n` +
      `${declaration("--token-font-size-display", dimension(displaySize.$value))}\n` +
      `${declaration("--token-line-height-display", dimension(displayLineHeight.$value))}` +
      "\n  }\n}",
    "@supports not (color: oklch(0 0 0)) {",
    `  ${fallbackBlock("light", ':root, :root[data-theme="light"]').replaceAll("\n", "\n  ")}`,
    `  ${fallbackBlock("dark", ':root[data-theme="dark"]').replaceAll("\n", "\n  ")}`,
    "  @media (prefers-color-scheme: dark) {",
    `    ${fallbackBlock("dark", ":root:not([data-theme])").replaceAll("\n", "\n    ")}`,
    "  }",
    "}",
    tailwindTheme(),
    "",
  ].join("\n");
}

function union(name: string, values: string[]): string {
  return `export type ${name} = ${values.map((value) => JSON.stringify(value)).join(" | ")};`;
}

function generateTypes(): string {
  return [
    "/* Generated by `pnpm tokens`; edit tokens.json instead. */",
    union("Theme", ["light", "dark"]),
    union("ColorToken", Object.keys(tokens.color.light)),
    union("FontFamilyToken", Object.keys(tokens.typography.family)),
    union(
      "TypeScaleToken",
      Object.keys(tokens.typography.size).filter((name) => name !== "display-wide"),
    ),
    union("SpacingToken", Object.keys(tokens.spacing)),
    union("RadiusToken", Object.keys(tokens.radius)),
    union("ElevationToken", Object.keys(tokens.elevation.light)),
    union("MotionDurationToken", Object.keys(tokens.motion.duration)),
    union("MotionEasingToken", Object.keys(tokens.motion.easing)),
    "",
  ].join("\n");
}

await mkdir(dirname(cssPath), { recursive: true });
await writeFile(cssPath, generateCss());
await writeFile(typesPath, generateTypes());
