import { act, renderHook } from "@testing-library/react";
import { describe, expect, it } from "vitest";
import {
  catalogueKeysFromUxStates,
  checkCatalogues,
  checkIcu,
  findLiteralJsxText,
  validateI18n,
} from "../../scripts/check-i18n";
import { useFormat } from "../lib/format";
import en from "./en.json";
import { setAccountLocale, useT } from "./index";

describe("i18n", () => {
  it("i18n::uxs_01_all_catalogue_keys_present", async () => {
    expect(await validateI18n()).toEqual([]);
    expect(() =>
      catalogueKeysFromUxStates("## 2. Global\n| Key | Element | Unknown | ID | Actions |\n"),
    ).toThrow("Unrecognised UX table header");
  });

  it("i18n::fe_05_parity_failure_is_reported", () => {
    const findings = checkCatalogues({ "state.only_in_english": "English" }, {}, "");

    expect(findings.join("\n")).toContain("state.only_in_english");
  });

  it("i18n::icu_syntax_validated", () => {
    const findings = checkIcu({ "state.broken": "{count, plural, one {one item}}" }, "en");

    expect(findings.join("\n")).toContain("state.broken");
  });

  it("i18n::plural_rules_per_language", async () => {
    const { result } = renderHook(() => useT());

    await act(async () => setAccountLocale("en", "UTC"));
    expect(result.current("state.sync.pending", { count: 1 })).toBe("1 change waiting to sync");
    expect(result.current("state.sync.pending", { count: 2 })).toBe("2 changes waiting to sync");

    await act(async () => setAccountLocale("id", "Asia/Jakarta"));
    expect(result.current("state.sync.pending", { count: 1 })).toBe(
      "1 perubahan menunggu sinkronisasi",
    );
  });

  it("i18n::ds_a11y_10_html_lang_and_format", async () => {
    const { result } = renderHook(() => useFormat());

    await act(async () => setAccountLocale("id", "Asia/Jakarta"));

    expect(document.documentElement.lang).toBe("id");
    const formatted = result.current.date("2026-01-01T18:00:00Z", { dateStyle: "medium" });
    const expected = new Intl.DateTimeFormat("id", {
      dateStyle: "medium",
      timeZone: "Asia/Jakarta",
    }).format(new Date("2026-01-01T18:00:00Z"));
    const utc = new Intl.DateTimeFormat("id", { dateStyle: "medium", timeZone: "UTC" }).format(
      new Date("2026-01-01T18:00:00Z"),
    );

    expect(formatted).toBe(expected);
    expect(formatted).not.toBe(utc);
  });

  it("i18n::no_literal_text_in_jsx", () => {
    expect(findLiteralJsxText("const View = () => <button>Save changes</button>")).toEqual([
      { line: 1, text: "Save changes" },
    ]);
    expect(findLiteralJsxText("const View = () => <button>{t('action.save')}</button>")).toEqual(
      [],
    );
  });

  it("i18n::tpl_03_terms_are_message_keys", async () => {
    const keysBefore = Object.keys(en).sort();
    const { result } = renderHook(() => useT({ "term.goal": "term.task" }));

    await act(async () => setAccountLocale("en", "UTC"));

    expect(result.current("term.goal")).toBe(result.current("term.task"));
    expect(Object.keys(en).sort()).toEqual(keysBefore);
  });
});
