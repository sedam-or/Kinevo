//! Authenticated instance summary endpoint.

use std::sync::Arc;

use axum::{Json, http::Method, routing::get};
use kinevo_http::{
    auth::Auth,
    problem::ApiError,
    route::{Access, IdemMode, RateClass, RouteSpec, RouteTable},
};

use crate::app::modules::ModuleService;

/// Adds the documented instance summary route.
#[must_use]
pub fn routes(service: Arc<ModuleService>) -> RouteTable {
    let get_service = service;
    let get_instance = get(move |Auth(auth): Auth| {
        let service = Arc::clone(&get_service);
        async move {
            service
                .instance_summary(&auth)
                .await
                .map(Json)
                .map_err(ApiError)
        }
    });

    RouteTable::new().route(
        RouteSpec {
            method: Method::GET,
            path: "/instance",
            module: "admin",
            access: Access::Permission("admin.instance.read"),
            rate: RateClass::Api,
            idempotency: IdemMode::Off,
            store_response_body: false,
            cookie_endpoint: false,
            summary: "Read instance settings",
            allow_disabled: true,
        },
        get_instance,
    )
}
