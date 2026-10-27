//! Typed, bounded policy values and deterministic effective dating.

// SPEC: POL-01, POL-03, POL-04, POL-06, POL-07, POL-08, CQ-05

use crate::{errors::ErrorCode, time::Instant};
use serde_json::{Value, json};
use std::marker::PhantomData;

pub mod registry;
pub mod resolve;

pub use resolve::{
    PolicyVersion, ResolutionSource, Resolved, Snapshot, resolve, resolve_with_snapshot,
};

#[derive(Clone, Copy, Debug, Eq, Hash, PartialEq)]
// SPEC: POL-01
pub enum Scope {
    Instance,
    Account,
}

#[derive(Clone, Copy, Debug, Eq, PartialEq)]
// SPEC: POL-04
pub enum EffectiveDating {
    Immediate,
    FutureOnly,
}

#[derive(Clone, Copy, Debug, Eq, PartialEq)]
// SPEC: POL-01, POL-03
pub enum PolicyType {
    Int { min: i64, max: i64 },
    Bool,
    Enum { allowed: &'static [&'static str] },
    DurationSeconds { min: i64, max: i64 },
    LocalTime,
    Bp,
    TextKey,
    Json { schema: &'static str },
}

#[derive(Clone, Debug, PartialEq)]
// SPEC: POL-03, POL-06
pub enum PolicyValue {
    Int(i64),
    Bool(bool),
    Str(&'static str),
    Text(String),
    Seconds(i64),
    LocalTime(u16),
    Bp(u16),
    Json(Value),
}

impl PolicyValue {
    fn as_json(&self) -> Value {
        match self {
            Self::Int(value) | Self::Seconds(value) => json!(value),
            Self::Bool(value) => json!(value),
            Self::Str(value) => json!(value),
            Self::Text(value) => json!(value),
            Self::LocalTime(minutes) => {
                let hour = minutes / 60;
                let minute = minutes % 60;
                json!(format!("{hour:02}:{minute:02}"))
            }
            Self::Bp(value) => json!(value),
            Self::Json(value) => value.clone(),
        }
    }
}

#[derive(Clone, Debug, PartialEq)]
// SPEC: POL-01
pub struct PolicySpec {
    pub key: &'static str,
    pub ty: PolicyType,
    pub default: PolicyValue,
    pub scopes: &'static [Scope],
    pub effective_dating: EffectiveDating,
    pub snapshot: bool,
}

#[derive(Clone, Debug, PartialEq)]
// SPEC: POL-03, ERR-03
pub struct PolicyError {
    pub code: ErrorCode,
    /// Contains only schema-approved bounds or enum literals; never the rejected value.
    pub details: Value,
}

impl PolicyError {
    fn invalid(bounds: Value) -> Self {
        let mut details = serde_json::Map::new();
        details.insert("bounds".to_owned(), bounds);
        Self {
            code: ErrorCode::PolicyValueInvalid,
            details: Value::Object(details),
        }
    }

