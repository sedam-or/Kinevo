//! Refresh, logout and logout-all HTTP adapters.

use std::sync::Arc;

use axum::{
    body::Bytes,
    extract::State,
    http::{HeaderMap, Method, StatusCode, header},
    response::{IntoResponse, Response},
    routing::{MethodRouter, post},
};
use kinevo_http::{
    auth::Auth,
    problem::ApiError,
    route::{Access, IdemMode, RateClass, RouteSpec, RouteTable},
    state::HttpState,
};
use kinevo_kernel::{
    errors::{AppError, DetailValue, ErrorCode},
    id::AccountId,
    policy::{PolicyDuration, registry::identity as policy},
    secret::Secret,
    time::Clock,
};
use kinevo_ports::platform::PolicyReader;
use serde::Deserialize;

use crate::{
    app::refresh::{Client, RefreshService},
    domain::session::SessionPolicy,
    http::{cookie, session_response},
};

/// Android refresh body. The wire string is immediately wrapped in `Secret`.
#[derive(Deserialize, utoipa::ToSchema)]
#[serde(from = "RefreshBodyWire")]
pub struct RefreshBody {
    #[schema(value_type = Option<String>)]
    pub refresh_token: Option<Secret<String>>,
}

#[derive(Deserialize)]
#[serde(deny_unknown_fields)]
struct RefreshBodyWire {
    refresh_token: Option<String>,
}

impl From<RefreshBodyWire> for RefreshBody {
    fn from(wire: RefreshBodyWire) -> Self {
        Self {
            refresh_token: wire.refresh_token.map(Secret::new),
        }
    }
}

/// Builds route descriptors and handlers for the refresh and revocation endpoints.
pub fn routes(
    service: Arc<RefreshService>,
    policies: Arc<dyn PolicyReader>,
) -> Vec<(RouteSpec, MethodRouter<HttpState>)> {
    let refresh_service = Arc::clone(&service);
    let refresh_policies = policies;
    let refresh = post(
        move |State(state): State<HttpState>, headers: HeaderMap, body: Bytes| {
            let service = Arc::clone(&refresh_service);
            let policies = Arc::clone(&refresh_policies);
            async move {
                match refresh(service, policies, state, headers, body).await {
                    Ok(response) => Ok::<Response, ApiError>(response),
                    Err(error) => Err(ApiError(error)),
                }
            }
        },
    );

    let logout_service = Arc::clone(&service);
    let logout = post(
        move |State(state): State<HttpState>, headers: HeaderMap, body: Bytes| {
            let service = Arc::clone(&logout_service);
            async move {
                match logout(service, state, headers, body).await {
                    Ok(response) => Ok::<Response, ApiError>(response),
                    Err(error) => Err(ApiError(error)),
                }
            }
        },
    );

    let logout_all = post(move |State(state): State<HttpState>, Auth(auth): Auth| {
        let service = Arc::clone(&service);
        async move {
            match logout_all(service, state, auth.account_id()).await {
                Ok(response) => Ok::<Response, ApiError>(response),
                Err(error) => Err(ApiError(error)),
            }
        }
    });

    vec![
        (
            RouteSpec {
                method: Method::POST,
                path: "/auth/refresh",
                module: "identity",
                access: Access::Public,
                rate: RateClass::Auth,
                idempotency: IdemMode::Off,
                store_response_body: false,
                cookie_endpoint: true,
                summary: "Refresh the current session",
                allow_disabled: false,
            },
            refresh,
        ),
        (
            RouteSpec {
                method: Method::POST,
                path: "/auth/logout",
                module: "identity",
                access: Access::Public,
                rate: RateClass::Auth,
                idempotency: IdemMode::Off,
                store_response_body: false,
                cookie_endpoint: true,
                summary: "Sign out the current session",
                allow_disabled: false,
            },
            logout,
        ),
        (
            RouteSpec {
                method: Method::POST,
                path: "/auth/logout-all",
                module: "identity",
                access: Access::Permission("identity.session.manage"),
                rate: RateClass::Api,
                idempotency: IdemMode::Off,
                store_response_body: false,
                cookie_endpoint: false,
                summary: "Sign out every session for this account",
                allow_disabled: false,
            },
            logout_all,
        ),
    ]
}

/// Builds a route table containing the refresh and revocation endpoints.
#[must_use]
pub fn route_table(service: Arc<RefreshService>, policies: Arc<dyn PolicyReader>) -> RouteTable {
    routes(service, policies)
        .into_iter()
        .fold(RouteTable::new(), |table, (spec, route)| {
            table.route(spec, route)
        })
}

