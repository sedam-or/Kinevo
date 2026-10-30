//! Authenticated account-export request, status and download endpoints.

use std::sync::Arc;

use axum::{
    Json,
    body::Body,
    extract::Path,
    http::{HeaderValue, Method, StatusCode, header},
    response::{IntoResponse, Response},
    routing::{get, post},
};
use kinevo_http::{
    auth::Auth,
    problem::ApiError,
    route::{Access, IdemMode, RateClass, RouteSpec, RouteTable},
};
use serde::Serialize;
use sqlx::types::Uuid;

use crate::app::export::ExportService;

/// Creates request, status and download endpoints for an account's own exports.
#[must_use]
pub fn routes(service: Arc<ExportService>) -> RouteTable {
    let request_service = Arc::clone(&service);
    let request = post(move |Auth(auth): Auth| {
        let service = Arc::clone(&request_service);
        async move {
            let export_id = service
                .request_export(auth.account_id())
                .await
                .map_err(ApiError)?;
            let mut response = (
                StatusCode::ACCEPTED,
                Json(ExportRequestResponse { export_id }),
            )
                .into_response();
            no_store(&mut response);
            Ok::<Response, ApiError>(response)
        }
    });

    let status_service = Arc::clone(&service);
    let status = get(move |Auth(auth): Auth, Path(id): Path<Uuid>| {
        let service = Arc::clone(&status_service);
        async move {
            service
                .status(auth.account_id(), id)
                .await
                .map(Json)
                .map_err(ApiError)
        }
    });

    let download = get(move |Auth(auth): Auth, Path(id): Path<Uuid>| {
        let service = Arc::clone(&service);
        async move {
            let archive = service
                .download(auth.account_id(), id)
                .await
                .map_err(ApiError)?;
            let mut response = Response::new(Body::from(archive.bytes));
            *response.status_mut() = StatusCode::OK;
            response.headers_mut().insert(
                header::CONTENT_TYPE,
                HeaderValue::from_static(archive.content_type),
            );
            let disposition = format!("attachment; filename=\"{}\"", archive.file_name);
            response.headers_mut().insert(
                header::CONTENT_DISPOSITION,
                HeaderValue::from_str(&disposition).map_err(|_| {
                    ApiError(kinevo_kernel::errors::AppError::new(
                        kinevo_kernel::errors::ErrorCode::InternalError,
                    ))
                })?,
            );
            no_store(&mut response);
            Ok::<Response, ApiError>(response)
        }
    });

    RouteTable::new()
        .route(
            spec(
                Method::POST,
                "/account/export",
                RateClass::Export,
                IdemMode::Optional,
                "Request account data export",
            ),
            request,
        )
        .route(
            spec(
                Method::GET,
                "/account/export/{id}",
                RateClass::Api,
                IdemMode::Off,
                "Get account export status",
            ),
            status,
        )
        .route(
            spec(
                Method::GET,
                "/account/export/{id}/download",
                RateClass::Export,
                IdemMode::Off,
                "Download account export archive",
            ),
            download,
        )
}

#[derive(Serialize, utoipa::ToSchema)]
pub(crate) struct ExportRequestResponse {
    #[schema(value_type = String)]
    export_id: Uuid,
}

fn spec(
    method: Method,
    path: &'static str,
    rate: RateClass,
    idempotency: IdemMode,
    summary: &'static str,
) -> RouteSpec {
    RouteSpec {
        method,
        path,
        module: "identity",
        access: Access::Permission("identity.account.export"),
        rate,
        idempotency,
        store_response_body: false,
        cookie_endpoint: false,
        summary,
        allow_disabled: false,
    }
}

fn no_store(response: &mut Response) {
    response
        .headers_mut()
        .insert(header::CACHE_CONTROL, HeaderValue::from_static("no-store"));
}
