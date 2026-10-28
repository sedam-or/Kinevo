use std::{net::SocketAddr, sync::Arc};

use axum::{
    Json,
    extract::ConnectInfo,
    http::{HeaderMap, Method, header},
    response::Response,
    routing::{MethodRouter, post},
};
use kinevo_http::{
    problem::ApiError,
    route::{Access, IdemMode, RateClass, RouteSpec},
    state::HttpState,
};
use kinevo_kernel::errors::{AppError, ErrorCode};

use crate::{
    app::{
        login::{ClientAddr, LoginService},
        refresh::Client,
    },
    http::{dto::LoginDto, session_response::session_response},
};

/// Builds the public, rate-limited login handler.
pub fn route(service: Arc<LoginService>) -> (RouteSpec, MethodRouter<HttpState>) {
    let handler = post(
        move |ConnectInfo(address): ConnectInfo<SocketAddr>,
              headers: HeaderMap,
              Json(body): Json<LoginDto>| {
            let service = Arc::clone(&service);
            async move {
                match login(service, address, headers, body).await {
                    Ok(response) => Ok::<Response, ApiError>(response),
                    Err(error) => Err(ApiError(error)),
                }
            }
        },
    );
    (
        RouteSpec {
            method: Method::POST,
            path: "/auth/login",
            module: "identity",
            access: Access::Public,
            rate: RateClass::Auth,
            idempotency: IdemMode::Off,
            store_response_body: false,
            cookie_endpoint: true,
            summary: "Sign in with an email and password",
            allow_disabled: false,
        },
        handler,
    )
}

async fn login(
    service: Arc<LoginService>,
    address: SocketAddr,
    headers: HeaderMap,
    body: LoginDto,
) -> Result<Response, AppError> {
    let client = match headers
        .get("X-Kinevo-Client")
        .and_then(|value| value.to_str().ok())
    {
        Some("web") => Client::Web,
        Some("android") => Client::Android,
        _ => return Err(AppError::new(ErrorCode::ValidationFailed)),
    };
    let request = body.into_request()?;
    let policy = service.resolved_policy().await?;
    let user_agent = headers
        .get(header::USER_AGENT)
        .and_then(|value| value.to_str().ok());
    let outcome = service
        .login_with_user_agent(
            request,
            client,
            ClientAddr::new(address.ip()),
            policy,
            user_agent,
        )
        .await?;
    session_response(client, outcome)
}
