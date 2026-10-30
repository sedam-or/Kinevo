//! Admin HTTP handlers are added by the follow-up admin tasks.

/// Admin and account-owner audit routes.
pub mod audit;

/// Secret management endpoints.
pub mod secrets;

/// Policy listing and write endpoints.
pub mod policies;

/// Module listing and switch endpoints.
pub mod modules;

/// Admin transport schemas included in the generated API contract.
pub mod openapi;

/// Instance summary endpoint.
pub mod instance;

/// Public setup status and wizard-session endpoints.
pub mod setup;

/// Admin job summary and outbox retry routes.
pub mod jobs;

/// Returns the admin route descriptors used to generate the `OpenAPI` contract.
#[must_use]
#[allow(
    clippy::too_many_lines,
    reason = "the descriptor inventory mirrors the registered admin routes"
)]
pub fn route_specs() -> Vec<kinevo_http::route::RouteSpec> {
    use axum::http::Method;
    use kinevo_http::route::{Access, IdemMode, RateClass, RouteSpec};

    fn route(
        method: Method,
        path: &'static str,
        access: Access,
        rate: RateClass,
        idempotency: IdemMode,
        summary: &'static str,
        allow_disabled: bool,
    ) -> RouteSpec {
        RouteSpec {
            method,
            path,
            module: "admin",
            access,
            rate,
            idempotency,
            store_response_body: false,
            cookie_endpoint: false,
            summary,
            allow_disabled,
        }
    }

    use Access::{Permission, Public};
    use IdemMode::{Off, Optional};
    use RateClass::{Api, Auth, None};

    let mut routes = vec![
        route(
            Method::GET,
            "/admin/audit",
            Permission("admin.audit.read"),
            Api,
            Off,
            "Read the instance audit log",
            false,
        ),
        route(
            Method::GET,
            "/account/audit",
            Permission("admin.audit.read_own"),
            Api,
            Off,
            "Read the caller's audit log",
            false,
        ),
        route(
            Method::GET,
            "/instance",
            Permission("admin.instance.read"),
            Api,
            Off,
            "Read instance settings",
            true,
        ),
        route(
            Method::GET,
            "/admin/jobs/summary",
            Permission("admin.jobs.read"),
            Api,
            Off,
            "Read a payload-free job summary",
            false,
        ),
        route(
            Method::POST,
            "/admin/outbox/{event_id}/{subscriber}/retry",
            Permission("admin.jobs.retry"),
            Api,
            Optional,
            "Queue a retry for a dead outbox delivery",
            false,
        ),
        route(
            Method::GET,
            "/modules",
            Permission("admin.instance.read"),
            Api,
            Off,
            "List compiled modules and caller-visible navigation",
            false,
        ),
        route(
            Method::PUT,
            "/admin/modules/{key}",
            Permission("admin.modules.manage"),
            Api,
            Optional,
            "Switch a toggleable module",
            false,
        ),
        route(
            Method::GET,
            "/policies",
            Permission("admin.policies.instance.read"),
            Api,
            Off,
            "List resolved policies",
            false,
        ),
        route(
            Method::PUT,
            "/policies/{key}",
            Permission("admin.policies.account.write"),
            Api,
            Optional,
            "Create a policy version",
            false,
        ),
        route(
            Method::GET,
            "/admin/secrets",
            Permission("admin.secrets.manage"),
            Api,
            Off,
            "List instance secrets",
            false,
        ),
        route(
            Method::PUT,
            "/admin/secrets/{name}",
            Permission("admin.secrets.manage"),
            Api,
            Optional,
            "Set an instance secret",
            false,
        ),
        route(
            Method::DELETE,
            "/admin/secrets/{name}",
            Permission("admin.secrets.manage"),
            Api,
            Optional,
            "Delete an instance secret",
            false,
        ),
        route(
            Method::GET,
            "/setup/status",
            Public,
            None,
            Off,
            "Read setup status",
            false,
        ),
        route(
            Method::POST,
            "/setup/session",
            Public,
            Auth,
            Off,
            "Open setup session",
            false,
        ),
        route(
            Method::GET,
            "/setup/session/{id}",
            Public,
            None,
            Off,
            "Read setup session",
            false,
        ),
        route(
            Method::PUT,
            "/setup/session/{id}",
            Public,
            None,
            Off,
            "Update setup session",
            false,
        ),
        route(
            Method::POST,
            "/setup/session/{id}/finish",
            Public,
            Auth,
            Optional,
            "Finish setup and create the instance administrator",
            false,
        ),
    ];
    if let Some(finish) = routes.last_mut() {
        finish.cookie_endpoint = true;
    }
    routes
}
