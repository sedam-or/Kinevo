//! M1 policy declarations copied from `spec/04-policies.md` §3.3 and §3.5.

// SPEC: POL-01, POL-07, CQ-05

use super::{
    EffectiveDating, PolicyDuration, PolicyEnum, PolicyKey, PolicySpec, PolicyType, PolicyValue,
    Scope,
};

const INSTANCE_SCOPE: &[Scope] = &[Scope::Instance];

const fn registered(key: &'static str, ty: PolicyType, default: PolicyValue) -> PolicySpec {
    PolicySpec {
        key,
        ty,
        default,
        scopes: INSTANCE_SCOPE,
        effective_dating: EffectiveDating::Immediate,
        snapshot: false,
    }
}

pub mod identity {
    use super::{
        PolicyDuration, PolicyEnum, PolicyKey, PolicySpec, PolicyType, PolicyValue, registered,
    };

    pub const REGISTRATION_MODE: PolicyKey<PolicyEnum> = PolicyKey::new("auth.registration.mode");
    pub const REGISTRATION_MODE_SPEC: PolicySpec = registered(
        REGISTRATION_MODE.name,
        PolicyType::Enum {
            allowed: &["open", "closed", "invite"],
        },
        PolicyValue::Str("closed"),
    );

    pub const PASSWORD_MIN_LENGTH: PolicyKey<i64> = PolicyKey::new("auth.password.min_length");
    pub const PASSWORD_MIN_LENGTH_SPEC: PolicySpec = registered(
        PASSWORD_MIN_LENGTH.name,
        PolicyType::Int { min: 10, max: 128 },
        PolicyValue::Int(12),
    );

    pub const ACCESS_TOKEN_TTL: PolicyKey<PolicyDuration> = PolicyKey::new("auth.access_token.ttl");
    pub const ACCESS_TOKEN_TTL_SPEC: PolicySpec = registered(
        ACCESS_TOKEN_TTL.name,
        PolicyType::DurationSeconds {
            min: 60,
            max: 3_600,
        },
        PolicyValue::Seconds(600),
    );

    pub const REFRESH_TOKEN_ABSOLUTE_TTL: PolicyKey<PolicyDuration> =
        PolicyKey::new("auth.refresh_token.absolute_ttl");
    pub const REFRESH_TOKEN_ABSOLUTE_TTL_SPEC: PolicySpec = registered(
        REFRESH_TOKEN_ABSOLUTE_TTL.name,
        PolicyType::DurationSeconds {
            min: 86_400,
            max: 7_776_000,
        },
        PolicyValue::Seconds(2_592_000),
    );

    pub const REFRESH_TOKEN_IDLE_TTL: PolicyKey<PolicyDuration> =
        PolicyKey::new("auth.refresh_token.idle_ttl");
    pub const REFRESH_TOKEN_IDLE_TTL_SPEC: PolicySpec = registered(
        REFRESH_TOKEN_IDLE_TTL.name,
        PolicyType::DurationSeconds {
            min: 3_600,
            max: 2_592_000,
        },
        PolicyValue::Seconds(1_209_600),
    );

    pub const LOCKOUT_MAX_ATTEMPTS: PolicyKey<i64> = PolicyKey::new("auth.lockout.max_attempts");
    pub const LOCKOUT_MAX_ATTEMPTS_SPEC: PolicySpec = registered(
        LOCKOUT_MAX_ATTEMPTS.name,
        PolicyType::Int { min: 3, max: 20 },
        PolicyValue::Int(5),
    );

    pub const LOCKOUT_WINDOW: PolicyKey<PolicyDuration> = PolicyKey::new("auth.lockout.window");
    pub const LOCKOUT_WINDOW_SPEC: PolicySpec = registered(
        LOCKOUT_WINDOW.name,
        PolicyType::DurationSeconds {
            min: 60,
            max: 3_600,
        },
        PolicyValue::Seconds(900),
    );

    pub const LOCKOUT_DURATION: PolicyKey<PolicyDuration> = PolicyKey::new("auth.lockout.duration");
    pub const LOCKOUT_DURATION_SPEC: PolicySpec = registered(
        LOCKOUT_DURATION.name,
        PolicyType::DurationSeconds {
            min: 60,
            max: 86_400,
        },
        PolicyValue::Seconds(900),
    );

    pub const TOTP_REQUIRED: PolicyKey<bool> = PolicyKey::new("auth.totp.required");
    pub const TOTP_REQUIRED_SPEC: PolicySpec = registered(
        TOTP_REQUIRED.name,
        PolicyType::Bool,
        PolicyValue::Bool(false),
    );

    pub const REFRESH_GRACE: PolicyKey<PolicyDuration> = PolicyKey::new("auth.refresh.grace");
    pub const REFRESH_GRACE_SPEC: PolicySpec = registered(
        REFRESH_GRACE.name,
        PolicyType::DurationSeconds { min: 0, max: 60 },
        PolicyValue::Seconds(10),
    );

