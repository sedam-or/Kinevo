//! Canonical `OpenAPI` contract assembled from the module route descriptors.

use axum::http::Method;
use kinevo_http::route::{Access, IdemMode, RouteSpec};
use kinevo_kernel::errors::ErrorCode;
use serde_json::{Map, Value, json};
use std::collections::{BTreeMap, BTreeSet};

/// Returns the route descriptors represented by the generated contract.
#[must_use]
pub fn route_specs() -> Vec<RouteSpec> {
    let mut specs = kinevo_mod_identity::http::route_specs();
    specs.extend(kinevo_mod_admin::http::route_specs());
    specs.extend(
        kinevo_http::system_routes::system_routes()
            .specs()
            .iter()
            .cloned(),
    );
    specs
}

/// Health descriptors for the standalone health router used by the composition root.
#[must_use]
pub(crate) fn active_health_route_specs() -> Vec<RouteSpec> {
    kinevo_http::system_routes::system_routes()
        .specs()
        .iter()
        .filter(|spec| matches!(spec.path, "/livez" | "/readyz"))
        .cloned()
        .collect()
}

/// Builds the `OpenAPI` 3.1 document using sorted JSON objects.
#[must_use]
pub fn document() -> Value {
    let mut paths = Map::new();
    for spec in route_specs() {
        let path = document_path(&spec);
        let path_item = paths.entry(path).or_insert_with(|| json!({}));
        if let Some(path_item) = path_item.as_object_mut() {
            path_item.insert(spec.method.as_str().to_ascii_lowercase(), operation(&spec));
        }
    }

    let mut schemas = kinevo_mod_identity::http::openapi::schemas();
    schemas.extend(kinevo_mod_admin::http::openapi::schemas());
    schemas.insert(
        "Problem".to_owned(),
        json!({
            "type": "object",
            "required": ["type", "title", "status", "detail", "code", "request_id"],
            "properties": {
                "type": {"type": "string", "format": "uri"},
                "title": {"type": "string"},
                "status": {"type": "integer", "minimum": 100, "maximum": 599},
                "detail": {"type": "string"},
                "code": {"type": "string"},
                "request_id": {"type": "string", "format": "uuid"},
                "details": {"type": "object", "additionalProperties": true},
                "errors": {"type": "array", "items": {"$ref": "#/components/schemas/ValidationError"}}
            }
        }),
    );
    schemas.insert(
        "ValidationError".to_owned(),
        json!({
            "type": "object",
            "required": ["path", "code"],
            "properties": {
                "path": {"type": "string"},
                "code": {"type": "string"},
                "details": {"type": "object", "additionalProperties": true}
            }
        }),
    );
    schemas.insert(
        "ValidationProblem".to_owned(),
        json!({"allOf": [{"$ref": "#/components/schemas/Problem"}]}),
    );
    schemas.insert(
        "PaginationEnvelope".to_owned(),
        json!({
            "type": "object",
            "required": ["items", "next_cursor"],
            "properties": {
                "items": {"type": "array", "items": {}},
                "next_cursor": {"type": ["string", "null"]}
            }
        }),
    );

    json!({
        "openapi": "3.1.0",
        "info": {
            "title": "Kinevo API",
            "version": env!("CARGO_PKG_VERSION")
        },
        "servers": [{"url": "/"}],
        "paths": paths,
        "components": {
            "schemas": schemas,
            "securitySchemes": {
                "bearerAuth": {"type": "http", "scheme": "bearer"}
            }
        }
    })
}

/// Renders the document as sorted, two-space-indented JSON with one trailing newline.
#[must_use]
pub fn render() -> String {
    let mut output = serde_json::to_string_pretty(&document()).unwrap_or_else(|_| "{}".to_owned());
    output.push('\n');
    output
}

/// Checks a committed contract against fresh generation and reports only byte counts on drift.
pub fn check(path: &std::path::Path) -> Result<(), String> {
    let generated = render();
    let committed = std::fs::read_to_string(path)
        .map_err(|_| format!("OpenAPI contract unavailable: {}", path.display()))?;
    if committed == generated {
        Ok(())
    } else {
        Err(format!(
            "OpenAPI drift: generated {} bytes, committed {} bytes",
            generated.len(),
            committed.len()
        ))
    }
}

fn document_path(spec: &RouteSpec) -> String {
    if matches!(spec.path, "/livez" | "/readyz") || spec.path.starts_with("/api/v1/") {
        spec.path.to_owned()
    } else if spec.path == "time" {
        "/api/v1/time".to_owned()
    } else {
        format!("/api/v1/{}", spec.path.trim_start_matches('/'))
    }
}

