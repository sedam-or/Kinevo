//! Route metadata and assembly for the versioned API.

use crate::{
    gate::{GateBuildError, GateRequestState, gate_request},
    layers::request_context,
    problem::panic_response,
    state::HttpState,
};
use axum::http::Method;
use axum::{Router, routing::MethodRouter};
use std::sync::Arc;
use tower_http::catch_panic::CatchPanicLayer;

/// Whether a route is public or requires a registered permission key.
#[derive(Clone, Copy, Debug, Eq, PartialEq)]
pub enum Access {
    /// The route may be reached without an account token.
    Public,
    /// The route requires this permission after authentication.
    Permission(&'static str),
}

/// Rate limiting class consumed by the later rate-limit layer.
#[derive(Clone, Copy, Debug, Eq, PartialEq)]
pub enum RateClass {
    /// No rate limit is applied.
    None,
    /// Authentication flow limit.
    Auth,
    /// General account API limit.
    Api,
    /// AI provider limit.
    Ai,
    /// Upload limit.
    Upload,
    /// Synchronisation limit.
    Sync,
    /// Export limit.
    Export,
}

/// Idempotency behaviour consumed by the later idempotency layer.
#[derive(Clone, Copy, Debug, Eq, PartialEq)]
pub enum IdemMode {
    /// The route does not use an idempotency key.
    Off,
    /// The route accepts an optional idempotency key.
    Optional,
}

/// Static route descriptor used by gates, architecture tests and `OpenAPI` assembly.
#[derive(Clone, Debug)]
pub struct RouteSpec {
    /// Method accepted by the route.
    pub method: Method,
    /// Path template, relative to `/api/v1` unless it is a system health path.
    pub path: &'static str,
    /// Owning module key.
    pub module: &'static str,
    /// Public access or one required permission key.
    pub access: Access,
    /// Route rate class.
    pub rate: RateClass,
    /// Route idempotency behaviour.
    pub idempotency: IdemMode,
    /// Human-readable `OpenAPI` summary.
    pub summary: &'static str,
    /// Allows a disabled or deleting account through this route (only `GET /instance`).
    pub allow_disabled: bool,
}

/// A router paired with the descriptors used to build it.
pub struct RouteTable {
    router: Router<HttpState>,
    specs: Vec<RouteSpec>,
}

impl RouteTable {
    /// Starts an empty route table.
    #[must_use]
    pub fn new() -> Self {
        Self {
            router: Router::new(),
            specs: Vec::new(),
        }
    }

    /// Adds the method router and its mandatory access descriptor.
    #[must_use]
    pub fn route(mut self, spec: RouteSpec, method_router: MethodRouter<HttpState>) -> Self {
        let path = route_path(spec.path);
        self.router = self.router.route(&path, method_router);
        self.specs.push(spec);
        self
    }

    /// Returns all registered descriptors in registration order.
    #[must_use]
    pub fn specs(&self) -> &[RouteSpec] {
        &self.specs
    }

    /// Builds the complete router after validating route permissions and module ownership.
    pub fn into_router(self, state: HttpState) -> Result<Router, GateBuildError> {
        state.gate.validate_routes(&self.specs)?;
        let gate_state = GateRequestState {
            state: state.clone(),
            specs: Arc::new(self.specs),
        };
        Ok(self
            .router
            .layer(CatchPanicLayer::custom(|_| panic_response()))
            .layer(axum::middleware::from_fn_with_state(
                gate_state,
                gate_request,
            ))
            .layer(axum::middleware::from_fn_with_state(
                state.clone(),
                request_context,
            ))
            .with_state(state))
    }
}

impl Default for RouteTable {
    fn default() -> Self {
        Self::new()
    }
}

pub(crate) fn route_path(path: &str) -> String {
    if matches!(path, "/livez" | "/readyz") || path.starts_with("/api/v1/") {
        return path.to_owned();
    }
    if path == "/api/v1" {
        return path.to_owned();
    }
    format!("/api/v1/{}", path.trim_start_matches('/'))
}

#[cfg(test)]
mod tests {
    use super::Access;
    use crate::system_routes;

    #[test]
    fn rbac_01_every_route_declares_access() {
        let table = system_routes::system_routes();
        let specs = table.specs();
        assert!(!specs.is_empty());
        assert!(specs.iter().all(|spec| match spec.access {
            Access::Public | Access::Permission(_) => true,
        }));
        assert!(specs.iter().any(|spec| spec.path == "/livez"));
        assert!(specs.iter().any(|spec| spec.path == "/readyz"));
        assert!(specs.iter().any(|spec| spec.path == "time"));
    }
}
