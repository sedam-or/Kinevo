//! Admin job queue summary and outbox retry endpoints.

use std::sync::Arc;

use axum::{
    Json,
    extract::Path,
    http::{Method, StatusCode},
    routing::{get, post},
};
use kinevo_http::{
    auth::Auth,
    problem::ApiError,
    route::{Access, IdemMode, RateClass, RouteSpec, RouteTable},
};
use kinevo_kernel::{
    errors::{AppError, ErrorCode},
    id::EventId,
};
use serde_json::json;
use sqlx::types::Uuid;

use crate::app::jobs_admin::JobsAdminService;

/// Adds the payload-free summary and retry-job endpoints.
#[must_use]
pub fn routes(service: Arc<JobsAdminService>) -> RouteTable {
    let summary_service = Arc::clone(&service);
    let summary = get(move |Auth(auth): Auth| {
        let service = Arc::clone(&summary_service);
        async move {
            let result = service.summary(auth.account_id()).await.map_err(ApiError)?;
            Ok::<_, ApiError>(Json(result))
        }
    });
    let retry_service = service;
    let retry = post(
        move |Auth(auth): Auth, Path((event_id, subscriber)): Path<(String, String)>| {
            let service = Arc::clone(&retry_service);
            async move {
                let event_id = Uuid::parse_str(&event_id)
                    .map(EventId::from_uuid)
                    .map_err(|_| ApiError(AppError::new(ErrorCode::NotFound)))?;
                service
                    .retry_dead(auth.account_id(), event_id, &subscriber)
                    .await
                    .map_err(ApiError)?;
                Ok::<_, ApiError>((StatusCode::ACCEPTED, Json(json!({}))))
            }
        },
    );
    RouteTable::new()
        .route(
            spec(
                Method::GET,
                "/admin/jobs/summary",
                Access::Permission("admin.jobs.read"),
                IdemMode::Off,
                "Read a payload-free job summary",
            ),
            summary,
        )
        .route(
            spec(
                Method::POST,
                "/admin/outbox/{event_id}/{subscriber}/retry",
                Access::Permission("admin.jobs.retry"),
                IdemMode::Optional,
                "Queue a retry for a dead outbox delivery",
            ),
            retry,
        )
}

fn spec(
    method: Method,
    path: &'static str,
    access: Access,
    idempotency: IdemMode,
    summary: &'static str,
) -> RouteSpec {
    RouteSpec {
        method,
        path,
        module: "admin",
        access,
        rate: RateClass::Api,
        idempotency,
        store_response_body: false,
        cookie_endpoint: false,
        summary,
        allow_disabled: false,
    }
}
