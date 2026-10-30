//! Public setup status and wizard session endpoints.

use std::{net::SocketAddr, sync::Arc};

use axum::{
    Extension, Json,
    extract::{ConnectInfo, Path, State},
    http::{HeaderMap, HeaderValue, Method, StatusCode, header},
    response::{IntoResponse, Response},
    routing::{get, post, put},
};
use kinevo_http::{
    problem::ApiError,
    route::{Access, IdemMode, RateClass, RouteSpec, RouteTable},
    state::HttpState,
};
use kinevo_kernel::{
    errors::{AppError, Details, ErrorCode, FieldCode, FieldError},
    secret::Secret,
    validate::IdemKey,
};
use kinevo_ports::platform::Client;
use serde::{Deserialize, Serialize};

use crate::app::{
    finish_setup::{FinishInput, FinishOutcome, FinishSetup, NewSetupAccount},
    setup::{OpenedSession, SessionId, SetupService, WizardPatch},
};

#[derive(Deserialize, utoipa::ToSchema)]
#[serde(deny_unknown_fields)]
pub(crate) struct TokenInput {
    token: String,
}

#[derive(Deserialize, utoipa::ToSchema)]
#[serde(deny_unknown_fields)]
pub(crate) struct FinishRequest {
    account: FinishAccountRequest,
}

#[derive(Deserialize, utoipa::ToSchema)]
#[serde(deny_unknown_fields)]
pub(crate) struct FinishAccountRequest {
    email: String,
    password: String,
    display_name: Option<String>,
    timezone: Option<String>,
}

#[derive(Serialize, utoipa::ToSchema)]
pub(crate) struct FinishResponse {
    account: kinevo_ports::platform::AccountProfile,
    access_token: String,
    expires_in: u32,
    #[serde(skip_serializing_if = "Option::is_none")]
    refresh_token: Option<String>,
}

/// Registers all pre-setup wizard routes as public, rate-limited endpoints.
#[must_use]
pub fn routes(service: Arc<SetupService>) -> RouteTable {
    routes_inner(service, None)
}

/// Registers setup routes including the atomic first-account Finish endpoint.
#[must_use]
pub fn routes_with_finish(service: Arc<SetupService>, finish: Arc<FinishSetup>) -> RouteTable {
    routes_inner(service, Some(finish))
}

#[allow(
    clippy::too_many_lines,
    reason = "these public setup routes are registered together as one route table"
)]
fn routes_inner(service: Arc<SetupService>, finish: Option<Arc<FinishSetup>>) -> RouteTable {
    let status_service = Arc::clone(&service);
    let status = get(move || {
        let service = Arc::clone(&status_service);
        async move { service.status().await.map(Json).map_err(ApiError) }
    });

    let open_service = Arc::clone(&service);
    let open = post(
        move |State(state): State<HttpState>,
              headers: HeaderMap,
              peer: Option<Extension<ConnectInfo<SocketAddr>>>,
              Json(input): Json<TokenInput>| {
            let service = Arc::clone(&open_service);
            async move {
                let source = source_key(&state, &headers, peer);
                let token = Secret::new(input.token);
                service
                    .open_session(&source, &token)
                    .await
                    .map(|state| {
                        (
                            StatusCode::CREATED,
                            Json(OpenedSession {
                                session_id: state.session_id,
                                step: state.step,
                            }),
                        )
                    })
                    .map_err(ApiError)
            }
        },
    );

    let get_service = Arc::clone(&service);
    let get_session = get(move |Path(id): Path<String>| {
        let service = Arc::clone(&get_service);
        async move {
            let id = SessionId::parse(&id).map_err(ApiError)?;
            service
                .get(&id)
                .map(|state| Json(service.response(state)))
                .map_err(ApiError)
        }
    });

    let update_service = Arc::clone(&service);
    let update = put(
        move |Path(id): Path<String>, Json(patch): Json<WizardPatch>| {
            let service = Arc::clone(&update_service);
            async move {
                let id = SessionId::parse(&id).map_err(ApiError)?;
                service
                    .update(id, patch)
                    .await
                    .map(|state| Json(service.response(state)))
                    .map_err(ApiError)
            }
        },
    );

    let routes = RouteTable::new()
        .route(
            spec(
                Method::GET,
                "/setup/status",
                RateClass::None,
                "Read setup status",
            ),
            status,
        )
        .route(
            spec(
                Method::POST,
                "/setup/session",
                RateClass::Auth,
                "Open setup session",
            ),
            open,
        )
        .route(
            spec(
                Method::GET,
                "/setup/session/{id}",
                RateClass::None,
                "Read setup session",
            ),
            get_session,
        )
        .route(
            spec(
                Method::PUT,
                "/setup/session/{id}",
                RateClass::None,
                "Update setup session",
            ),
            update,
        );

    let Some(finish_service) = finish else {
        return routes;
    };
    let setup_service = service;
    let finish = post(
        move |Path(id): Path<String>,
              State(state): State<HttpState>,
              headers: HeaderMap,
              Json(body): Json<FinishRequest>| {
            let setup_service = Arc::clone(&setup_service);
            let finish_service = Arc::clone(&finish_service);
            async move {
                let session_id = SessionId::parse(&id).map_err(ApiError)?;
                let session = setup_service.get(&session_id).map_err(ApiError)?;
                let key = headers
                    .get("Idempotency-Key")
                    .and_then(|value| value.to_str().ok())
                    .ok_or_else(|| ApiError(idempotency_key_error()))?;
                let key = IdemKey::parse(key).map_err(|code| {
                    ApiError(AppError::validation(vec![FieldError {
                        path: "Idempotency-Key".to_owned(),
                        code,
                        details: Details::new(),
                    }]))
                })?;
                let client = match headers
                    .get("X-Kinevo-Client")
                    .and_then(|value| value.to_str().ok())
                {
                    Some("web") => Client::Web,
                    Some("android") => Client::Android,
                    _ => return Err(ApiError(AppError::new(ErrorCode::ValidationFailed))),
                };
                let display_name = body.account.display_name.unwrap_or_else(|| {
                    body.account
                        .email
                        .split_once('@')
                        .map_or_else(String::new, |(local, _)| local.to_owned())
                });
                let outcome = finish_service
                    .run_idempotent(
                        FinishInput {
                            account: NewSetupAccount {
                                email: body.account.email,
                                password: Secret::new(body.account.password),
                                display_name,
                                timezone: body.account.timezone.unwrap_or_else(|| "UTC".to_owned()),
                            },
                            session,
                        },
                        client,
                        key,
                    )
                    .await
                    .map_err(ApiError)?;
                finish_response(outcome, client, &state).map_err(ApiError)
            }
        },
    );

    routes.route(finish_spec(), finish)
}

