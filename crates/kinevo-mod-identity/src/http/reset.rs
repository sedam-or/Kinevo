//! Public password reset request and confirmation endpoints.

use std::sync::Arc;

use axum::{
    http::{HeaderValue, Method, StatusCode, header},
    response::{IntoResponse, Response},
    routing::post,
};
use kinevo_http::{
    extract::{Validate, ValidatedJson},
    problem::ApiError,
    route::{Access, IdemMode, RateClass, RouteSpec, RouteTable},
};
use kinevo_kernel::{
    secret::Secret,
    validate::{Collector, net::Email},
};
use serde::Deserialize;

use crate::app::reset::ResetService;

/// Creates public reset-request and one-time confirmation routes.
#[must_use]
pub fn routes(service: Arc<ResetService>) -> RouteTable {
    let confirm_service = Arc::clone(&service);
    let request = post(move |ValidatedJson(body): ValidatedJson<RequestBody>| {
        let service = Arc::clone(&service);
        async move {
            service
                .request(Email::parse(&body.email).map_err(|_| {
                    ApiError(kinevo_kernel::errors::AppError::validation(vec![
                        kinevo_kernel::errors::FieldError {
                            path: "email".to_owned(),
                            code: kinevo_kernel::errors::FieldCode::EmailInvalid,
                            details: kinevo_kernel::errors::Details::new(),
                        },
                    ]))
                })?)
                .await
                .map_err(ApiError)?;
            Ok::<Response, ApiError>(no_store(StatusCode::ACCEPTED))
        }
    });
    let confirm = post(move |ValidatedJson(body): ValidatedJson<ConfirmBody>| {
        let service = Arc::clone(&confirm_service);
        async move {
            service
                .confirm(Secret::new(body.token), Secret::new(body.new_password))
                .await
                .map_err(ApiError)?;
            Ok::<Response, ApiError>(no_store(StatusCode::NO_CONTENT))
        }
    });
    RouteTable::new()
        .route(
            spec(
                Method::POST,
                "/auth/password-reset/request",
                "Request a password reset",
            ),
            request,
        )
        .route(
            spec(
                Method::POST,
                "/auth/password-reset/confirm",
                "Confirm a password reset",
            ),
            confirm,
        )
}

#[derive(Deserialize, utoipa::ToSchema)]
#[serde(deny_unknown_fields)]
pub(crate) struct RequestBody {
    email: String,
}

impl Validate for RequestBody {
    fn validate(&self) -> Collector {
        Collector::new()
    }
}

#[derive(Deserialize, utoipa::ToSchema)]
#[serde(deny_unknown_fields)]
pub(crate) struct ConfirmBody {
    token: String,
    new_password: String,
}

impl Validate for ConfirmBody {
    fn validate(&self) -> Collector {
        Collector::new()
    }
}

fn spec(method: Method, path: &'static str, summary: &'static str) -> RouteSpec {
    RouteSpec {
        method,
        path,
        module: "identity",
        access: Access::Public,
        rate: RateClass::Auth,
        idempotency: IdemMode::Off,
        store_response_body: false,
        cookie_endpoint: false,
        summary,
        allow_disabled: false,
    }
}

fn no_store(status: StatusCode) -> Response {
    let mut response = status.into_response();
    response
        .headers_mut()
        .insert(header::CACHE_CONTROL, HeaderValue::from_static("no-store"));
    response
}
