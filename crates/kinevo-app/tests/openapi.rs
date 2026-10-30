#![allow(
    clippy::expect_used,
    clippy::panic,
    reason = "contract tests should report assertion setup failures directly"
)]

use assert_cmd::Command;
use kinevo_app::openapi;
use kinevo_app::{config::Config, serve::prepare};
use kinevo_db::testing::TestApp;
use kinevo_http::route::{IdemMode, RouteSpec};
use kinevo_kernel::errors::ErrorCode;
use kinevo_kernel::time::Clock;
use serde_json::Value;
use std::{
    collections::{BTreeMap, BTreeSet},
    io,
    path::Path,
    sync::Arc,
};

fn route_path(spec: &RouteSpec) -> String {
    if matches!(spec.path, "/livez" | "/readyz") || spec.path.starts_with("/api/v1/") {
        spec.path.to_owned()
    } else {
        format!("/api/v1/{}", spec.path.trim_start_matches('/'))
    }
}

fn test_config(app: &TestApp) -> Config {
    let values = BTreeMap::from([
        (
            "KINEVO_DATABASE_URL".to_owned(),
            app.app_database_url().to_owned(),
        ),
        (
            "KINEVO_MIGRATOR_DATABASE_URL".to_owned(),
            app.migrator_database_url().to_owned(),
        ),
        (
            "KINEVO_SYSTEM_DATABASE_URL".to_owned(),
            app.system_database_url().to_owned(),
        ),
        (
            "KINEVO_SECRET_KEY".to_owned(),
            "AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=".to_owned(),
        ),
        (
            "KINEVO_PUBLIC_URL".to_owned(),
            "http://127.0.0.1".to_owned(),
        ),
        ("KINEVO_LISTEN".to_owned(), "127.0.0.1:0".to_owned()),
        ("KINEVO_AUTO_MIGRATE".to_owned(), "true".to_owned()),
    ]);
    match Config::from_env(&|name| values.get(name).cloned(), &|_: &Path| {
        Err(io::Error::new(
            io::ErrorKind::NotFound,
            "no synthetic file configured",
        ))
    }) {
        Ok(config) => config,
        Err(issues) => panic!("synthetic configuration should be valid: {issues:?}"),
    }
}

#[tokio::test]
async fn every_route_documented() {
    let database = TestApp::builder()
        .build()
        .await
        .expect("shared PostgreSQL should create an isolated contract-test database");
    let app = prepare(
        test_config(&database),
        Arc::clone(&database.clock) as Arc<dyn Clock>,
    )
    .await
    .expect("application router should prepare with migrations enabled");
    let document = openapi::document();
    let declared_routes = openapi::route_specs();
    let active_routes = &app.route_specs;
    let paths = document
        .get("paths")
        .and_then(Value::as_object)
        .expect("OpenAPI paths should be an object");
    let expected_paths = declared_routes
        .iter()
        .map(route_path)
        .collect::<BTreeSet<_>>();
    let actual_paths = paths.keys().cloned().collect::<BTreeSet<_>>();

    assert_eq!(
        actual_paths, expected_paths,
        "documented paths must match the route inventory"
    );
    let declared_keys = openapi::route_keys(&declared_routes);
    for route_key in openapi::route_keys(active_routes) {
        assert!(
            declared_keys.contains(&route_key),
            "active route is absent from OpenAPI inventory: {route_key}"
        );
    }
    for route in active_routes {
        let path = route_path(route);
        let method = route.method.as_str().to_ascii_lowercase();
        assert!(
            paths
                .get(&path)
                .and_then(|path_item| path_item.get(&method))
                .is_some_and(Value::is_object),
            "active route {} {} is missing from OpenAPI",
            route.method,
            path
        );
    }
    let operation_ids = paths
        .values()
        .flat_map(|item| {
            item.as_object()
                .into_iter()
                .flat_map(|methods| methods.values())
        })
        .filter_map(|operation| operation["operationId"].as_str())
        .collect::<BTreeSet<_>>();
    let operation_count = paths
        .values()
        .flat_map(|item| {
            item.as_object()
                .into_iter()
                .flat_map(|methods| methods.values())
        })
        .count();
    assert_eq!(
        operation_ids.len(),
        operation_count,
        "operationIds should be unique"
    );
    let schemas = document
        .get("components")
        .and_then(|components| components.get("schemas"))
        .and_then(Value::as_object)
        .expect("module schema fragments should be present");
    assert_component_refs_exist(&document, schemas);
}