async fn refresh(
    service: Arc<RefreshService>,
    policies: Arc<dyn PolicyReader>,
    state: HttpState,
    headers: HeaderMap,
    body: Bytes,
) -> Result<Response, AppError> {
    let client = request_client(&headers)?;
    let secure = cookie::is_secure_origin(state.origin_config.public_origin.as_deref());
    let presented = match client {
        Client::Web => cookie::read_refresh_cookie(&headers, secure),
        Client::Android => serde_json::from_slice::<RefreshBody>(&body)
            .ok()
            .and_then(|body| body.refresh_token),
    }
    .ok_or_else(unauthenticated)?;
    let policy = session_policy(policies.as_ref(), state.clock.as_ref()).await?;
    match service.refresh(presented, policy, client).await {
        Ok(refreshed) => session_response::refresh_response(client, refreshed, secure),
        Err(error) if client == Client::Web && error.code == ErrorCode::RefreshReuseDetected => {
            let mut response = ApiError(error).into_response();
            response
                .headers_mut()
                .insert(header::SET_COOKIE, cookie::clear_refresh_cookie(secure)?);
            session_response::mark_no_store(&mut response);
            Ok(response)
        }
        Err(error) => Err(error),
    }
}

async fn logout(
    service: Arc<RefreshService>,
    state: HttpState,
    headers: HeaderMap,
    body: Bytes,
) -> Result<Response, AppError> {
    let client = request_client(&headers)?;
    let secure = cookie::is_secure_origin(state.origin_config.public_origin.as_deref());
    let presented = match client {
        Client::Web => cookie::read_refresh_cookie(&headers, secure),
        Client::Android => serde_json::from_slice::<RefreshBody>(&body)
            .ok()
            .and_then(|body| body.refresh_token),
    };
    service.logout(presented).await?;
    let mut response = StatusCode::NO_CONTENT.into_response();
    if client == Client::Web {
        response
            .headers_mut()
            .insert(header::SET_COOKIE, cookie::clear_refresh_cookie(secure)?);
    }
    session_response::mark_no_store(&mut response);
    Ok(response)
}

async fn logout_all(
    service: Arc<RefreshService>,
    state: HttpState,
    account_id: AccountId,
) -> Result<Response, AppError> {
    service.logout_all(account_id).await?;
    let secure = cookie::is_secure_origin(state.origin_config.public_origin.as_deref());
    let mut response = StatusCode::NO_CONTENT.into_response();
    response
        .headers_mut()
        .insert(header::SET_COOKIE, cookie::clear_refresh_cookie(secure)?);
    session_response::mark_no_store(&mut response);
    Ok(response)
}

fn request_client(headers: &HeaderMap) -> Result<Client, AppError> {
    match headers
        .get("X-Kinevo-Client")
        .and_then(|value| value.to_str().ok())
    {
        Some("web") => Ok(Client::Web),
        Some("android") => Ok(Client::Android),
        _ => Err(AppError::new(ErrorCode::CsrfCheckFailed)),
    }
}

async fn session_policy(
    policies: &dyn PolicyReader,
    clock: &dyn Clock,
) -> Result<SessionPolicy, AppError> {
    let values = policies.snapshot_values(None, clock.now()).await?;
    let required = |key| {
        AppError::new(ErrorCode::ServiceUnavailable).with_detail("status", DetailValue::Str(key))
    };
    Ok(SessionPolicy {
        access_ttl: policy_seconds(
            values
                .get(&policy::ACCESS_TOKEN_TTL)
                .ok_or_else(|| required("policy_missing"))?,
        )?,
        idle_ttl: policy_seconds(
            values
                .get(&policy::REFRESH_TOKEN_IDLE_TTL)
                .ok_or_else(|| required("policy_missing"))?,
        )?,
        absolute_ttl: policy_seconds(
            values
                .get(&policy::REFRESH_TOKEN_ABSOLUTE_TTL)
                .ok_or_else(|| required("policy_missing"))?,
        )?,
        grace: policy_seconds(
            values
                .get(&policy::REFRESH_GRACE)
                .ok_or_else(|| required("policy_missing"))?,
        )?,
    })
}

fn policy_seconds(value: PolicyDuration) -> Result<u32, AppError> {
    u32::try_from(value.0).map_err(|_| AppError::new(ErrorCode::InternalError))
}

fn unauthenticated() -> AppError {
    AppError::new(ErrorCode::Unauthenticated)
}
