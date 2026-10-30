//! Admin-wide and account-owner audit endpoints.

use std::sync::Arc;

use axum::{
    Json,
    extract::{Query, State},
    http::Method,
    routing::get,
};
use kinevo_http::{
    auth::Auth,
    extract::{PageParams, SortParam},
    problem::ApiError,
    route::{Access, IdemMode, RateClass, RouteSpec, RouteTable},
    state::HttpState,
};
use kinevo_kernel::{
    errors::{AppError, ErrorCode},
    id::AccountId,
    page::{CursorPart, CursorPosition, Page},
};
use serde::Deserialize;
use sqlx::types::Uuid;

use crate::app::audit::{AuditFilter, AuditService, AuditView};

const ADMIN_ENDPOINT: &str = "/api/v1/admin/audit";
const OWNER_ENDPOINT: &str = "/api/v1/account/audit";
const SORT: &str = "-at,id";

/// Adds the admin and owner audit list routes.
#[must_use]
pub fn routes(service: Arc<AuditService>) -> RouteTable {
    let admin_service = Arc::clone(&service);
    let admin = get(
        move |Auth(auth): Auth, State(state): State<HttpState>, Query(query): Query<AuditQuery>| {
            let service = Arc::clone(&admin_service);
            async move {
                let page = page_params(&query, &state, ADMIN_ENDPOINT)?;
                let filter = AuditFilter {
                    action: query.action,
                    from: query.from,
                    to: query.to,
                    account_id: parse_account_id(query.account_id)?,
                    before: audit_cursor(page.cursor)?,
                    limit: page.limit,
                };
                let rows = service
                    .list_admin(auth.account_id(), filter)
                    .await
                    .map_err(ApiError)?;
                Ok::<_, ApiError>(Json(to_page(rows, page.limit, &state, ADMIN_ENDPOINT)))
            }
        },
    );

    let own_service = service;
    let own = get(
        move |Auth(auth): Auth, State(state): State<HttpState>, Query(query): Query<AuditQuery>| {
            let service = Arc::clone(&own_service);
            async move {
                let page = page_params(&query, &state, OWNER_ENDPOINT)?;
                let filter = AuditFilter {
                    action: query.action,
                    from: query.from,
                    to: query.to,
                    account_id: None,
                    before: audit_cursor(page.cursor)?,
                    limit: page.limit,
                };
                let rows = service
                    .list_own(auth.account_id(), filter)
                    .await
                    .map_err(ApiError)?;
                Ok::<_, ApiError>(Json(to_page(rows, page.limit, &state, OWNER_ENDPOINT)))
            }
        },
    );

    RouteTable::new()
        .route(
            spec(
                Method::GET,
                "/admin/audit",
                Access::Permission("admin.audit.read"),
                "Read the instance audit log",
            ),
            admin,
        )
        .route(
            spec(
                Method::GET,
                "/account/audit",
                Access::Permission("admin.audit.read_own"),
                "Read the caller's audit log",
            ),
            own,
        )
}

#[derive(Deserialize, utoipa::ToSchema)]
#[serde(deny_unknown_fields)]
pub(crate) struct AuditQuery {
    action: Option<String>,
    #[schema(value_type = Option<String>, format = DateTime)]
    from: Option<kinevo_kernel::time::Instant>,
    #[schema(value_type = Option<String>, format = DateTime)]
    to: Option<kinevo_kernel::time::Instant>,
    account_id: Option<String>,
    cursor: Option<String>,
    limit: Option<String>,
}

fn page_params(
    query: &AuditQuery,
    state: &HttpState,
    endpoint: &str,
) -> Result<PageParams, ApiError> {
    let sort = SortParam::parse("-at", &["at"]).map_err(ApiError)?;
    PageParams::parse(
        query.limit.as_deref(),
        query.cursor.as_deref(),
        &state.cursor,
        endpoint,
        &sort,
    )
    .map_err(ApiError)
}

fn audit_cursor(
    position: Option<CursorPosition>,
) -> Result<Option<kinevo_db::audit::AuditCursor>, ApiError> {
    let Some(position) = position else {
        return Ok(None);
    };
    match position.0.as_slice() {
        [CursorPart::Instant(at), CursorPart::Id(id)] => {
            Ok(Some(kinevo_db::audit::AuditCursor { at: *at, id: *id }))
        }
        _ => Err(ApiError(AppError::new(ErrorCode::CursorInvalid))),
    }
}

fn parse_account_id(value: Option<String>) -> Result<Option<AccountId>, ApiError> {
    value
        .map(|value| {
            Uuid::parse_str(&value)
                .map(AccountId::from_uuid)
                .map_err(|_| ApiError(AppError::new(ErrorCode::ValidationFailed)))
        })
        .transpose()
}

fn to_page(rows: Vec<AuditView>, limit: u16, state: &HttpState, endpoint: &str) -> Page<AuditView> {
    Page::from_rows(rows, limit, |row| {
        state.cursor.encode(
            endpoint,
            SORT,
            &CursorPosition(vec![CursorPart::Instant(row.at), CursorPart::Id(row.id)]),
        )
    })
}

fn spec(method: Method, path: &'static str, access: Access, summary: &'static str) -> RouteSpec {
    RouteSpec {
        method,
        path,
        module: "admin",
        access,
        rate: RateClass::Api,
        idempotency: IdemMode::Off,
        store_response_body: false,
        cookie_endpoint: false,
        summary,
        allow_disabled: false,
    }
}
