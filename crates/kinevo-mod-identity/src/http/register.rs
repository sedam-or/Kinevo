//! Public registration HTTP adapter.

use std::{net::SocketAddr, sync::Arc};

use axum::{
    Json,
    extract::{ConnectInfo, State},
    http::{HeaderMap, Method, StatusCode},
    response::Response,
    routing::{MethodRouter, post},
};
use kinevo_http::{
    problem::ApiError,
    route::{Access, IdemMode, RateClass, RouteSpec},
    state::HttpState,
};
use kinevo_kernel::{
    errors::{AppError, ErrorCode},
    secret::Secret,
};
use serde::Deserialize;

use crate::{
    app::{
        login::ClientAddr,
        refresh::Client,
        register::{RegisterRequest, RegisterService},
    },
    http::session_response::session_response,
};

#[derive(Deserialize)]
#[serde(deny_unknown_fields)]
struct RegisterBody {
    email: String,
    password: String,
    display_name: String,
    timezone: String,
    locale: Option<String>,
    invite_token: Option<String>,
}

impl From<RegisterBody> for RegisterRequest {
    fn from(body: RegisterBody) -> Self {
        Self {
            email: body.email,
            password: Secret::new(body.password),
            display_name: body.display_name,
            timezone: body.timezone,
            locale: body.locale,
            invite_token: body.invite_token,
        }
    }
}

/// Builds the public, rate-limited registration handler and its route contract.
pub fn route(service: Arc<RegisterService>) -> (RouteSpec, MethodRouter<HttpState>) {
    let handler = post(
        move |ConnectInfo(address): ConnectInfo<SocketAddr>,
              State(state): State<HttpState>,
              headers: HeaderMap,
              Json(body): Json<RegisterBody>| {
            let service = Arc::clone(&service);
            async move {
                match register(service, state, address, headers, body).await {
                    Ok(response) => Ok::<Response, ApiError>(response),
                    Err(error) => Err(ApiError(error)),
                }
            }
        },
    );
    (
        RouteSpec {
            method: Method::POST,
            path: "/auth/register",
            module: "identity",
            access: Access::Public,
            rate: RateClass::Auth,
            idempotency: IdemMode::Off,
            store_response_body: false,
            cookie_endpoint: true,
            summary: "Create an account",
            allow_disabled: false,
        },
        handler,
    )
}

async fn register(
    service: Arc<RegisterService>,
    state: HttpState,
    address: SocketAddr,
    headers: HeaderMap,
    body: RegisterBody,
) -> Result<Response, AppError> {
    let client = match headers
        .get("X-Kinevo-Client")
        .and_then(|value| value.to_str().ok())
    {
        Some("web") => Client::Web,
        Some("android") => Client::Android,
        _ => return Err(AppError::new(ErrorCode::ValidationFailed)),
    };
    let user_agent = headers
        .get(axum::http::header::USER_AGENT)
        .and_then(|value| value.to_str().ok());
    let outcome = service
        .register_with_user_agent(
            body.into(),
            client,
            ClientAddr::new(address.ip()),
            state.clock.now(),
            user_agent,
        )
        .await?;
    let secure = super::cookie::is_secure_origin(state.origin_config.public_origin.as_deref());
    let mut response = session_response(client, outcome, secure)?;
    *response.status_mut() = StatusCode::CREATED;
    Ok(response)
}
