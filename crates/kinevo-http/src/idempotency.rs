//! Optional, account-scoped HTTP idempotency middleware.

use crate::{
    auth::AuthCtx,
    problem::ApiError,
    route::{IdemMode, RouteSpec, route_path},
    state::HttpState,
};
use axum::{
    body::{Body, to_bytes},
    extract::{MatchedPath, Request, State},
    http::{HeaderValue, Method, StatusCode, header},
    middleware::Next,
    response::{IntoResponse, Response},
};
use kinevo_kernel::{
    crypto::hash_token,
    errors::{AppError, ErrorCode, FieldError},
    validate::IdemKey,
};
use kinevo_ports::platform::{IdemBegin, IdempotencyPort};
use std::sync::Arc;

/// Responses larger than this are returned but their body is not retained for replay.
pub const MAX_IDEMPOTENCY_RESPONSE_BODY_BYTES: usize = 262_144;

#[derive(Clone)]
pub(crate) struct IdempotencyRequestState {
    pub state: HttpState,
    pub specs: Arc<Vec<RouteSpec>>,
}

/// Applies idempotency after authentication and before the route handler.
pub(crate) async fn idempotency_request(
    State(context): State<IdempotencyRequestState>,
    mut request: Request,
    next: Next,
) -> Response {
    let Some(spec) = matched_spec(&context.specs, &request) else {
        return next.run(request).await;
    };
    if spec.idempotency != IdemMode::Optional
        || matches!(
            request.method(),
            &Method::GET | &Method::HEAD | &Method::OPTIONS
        )
    {
        return next.run(request).await;
    }
    let Some(auth) = request.extensions().get::<AuthCtx>().cloned() else {
        return next.run(request).await;
    };
    let Some(raw_key) = request.headers().get("Idempotency-Key") else {
        return next.run(request).await;
    };
    let key = raw_key
        .to_str()
        .ok()
        .and_then(|value| IdemKey::parse(value).ok());
    let Some(key) = key else {
        return ApiError(validation_error()).into_response();
    };
    let Some(port) = context.state.idempotency.clone() else {
        return ApiError(AppError::new(ErrorCode::ServiceUnavailable)).into_response();
    };
    let Some(ttl_seconds) = context.state.idempotency_ttl_seconds else {
        return ApiError(AppError::new(ErrorCode::ServiceUnavailable)).into_response();
    };

    let request_path = request.uri().path().to_owned();
    let method = request.method().as_str().to_ascii_uppercase();
    let Ok(request_body) = read_request_body(&mut request, context.state.max_body_bytes).await
    else {
        return ApiError(AppError::new(ErrorCode::PayloadTooLarge)).into_response();
    };
    *request.body_mut() = Body::from(request_body.clone());
    let request_hash = request_hash(&method, &request_path, &request_body);
    let begin = match port
        .begin(
            auth.account_id(),
            key.as_str(),
            &method,
            &request_path,
            request_hash,
            ttl_seconds,
        )
        .await
    {
        Ok(begin) => begin,
        Err(error) => return ApiError(error).into_response(),
    };

    match begin {
        IdemBegin::Fresh => {
            let response = next.run(request).await;
            finish_response(
                response,
                &port,
                auth.account_id(),
                key.as_str(),
                spec.store_response_body,
            )
            .await
        }
        IdemBegin::Replay {
            status,
            content_type,
            body,
        } => replay_response(status, content_type.as_deref(), body),
        IdemBegin::InProgress => {
            ApiError(AppError::new(ErrorCode::IdempotencyInProgress)).into_response()
        }
        IdemBegin::Mismatch => {
            ApiError(AppError::new(ErrorCode::IdempotencyKeyReuse)).into_response()
        }
    }
}

async fn read_request_body(request: &mut Request, max_bytes: usize) -> Result<Vec<u8>, ()> {
    let body = std::mem::replace(request.body_mut(), Body::empty());
    to_bytes(body, max_bytes.saturating_add(1))
        .await
        .map(|body| body.to_vec())
        .map_err(|_| ())
}

fn request_hash(method: &str, path: &str, body: &[u8]) -> [u8; 32] {
    let mut material = Vec::with_capacity(method.len() + path.len() + body.len() + 2);
    material.extend_from_slice(method.as_bytes());
    material.push(0);
    material.extend_from_slice(path.as_bytes());
    material.push(0);
    material.extend_from_slice(body);
    hash_token(&material)
}

async fn finish_response(
    response: Response,
    port: &Arc<dyn IdempotencyPort>,
    account: kinevo_kernel::id::AccountId,
    key: &str,
    store_response_body: bool,
) -> Response {
    let status = response.status();
    if status.is_server_error() || status == StatusCode::TOO_MANY_REQUESTS {
        if let Err(error) = port.release(account, key).await {
            return ApiError(error).into_response();
        }
        return response;
    }

    let (parts, body) = response.into_parts();
    let Ok(body) = to_bytes(body, usize::MAX).await else {
        if let Err(error) = port.release(account, key).await {
            return ApiError(error).into_response();
        }
        return ApiError(AppError::new(ErrorCode::InternalError)).into_response();
    };
    let can_store_body = store_response_body && body.len() <= MAX_IDEMPOTENCY_RESPONSE_BODY_BYTES;
    let content_type = can_store_body
        .then(|| parts.headers.get(header::CONTENT_TYPE))
        .flatten()
        .and_then(|value| value.to_str().ok())
        .map(str::to_owned);
    let stored_body = can_store_body.then(|| body.to_vec());
    if store_response_body && !can_store_body {
        if let Err(error) = port.release(account, key).await {
            return ApiError(error).into_response();
        }
    } else if let Err(error) = port
        .finish(account, key, status.as_u16(), content_type, stored_body)
        .await
    {
        return ApiError(error).into_response();
    }

    Response::from_parts(parts, Body::from(body))
}

fn replay_response(status: u16, content_type: Option<&str>, body: Vec<u8>) -> Response {
    let Ok(status) = StatusCode::from_u16(status) else {
        return ApiError(AppError::new(ErrorCode::InternalError)).into_response();
    };
    let mut response = Response::new(Body::from(body));
    *response.status_mut() = status;
    if let Some(content_type) = content_type.and_then(|value| HeaderValue::from_str(value).ok()) {
        response
            .headers_mut()
            .insert(header::CONTENT_TYPE, content_type);
    }
    response
        .headers_mut()
        .insert("Idempotency-Replayed", HeaderValue::from_static("true"));
    response
}

fn validation_error() -> AppError {
    AppError::validation(vec![FieldError {
        path: "Idempotency-Key".to_owned(),
        code: kinevo_kernel::errors::FieldCode::KeyInvalid,
        details: kinevo_kernel::errors::Details::default(),
    }])
}

fn matched_spec<'a>(specs: &'a [RouteSpec], request: &Request) -> Option<&'a RouteSpec> {
    let matched_path = request.extensions().get::<MatchedPath>()?.as_str();
    let method = if request.method() == Method::HEAD {
        &Method::GET
    } else {
        request.method()
    };
    specs
        .iter()
        .find(|spec| spec.method == *method && route_path(spec.path) == matched_path)
}
