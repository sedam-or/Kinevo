use axum::{
    Json,
    http::{HeaderValue, header},
    response::{IntoResponse, Response},
};
use kinevo_kernel::errors::AppError;
use serde::Serialize;

use crate::{
    app::{
        login::LoginOutcome,
        refresh::{Client, Refreshed},
    },
    http::{cookie::build_refresh_cookie, dto::LoginResponse},
};

/// Delivers the refresh credential through the web cookie or Android response body.
pub fn session_response(
    client: Client,
    outcome: LoginOutcome,
    secure: bool,
) -> Result<Response, AppError> {
    let android = client == Client::Android;
    let (body, refresh_token, max_age) = LoginResponse::from_outcome(outcome, android);
    let mut response = Json(body).into_response();
    response
        .headers_mut()
        .insert(header::CACHE_CONTROL, HeaderValue::from_static("no-store"));
    if !android {
        response.headers_mut().insert(
            header::SET_COOKIE,
            build_refresh_cookie(&refresh_token, max_age, secure)?,
        );
    }
    Ok(response)
}

#[derive(Serialize)]
struct RefreshResponse {
    access_token: String,
    expires_in: u32,
    #[serde(skip_serializing_if = "Option::is_none")]
    refresh_token: Option<String>,
}

/// Delivers a rotated pair without returning the account profile.
pub fn refresh_response(
    client: Client,
    outcome: Refreshed,
    secure: bool,
) -> Result<Response, AppError> {
    let android = client == Client::Android;
    let refresh_token = outcome.refresh_token.expose().clone();
    let body = RefreshResponse {
        access_token: outcome.access_token,
        expires_in: outcome.expires_in,
        refresh_token: android.then(|| refresh_token.clone()),
    };
    let mut response = Json(body).into_response();
    response.headers_mut().insert(
        header::CACHE_CONTROL,
        axum::http::HeaderValue::from_static("no-store"),
    );
    if !android {
        response.headers_mut().insert(
            header::SET_COOKIE,
            build_refresh_cookie(&refresh_token, outcome.refresh_cookie_max_age, secure)?,
        );
    }
    Ok(response)
}

/// Adds a no-store response header to a bodyless authentication response.
pub fn mark_no_store(response: &mut Response) {
    response.headers_mut().insert(
        header::CACHE_CONTROL,
        axum::http::HeaderValue::from_static("no-store"),
    );
}
