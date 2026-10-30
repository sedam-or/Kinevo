//! Authenticated account-profile and session endpoints.

use std::sync::Arc;

use axum::{
    Json,
    body::Bytes,
    extract::{Path, State},
    http::{HeaderMap, Method, StatusCode, header},
    routing::{delete, get, patch, post},
};
use kinevo_http::{
    auth::Auth,
    concurrency::{IfMatch, Version},
    extract::{PageParams, SortParam, StrictQuery, Validate, ValidatedJson},
    problem::ApiError,
    route::{Access, IdemMode, RateClass, RouteSpec, RouteTable},
    state::HttpState,
};
use kinevo_kernel::{
    errors::{AppError, DetailValue, Details, ErrorCode, FieldCode, FieldError},
    id::FamilyId,
    page::{CursorPart, CursorPosition},
    secret::Secret,
    validate::Collector,
};
use serde::{Deserialize, Serialize};
use serde_json::{Map, Value};
use sqlx::types::Uuid;

use crate::{
    app::{
        password::PasswordService,
        profile::ProfileService,
        sessions::{SessionService, SessionView},
    },
    domain::profile::ProfilePatch,
};

const SESSIONS_PATH: &str = "/account/sessions";

#[derive(Deserialize, utoipa::ToSchema)]
#[serde(deny_unknown_fields)]
pub(crate) struct PasswordChangeBody {
    current_password: String,
    new_password: String,
}

impl Validate for PasswordChangeBody {
    fn validate(&self) -> Collector {
        Collector::new()
    }
}

#[derive(Deserialize, utoipa::ToSchema)]
#[serde(deny_unknown_fields)]
pub(crate) struct SessionsQuery {
    limit: Option<String>,
    cursor: Option<String>,
    sort: Option<String>,
}

#[derive(Serialize, utoipa::ToSchema)]
pub(crate) struct SessionsPage {
    #[schema(value_type = Vec<serde_json::Value>)]
    items: Vec<SessionView>,
    next_cursor: Option<String>,
}

/// Builds the five authenticated account routes.
#[must_use]
#[allow(
    clippy::too_many_lines,
    reason = "five account endpoints share one route table and their metadata"
)]
pub fn routes(
    profiles: Arc<ProfileService>,
    passwords: Arc<PasswordService>,
    sessions: Arc<SessionService>,
) -> RouteTable {
    let update_profiles = Arc::clone(&profiles);
    let get_profile = get(move |Auth(auth): Auth| {
        let service = Arc::clone(&profiles);
        async move {
            service
                .get_profile(auth.account_id())
                .await
                .map(Json)
                .map_err(ApiError)
        }
    });

    let update_profile = patch(
        move |State(state): State<HttpState>,
              Auth(auth): Auth,
              IfMatch(Version(version)): IfMatch,
              headers: HeaderMap,
              body: Bytes| {
            let service = Arc::clone(&update_profiles);
            async move {
                let patch = parse_profile_patch(&headers, &body).map_err(ApiError)?;
                service
                    .update_profile(auth.account_id(), patch, version, state.clock.now())
                    .await
                    .map(Json)
                    .map_err(ApiError)
            }
        },
    );

    let change_password = post(
        move |Auth(auth): Auth, ValidatedJson(body): ValidatedJson<PasswordChangeBody>| {
            let service = Arc::clone(&passwords);
            async move {
                service
                    .change_password(
                        auth.account_id(),
                        Secret::new(body.current_password),
                        Secret::new(body.new_password),
                        auth.session_id(),
                    )
                    .await
                    .map_err(ApiError)?;
                Ok::<_, ApiError>(StatusCode::NO_CONTENT)
            }
        },
    );

    let list_sessions = Arc::clone(&sessions);
    let list = get(
        move |State(state): State<HttpState>,
              Auth(auth): Auth,
              StrictQuery(query): StrictQuery<SessionsQuery>| {
            let service = Arc::clone(&list_sessions);
            async move {
                let sort = SortParam::parse(
                    query.sort.as_deref().unwrap_or("-last_used_at"),
                    &["last_used_at"],
                )?;
                if sort.field != "last_used_at" || !sort.descending {
                    return Err(ApiError(AppError::new(ErrorCode::SortInvalid).with_detail(
                        "allowed",
                        DetailValue::StrList(vec!["-last_used_at".to_owned()]),
                    )));
                }
                let sort_key = sort.sort_key();
                let page = PageParams::parse(
                    query.limit.as_deref(),
                    query.cursor.as_deref(),
                    state.cursor.as_ref(),
                    SESSIONS_PATH,
                    &sort,
                )?;
                let sessions = service
                    .list(auth.account_id(), auth.session_id())
                    .await
                    .map_err(ApiError)?;
                let mut items = Vec::with_capacity(sessions.len());
                for session in sessions {
                    let after = match page.cursor.as_ref() {
                        Some(cursor) => session_after_cursor(&session, cursor).map_err(ApiError)?,
                        None => true,
                    };
                    if after {
                        items.push(session);
                    }
                }
                let limit = usize::from(page.limit);
                let has_more = items.len() > limit;
                items.truncate(limit);
                let next_cursor = if has_more {
                    items.last().map(|last| {
                        state.cursor.encode(
                            SESSIONS_PATH,
                            &sort_key,
                            &CursorPosition(vec![
                                CursorPart::Instant(last.last_used_at),
                                CursorPart::Id(*last.id.as_uuid()),
                            ]),
                        )
                    })
                } else {
                    None
                };
                Ok::<_, ApiError>(Json(SessionsPage { items, next_cursor }))
            }
        },
    );

    let revoke = delete(move |Auth(auth): Auth, Path(id): Path<Uuid>| {
        let service = Arc::clone(&sessions);
        async move {
            service
                .revoke(auth.account_id(), FamilyId::from_uuid(id))
                .await
                .map_err(ApiError)?;
            Ok::<_, ApiError>(StatusCode::NO_CONTENT)
        }
    });

    RouteTable::new()
        .route(
            spec(
                Method::GET,
                "/account",
                "identity.account.read",
                IdemMode::Off,
                "Read the account profile",
            ),
            get_profile,
        )
        .route(
            spec(
                Method::PATCH,
                "/account",
                "identity.account.update",
                IdemMode::Off,
                "Update the account profile",
            ),
            update_profile,
        )
        .route(
            spec(
                Method::POST,
                "/account/password",
                "identity.account.update",
                IdemMode::Optional,
                "Change the account password",
            ),
            change_password,
        )
        .route(
            spec(
                Method::GET,
                SESSIONS_PATH,
                "identity.session.manage",
                IdemMode::Off,
                "List account sessions",
            ),
            list,
        )
        .route(
            spec(
                Method::DELETE,
                "/account/sessions/{id}",
                "identity.session.manage",
                IdemMode::Optional,
                "Revoke an account session",
            ),
            revoke,
        )
}

