#![allow(
    clippy::expect_used,
    clippy::unwrap_used,
    clippy::indexing_slicing,
    reason = "acceptance tests use explicit synthetic requests"
)]

#[path = "support/mod.rs"]
mod support;

use axum::{
    Json,
    http::{Method, StatusCode},
    routing::patch,
};
use kinevo_http::{
    concurrency::{IfMatch, Version, version_conflict},
    problem::ApiError,
    route::{Access, IdemMode},
};
use serde::Serialize;
use serde_json::{Value, json};
use std::error::Error;
use support::{fixture, request, route_spec, router, send};

#[derive(Serialize)]
struct Resource {
    id: u64,
    title: &'static str,
    version: i64,
}

async fn update(IfMatch(Version(version)): IfMatch) -> Json<Value> {
    Json(json!({"version": version}))
}

async fn conflict() -> ApiError {
    ApiError(version_conflict(
        8,
        &Resource {
            id: 7,
            title: "synthetic",
            version: 8,
        },
    ))
}

#[tokio::test]
async fn api_60_extractor_cases() -> Result<(), Box<dyn Error>> {
    let fixture = fixture()?;
    let app = router(
        fixture.state.clone(),
        route_spec(
            Method::PATCH,
            "/versioned",
            Access::Public,
            IdemMode::Off,
            true,
            false,
        ),
        patch(update),
    )?;
    let token = fixture.token(fixture.account_a);
    let missing = send(
        app.clone(),
        request(Method::PATCH, "/api/v1/versioned", Some(&token), b""),
    )
    .await?;
    assert_eq!(missing.status, StatusCode::PRECONDITION_REQUIRED);
    let mut unquoted_request = request(Method::PATCH, "/api/v1/versioned", Some(&token), b"");
    unquoted_request
        .headers_mut()
        .insert("if-match", "7".parse()?);
    let unquoted = send(app.clone(), unquoted_request).await?;
    assert_eq!(unquoted.status, StatusCode::UNPROCESSABLE_ENTITY);
    let mut quoted_request = request(Method::PATCH, "/api/v1/versioned", Some(&token), b"");
    quoted_request
        .headers_mut()
        .insert("if-match", "\"7\"".parse()?);
    let quoted = send(app, quoted_request).await?;
    assert_eq!(quoted.status, StatusCode::OK);
    assert_eq!(serde_json::from_slice::<Value>(&quoted.body)?["version"], 7);
    Ok(())
}

#[tokio::test]
async fn api_60_conflict_body() -> Result<(), Box<dyn Error>> {
    let fixture = fixture()?;
    let app = router(
        fixture.state,
        route_spec(
            Method::POST,
            "/conflict",
            Access::Public,
            IdemMode::Off,
            true,
            false,
        ),
        axum::routing::post(conflict),
    )?;
    let response = send(app, request(Method::POST, "/api/v1/conflict", None, b"")).await?;
    assert_eq!(response.status, StatusCode::CONFLICT);
    let json: Value = serde_json::from_slice(&response.body)?;
    assert_eq!(json["details"]["current_version"], 8);
    assert_eq!(
        json["details"]["current"],
        json!({"id":7,"title":"synthetic","version":8})
    );
    Ok(())
}