fn operation(spec: &RouteSpec) -> Value {
    let path = document_path(spec);
    let method = spec.method.as_str().to_ascii_lowercase();
    let mut operation = Map::new();
    operation.insert(
        "operationId".to_owned(),
        json!(operation_id(spec, &path, &method)),
    );
    operation.insert("summary".to_owned(), json!(spec.summary));
    operation.insert(
        "parameters".to_owned(),
        json!(operation_parameters(spec, &path)),
    );
    operation.insert("responses".to_owned(), Value::Object(operation_responses()));
    operation.insert("x-kinevo-module".to_owned(), json!(spec.module));
    operation.insert(
        "x-kinevo-rate-class".to_owned(),
        json!(format!("{:?}", spec.rate).to_ascii_lowercase()),
    );
    match spec.access {
        Access::Public => {
            operation.insert("security".to_owned(), json!([]));
        }
        Access::Permission(permission) => {
            operation.insert("security".to_owned(), json!([{"bearerAuth": []}]));
            operation.insert("x-kinevo-permission".to_owned(), json!(permission));
        }
    }
    if let Some(schema) = request_schema(spec, &path) {
        operation.insert(
            "requestBody".to_owned(),
            json!({
                "required": true,
                "content": {"application/json": {"schema": {"$ref": format!("#/components/schemas/{schema}")}}}
            }),
        );
    }
    if let Some(schema) = success_response_schema(spec, &path)
        && let Some(response) = operation
            .get_mut("responses")
            .and_then(Value::as_object_mut)
            .and_then(|responses| responses.get_mut("200"))
            .and_then(Value::as_object_mut)
    {
        response.insert(
            "content".to_owned(),
            json!({"application/json": {"schema": {"$ref": format!("#/components/schemas/{schema}")}}}),
        );
    }
    Value::Object(operation)
}

fn request_schema(spec: &RouteSpec, path: &str) -> Option<&'static str> {
    let method = spec.method.as_str();
    match (method, path) {
        ("POST", "/api/v1/auth/login") => Some("LoginDto"),
        ("POST", "/api/v1/auth/register") => Some("RegisterBody"),
        ("POST", "/api/v1/auth/refresh") => Some("RefreshBody"),
        ("POST", "/api/v1/auth/password-reset/request") => Some("PasswordResetRequest"),
        ("POST", "/api/v1/auth/password-reset/confirm") => Some("PasswordResetConfirm"),
        ("POST", "/api/v1/account/password") => Some("PasswordChangeBody"),
        ("POST", "/api/v1/account/totp/confirm") => Some("TotpCodeBody"),
        ("DELETE", "/api/v1/account/totp") => Some("TotpDisableBody"),
        ("DELETE", "/api/v1/account") => Some("DeletionBody"),
        ("PATCH", "/api/v1/admin/accounts/{id}") => Some("AccountStatusBody"),
        ("POST", "/api/v1/admin/invites") => Some("InviteBody"),
        ("PUT", "/api/v1/admin/modules/{key}") => Some("ModuleSwitchBody"),
        ("PUT", "/api/v1/policies/{key}") => Some("PolicyPutBody"),
        ("PUT", "/api/v1/admin/secrets/{name}") => Some("SecretPutBody"),
        ("POST", "/api/v1/setup/session") => Some("SetupTokenInput"),
        ("POST", "/api/v1/setup/session/{id}/finish") => Some("FinishRequest"),
        _ => None,
    }
}

fn success_response_schema(spec: &RouteSpec, path: &str) -> Option<&'static str> {
    let method = spec.method.as_str();
    match (method, path) {
        ("GET" | "PATCH", "/api/v1/account") | ("POST", "/api/v1/auth/register") => {
            Some("AccountProfile")
        }
        ("POST", "/api/v1/auth/login") => Some("LoginResponse"),
        ("POST", "/api/v1/auth/refresh") => Some("RefreshResponse"),
        ("GET", "/api/v1/account/sessions") => Some("SessionsPage"),
        ("GET", "/api/v1/modules") => Some("ModuleList"),
        ("GET", "/api/v1/policies") => Some("PolicyListResponse"),
        ("POST", "/api/v1/admin/invites") => Some("InviteResponse"),
        ("POST", "/api/v1/admin/accounts/{id}/password-reset") => Some("ResetResponse"),
        ("POST", "/api/v1/account/export") => Some("ExportRequestResponse"),
        ("POST", "/api/v1/account/totp/enroll") => Some("TotpEnrolmentResponse"),
        ("POST", "/api/v1/setup/session/{id}/finish") => Some("FinishResponse"),
        _ => None,
    }
}