    pub const PURGE_DELAY: PolicyKey<PolicyDuration> = PolicyKey::new("identity.purge.delay");
    pub const PURGE_DELAY_SPEC: PolicySpec = registered(
        PURGE_DELAY.name,
        PolicyType::DurationSeconds {
            min: 0,
            max: 2_592_000,
        },
        PolicyValue::Seconds(0),
    );

    pub const INVITE_TTL: PolicyKey<PolicyDuration> = PolicyKey::new("auth.invite.ttl");
    pub const INVITE_TTL_SPEC: PolicySpec = registered(
        INVITE_TTL.name,
        PolicyType::DurationSeconds {
            min: 3_600,
            max: 2_592_000,
        },
        PolicyValue::Seconds(604_800),
    );

    pub const PASSWORD_RESET_TTL: PolicyKey<PolicyDuration> =
        PolicyKey::new("auth.password_reset.ttl");
    pub const PASSWORD_RESET_TTL_SPEC: PolicySpec = registered(
        PASSWORD_RESET_TTL.name,
        PolicyType::DurationSeconds {
            min: 300,
            max: 86_400,
        },
        PolicyValue::Seconds(3_600),
    );
}

pub mod http {
    use super::{PolicyDuration, PolicyKey, PolicySpec, PolicyType, PolicyValue, registered};

    pub const IDEMPOTENCY_TTL: PolicyKey<PolicyDuration> = PolicyKey::new("api.idempotency.ttl");
    pub const IDEMPOTENCY_TTL_SPEC: PolicySpec = registered(
        IDEMPOTENCY_TTL.name,
        PolicyType::DurationSeconds {
            min: 3_600,
            max: 604_800,
        },
        PolicyValue::Seconds(86_400),
    );

    pub const AUTH_PER_MINUTE: PolicyKey<i64> = PolicyKey::new("ratelimit.auth_per_minute");
    pub const AUTH_PER_MINUTE_SPEC: PolicySpec = registered(
        AUTH_PER_MINUTE.name,
        PolicyType::Int { min: 1, max: 120 },
        PolicyValue::Int(5),
    );

    pub const API_PER_MINUTE: PolicyKey<i64> = PolicyKey::new("ratelimit.api_per_minute");
    pub const API_PER_MINUTE_SPEC: PolicySpec = registered(
        API_PER_MINUTE.name,
        PolicyType::Int {
            min: 10,
            max: 6_000,
        },
        PolicyValue::Int(120),
    );

    pub const AI_PER_MINUTE: PolicyKey<i64> = PolicyKey::new("ratelimit.ai_per_minute");
    pub const AI_PER_MINUTE_SPEC: PolicySpec = registered(
        AI_PER_MINUTE.name,
        PolicyType::Int { min: 1, max: 120 },
        PolicyValue::Int(10),
    );

    pub const UPLOAD_PER_MINUTE: PolicyKey<i64> = PolicyKey::new("ratelimit.upload_per_minute");
    pub const UPLOAD_PER_MINUTE_SPEC: PolicySpec = registered(
        UPLOAD_PER_MINUTE.name,
        PolicyType::Int { min: 1, max: 600 },
        PolicyValue::Int(20),
    );

    pub const SYNC_PER_MINUTE: PolicyKey<i64> = PolicyKey::new("ratelimit.sync_per_minute");
    pub const SYNC_PER_MINUTE_SPEC: PolicySpec = registered(
        SYNC_PER_MINUTE.name,
        PolicyType::Int {
            min: 10,
            max: 6_000,
        },
        PolicyValue::Int(120),
    );

    pub const EXPORT_PER_MINUTE: PolicyKey<i64> = PolicyKey::new("ratelimit.export_per_minute");
    pub const EXPORT_PER_MINUTE_SPEC: PolicySpec = registered(
        EXPORT_PER_MINUTE.name,
        PolicyType::Int { min: 1, max: 120 },
        PolicyValue::Int(10),
    );
}

pub mod admin {
    use super::{PolicyDuration, PolicyKey, PolicySpec, PolicyType, PolicyValue, registered};

    pub const AUDIT_RETENTION_DAYS: PolicyKey<i64> = PolicyKey::new("audit.retention_days");
    pub const AUDIT_RETENTION_DAYS_SPEC: PolicySpec = registered(
        AUDIT_RETENTION_DAYS.name,
        PolicyType::Int {
            min: 30,
            max: 3_650,
        },
        PolicyValue::Int(365),
    );

    pub const MODULES_CACHE_TTL_SECONDS: PolicyKey<PolicyDuration> =
        PolicyKey::new("modules.cache_ttl_seconds");
    pub const MODULES_CACHE_TTL_SECONDS_SPEC: PolicySpec = registered(
        MODULES_CACHE_TTL_SECONDS.name,
        PolicyType::DurationSeconds { min: 1, max: 300 },
        PolicyValue::Seconds(10),
    );
}

pub mod platform {
    use super::{PolicyDuration, PolicyKey, PolicySpec, PolicyType, PolicyValue, registered};

    pub const JOB_LEASE_SECONDS: PolicyKey<PolicyDuration> = PolicyKey::new("jobs.lease_seconds");
    pub const JOB_LEASE_SECONDS_SPEC: PolicySpec = registered(
        JOB_LEASE_SECONDS.name,
        PolicyType::DurationSeconds {
            min: 10,
            max: 3_600,
        },
        PolicyValue::Seconds(120),
    );

