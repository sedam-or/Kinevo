//! Process and database health endpoints used by the composition root.

use axum::{
    Json, Router,
    extract::State,
    http::StatusCode,
    response::{IntoResponse, Response},
    routing::get,
};
use kinevo_kernel::time::{Clock, Instant};
use serde::Serialize;
use sqlx::PgPool;
use std::sync::Arc;

const MAX_CLOCK_SKEW_MILLIS: i64 = 5_000;

#[derive(Clone)]
struct Readiness {
    pool: PgPool,
    clock: Arc<dyn Clock>,
    migrations_current: bool,
    isolation_verified: bool,
}

#[derive(Serialize)]
struct HealthBody {
    status: &'static str,
}

/// Builds the public liveness and readiness routes.
pub fn router(
    pool: PgPool,
    clock: Arc<dyn Clock>,
    migrations_current: bool,
    isolation_verified: bool,
) -> Router {
    let readiness = Readiness {
        pool,
        clock,
        migrations_current,
        isolation_verified,
    };
    Router::new()
        .route("/livez", get(livez))
        .route("/readyz", get(readyz))
        .with_state(readiness)
}

async fn livez() -> Json<HealthBody> {
    Json(HealthBody { status: "ok" })
}

async fn readyz(State(readiness): State<Readiness>) -> Response {
    if !readiness.migrations_current || !readiness.isolation_verified {
        return unavailable();
    }

    let database_now = sqlx::query_scalar::<_, String>(
        "SELECT to_char(now() AT TIME ZONE 'UTC', 'YYYY-MM-DD\"T\"HH24:MI:SS.MS\"Z\"')",
    )
    .fetch_one(&readiness.pool)
    .await;
    let Ok(database_now) = database_now else {
        return unavailable();
    };
    let Ok(database_now) = Instant::parse_rfc3339(&database_now) else {
        return unavailable();
    };
    let skew = readiness
        .clock
        .now()
        .unix_millis()
        .saturating_sub(database_now.unix_millis())
        .saturating_abs();
    let status = if skew > MAX_CLOCK_SKEW_MILLIS {
        "degraded"
    } else {
        "ok"
    };
    (StatusCode::OK, Json(HealthBody { status })).into_response()
}

fn unavailable() -> Response {
    (
        StatusCode::SERVICE_UNAVAILABLE,
        Json(HealthBody {
            status: "unavailable",
        }),
    )
        .into_response()
}