fn operation_parameters(spec: &RouteSpec, path: &str) -> Vec<Value> {
    let mut parameters = path_parameters(path);
    if spec.method == Method::GET && is_paginated_path(path) {
        parameters.push(query_parameter(
            "limit",
            &json!({"type": "integer", "minimum": 1, "maximum": 200}),
            "Maximum number of rows to return; defaults to 50.",
        ));
        parameters.push(query_parameter(
            "cursor",
            &json!({"type": "string"}),
            "Opaque signed cursor returned by the previous page.",
        ));
        if path == "/api/v1/account/sessions" {
            parameters.push(query_parameter(
                "sort",
                &json!({"type": "string", "enum": ["-last_used_at"]}),
                "Stable session order.",
            ));
        }
        if path == "/api/v1/admin/audit" || path == "/api/v1/account/audit" {
            for (name, format) in [
                ("action", None),
                ("from", Some("date-time")),
                ("to", Some("date-time")),
                ("account_id", Some("uuid")),
            ] {
                let mut schema = Map::from_iter([("type".to_owned(), json!("string"))]);
                if let Some(format) = format {
                    schema.insert("format".to_owned(), json!(format));
                }
                parameters.push(query_parameter(
                    name,
                    &Value::Object(schema),
                    "Optional audit-log filter.",
                ));
            }
        }
    }
    if spec.idempotency != IdemMode::Off {
        parameters.push(header_parameter(
            "Idempotency-Key",
            "UUID used to make a command safe to retry.",
            true,
        ));
    }
    if path == "/api/v1/account" && spec.method == Method::PATCH {
        parameters.push(header_parameter(
            "If-Match",
            "Current resource version required for an update.",
            true,
        ));
    }
    if spec.cookie_endpoint {
        parameters.push(header_parameter(
            "X-Kinevo-Client",
            "Client transport used for session-cookie handling.",
            true,
        ));
    }
    parameters
}

fn operation_responses() -> Map<String, Value> {
    let mut responses = Map::new();
    responses.insert(
        "200".to_owned(),
        json!({"description": "Successful response"}),
    );
    for (status, codes) in error_codes_by_status() {
        let codes = codes.into_iter().map(Value::String).collect::<Vec<_>>();
        responses.insert(
            status.to_string(),
            json!({
                "description": "RFC 9457 problem response",
                "content": {
                    "application/problem+json": {
                        "schema": {"$ref": "#/components/schemas/Problem"}
                    }
                },
                "x-kinevo-error-codes": codes
            }),
        );
    }
    responses
}

fn path_parameters(path: &str) -> Vec<Value> {
    path.split('/')
        .filter_map(|segment| {
            segment
                .strip_prefix('{')
                .and_then(|parameter| parameter.strip_suffix('}'))
                .map(|name| {
                    json!({
                        "name": name,
                        "in": "path",
                        "required": true,
                        "schema": {"type": "string"}
                    })
                })
        })
        .collect()
}

fn header_parameter(name: &str, description: &str, required: bool) -> Value {
    json!({
        "name": name,
        "in": "header",
        "required": required,
        "description": description,
        "schema": {"type": "string"}
    })
}

fn query_parameter(name: &str, schema: &Value, description: &str) -> Value {
    json!({
        "name": name,
        "in": "query",
        "required": false,
        "description": description,
        "schema": schema
    })
}

fn is_paginated_path(path: &str) -> bool {
    matches!(
        path,
        "/api/v1/account/sessions"
            | "/api/v1/admin/accounts"
            | "/api/v1/admin/audit"
            | "/api/v1/account/audit"
    )
}

fn operation_id(spec: &RouteSpec, path: &str, method: &str) -> String {
    let noun = path
        .strip_prefix("/api/v1/")
        .unwrap_or(path)
        .split('/')
        .filter(|part| !part.is_empty())
        .map(|part| part.trim_matches(|character| character == '{' || character == '}'))
        .collect::<Vec<_>>()
        .join("_");
    format!("{}_{}_{}", spec.module, method, noun)
}

fn error_codes_by_status() -> BTreeMap<u16, Vec<String>> {
    let mut by_status = BTreeMap::<u16, Vec<String>>::new();
    for code in ErrorCode::ALL {
        by_status
            .entry(code.http_status())
            .or_default()
            .push(code.as_str().to_owned());
    }
    for codes in by_status.values_mut() {
        codes.sort_unstable();
        codes.dedup();
    }
    by_status
}

/// Stable route keys used by the composition-level coverage test.
#[must_use]
pub fn route_keys(specs: &[RouteSpec]) -> BTreeSet<String> {
    specs
        .iter()
        .map(|spec| {
            format!(
                "{} {} {} {:?} {:?} {:?} {} {}",
                spec.method,
                spec.path,
                spec.module,
                spec.access,
                spec.rate,
                spec.idempotency,
                spec.cookie_endpoint,
                spec.summary
            )
        })
        .collect()
}
