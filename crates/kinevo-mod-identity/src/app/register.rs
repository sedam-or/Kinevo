//! Public account-registration use case.

use std::sync::Arc;

use kinevo_db::Tx;
use kinevo_http::token::{Claims, TokenCodec};
use kinevo_kernel::{
    crypto::{decode_token, hash_token},
    errors::{AppError, DetailValue, ErrorCode},
    id::{AccountId, IdGen},
    policy::{PolicyDuration, PolicyEnum, registry::identity as policy},
    secret::Secret,
    time::Instant,
    validate::{
        Collector,
        net::Email,
        text::{OpaqueToken, ShortText},
        tz::{IanaTz, Locale},
    },
};
use kinevo_ports::platform::{
    AccountCreation, AccountSessionSettings, Client, InstanceState, NewAccount, PolicyReader,
    ResolvedPolicies,
};

use crate::{
    app::login::{ClientAddr, LoginOutcome, PasswordWork},
    domain::{
        device,
        password::{CommonPasswords, PasswordPolicy, PlainPassword},
        session::SessionPolicy,
    },
    infra::invites,
};

/// Parsed body for the public registration endpoint.
pub struct RegisterRequest {
    pub email: String,
    pub password: Secret<String>,
    pub display_name: String,
    pub timezone: String,
    pub locale: Option<String>,
    pub invite_token: Option<String>,
}

/// Coordinates policy, validation, hashing, invite use and transactional account creation.
pub struct RegisterService {
    db: kinevo_db::Db,
    passwords: Arc<dyn PasswordWork>,
    policies: Arc<dyn PolicyReader>,
    instance: Arc<dyn InstanceState>,
    accounts: Arc<dyn AccountCreation<Tx>>,
    ids: Arc<dyn IdGen>,
    codec: Arc<TokenCodec>,
}

impl RegisterService {
    #[allow(clippy::too_many_arguments)]
    #[must_use]
    pub fn new(
        db: kinevo_db::Db,
        passwords: Arc<dyn PasswordWork>,
        policies: Arc<dyn PolicyReader>,
        instance: Arc<dyn InstanceState>,
        accounts: Arc<dyn AccountCreation<Tx>>,
        ids: Arc<dyn IdGen>,
        codec: Arc<TokenCodec>,
    ) -> Self {
        Self {
            db,
            passwords,
            policies,
            instance,
            accounts,
            ids,
            codec,
        }
    }

    /// Registers an account using the injected request instant and no user-agent metadata.
    pub async fn register(
        &self,
        request: RegisterRequest,
        client: Client,
        addr: ClientAddr,
        now: Instant,
    ) -> Result<LoginOutcome, AppError> {
        self.register_with_user_agent(request, client, addr, now, None)
            .await
    }

    /// Registers an account while retaining only a fixed safe label from user-agent metadata.
    #[allow(
        clippy::too_many_lines,
        reason = "validation, hashing, invite consumption, and account creation follow one ordered flow"
    )]
    pub async fn register_with_user_agent(
        &self,
        request: RegisterRequest,
        client: Client,
        _addr: ClientAddr,
        now: Instant,
        user_agent: Option<&str>,
    ) -> Result<LoginOutcome, AppError> {
        let snapshot = self.policies.snapshot_values(None, now).await?;
        let mode = snapshot
            .get(&policy::REGISTRATION_MODE)
            .map(|mode: PolicyEnum| mode.0)
            .ok_or_else(|| missing_policy(policy::REGISTRATION_MODE.name))?;
        if mode == "closed" {
            return Err(AppError::new(ErrorCode::RegistrationClosed));
        }
        let policy = RegistrationPolicy::from_snapshot(&snapshot, mode)?;

        let default_locale = self.instance.get().await?.default_locale;
        let request_invite = request.invite_token.clone();
        let validated = validate(request, policy.password_minimum, default_locale)?;
        let invite_hash = if mode == "invite" {
            let raw = request_invite.as_deref().ok_or_else(invite_invalid)?;
            OpaqueToken::parse(raw).map_err(|_| invite_invalid())?;
            let decoded = decode_token(raw).map_err(|_| invite_invalid())?;
            Some(hash_token(&decoded))
        } else {
            None
        };

        let password_hash = self
            .passwords
            .hash(&Secret::new(validated.password.expose().to_owned()))
            .await?;
        let account_id = AccountId::from_uuid(self.ids.new_uuid_v7());
        let mut tx = Tx::begin(&self.db, account_id).await?;
        if let Some(token_hash) = invite_hash {
            invites::consume(&mut tx, &token_hash, &validated.email, now).await?;
        }

        let creation = self
            .accounts
            .create(
                &mut tx,
                NewAccount {
                    id: account_id,
                    email: validated.email,
                    password_hash,
                    display_name: validated.display_name,
                    timezone: validated.timezone,
                    locale: validated.locale,
                    is_instance_admin: false,
                    client,
                    device_label: Some(device::label(user_agent, client).to_owned()),
                    session: AccountSessionSettings {
                        now,
                        access_ttl: policy.session.access_ttl,
                        idle_ttl: policy.session.idle_ttl,
                        absolute_ttl: policy.session.absolute_ttl,
                        grace: policy.session.grace,
                    },
                },
            )
            .await?;

        let access_token = self.codec.issue(&Claims {
            sub: creation.access_claims.account_id,
            sid: creation.access_claims.family_id,
            jti: self.ids.new_uuid_v7(),
            iat: creation.access_claims.issued_at,
            exp: creation.access_claims.expires_at,
            mfa: creation.access_claims.mfa,
        });
        if access_token.is_empty() {
            return Err(AppError::new(ErrorCode::InternalError));
        }
        tx.commit().await?;

        Ok(LoginOutcome {
            profile: creation.profile,
            access_token,
            expires_in: creation.expires_in,
            refresh_token: creation.refresh_token,
            refresh_cookie_max_age: creation.refresh_cookie_max_age,
            mfa_enrol_required: false,
        })
    }
}