fn finish_response(
    outcome: FinishOutcome,
    client: Client,
    state: &HttpState,
) -> Result<Response, AppError> {
    let android = client == Client::Android;
    let refresh_token = outcome.refresh_token.expose().clone();
    let body = FinishResponse {
        account: outcome.account,
        access_token: outcome.access_token.expose().clone(),
        expires_in: outcome.expires_in,
        refresh_token: android.then(|| refresh_token.clone()),
    };
    let mut response = Json(body).into_response();
    *response.status_mut() = StatusCode::CREATED;
    response
        .headers_mut()
        .insert(header::CACHE_CONTROL, HeaderValue::from_static("no-store"));
    if !android {
        let secure =
            kinevo_http::cookie::is_secure_origin(state.origin_config.public_origin.as_deref());
        response.headers_mut().insert(
            header::SET_COOKIE,
            kinevo_http::cookie::build_refresh_cookie(
                &refresh_token,
                outcome.refresh_cookie_max_age,
                secure,
            )?,
        );
    }
    if outcome.replayed {
        response
            .headers_mut()
            .insert("Idempotency-Replayed", HeaderValue::from_static("true"));
    }
    Ok(response)
}

fn spec(method: Method, path: &'static str, rate: RateClass, summary: &'static str) -> RouteSpec {
    RouteSpec {
        method,
        path,
        module: "admin",
        access: Access::Public,
        rate,
        idempotency: IdemMode::Off,
        store_response_body: false,
        cookie_endpoint: false,
        summary,
        allow_disabled: false,
    }
}

fn finish_spec() -> RouteSpec {
    RouteSpec {
        method: Method::POST,
        path: "/setup/session/{id}/finish",
        module: "admin",
        access: Access::Public,
        rate: RateClass::Auth,
        idempotency: IdemMode::Optional,
        store_response_body: false,
        cookie_endpoint: true,
        summary: "Finish setup and create the instance administrator",
        allow_disabled: false,
    }
}

fn idempotency_key_error() -> AppError {
    AppError::validation(vec![FieldError {
        path: "Idempotency-Key".to_owned(),
        code: FieldCode::KeyInvalid,
        details: Details::new(),
    }])
}

fn source_key(
    state: &HttpState,
    headers: &HeaderMap,
    peer: Option<Extension<ConnectInfo<SocketAddr>>>,
) -> String {
    let Some(Extension(ConnectInfo(peer))) = peer else {
        return "unknown".to_owned();
    };
    let forwarded = headers
        .get("x-forwarded-for")
        .and_then(|value| value.to_str().ok());
    let address = state.rate_limiter.as_ref().map_or(peer.ip(), |limiter| {
        limiter.client_addr(peer.ip(), forwarded)
    });
    address.to_string()
}
