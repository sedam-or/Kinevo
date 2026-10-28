use kinevo_kernel::{
    errors::{AppError, Details, FieldCode, FieldError},
    secret::Secret,
    validate::{net::Email, text::SecretText},
};
use serde::{Deserialize, Serialize};
use utoipa::ToSchema;

use crate::app::login::{AccountProfile, LoginOutcome, LoginRequest};

/// JSON request body for `POST /auth/login`.
#[derive(Deserialize, ToSchema)]
#[serde(deny_unknown_fields)]
pub struct LoginDto {
    pub email: String,
    pub password: String,
    pub totp_code: Option<String>,
    pub recovery_code: Option<String>,
}

impl LoginDto {
    /// Validates public inputs before handing credentials to the use case.
    pub fn into_request(self) -> Result<LoginRequest, AppError> {
        let email = Email::parse(&self.email).map_err(|code| field_error("email", code))?;
        let password =
            SecretText::parse(self.password).map_err(|code| field_error("password", code))?;
        if self.totp_code.is_some() && self.recovery_code.is_some() {
            return Err(field_error("totp_code", FieldCode::DigitsInvalid));
        }
        let totp_code = self
            .totp_code
            .map(|code| {
                kinevo_kernel::validate::text::Digits::parse(&code, 6)
                    .map(|digits| digits.as_str().to_owned())
                    .map_err(|code| field_error("totp_code", code))
            })
            .transpose()?;
        let recovery_code = self
            .recovery_code
            .map(|code| {
                let normalized = code.to_ascii_uppercase();
                let valid = normalized.len() == 10
                    && normalized
                        .bytes()
                        .all(|byte| b"23456789ABCDEFGHJKLMNPQRSTUVWXYZ".contains(&byte));
                if valid {
                    Ok(Secret::new(normalized))
                } else {
                    Err(field_error("recovery_code", FieldCode::DigitsInvalid))
                }
            })
            .transpose()?;
        Ok(LoginRequest {
            email: email.as_str().to_owned(),
            password: Secret::new(password.expose().to_owned()),
            totp_code,
            recovery_code,
        })
    }
}

/// Safe response fields for a successful login.
#[derive(Serialize, ToSchema)]
pub struct LoginResponse {
    pub account: AccountProfile,
    pub access_token: String,
    pub expires_in: u32,
    #[serde(skip_serializing_if = "Option::is_none")]
    pub refresh_token: Option<String>,
    pub mfa_enrol_required: bool,
}

impl LoginResponse {
    #[must_use]
    pub fn from_outcome(outcome: LoginOutcome, android: bool) -> (Self, String, u32) {
        let refresh_token = outcome.refresh_token.expose().clone();
        (
            Self {
                account: outcome.profile,
                access_token: outcome.access_token,
                expires_in: outcome.expires_in,
                refresh_token: android.then(|| refresh_token.clone()),
                mfa_enrol_required: outcome.mfa_enrol_required,
            },
            refresh_token,
            outcome.refresh_cookie_max_age,
        )
    }
}

fn field_error(path: &str, code: FieldCode) -> AppError {
    AppError::validation(vec![FieldError {
        path: path.to_owned(),
        code,
        details: Details::new(),
    }])
}