fn spec(
    method: Method,
    path: &'static str,
    permission: &'static str,
    idempotency: IdemMode,
    summary: &'static str,
) -> RouteSpec {
    RouteSpec {
        method,
        path,
        module: "identity",
        access: Access::Permission(permission),
        rate: RateClass::Api,
        idempotency,
        store_response_body: false,
        cookie_endpoint: false,
        summary,
        allow_disabled: false,
    }
}

fn parse_profile_patch(headers: &HeaderMap, body: &[u8]) -> Result<ProfilePatch, AppError> {
    let content_type = headers
        .get(header::CONTENT_TYPE)
        .and_then(|value| value.to_str().ok())
        .unwrap_or_default();
    let media_type = content_type.split(';').next().unwrap_or_default().trim();
    if !media_type.eq_ignore_ascii_case("application/json") {
        return Err(AppError::new(ErrorCode::UnsupportedMediaType).with_detail(
            "accepted",
            DetailValue::StrList(vec!["application/json".to_owned()]),
        ));
    }

    let value: Value = serde_json::from_slice(body).map_err(|_| schema_error("body"))?;
    let Some(object) = value.as_object() else {
        return Err(schema_error("body"));
    };
    let allowed = ["display_name", "locale", "timezone", "week_start"];
    let unknown = object
        .keys()
        .filter(|key| !allowed.contains(&key.as_str()))
        .map(|key| FieldError {
            path: key.clone(),
            code: FieldCode::UnknownField,
            details: Details::new(),
        })
        .collect::<Vec<_>>();
    if !unknown.is_empty() {
        return Err(AppError::validation(unknown));
    }
    if let Some((path, _)) = object.iter().find(|(_, value)| value.is_null()) {
        return Err(AppError::new(ErrorCode::FieldNotNullable)
            .with_detail("path", DetailValue::OwnedStr(path.clone())));
    }

    let mut errors = Vec::new();
    let display_name = string_field(object, "display_name", &mut errors);
    let locale = string_field(object, "locale", &mut errors);
    let timezone = string_field(object, "timezone", &mut errors);
    let week_start = integer_field(object, "week_start", &mut errors);
    if !errors.is_empty() {
        return Err(AppError::validation(errors));
    }
    Ok(ProfilePatch {
        display_name,
        locale,
        timezone,
        week_start,
    })
}

fn string_field(
    object: &Map<String, Value>,
    name: &'static str,
    errors: &mut Vec<FieldError>,
) -> Option<String> {
    match object.get(name) {
        None => None,
        Some(Value::String(value)) => Some(value.clone()),
        Some(_) => {
            errors.push(schema_field_error(name));
            None
        }
    }
}

fn integer_field(
    object: &Map<String, Value>,
    name: &'static str,
    errors: &mut Vec<FieldError>,
) -> Option<i64> {
    match object.get(name) {
        None => None,
        Some(Value::Number(value)) => value.as_i64().or_else(|| {
            errors.push(schema_field_error(name));
            None
        }),
        Some(_) => {
            errors.push(schema_field_error(name));
            None
        }
    }
}

fn schema_error(path: &str) -> AppError {
    AppError::validation(vec![FieldError {
        path: path.to_owned(),
        code: FieldCode::SchemaInvalid,
        details: Details::new(),
    }])
}

fn schema_field_error(path: &'static str) -> FieldError {
    FieldError {
        path: path.to_owned(),
        code: FieldCode::SchemaInvalid,
        details: Details::new(),
    }
}

fn session_after_cursor(session: &SessionView, cursor: &CursorPosition) -> Result<bool, AppError> {
    let (Some(CursorPart::Instant(last_used)), Some(CursorPart::Id(id))) =
        (cursor.0.first(), cursor.0.get(1))
    else {
        return Err(AppError::new(ErrorCode::CursorInvalid));
    };
    if cursor.0.len() != 2 {
        return Err(AppError::new(ErrorCode::CursorInvalid));
    }
    Ok(session.last_used_at < *last_used
        || (session.last_used_at == *last_used && *session.id.as_uuid() > *id))
}