struct ValidatedRegistration {
    email: Email,
    password: PlainPassword,
    display_name: String,
    timezone: IanaTz,
    locale: Locale,
}

struct RegistrationPolicy {
    password_minimum: usize,
    session: SessionPolicy,
}

impl RegistrationPolicy {
    fn from_snapshot(snapshot: &ResolvedPolicies, mode: &'static str) -> Result<Self, AppError> {
        if !matches!(mode, "open" | "invite") {
            return Err(AppError::new(ErrorCode::InternalError));
        }
        let required = |name| missing_policy(name);
        let password_minimum = snapshot
            .get(&policy::PASSWORD_MIN_LENGTH)
            .ok_or_else(|| required(policy::PASSWORD_MIN_LENGTH.name))?;
        let access_ttl = snapshot
            .get(&policy::ACCESS_TOKEN_TTL)
            .ok_or_else(|| required(policy::ACCESS_TOKEN_TTL.name))?;
        let idle_ttl = snapshot
            .get(&policy::REFRESH_TOKEN_IDLE_TTL)
            .ok_or_else(|| required(policy::REFRESH_TOKEN_IDLE_TTL.name))?;
        let absolute_ttl = snapshot
            .get(&policy::REFRESH_TOKEN_ABSOLUTE_TTL)
            .ok_or_else(|| required(policy::REFRESH_TOKEN_ABSOLUTE_TTL.name))?;
        let grace = snapshot
            .get(&policy::REFRESH_GRACE)
            .ok_or_else(|| required(policy::REFRESH_GRACE.name))?;
        Ok(Self {
            password_minimum: usize::try_from(password_minimum)
                .map_err(|_| AppError::new(ErrorCode::InternalError))?,
            session: SessionPolicy {
                access_ttl: policy_seconds(access_ttl)?,
                idle_ttl: policy_seconds(idle_ttl)?,
                absolute_ttl: policy_seconds(absolute_ttl)?,
                grace: policy_seconds(grace)?,
            },
        })
    }
}

fn validate(
    request: RegisterRequest,
    password_minimum: usize,
    default_locale: Locale,
) -> Result<ValidatedRegistration, AppError> {
    let mut errors = Collector::new();
    let email = errors.check("email", Email::parse(&request.email));
    let password = errors.check(
        "password",
        PlainPassword::parse(
            request.password,
            &PasswordPolicy::new(password_minimum),
            &CommonPasswords::embedded(),
        ),
    );
    let display_name = errors.check(
        "display_name",
        ShortText::parse(&request.display_name, 1, 80).map(|value| value.as_str().to_owned()),
    );
    let timezone = errors.check("timezone", IanaTz::parse(&request.timezone));
    let locale = errors.check(
        "locale",
        request
            .locale
            .as_deref()
            .map_or(Ok(default_locale), Locale::parse),
    );
    errors.finish()?;
    Ok(ValidatedRegistration {
        email: email.ok_or_else(|| AppError::new(ErrorCode::InternalError))?,
        password: password.ok_or_else(|| AppError::new(ErrorCode::InternalError))?,
        display_name: display_name.ok_or_else(|| AppError::new(ErrorCode::InternalError))?,
        timezone: timezone.ok_or_else(|| AppError::new(ErrorCode::InternalError))?,
        locale: locale.ok_or_else(|| AppError::new(ErrorCode::InternalError))?,
    })
}

fn policy_seconds(value: PolicyDuration) -> Result<u32, AppError> {
    u32::try_from(value.0).map_err(|_| AppError::new(ErrorCode::InternalError))
}

fn missing_policy(name: &'static str) -> AppError {
    AppError::new(ErrorCode::ServiceUnavailable).with_detail("status", DetailValue::Str(name))
}

fn invite_invalid() -> AppError {
    AppError::new(ErrorCode::InviteInvalid)
}
