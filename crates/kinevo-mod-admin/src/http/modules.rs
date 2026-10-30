//! Authenticated module listing and instance-admin switch endpoints.

use std::sync::Arc;

use axum::{
    Json,
    extract::Path,
    http::{Method, StatusCode},
    routing::{get, put},
};
use kinevo_http::{
    auth::Auth,
    extract::{Validate, ValidatedJson},
    problem::ApiError,
    route::{Access, IdemMode, RateClass, RouteSpec, RouteTable},
};
use kinevo_kernel::validate::Collector;
use serde::{Deserialize, Serialize};

use crate::app::modules::{ModuleService, ModuleView};

/// Adds `GET /modules` and `PUT /admin/modules/{key}`.
#[must_use]
pub fn routes(service: Arc<ModuleService>) -> RouteTable {
    let list_service = Arc::clone(&service);
    let list = get(move |Auth(auth): Auth| {
        let service = Arc::clone(&list_service);
        async move {
            let items = service.list(&auth).await.map_err(ApiError)?;
            Ok::<_, ApiError>(Json(ModuleList { items }))
        }
    });

    let switch_service = service;
    let switch = put(
        move |Auth(auth): Auth,
              Path(key): Path<String>,
              ValidatedJson(body): ValidatedJson<SwitchBody>| {
            let service = Arc::clone(&switch_service);
            async move {
                let item = service
                    .set(&auth, &key, body.enabled, body.version)
                    .await
                    .map_err(ApiError)?;
                Ok::<_, ApiError>((StatusCode::OK, Json(item)))
            }
        },
    );

    RouteTable::new()
        .route(
            spec(
                Method::GET,
                "/modules",
                Access::Permission("admin.instance.read"),
                IdemMode::Off,
                "List compiled modules and caller-visible navigation",
                false,
            ),
            list,
        )
        .route(
            spec(
                Method::PUT,
                "/admin/modules/{key}",
                Access::Permission("admin.modules.manage"),
                IdemMode::Optional,
                "Switch a toggleable module",
                false,
            ),
            switch,
        )
}

fn spec(
    method: Method,
    path: &'static str,
    access: Access,
    idempotency: IdemMode,
    summary: &'static str,
    allow_disabled: bool,
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
        allow_disabled,
    }
}

#[derive(Serialize, utoipa::ToSchema)]
pub(crate) struct ModuleList {
    #[schema(value_type = Vec<serde_json::Value>)]
    items: Vec<ModuleView>,
}

#[derive(Deserialize, utoipa::ToSchema)]
#[serde(deny_unknown_fields)]
pub(crate) struct SwitchBody {
    enabled: bool,
    version: i64,
}

impl Validate for SwitchBody {
    fn validate(&self) -> Collector {
        Collector::new()
    }
}
