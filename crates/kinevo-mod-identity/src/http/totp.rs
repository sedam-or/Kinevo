//! Authenticated TOTP and recovery-code endpoints.

use std::sync::Arc;

use axum::{
    Json,
    extract::State,
    http::{HeaderValue, Method, StatusCode, header},
    response::{IntoResponse, Response},
    routing::{delete, post},
};
use kinevo_http::{
    auth::Auth,
    extract::{Validate, ValidatedJson},
    problem::ApiError,
    route::{Access, IdemMode, RateClass, RouteSpec, RouteTable},
    state::HttpState,
};
use kinevo_kernel::{
    errors::FieldCode,
    secret::Secret,
    validate::{Collector, text::Digits},
};
use serde::{Deserialize, Serialize};

use crate::{
    app::totp::{Enrolment, TotpService},
    domain::totp::RecoveryCode,
};

/// Builds the account enrolment, confirmation, and disable routes.
#[must_use]
pub fn routes(service: Arc<TotpService>) -> RouteTable {
    let enroll_service = Arc::clone(&service);
    let enroll = post(move |Auth(auth): Auth| {
        let service = Arc::clone(&enroll_service);
        async move {
            let enrolment = service.enroll(auth.account_id()).await.map_err(ApiError)?;
            Ok::<_, ApiError>(enrolment_response(&enrolment))
        }
    });

    let confirm_service = Arc::clone(&service);
    let confirm = post(
        move |State(state): State<HttpState>,
              Auth(auth): Auth,
              ValidatedJson(body): ValidatedJson<CodeBody>| {
            let service = Arc::clone(&confirm_service);
            async move {
                service
                    .confirm(auth.account_id(), Secret::new(body.code), state.clock.now())
                    .await
                    .map_err(ApiError)?;
                Ok::<_, ApiError>(no_store(StatusCode::NO_CONTENT))
            }
        },
    );

    let disable = delete(
        move |Auth(auth): Auth, ValidatedJson(body): ValidatedJson<DisableBody>| {
            let service = Arc::clone(&service);
            async move {
                service
                    .disable(
                        auth.account_id(),
                        Secret::new(body.password),
                        Secret::new(body.code),
                    )
                    .await
                    .map_err(ApiError)?;
                Ok::<_, ApiError>(no_store(StatusCode::NO_CONTENT))
            }
        },
    );

    RouteTable::new()
        .route(
            spec(
                Method::POST,
                "/account/totp/enroll",
                "Enrol a TOTP authenticator",
            ),
            enroll,
        )
        .route(
            spec(
                Method::POST,
                "/account/totp/confirm",
                "Confirm TOTP enrolment",
            ),
            confirm,
        )
        .route(
            spec(
                Method::DELETE,
                "/account/totp",
                "Disable TOTP authentication",
            ),
            disable,
        )
}

#[derive(Serialize)]
struct EnrolmentResponse {
    otpauth_uri: String,
    recovery_codes: Vec<String>,
}

#[derive(Deserialize)]
#[serde(deny_unknown_fields)]
struct CodeBody {
    code: String,
}

impl Validate for CodeBody {
    fn validate(&self) -> Collector {
        let mut collector = Collector::new();
        let _: Option<()> = collector.check(
            "code",
            Digits::parse(&self.code, 6)
                .map(|_| ())
                .map_err(|_| FieldCode::DigitsInvalid),
        );
        collector
    }
}

#[derive(Deserialize)]
#[serde(deny_unknown_fields)]
struct DisableBody {
    password: String,
    code: String,
}

impl Validate for DisableBody {
    fn validate(&self) -> Collector {
        let mut collector = Collector::new();
        if Digits::parse(&self.code, 6).is_err() && RecoveryCode::parse(&self.code).is_none() {
            let _: Option<()> = collector.check("code", Err(FieldCode::DigitsInvalid));
        }
        collector
    }
}

fn enrolment_response(enrolment: &Enrolment) -> Response {
    let body = EnrolmentResponse {
        otpauth_uri: enrolment.otpauth_uri.expose().clone(),
        recovery_codes: enrolment
            .recovery_codes
            .iter()
            .map(|code| code.as_str().to_owned())
            .collect(),
    };
    let mut response = Json(body).into_response();
    response
        .headers_mut()
        .insert(header::CACHE_CONTROL, HeaderValue::from_static("no-store"));
    response
}

fn no_store(status: StatusCode) -> Response {
    let mut response = status.into_response();
    response
        .headers_mut()
        .insert(header::CACHE_CONTROL, HeaderValue::from_static("no-store"));
    response
}

fn spec(method: Method, path: &'static str, summary: &'static str) -> RouteSpec {
    RouteSpec {
        method,
        path,
        module: "identity",
        access: Access::Permission("identity.totp.manage"),
        rate: RateClass::Api,
        idempotency: IdemMode::Optional,
        store_response_body: false,
        cookie_endpoint: false,
        summary,
        allow_disabled: false,
    }
}
