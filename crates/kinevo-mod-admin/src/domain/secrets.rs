//! Registered secret names and scope rules.

use kinevo_kernel::errors::{AppError, DetailValue, ErrorCode};
use kinevo_ports::platform::{SecretName, SecretScope};

/// One entry in the closed D-40 secret registry.
#[derive(Clone, Copy, Debug, Eq, PartialEq)]
pub enum SecretNameKind {
    SmtpPassword,
    AiApiKey,
    BillingGatewayKey,
    BillingWebhookSecret,
    IdentityTotpSeed,
}

/// Scope support declared by a registered secret name.
#[derive(Clone, Copy, Debug, Eq, PartialEq)]
pub enum SecretNameScope {
    Instance,
    Account,
    Both,
}

const INSTANCE_NAMES: [SecretNameKind; 4] = [
    SecretNameKind::SmtpPassword,
    SecretNameKind::AiApiKey,
    SecretNameKind::BillingGatewayKey,
    SecretNameKind::BillingWebhookSecret,
];
const ACCOUNT_NAMES: [SecretNameKind; 2] =
    [SecretNameKind::AiApiKey, SecretNameKind::IdentityTotpSeed];

impl SecretNameKind {
    /// Resolves one port name into its closed admin registry variant.
    #[must_use]
    pub fn from_registered(name: SecretName) -> Option<Self> {
        Some(match name.as_str() {
            "smtp.password" => Self::SmtpPassword,
            "ai.api_key" => Self::AiApiKey,
            "billing.gateway_key" => Self::BillingGatewayKey,
            "billing.webhook_secret" => Self::BillingWebhookSecret,
            "identity.totp_seed" => Self::IdentityTotpSeed,
            _ => return None,
        })
    }

    /// Returns the stable external registry name.
    #[must_use]
    pub const fn as_str(self) -> &'static str {
        match self {
            Self::SmtpPassword => "smtp.password",
            Self::AiApiKey => "ai.api_key",
            Self::BillingGatewayKey => "billing.gateway_key",
            Self::BillingWebhookSecret => "billing.webhook_secret",
            Self::IdentityTotpSeed => "identity.totp_seed",
        }
    }

    /// Returns the scopes in which this value may be stored.
    #[must_use]
    pub const fn scope(self) -> SecretNameScope {
        match self {
            Self::SmtpPassword | Self::BillingGatewayKey | Self::BillingWebhookSecret => {
                SecretNameScope::Instance
            }
            Self::IdentityTotpSeed => SecretNameScope::Account,
            Self::AiApiKey => SecretNameScope::Both,
        }
    }

    /// Returns the content-free enum literal used by the `SecretChanged` event.
    #[must_use]
    pub const fn event_name(self) -> &'static str {
        match self {
            Self::SmtpPassword => "smtp_password",
            Self::AiApiKey => "ai_api_key",
            Self::BillingGatewayKey => "billing_gateway_key",
            Self::BillingWebhookSecret => "billing_webhook_secret",
            Self::IdentityTotpSeed => "identity_totp_seed",
        }
    }

    /// Returns whether a secret name can be used at the supplied scope.
    #[must_use]
    pub const fn allows(self, scope: SecretScope) -> bool {
        matches!(
            (self.scope(), scope),
            (SecretNameScope::Both, _)
                | (SecretNameScope::Instance, SecretScope::Instance)
                | (SecretNameScope::Account, SecretScope::Account(_))
        )
    }
}

/// Parses any known name and verifies that its registry scope matches.
pub fn parse_for_scope(value: &str, scope: SecretScope) -> Result<SecretName, AppError> {
    let name = SecretName::parse(value)?;
    let kind = SecretNameKind::from_registered(name)
        .ok_or_else(|| AppError::new(ErrorCode::InternalError))?;
    if kind.allows(scope) {
        return Ok(name);
    }
    Err(unknown_name(scope))
}

/// Builds the scope-specific form of `secret_name_unknown` with safe registry names.
#[must_use]
pub fn unknown_name(scope: SecretScope) -> AppError {
    let allowed = match scope {
        SecretScope::Instance => &INSTANCE_NAMES[..],
        SecretScope::Account(_) => &ACCOUNT_NAMES[..],
    };
    AppError::new(ErrorCode::SecretNameUnknown).with_detail(
        "allowed",
        DetailValue::StrList(
            allowed
                .iter()
                .map(|kind| kind.as_str().to_owned())
                .collect(),
        ),
    )
}

/// Returns the stable set exposed by the instance-secret endpoint.
#[must_use]
pub const fn instance_names() -> [SecretNameKind; 4] {
    INSTANCE_NAMES
}

/// Builds the AAD binding for a name and one database scope.
#[must_use]
pub fn aad(name: SecretName, scope: SecretScope) -> Vec<u8> {
    let scope_id = match scope {
        SecretScope::Instance => "instance".to_owned(),
        SecretScope::Account(account) => account.to_string(),
    };
    format!("secret:{}:{scope_id}", name.as_str()).into_bytes()
}
