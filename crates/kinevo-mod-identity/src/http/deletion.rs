//! Authenticated account-deletion endpoint.

use std::sync::Arc;

use axum::{
    http::{Method, StatusCode, header},
    response::{IntoResponse, Response},
    routing::delete,
};
use kinevo_http::{
    auth::Auth,
    extract::{Validate, ValidatedJson},
    problem::ApiError,
    route::{Access, IdemMode, RateClass, RouteSpec, RouteTable},
};
use kinevo_kernel::{secret::Secret, validate::Collector};
use serde::Deserialize;

use crate::app::deletion::DeletionService;

/// Creates the authenticated self-service account-deletion route.
#[must_use]
pub fn route(service: Arc<DeletionService>) -> RouteTable {
    let handler = delete(
        move |Auth(auth): Auth, ValidatedJson(body): ValidatedJson<DeletionBody>| {
            let service = Arc::clone(&service);
            async move {
                service
                    .request_deletion(auth.account_id(), Secret::new(body.password), &body.confirm)
                    .await
                    .map_err(ApiError)?;
                let mut response = StatusCode::ACCEPTED.into_response();
                response.headers_mut().insert(
                    header::CACHE_CONTROL,
                    axum::http::HeaderValue::from_static("no-store"),
                );
                Ok::<Response, ApiError>(response)
            }
        },
    );

    RouteTable::new().route(
        RouteSpec {
            method: Method::DELETE,
            path: "/account",
            module: "identity",
            access: Access::Permission("identity.account.delete"),
            rate: RateClass::Api,
            idempotency: IdemMode::Optional,
            store_response_body: false,
            cookie_endpoint: false,
            summary: "Request account deletion",
            allow_disabled: false,
        },
        handler,
    )
}

#[derive(Deserialize)]
#[serde(deny_unknown_fields)]
struct DeletionBody {
    password: String,
    confirm: String,
}

impl Validate for DeletionBody {
    fn validate(&self) -> Collector {
        Collector::new()
    }
}