    pub const JOB_RETRY_MAX_ATTEMPTS: PolicyKey<i64> = PolicyKey::new("jobs.retry.max_attempts");
    pub const JOB_RETRY_MAX_ATTEMPTS_SPEC: PolicySpec = registered(
        JOB_RETRY_MAX_ATTEMPTS.name,
        PolicyType::Int { min: 1, max: 20 },
        PolicyValue::Int(8),
    );

    pub const JOB_RETRY_BASE_SECONDS: PolicyKey<PolicyDuration> =
        PolicyKey::new("jobs.retry.base_seconds");
    pub const JOB_RETRY_BASE_SECONDS_SPEC: PolicySpec = registered(
        JOB_RETRY_BASE_SECONDS.name,
        PolicyType::DurationSeconds { min: 1, max: 600 },
        PolicyValue::Seconds(5),
    );

    pub const JOB_RETRY_CAP_SECONDS: PolicyKey<PolicyDuration> =
        PolicyKey::new("jobs.retry.cap_seconds");
    pub const JOB_RETRY_CAP_SECONDS_SPEC: PolicySpec = registered(
        JOB_RETRY_CAP_SECONDS.name,
        PolicyType::DurationSeconds {
            min: 60,
            max: 86_400,
        },
        PolicyValue::Seconds(3_600),
    );

    pub const JOB_SCHEDULE_DAILY_SECONDS: PolicyKey<PolicyDuration> =
        PolicyKey::new("jobs.schedule.daily_seconds");
    pub const JOB_SCHEDULE_DAILY_SECONDS_SPEC: PolicySpec = registered(
        JOB_SCHEDULE_DAILY_SECONDS.name,
        PolicyType::DurationSeconds {
            min: 3_600,
            max: 2_592_000,
        },
        PolicyValue::Seconds(86_400),
    );

    pub const JOB_SCHEDULE_HOURLY_SECONDS: PolicyKey<PolicyDuration> =
        PolicyKey::new("jobs.schedule.hourly_seconds");
    pub const JOB_SCHEDULE_HOURLY_SECONDS_SPEC: PolicySpec = registered(
        JOB_SCHEDULE_HOURLY_SECONDS.name,
        PolicyType::DurationSeconds {
            min: 900,
            max: 86_400,
        },
        PolicyValue::Seconds(3_600),
    );

    pub const EXPORT_MAX_AGE_DAYS: PolicyKey<i64> = PolicyKey::new("export.max_age_days");
    pub const EXPORT_MAX_AGE_DAYS_SPEC: PolicySpec = registered(
        EXPORT_MAX_AGE_DAYS.name,
        PolicyType::Int { min: 1, max: 30 },
        PolicyValue::Int(7),
    );
}

static ALL_SPECS: &[&PolicySpec] = &[
    &identity::REGISTRATION_MODE_SPEC,
    &identity::PASSWORD_MIN_LENGTH_SPEC,
    &identity::ACCESS_TOKEN_TTL_SPEC,
    &identity::REFRESH_TOKEN_ABSOLUTE_TTL_SPEC,
    &identity::REFRESH_TOKEN_IDLE_TTL_SPEC,
    &identity::LOCKOUT_MAX_ATTEMPTS_SPEC,
    &identity::LOCKOUT_WINDOW_SPEC,
    &identity::LOCKOUT_DURATION_SPEC,
    &identity::TOTP_REQUIRED_SPEC,
    &identity::REFRESH_GRACE_SPEC,
    &identity::PURGE_DELAY_SPEC,
    &http::IDEMPOTENCY_TTL_SPEC,
    &identity::INVITE_TTL_SPEC,
    &identity::PASSWORD_RESET_TTL_SPEC,
    &http::AUTH_PER_MINUTE_SPEC,
    &http::API_PER_MINUTE_SPEC,
    &http::AI_PER_MINUTE_SPEC,
    &http::UPLOAD_PER_MINUTE_SPEC,
    &http::SYNC_PER_MINUTE_SPEC,
    &http::EXPORT_PER_MINUTE_SPEC,
    &admin::AUDIT_RETENTION_DAYS_SPEC,
    &admin::MODULES_CACHE_TTL_SECONDS_SPEC,
    &platform::JOB_LEASE_SECONDS_SPEC,
    &platform::JOB_RETRY_MAX_ATTEMPTS_SPEC,
    &platform::JOB_RETRY_BASE_SECONDS_SPEC,
    &platform::JOB_RETRY_CAP_SECONDS_SPEC,
    &platform::JOB_SCHEDULE_DAILY_SECONDS_SPEC,
    &platform::JOB_SCHEDULE_HOURLY_SECONDS_SPEC,
    &platform::EXPORT_MAX_AGE_DAYS_SPEC,
];

/// All stored M1 policies, in the same grouping and order as the task list.
// SPEC: POL-01, POL-07, CQ-05
#[must_use]
pub fn all_specs() -> &'static [&'static PolicySpec] {
    ALL_SPECS
}
