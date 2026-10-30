//! Identity HTTP routes and request adapters.

pub mod account;
pub mod admin;
pub mod cookie;
pub mod deletion;
pub mod dto;
pub mod export;
pub mod login;
pub mod openapi;
pub mod refresh;
pub mod register;
pub mod reset;
pub mod session_response;
pub mod totp;

/// Returns the identity route descriptors used to generate the `OpenAPI` contract.
#[must_use]
#[allow(
    clippy::too_many_lines,
    reason = "the descriptor inventory mirrors the registered identity routes"
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
        cookie_endpoint: bool,
    ) -> RouteSpec {
        RouteSpec {
            method,
            path,
            module: "identity",
            access,
            rate,
            idempotency,
            store_response_body: false,
            cookie_endpoint,
            summary,
            allow_disabled: false,
        }
    }

    use Access::{Permission, Public};
    use IdemMode::{Off, Optional};
    use RateClass::{Api, Auth, Export};

    vec![
        route(
            Method::GET,
            "/account",
            Permission("identity.account.read"),
            Api,
            Off,
            "Read the account profile",
            false,
        ),
        route(
            Method::PATCH,
            "/account",
            Permission("identity.account.update"),
            Api,
            Off,
            "Update the account profile",
            false,
        ),
        route(
            Method::POST,
            "/account/password",
            Permission("identity.account.update"),
            Api,
            Optional,
            "Change the account password",
            false,
        ),
        route(
            Method::GET,
            "/account/sessions",
            Permission("identity.session.manage"),
            Api,
            Off,
            "List account sessions",
            false,
        ),
        route(
            Method::DELETE,
            "/account/sessions/{id}",
            Permission("identity.session.manage"),
            Api,
            Optional,
            "Revoke an account session",
            false,
        ),
        route(
            Method::GET,
            "/admin/accounts",
            Permission("identity.admin.accounts.read"),
            Api,
            Off,
            "List account metadata",
            false,
        ),
        route(
            Method::PATCH,
            "/admin/accounts/{id}",
            Permission("identity.admin.accounts.update"),
            Api,
            Off,
            "Change account status",
            false,
        ),
        route(
            Method::POST,
            "/admin/invites",
            Permission("identity.admin.invite.create"),
            Api,
            Off,
            "Create an invite",
            false,
        ),
        route(
            Method::POST,
            "/admin/accounts/{id}/password-reset",
            Permission("identity.admin.password_reset.issue"),
            Api,
            Off,
            "Issue a password reset link",
            false,
        ),
        route(
            Method::DELETE,
            "/account",
            Permission("identity.account.delete"),
            Api,
            Optional,
            "Request account deletion",
            false,
        ),
        route(
            Method::POST,
            "/account/export",
            Permission("identity.account.export"),
            Export,
            Optional,
            "Request account data export",
            false,
        ),
        route(
            Method::GET,
            "/account/export/{id}",
            Permission("identity.account.export"),
            Api,
            Off,
            "Get account export status",
            false,
        ),
        route(
            Method::GET,
            "/account/export/{id}/download",
            Permission("identity.account.export"),
            Export,
            Off,
            "Download account export archive",
            false,
        ),
        route(
            Method::POST,
            "/auth/login",
            Public,
            Auth,
            Off,
            "Sign in with an email and password",
            true,
        ),
        route(
            Method::POST,
            "/auth/refresh",
            Public,
            Auth,
            Off,
            "Refresh the current session",
            true,
        ),
        route(
            Method::POST,
            "/auth/logout",
            Public,
            Auth,
            Off,
            "Sign out the current session",
            true,
        ),
        route(
            Method::POST,
            "/auth/logout-all",
            Permission("identity.session.manage"),
            Api,
            Off,
            "Sign out every session for this account",
            false,
        ),
        route(
            Method::POST,
            "/auth/register",
            Public,
            Auth,
            Off,
            "Create an account",
            true,
        ),
        route(
            Method::POST,
            "/auth/password-reset/request",
            Public,
            Auth,
            Off,
            "Request a password reset",
            false,
        ),
        route(
            Method::POST,
            "/auth/password-reset/confirm",
            Public,
            Auth,
            Off,
            "Confirm a password reset",
            false,
        ),
        route(
            Method::POST,
            "/account/totp/enroll",
            Permission("identity.totp.manage"),
            Api,
            Optional,
            "Enrol a TOTP authenticator",
            false,
        ),
        route(
            Method::POST,
            "/account/totp/confirm",
            Permission("identity.totp.manage"),
            Api,
            Optional,
            "Confirm TOTP enrolment",
            false,
        ),
        route(
            Method::DELETE,
            "/account/totp",
            Permission("identity.totp.manage"),
            Api,
            Optional,
            "Disable TOTP authentication",
            false,
        ),
    ]
}