fn assert_component_refs_exist(value: &Value, schemas: &serde_json::Map<String, Value>) {
    match value {
        Value::Array(values) => {
            for value in values {
                assert_component_refs_exist(value, schemas);
            }
        }
        Value::Object(object) => {
            if let Some(Value::String(reference)) = object.get("$ref")
                && let Some(name) = reference.strip_prefix("#/components/schemas/")
            {
                assert!(
                    schemas.contains_key(name),
                    "unresolved OpenAPI schema reference: {reference}"
                );
            }
            for value in object.values() {
                assert_component_refs_exist(value, schemas);
            }
        }
        Value::Null | Value::Bool(_) | Value::Number(_) | Value::String(_) => {}
    }
}

#[test]
fn tst_04_no_drift() {
    let path = Path::new(env!("CARGO_MANIFEST_DIR")).join("../../contracts/openapi.json");
    let output = Command::cargo_bin("kinevo")
        .expect("kinevo binary should be available")
        .args(["openapi", "--check"])
        .arg(path)
        .output()
        .expect("OpenAPI drift check should run");
    assert!(
        output.status.success(),
        "committed OpenAPI document drifted: {}",
        String::from_utf8_lossy(&output.stderr)
    );
    assert_eq!(
        openapi::render(),
        std::fs::read_to_string(
            Path::new(env!("CARGO_MANIFEST_DIR")).join("../../contracts/openapi.json")
        )
        .expect("committed OpenAPI document should be readable")
    );

    let mismatch =
        std::env::temp_dir().join(format!("kinevo-openapi-drift-{}.json", std::process::id()));
    std::fs::write(&mismatch, "{}\n").expect("drift fixture should be writable");
    let output = Command::cargo_bin("kinevo")
        .expect("kinevo binary should be available")
        .args(["openapi", "--check"])
        .arg(&mismatch)
        .output()
        .expect("OpenAPI drift check should run");
    let _ = std::fs::remove_file(&mismatch);
    assert_eq!(output.status.code(), Some(1));
    assert!(String::from_utf8_lossy(&output.stderr).contains("OpenAPI drift"));
}

#[test]
fn errors_use_registry_codes() {
    let document = openapi::document();
    let registered = ErrorCode::ALL
        .iter()
        .map(|code| code.as_str())
        .collect::<BTreeSet<_>>();
    for (path, path_item) in document
        .get("paths")
        .and_then(Value::as_object)
        .expect("OpenAPI paths should be an object")
    {
        for (method, operation) in path_item
            .as_object()
            .expect("path item should be an object")
        {
            let responses = operation["responses"]
                .as_object()
                .expect("each operation should document responses");
            let error_responses = responses
                .values()
                .filter(|response| {
                    response
                        .get("content")
                        .and_then(|content| content.get("application/problem+json"))
                        .is_some_and(Value::is_object)
                })
                .collect::<Vec<_>>();
            assert!(
                !error_responses.is_empty(),
                "{method} {path} has no problem+json response"
            );
            for response in error_responses {
                let codes = response
                    .get("x-kinevo-error-codes")
                    .and_then(Value::as_array)
                    .expect("problem responses should list registry codes");
                for code in codes {
                    assert!(
                        registered.contains(&code.as_str().expect("error code should be a string")),
                        "unregistered OpenAPI error code: {code}"
                    );
                }
            }
        }
    }
}

fn contains_secret_pattern(value: &Value) -> bool {
    match value {
        Value::String(text) => {
            text.contains("sk-")
                || text.contains("Bearer ey")
                || text
                    .split(|character: char| {
                        !character.is_ascii_alphanumeric() && character != '_' && character != '-'
                    })
                    .any(|token| token.len() == 43)
        }
        Value::Array(values) => values.iter().any(contains_secret_pattern),
        Value::Object(values) => values.values().any(contains_secret_pattern),
        _ => false,
    }
}

#[test]
fn sec_ac_11_no_secret_examples() {
    assert!(
        !contains_secret_pattern(&openapi::document()),
        "OpenAPI examples or descriptions contain a secret-like value"
    );
}

#[test]
fn idempotency_header_documented() {
    let document = openapi::document();
    let route = openapi::route_specs()
        .into_iter()
        .find(|route| route.idempotency != IdemMode::Off)
        .expect("the route inventory should contain an idempotent route");
    let path = route_path(&route);
    let method = route.method.as_str().to_ascii_lowercase();
    let parameters = document
        .get("paths")
        .and_then(|paths| paths.get(&path))
        .and_then(|path_item| path_item.get(&method))
        .and_then(|operation| operation.get("parameters"))
        .and_then(Value::as_array)
        .expect("idempotent route should have parameters");
    assert!(parameters.iter().any(|parameter| {
        parameter.get("name").and_then(Value::as_str) == Some("Idempotency-Key")
            && parameter.get("required").and_then(Value::as_bool) == Some(true)
    }));
}

#[test]
fn deterministic_output() {
    assert_eq!(openapi::render(), openapi::render());
}
