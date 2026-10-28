//! Public health endpoints and the injected server-time endpoint.

use crate::{
    route::{Access, IdemMode, RateClass, RouteSpec, RouteTable},
    state::HttpState,
};
use axum::{
    Json,
    extract::State,
    http::{Method, StatusCode},
    response::{IntoResponse, Response},
    routing::get,
};
use serde::Serialize;

#[derive(Serialize)]
struct HealthBody {
    status: &'static str,
}

#[derive(Serialize)]
struct TimeBody {
    now: String,
}

/// Builds the health and server-time routes with explicit access metadata.
#[must_use]
pub fn system_routes() -> RouteTable {
    RouteTable::new()
        .route(
            RouteSpec {
                method: Method::GET,
                path: "/livez",
                module: "kernel",
                access: Access::Public,
                rate: RateClass::None,
                idempotency: IdemMode::Off,
                store_response_body: true,
                cookie_endpoint: false,
                summary: "Process liveness",
                allow_disabled: false,
            },
            get(livez),
        )
        .route(
            RouteSpec {
                method: Method::GET,
                path: "/readyz",
                module: "kernel",
                access: Access::Public,
                rate: RateClass::None,
                idempotency: IdemMode::Off,
                store_response_body: true,
                cookie_endpoint: false,
                summary: "Database readiness",
                allow_disabled: false,
            },
            get(readyz),
        )
        .route(
            RouteSpec {
                method: Method::GET,
                path: "time",
                module: "kernel",
                access: Access::Permission("identity.account.read"),
                rate: RateClass::None,
                idempotency: IdemMode::Off,
                store_response_body: true,
                cookie_endpoint: false,
                summary: "Server time",
                allow_disabled: false,
            },
            get(time),
        )
}

async fn livez() -> Json<HealthBody> {
    Json(HealthBody { status: "ok" })
}

async fn readyz(State(state): State<HttpState>) -> Response {
    match state.ready.check().await {
        Ok(()) => (StatusCode::OK, Json(HealthBody { status: "ok" })).into_response(),
        Err(_) => (
            StatusCode::SERVICE_UNAVAILABLE,
            Json(HealthBody {
                status: "unavailable",
            }),
        )
            .into_response(),
    }
}

async fn time(State(state): State<HttpState>) -> Json<TimeBody> {
    Json(TimeBody {
        now: state.clock.now().to_rfc3339(),
    })
}
