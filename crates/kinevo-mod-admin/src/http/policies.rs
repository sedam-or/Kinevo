//! Authenticated policy listing and version creation endpoints.

use std::sync::Arc;

use axum::{
    Json,
    extract::{Path, Query},
    http::{Method, StatusCode},
    routing::{get, put},
};
use kinevo_http::{
    auth::Auth,
    extract::{Validate, ValidatedJson},
    problem::ApiError,
    route::{Access, IdemMode, RateClass, RouteSpec, RouteTable},
};
use kinevo_kernel::{
    errors::{AppError, ErrorCode},
    policy::Scope,
    validate::Collector,
};
use serde::Deserialize;
use serde_json::Value;

use crate::app::policies::{PolicyService, PolicyView};

/// Adds the policy registry listing and version write routes.
#[must_use]
pub fn routes(service: Arc<PolicyService>) -> RouteTable {
    let list_service = Arc::clone(&service);
    let list = get(move |Auth(auth): Auth, Query(query): Query<ListQuery>| {
        let service = Arc::clone(&list_service);
        async move {
            let items = service
                .list(&auth, parse_scope(&query.scope)?)
                .await
                .map_err(ApiError)?;
            Ok::<_, ApiError>(Json(ListResponse { items }))
        }
    });

    let put_service = service;
    let set = put(
        move |Auth(auth): Auth,
              Path(key): Path<String>,
              ValidatedJson(body): ValidatedJson<PutBody>| {
            let service = Arc::clone(&put_service);
            async move {
                let scope = parse_scope(&body.scope)?;
                let result = service
                    .set(&auth, &key, scope, body.value, body.effective_from)
                    .await
                    .map_err(ApiError)?;
                Ok::<_, ApiError>((StatusCode::OK, Json(result)))
            }
        },
    );

    RouteTable::new()
        .route(
            spec(
                Method::GET,
                "/policies",
                Access::Permission("admin.policies.instance.read"),
                IdemMode::Off,
                "List resolved policies",
            ),
            list,
        )
        .route(
            spec(
                Method::PUT,
                "/policies/{key}",
                Access::Permission("admin.policies.account.write"),
                IdemMode::Optional,
                "Create a policy version",
            ),
            set,
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

#[derive(Deserialize)]
#[serde(deny_unknown_fields)]
struct ListQuery {
    scope: String,
}

#[derive(serde::Serialize)]
struct ListResponse {
    items: Vec<PolicyView>,
}

#[derive(Deserialize)]
#[serde(deny_unknown_fields)]
struct PutBody {
    scope: String,
    value: Value,
    effective_from: Option<kinevo_kernel::time::Instant>,
}

impl Validate for PutBody {
    fn validate(&self) -> Collector {
        Collector::new()
    }
}

fn parse_scope(value: &str) -> Result<Scope, ApiError> {
    match value {
        "instance" => Ok(Scope::Instance),
        "account" => Ok(Scope::Account),
        _ => Err(ApiError(AppError::new(ErrorCode::PolicyScopeInvalid))),
    }
}