    fn effective(code: ErrorCode) -> Self {
        Self {
            code,
            details: Value::Null,
        }
    }
}

/// A type that can be extracted from a validated policy value.
// SPEC: POL-07, CQ-05
pub trait PolicyValueType: Sized {
    fn from_policy_value(value: &PolicyValue) -> Option<Self>;
}

impl PolicyValueType for i64 {
    fn from_policy_value(value: &PolicyValue) -> Option<Self> {
        match value {
            PolicyValue::Int(value) => Some(*value),
            _ => None,
        }
    }
}

impl PolicyValueType for bool {
    fn from_policy_value(value: &PolicyValue) -> Option<Self> {
        match value {
            PolicyValue::Bool(value) => Some(*value),
            _ => None,
        }
    }
}

#[derive(Clone, Copy, Debug, Eq, PartialEq)]
pub struct PolicyEnum(pub &'static str);

impl PolicyValueType for PolicyEnum {
    fn from_policy_value(value: &PolicyValue) -> Option<Self> {
        match value {
            PolicyValue::Str(value) => Some(Self(value)),
            _ => None,
        }
    }
}

#[derive(Clone, Copy, Debug, Eq, PartialEq)]
pub struct PolicyDuration(pub i64);

impl PolicyValueType for PolicyDuration {
    fn from_policy_value(value: &PolicyValue) -> Option<Self> {
        match value {
            PolicyValue::Seconds(value) => Some(Self(*value)),
            _ => None,
        }
    }
}

#[derive(Clone, Copy, Debug, Eq, PartialEq)]
pub struct LocalTime(pub u16);

impl PolicyValueType for LocalTime {
    fn from_policy_value(value: &PolicyValue) -> Option<Self> {
        match value {
            PolicyValue::LocalTime(value) => Some(Self(*value)),
            _ => None,
        }
    }
}

#[derive(Clone, Copy, Debug, Eq, PartialEq)]
pub struct BasisPoints(pub u16);

impl PolicyValueType for BasisPoints {
    fn from_policy_value(value: &PolicyValue) -> Option<Self> {
        match value {
            PolicyValue::Bp(value) => Some(Self(*value)),
            _ => None,
        }
    }
}

#[derive(Clone, Debug, Eq, PartialEq)]
pub struct MessageKey(pub String);

impl PolicyValueType for MessageKey {
    fn from_policy_value(value: &PolicyValue) -> Option<Self> {
        match value {
            PolicyValue::Text(value) => Some(Self(value.clone())),
            _ => None,
        }
    }
}

impl PolicyValueType for Value {
    fn from_policy_value(value: &PolicyValue) -> Option<Self> {
        match value {
            PolicyValue::Json(value) => Some(value.clone()),
            _ => None,
        }
    }
}

/// A statically named policy key with a compile-time value type.
// SPEC: POL-07, CQ-05
pub struct PolicyKey<T> {
    pub name: &'static str,
    marker: PhantomData<fn() -> T>,
}

impl<T> PolicyKey<T> {
    #[must_use]
    pub const fn new(name: &'static str) -> Self {
        Self {
            name,
            marker: PhantomData,
        }
    }

    #[must_use]
    pub fn get(&self, resolved: &Resolved) -> Option<T>
    where
        T: PolicyValueType,
    {
        T::from_policy_value(&resolved.value)
    }
}

/// Validates a JSON policy value against its declared type and registered schema.
// SPEC: POL-03, POL-08
pub fn validate_value(spec: &PolicySpec, value: &Value) -> Result<PolicyValue, PolicyError> {
    match spec.ty {
        PolicyType::Int { min, max } => value
            .as_i64()
            .filter(|number| (min..=max).contains(number))
            .map(PolicyValue::Int)
            .ok_or_else(|| PolicyError::invalid(json!({ "min": min, "max": max }))),
        PolicyType::Bool => value
            .as_bool()
            .map(PolicyValue::Bool)
            .ok_or_else(|| PolicyError::invalid(json!({ "type": "bool" }))),
        PolicyType::Enum { allowed } => value
            .as_str()
            .and_then(|candidate| allowed.iter().copied().find(|item| *item == candidate))
            .map(PolicyValue::Str)
            .ok_or_else(|| PolicyError::invalid(json!({ "allowed": allowed }))),
        PolicyType::DurationSeconds { min, max } => value
            .as_i64()
            .filter(|seconds| (min..=max).contains(seconds))
            .map(PolicyValue::Seconds)
            .ok_or_else(|| PolicyError::invalid(json!({ "min": min, "max": max }))),
        PolicyType::LocalTime => value
            .as_str()
            .and_then(parse_local_time)
            .map(PolicyValue::LocalTime)
            .ok_or_else(|| PolicyError::invalid(json!({ "format": "HH:MM" }))),
        PolicyType::Bp => value
            .as_u64()
            .filter(|basis_points| *basis_points <= 10_000)
            .and_then(|basis_points| u16::try_from(basis_points).ok())
            .map(PolicyValue::Bp)
            .ok_or_else(|| PolicyError::invalid(json!({ "min": 0, "max": 10_000 }))),
        PolicyType::TextKey => value
            .as_str()
            .map(|text| PolicyValue::Text(text.to_owned()))
            .ok_or_else(|| PolicyError::invalid(json!({ "type": "text_key" }))),
        PolicyType::Json { schema } => {
            if validate_registered_json(schema, value) {
                Ok(PolicyValue::Json(value.clone()))
            } else {
                Err(PolicyError::invalid(json!({ "schema": schema })))
            }
        }
    }
}

/// Rejects writes to a scope not declared by the policy.
// SPEC: POL-03
pub fn validate_scope(spec: &PolicySpec, scope: Scope) -> Result<(), PolicyError> {
    if spec.scopes.contains(&scope) {
        return Ok(());
    }
    let allowed: Vec<_> = spec
        .scopes
        .iter()
        .map(|allowed_scope| match allowed_scope {
            Scope::Instance => "instance",
            Scope::Account => "account",
        })
        .collect();
    Err(PolicyError {
        code: ErrorCode::PolicyScopeInvalid,
        details: json!({ "bounds": { "scopes": allowed } }),
    })
}

fn parse_local_time(value: &str) -> Option<u16> {
    if value.len() != 5 || !value.is_ascii() || value.as_bytes().get(2) != Some(&b':') {
        return None;
    }
    let hour = value.get(..2)?.parse::<u16>().ok()?;
    let minute = value.get(3..)?.parse::<u16>().ok()?;
    if hour >= 24 || minute >= 60 {
        return None;
    }
    hour.checked_mul(60)?.checked_add(minute)
}

// SPEC: POL-03
fn validate_registered_json(schema: &str, value: &Value) -> bool {
    match schema {
        "billing_plans_v1" => value
            .as_array()
            .is_some_and(|plans| plans.iter().all(valid_billing_plan)),
        _ => false,
    }
}

fn valid_billing_plan(plan: &Value) -> bool {
    let Some(object) = plan.as_object() else {
        return false;
    };
    if object.get("id").and_then(Value::as_str).is_none()
        || object.get("name_key").and_then(Value::as_str).is_none()
    {
        return false;
    }
    let Some(price) = object.get("price").and_then(Value::as_object) else {
        return false;
    };
    let amount_valid = price
        .get("amount_minor")
        .and_then(Value::as_u64)
        .is_some_and(|amount| amount <= 1_000_000_000_000);
    let currency_valid = price
        .get("currency")
        .and_then(Value::as_str)
        .is_some_and(|currency| {
            currency.len() == 3 && currency.bytes().all(|byte| byte.is_ascii_uppercase())
        });
    let interval_valid = object
        .get("interval")
        .and_then(Value::as_str)
        .is_some_and(|interval| matches!(interval, "month" | "year"));
    let Some(limits) = object.get("limits").and_then(Value::as_object) else {
        return false;
    };
    let limits_valid = limits
        .get("max_workspaces")
        .and_then(Value::as_u64)
        .is_some()
        && limits
            .get("ai_monthly_runs")
            .and_then(Value::as_u64)
            .is_some()
        && limits
            .get("byok_allowed")
            .and_then(Value::as_bool)
            .is_some();
    let trial_days_valid = object
        .get("trial_days")
        .is_none_or(|trial_days| trial_days.as_u64().is_some());

    amount_valid && currency_valid && interval_valid && limits_valid && trial_days_valid
}

/// Enforces policy effective-dating rules using injected instants.
// SPEC: POL-04
pub fn check_effective(
    spec: &PolicySpec,
    now: Instant,
    requested: Option<Instant>,
    latest_for_scope: Option<Instant>,
) -> Result<Instant, PolicyError> {
    let effective_from = requested.unwrap_or(now);
    match spec.effective_dating {
        EffectiveDating::FutureOnly if effective_from < now => {
            Err(PolicyError::effective(ErrorCode::PolicyEffectiveInPast))
        }
        EffectiveDating::Immediate
            if latest_for_scope.is_some_and(|latest| effective_from < latest) =>
        {
            Err(PolicyError::effective(ErrorCode::PolicyEffectiveOrder))
        }
        _ => Ok(effective_from),
    }
}

#[cfg(test)]
mod tests {
    use super::{
        EffectiveDating, PolicySpec, PolicyType, PolicyValue, Scope, check_effective,
        validate_scope, validate_value,
    };
    use crate::{errors::ErrorCode, time::Instant};
    use serde_json::{Value, json};

    const INSTANCE: &[Scope] = &[Scope::Instance];

    fn spec(ty: PolicyType, default: PolicyValue) -> PolicySpec {
        PolicySpec {
            key: "test.policy",
            ty,
            default,
            scopes: INSTANCE,
            effective_dating: EffectiveDating::Immediate,
            snapshot: false,
        }
    }

    fn assert_invalid(policy: &PolicySpec, value: &Value) {
        let error = validate_value(policy, value).expect_err("invalid policy value must fail");
        assert_eq!(error.code, ErrorCode::PolicyValueInvalid);
        assert!(error.details.get("bounds").is_some());
    }

    #[test]
    fn pol_03_validate_each_type() {
        let integer = spec(PolicyType::Int { min: 1, max: 10 }, PolicyValue::Int(5));
        assert_eq!(
            validate_value(&integer, &json!(7)).expect("valid int"),
            PolicyValue::Int(7)
        );
        assert_invalid(&integer, &json!(11));

        let boolean = spec(PolicyType::Bool, PolicyValue::Bool(false));
        assert_eq!(
            validate_value(&boolean, &json!(true)).expect("valid bool"),
            PolicyValue::Bool(true)
        );
        assert_invalid(&boolean, &json!("true"));

        let enumeration = spec(
            PolicyType::Enum {
                allowed: &["open", "closed"],
            },
            PolicyValue::Str("closed"),
        );
        assert_eq!(
            validate_value(&enumeration, &json!("open")).expect("valid enum"),
            PolicyValue::Str("open")
        );
        assert_invalid(&enumeration, &json!("unknown"));

        let duration = spec(
            PolicyType::DurationSeconds { min: 1, max: 60 },
            PolicyValue::Seconds(10),
        );
        assert_eq!(
            validate_value(&duration, &json!(30)).expect("valid duration"),
            PolicyValue::Seconds(30)
        );
        assert_invalid(&duration, &json!(0));

        let local_time = spec(PolicyType::LocalTime, PolicyValue::LocalTime(570));
        assert_eq!(
            validate_value(&local_time, &json!("09:30")).expect("valid local time"),
            PolicyValue::LocalTime(570)
        );
        assert_invalid(&local_time, &json!("24:00"));

        let basis_points = spec(PolicyType::Bp, PolicyValue::Bp(0));
        assert_eq!(
            validate_value(&basis_points, &json!(10_000)).expect("valid bp"),
            PolicyValue::Bp(10_000)
        );
        assert_invalid(&basis_points, &json!(10_001));

        let text_key = spec(
            PolicyType::TextKey,
            PolicyValue::Text("policy.label".to_owned()),
        );
        assert_eq!(
            validate_value(&text_key, &json!("policy.other")).expect("valid text key"),
            PolicyValue::Text("policy.other".to_owned())
        );
        assert_invalid(&text_key, &json!(9));

        let json_policy = spec(
            PolicyType::Json {
                schema: "billing_plans_v1",
            },
            PolicyValue::Json(json!([])),
        );
        let plans = json!([{
            "id": "example-plan",
            "name_key": "billing.plan.example",
            "price": { "amount_minor": 1, "currency": "XXX" },
            "interval": "month",
            "limits": { "max_workspaces": 1, "ai_monthly_runs": 0, "byok_allowed": false }
        }]);
        assert_eq!(
            validate_value(&json_policy, &plans).expect("valid registered JSON schema"),
            PolicyValue::Json(plans)
        );
        assert_invalid(
            &json_policy,
            &json!([{"id":"example-plan","interval":"week"}]),
        );
        assert_invalid(
            &json_policy,
            &json!([{
                "id": "example-plan",
                "name_key": "billing.plan.example",
                "price": { "amount_minor": 1_000_000_000_001_u64, "currency": "XXX" },
                "interval": "month",
                "limits": { "max_workspaces": 1, "ai_monthly_runs": 0, "byok_allowed": false }
            }]),
        );
        let unknown_schema = spec(
            PolicyType::Json {
                schema: "not_registered",
            },
            PolicyValue::Json(json!([])),
        );
        assert_invalid(&unknown_schema, &json!([]));

        let instance_only = spec(PolicyType::Bool, PolicyValue::Bool(false));
        let scope_error = validate_scope(&instance_only, Scope::Account)
            .expect_err("undeclared account scope must fail");
        assert_eq!(scope_error.code, ErrorCode::PolicyScopeInvalid);
        assert!(scope_error.details.get("bounds").is_some());
    }

    #[test]
    fn typed_policy_keys_only_read_their_declared_value_type() {
        use crate::policy::{
            PolicyKey, PolicyValue, ResolutionSource, Resolved,
            registry::identity::{PASSWORD_MIN_LENGTH, TOTP_REQUIRED},
        };

        let resolved = Resolved {
            value: PolicyValue::Int(12),
            source: ResolutionSource::Default,
            version_id: None,
            out_of_bounds: false,
        };
        let custom_integer_key = PolicyKey::<i64>::new("test.integer");
        assert_eq!(custom_integer_key.get(&resolved), Some(12));
        assert_eq!(PASSWORD_MIN_LENGTH.get(&resolved), Some(12));
        assert_eq!(TOTP_REQUIRED.get(&resolved), None);
    }

    #[test]
    fn pol_04_effective_rules() {
        let now = Instant::from_unix_millis(1_800_000_000_000).expect("valid instant");
        let past = Instant::from_unix_millis(1_799_999_999_999).expect("valid instant");
        let future_only = PolicySpec {
            effective_dating: EffectiveDating::FutureOnly,
            ..spec(PolicyType::Bool, PolicyValue::Bool(false))
        };
        assert_eq!(
            check_effective(&future_only, now, Some(past), None)
                .expect_err("past future-only value")
                .code,
            ErrorCode::PolicyEffectiveInPast,
        );

        let immediate = spec(PolicyType::Bool, PolicyValue::Bool(false));
        assert_eq!(
            check_effective(&immediate, now, Some(past), Some(now))
                .expect_err("earlier immediate value")
                .code,
            ErrorCode::PolicyEffectiveOrder,
        );
        assert_eq!(
            check_effective(&immediate, now, None, Some(now)).expect("now is ordered"),
            now
        );
    }
}
