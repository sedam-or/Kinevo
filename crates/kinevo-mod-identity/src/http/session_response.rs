use axum::{
    Json,
    http::{HeaderValue, header},
    response::{IntoResponse, Response},
};
use kinevo_kernel::errors::{AppError, ErrorCode};

use crate::{app::login::LoginOutcome, app::refresh::Client, http::dto::LoginResponse};

/// Delivers the refresh credential through the web cookie or Android response body.
pub fn session_response(client: Client, outcome: LoginOutcome) -> Result<Response, AppError> {
    let android = client == Client::Android;
    let (body, refresh_token, max_age) = LoginResponse::from_outcome(outcome, android);
    let mut response = Json(body).into_response();
    response
        .headers_mut()
        .insert(header::CACHE_CONTROL, HeaderValue::from_static("no-store"));
    if !android {
        let cookie = format!(
            "__Secure-kinevo_rt={refresh_token}; HttpOnly; Secure; SameSite=Strict; Path=/api/v1/auth; Max-Age={max_age}"
        );
        let value =
            HeaderValue::from_str(&cookie).map_err(|_| AppError::new(ErrorCode::InternalError))?;
        response.headers_mut().insert(header::SET_COOKIE, value);
    }
    Ok(response)
}
