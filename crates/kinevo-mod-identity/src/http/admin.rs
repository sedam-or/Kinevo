//! Instance administrator account and invite endpoints.

use std::sync::Arc;

use axum::{
    Json,
    extract::{Path, State},
    http::{HeaderValue, Method, StatusCode, header},
    response::{IntoResponse, Response},
    routing::{get, patch, post},
};
use kinevo_http::{
    auth::Auth,
    concurrency::{IfMatch, Version},
    extract::{StrictQuery, Validate, ValidatedJson},
    problem::ApiError,
    route::{Access, IdemMode, RateClass, RouteSpec, RouteTable},
    state::HttpState,
};
use kinevo_kernel::{
    errors::{AppError, ErrorCode, FieldCode, FieldError},
    page::PageRequest,
    validate::{Collector, net::Email},
};
use kinevo_ports::platform::AccountStatus;
use serde::{Deserialize, Serialize};
use sqlx::types::Uuid;

use crate::app::{
    admin_accounts::AdminAccountsService, invites::InviteService, reset::ResetService,
};

/// Creates the account metadata list and status-update routes.
#[must_use]
pub fn accounts(service: Arc<AdminAccountsService>) -> RouteTable {
    let list_service = Arc::clone(&service);
    let list = get(
        move |State(state): State<HttpState>,
              Auth(_auth): Auth,
              StrictQuery(query): StrictQuery<AccountQuery>| {
            let service = Arc::clone(&list_service);
            async move {
                let page = PageRequest::parse(
                    query.limit,
                    query.cursor.as_deref(),
                    state.cursor.as_ref(),
                    "/api/v1/admin/accounts",
                    "created_at_desc,id_desc",
                )?;
                service.list(page).await.map(Json).map_err(ApiError)
            }
        },
    );
    let update = patch(
        move |Auth(auth): Auth,
              Path(id): Path<Uuid>,
              IfMatch(Version(version)): IfMatch,
              ValidatedJson(body): ValidatedJson<StatusBody>| {
            let service = Arc::clone(&service);
            async move {
                let status = match body.status.as_str() {
                    "active" => AccountStatus::Active,
                    "disabled" => AccountStatus::Disabled,
                    _ => return Err(ApiError(AppError::new(ErrorCode::ValidationFailed))),
                };
                service
                    .set_status(
                        auth.account_id(),
                        kinevo_kernel::id::AccountId::from_uuid(id),
                        status,
                        version,
                    )
                    .await
                    .map_err(ApiError)?;
                Ok::<_, ApiError>(StatusCode::OK)
            }
        },
    );
    RouteTable::new()
        .route(
            spec(
                Method::GET,
                "/admin/accounts",
                "identity.admin.accounts.read",
                RateClass::Api,
                "List account metadata",
                false,
            ),
            list,
        )
        .route(
            spec(
                Method::PATCH,
                "/admin/accounts/{id}",
                "identity.admin.accounts.update",
                RateClass::Api,
                "Change account status",
                false,
            ),
            update,
        )
}

/// Creates the one-time invite link endpoint.
#[must_use]
pub fn invites(service: Arc<InviteService>) -> RouteTable {
    let handler = post(
        move |Auth(auth): Auth, ValidatedJson(body): ValidatedJson<InviteBody>| {
            let service = Arc::clone(&service);
            async move {
                let email = body
                    .email
                    .as_deref()
                    .map(Email::parse)
                    .transpose()
                    .map_err(|_| {
                        ApiError(AppError::validation(vec![FieldError {
                            path: "email".to_owned(),
                            code: FieldCode::EmailInvalid,
                            details: kinevo_kernel::errors::Details::new(),
                        }]))
                    })?;
                let url = service
                    .create(auth.account_id(), email)
                    .await
                    .map_err(ApiError)?;
                let mut response = (
                    StatusCode::CREATED,
                    Json(InviteResponse {
                        invite_url: url.expose().clone(),
                    }),
                )
                    .into_response();
                response
                    .headers_mut()
                    .insert(header::CACHE_CONTROL, HeaderValue::from_static("no-store"));
                Ok::<Response, ApiError>(response)
            }
        },
    );
    RouteTable::new().route(
        spec(
            Method::POST,
            "/admin/invites",
            "identity.admin.invite.create",
            RateClass::Api,
            "Create an invite",
            false,
        ),
        handler,
    )
}

/// Creates the administrator-issued password-reset endpoint.
#[must_use]
pub fn issue_reset(service: Arc<ResetService>) -> RouteTable {
    let handler = post(move |Auth(auth): Auth, Path(id): Path<Uuid>| {
        let service = Arc::clone(&service);
        async move {
            let link = service
                .issue_for_account(
                    kinevo_kernel::id::AccountId::from_uuid(id),
                    Some(auth.account_id()),
                )
                .await
                .map_err(ApiError)?;
            let mut response = (
                StatusCode::CREATED,
                Json(ResetResponse {
                    reset_url: link.url.expose().clone(),
                }),
            )
                .into_response();
            response
                .headers_mut()
                .insert(header::CACHE_CONTROL, HeaderValue::from_static("no-store"));
            Ok::<Response, ApiError>(response)
        }
    });
    RouteTable::new().route(
        spec(
            Method::POST,
            "/admin/accounts/{id}/password-reset",
            "identity.admin.password_reset.issue",
            RateClass::Api,
            "Issue a password reset link",
            false,
        ),
        handler,
    )
}

#[derive(Deserialize, utoipa::ToSchema)]
#[serde(deny_unknown_fields)]
pub(crate) struct AccountQuery {
    limit: Option<i64>,
    cursor: Option<String>,
}

#[derive(Deserialize, utoipa::ToSchema)]
#[serde(deny_unknown_fields)]
pub(crate) struct StatusBody {
    status: String,
}

impl Validate for StatusBody {
    fn validate(&self) -> Collector {
        Collector::new()
    }
}

#[derive(Deserialize, utoipa::ToSchema)]
#[serde(deny_unknown_fields)]
pub(crate) struct InviteBody {
    email: Option<String>,
}

impl Validate for InviteBody {
    fn validate(&self) -> Collector {
        Collector::new()
    }
}

#[derive(Serialize, utoipa::ToSchema)]
pub(crate) struct InviteResponse {
    invite_url: String,
}

#[derive(Serialize, utoipa::ToSchema)]
pub(crate) struct ResetResponse {
    reset_url: String,
}

fn spec(
    method: Method,
    path: &'static str,
    permission: &'static str,
    rate: RateClass,
    summary: &'static str,
    store_response_body: bool,
) -> RouteSpec {
    RouteSpec {
        method,
        path,
        module: "identity",
        access: Access::Permission(permission),
        rate,
        idempotency: IdemMode::Off,
        store_response_body,
        cookie_endpoint: false,
        summary,
        allow_disabled: false,
    }
}
