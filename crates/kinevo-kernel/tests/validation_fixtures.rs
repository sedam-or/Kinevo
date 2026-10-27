#![allow(
    clippy::unwrap_used,
    clippy::expect_used,
    clippy::indexing_slicing,
    clippy::panic,
    reason = "fixed shared fixtures are malformed only by a source change"
)]

use kinevo_kernel::errors::FieldCode;
use kinevo_kernel::validate::{
    bounded_int, enum_check,
    net::{Email, HttpsUrl},
    text::{Digits, Key, OpaqueToken, SecretText, ShortText},
    tz::IanaTz,
};
use regex::Regex;
use serde_json::{Value, json};
use std::{fs, path::Path};

fn parse(kind: &str, input: &Value, params: &Value) -> Result<Value, FieldCode> {
    let raw = input.as_str().unwrap_or_default();
    match kind {
        "short_text" => ShortText::parse(
            raw,
            usize::try_from(params["min"].as_u64().unwrap()).unwrap(),
            usize::try_from(params["max"].as_u64().unwrap()).unwrap(),
        )
        .map(|value| Value::String(value.as_str().to_owned())),
        "secret_text" => {
            SecretText::parse(raw.to_owned()).map(|value| Value::String(value.expose().to_owned()))
        }
        "email" => Email::parse(raw).map(|value| Value::String(value.as_str().to_owned())),
        "key" => {
            let pattern = Regex::new(params["pattern"].as_str().unwrap()).unwrap();
            Key::parse(raw, &pattern).map(|value| Value::String(value.as_str().to_owned()))
        }
        "https_url" => HttpsUrl::parse(raw).map(|value| Value::String(value.as_str().to_owned())),
        "iana_tz" => IanaTz::parse(raw).map(|value| Value::String(value.as_str().to_owned())),
        "digits" => Digits::parse(
            raw,
            usize::try_from(params["len"].as_u64().unwrap()).unwrap(),
        )
        .map(|value| Value::String(value.as_str().to_owned())),
        "opaque_token" => {
            OpaqueToken::parse(raw).map(|value| Value::String(value.as_str().to_owned()))
        }
        "enum" => {
            let allowed = params["allowed"]
                .as_array()
                .unwrap()
                .iter()
                .map(|value| value.as_str().unwrap())
                .collect::<Vec<_>>();
            enum_check(raw, &allowed)
                .map(|value| Value::String(value.to_owned()))
                .map_err(|(code, _allowed)| code)
        }
        "int" => {
            let value = input.as_i64().unwrap();
            bounded_int(
                value,
                params["min"].as_i64().unwrap(),
                params["max"].as_i64().unwrap(),
            )
            .map(|value| json!(value))
        }
        _ => panic!("unknown fixture kind: {kind}"),
    }
}

#[test]
fn val_fixtures_all_kinds() {
    let root = Path::new(env!("CARGO_MANIFEST_DIR")).join("../../spec/fixtures/validation");
    let mut files = fs::read_dir(root)
        .unwrap()
        .map(|entry| entry.unwrap().path())
        .filter(|path| {
            path.extension()
                .is_some_and(|extension| extension == "json")
        })
        .collect::<Vec<_>>();
    files.sort();
    assert_eq!(files.len(), 10);

    for path in files {
        let fixture: Value = serde_json::from_slice(&fs::read(path).unwrap()).unwrap();
        let kind = fixture["kind"].as_str().unwrap();
        for example in fixture["valid"].as_array().unwrap() {
            assert_eq!(
                parse(kind, &example["input"], &fixture["params"]).unwrap(),
                example["normalised"],
                "{kind} valid example"
            );
        }
        for example in fixture["invalid"].as_array().unwrap() {
            let actual = parse(kind, &example["input"], &fixture["params"])
                .err()
                .map(FieldCode::as_str);
            assert_eq!(actual, example["code"].as_str(), "{kind} invalid example");
        }
    }
}
