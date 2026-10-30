#!/usr/bin/env python3
"""Check gamut and WCAG 2.x contrast for the design tokens in tokens.json.

Usage: python3 scripts/contrast.py [--markdown]
The JSON is the source for spec/09-design-system.md section 3; its generated
tables are pasted from this script's --markdown output (never typed by hand).
Exit code 1 if any required pair fails or any colour is outside the sRGB gamut.
"""
import json
import math
import sys
from pathlib import Path


def oklch_to_linear_srgb(L, C, h):
    a = C * math.cos(math.radians(h))
    b = C * math.sin(math.radians(h))
    l_ = L + 0.3963377774 * a + 0.2158037573 * b
    m_ = L - 0.1055613458 * a - 0.0638541728 * b
    s_ = L - 0.0894841775 * a - 1.2914855480 * b
    l, m, s = l_ ** 3, m_ ** 3, s_ ** 3
    return (
        4.0767416621 * l - 3.3077115913 * m + 0.2309699292 * s,
        -1.2684380046 * l + 2.6097574011 * m - 0.3413193965 * s,
        -0.0041960863 * l - 0.7034186147 * m + 1.7076147010 * s,
    )


def in_gamut(rgb):
    return all(-1e-4 <= c <= 1 + 1e-4 for c in rgb)


def clamp(c):
    return min(1.0, max(0.0, c))


def luminance(rgb):
    r, g, b = (clamp(c) for c in rgb)
    return 0.2126 * r + 0.7152 * g + 0.0722 * b


def ratio(a, b):
    la, lb = luminance(a), luminance(b)
    if la < lb:
        la, lb = lb, la
    return (la + 0.05) / (lb + 0.05)


def encode(c):
    c = clamp(c)
    v = 12.92 * c if c <= 0.0031308 else 1.055 * c ** (1 / 2.4) - 0.055
    return round(v * 255)


def to_hex(rgb):
    return "#%02x%02x%02x" % tuple(encode(c) for c in rgb)


# (foreground, background, minimum ratio): text 4.5, graphics/focus 3.0
PAIRS = [
    ("ink", "paper", 4.5), ("ink", "surface", 4.5), ("ink", "sunken", 4.5),
    ("ink-muted", "paper", 4.5), ("ink-muted", "surface", 4.5), ("ink-subtle", "paper", 4.5),
    ("accent", "paper", 4.5), ("accent", "surface", 4.5), ("accent-ink", "accent", 4.5),
    ("ink", "accent-subtle", 4.5), ("accent", "accent-subtle", 4.5),
    ("success", "paper", 4.5), ("warning", "paper", 4.5), ("danger", "paper", 4.5), ("info", "paper", 4.5),
    ("rule-strong", "paper", 3.0), ("focus-ring", "paper", 3.0), ("focus-ring", "surface", 3.0),
]


def read_palette():
    path = Path(__file__).resolve().parents[1] / "web/src/styles/tokens.json"
    with path.open(encoding="utf-8") as source:
        tokens = json.load(source)

    palette = {}
    for theme in ("light", "dark"):
        colors = {}
        for name, token in tokens["color"][theme].items():
            value = token["$value"]
            if token.get("$type") != "color" or value.get("colorSpace") != "oklch":
                raise ValueError(f"{theme}.{name} must be an OKLCH color token")
            components = value["components"]
            if len(components) != 3:
                raise ValueError(f"{theme}.{name} must have three OKLCH components")
            colors[name] = tuple(components)
        palette[theme] = colors
    return palette


def main():
    markdown = "--markdown" in sys.argv
    try:
        palette = read_palette()
    except (OSError, json.JSONDecodeError, KeyError, TypeError, ValueError) as error:
        print(f"contrast: unable to read token JSON: {error}", file=sys.stderr)
        return 1

    failures = 0
    for name in ("light", "dark"):
        theme = palette[name]
        cols = {k: oklch_to_linear_srgb(*v) for k, v in theme.items()}
        if markdown:
            print(f"\n#### {name} tokens\n\n| Token | OKLCH | sRGB hex (fallback) |\n|---|---|---|")
        else:
            print(f"== {name}")
        for k, v in theme.items():
            ok = in_gamut(cols[k])
            failures += 0 if ok else 1
            if markdown:
                print(f"| `{k}` | `oklch({v[0]} {v[1]} {v[2]})` | `{to_hex(cols[k])}`{'' if ok else ' **OUT OF GAMUT**'} |")
            else:
                print(f"  {k:14s} oklch({v[0]} {v[1]} {v[2]}) {to_hex(cols[k])} gamut={ok}")
        if markdown:
            print(f"\n#### {name} contrast (computed)\n\n| Foreground | Background | Ratio | Required | Result |\n|---|---|---|---|---|")
        for fg, bg, need in PAIRS:
            r = ratio(cols[fg], cols[bg])
            ok = r >= need
            failures += 0 if ok else 1
            if markdown:
                print(f"| `{fg}` | `{bg}` | {r:.2f} | {need} | {'pass' if ok else '**FAIL**'} |")
            else:
                print(f"  {fg:12s} on {bg:14s} {r:5.2f} need {need} {'OK' if ok else 'FAIL'}")
    print(f"\nfailures: {failures}", file=sys.stderr)
    return 1 if failures else 0


if __name__ == "__main__":
    sys.exit(main())
