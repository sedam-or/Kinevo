//! Public setup status and wizard session endpoints.

use std::{net::SocketAddr, sync::Arc};

use axum::{
    Extension, Json,
    extract::{ConnectInfo, Path, State},
    http::{HeaderMap, Method, StatusCode},
    routing::{get, post, put},
};
use kinevo_http::{
    problem::ApiError,
    route::{Access, IdemMode, RateClass, RouteSpec, RouteTable},
    state::HttpState,
};
use kinevo_kernel::secret::Secret;
use serde::Deserialize;

use crate::app::setup::{OpenedSession, SessionId, SetupService, WizardPatch};

#[derive(Deserialize)]
#[serde(deny_unknown_fields)]
struct TokenInput {
    token: String,
}

/// Registers all pre-setup wizard routes as public, rate-limited endpoints.
#[must_use]
pub fn routes(service: Arc<SetupService>) -> RouteTable {
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
            service.get(&id).map(Json).map_err(ApiError)
        }
    });

    let update_service = service;
    let update = put(
        move |Path(id): Path<String>, Json(patch): Json<WizardPatch>| {
            let service = Arc::clone(&update_service);
            async move {
                let id = SessionId::parse(&id).map_err(ApiError)?;
                service.update(id, patch).await.map(Json).map_err(ApiError)
            }
        },
    );

    RouteTable::new()
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
        )
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
