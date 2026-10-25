#![allow(
    clippy::expect_used,
    clippy::unwrap_used,
    clippy::indexing_slicing,
    reason = "registry sync tests use assertions with explicit failure messages"
)]

use kinevo_kernel::errors::{ErrorCode, FieldCode, RetryClass};
use regex::Regex;
use std::{collections::BTreeMap, fs, path::PathBuf};

fn registry() -> String {
    let path =
        PathBuf::from(env!("CARGO_MANIFEST_DIR")).join("../../spec/standards/error-handling.md");
    fs::read_to_string(path).expect("error-handling registry is readable")
}

fn table_codes(spec: &str) -> BTreeMap<String, (u16, char)> {
    let row = Regex::new(r"^\| `([a-z0-9_]+)` \| (\d{3}) \| ([NCBR]) \|")
        .expect("registry row pattern is valid");
    spec.lines()
        .filter_map(|line| row.captures(line))
        .map(|captures| {
            let code = captures.get(1).expect("code capture exists").as_str();
            let status = captures
                .get(2)
                .expect("status capture exists")
                .as_str()
                .parse::<u16>()
                .expect("registry status is numeric");
            let retry = captures
                .get(3)
                .expect("retry capture exists")
                .as_str()
                .chars()
                .next()
                .expect("retry capture is one letter");
            (code.to_owned(), (status, retry))
        })
        .collect()
}

fn retry_letter(retry: RetryClass) -> char {
    match retry {
        RetryClass::Never => 'N',
        RetryClass::AfterChange => 'C',
        RetryClass::Backoff => 'B',
        RetryClass::Reauth => 'R',
    }
}

#[test]
fn err_01_enum_matches_spec_registry() {
    let expected = table_codes(&registry());
    let actual: BTreeMap<_, _> = ErrorCode::ALL
        .iter()
        .map(|code| {
            (
                code.as_str().to_owned(),
                (code.http_status(), retry_letter(code.retry())),
            )
        })
        .collect();

    assert_eq!(actual, expected);
}

#[test]
fn err_01_field_codes_match_spec() {
    let spec = registry();
    let section = spec
        .split("### 2.2 Field-level validation codes")
        .nth(1)
        .expect("field-level registry section exists")
        .split("### 2.3")
        .next()
        .expect("field-level registry section ends before 2.3");
    let code_list = section
        .lines()
        .map(str::trim)
        .filter(|line| !line.is_empty())
        .nth(1)
        .expect("field-level code list exists")
        .split(". `details`")
        .next()
        .expect("field-code list ends before details");
    let expected: std::collections::BTreeSet<_> = code_list
        .split(',')
        .map(str::trim)
        .map(|code| code.trim_matches('`'))
        .collect();
    let actual: std::collections::BTreeSet<_> =
        FieldCode::ALL.iter().map(|code| code.as_str()).collect();

    assert_eq!(actual, expected);
}

#[test]
fn process_codes_are_not_api_codes() {
    let spec = registry();
    let process_section = spec
        .split("### 2.9 Non-HTTP (process-level) codes")
        .nth(1)
        .expect("process-level registry section exists");
    let code_pattern = Regex::new(r"`([a-z0-9_]+)`").expect("process-code pattern is valid");
    let api_codes: std::collections::BTreeSet<_> =
        ErrorCode::ALL.iter().map(|code| code.as_str()).collect();

    for capture in code_pattern.captures_iter(process_section) {
        let process_code = capture
            .get(1)
            .expect("process code capture exists")
            .as_str();
        assert!(
            !api_codes.contains(process_code),
            "{process_code} is process-only"
        );
    }
}
