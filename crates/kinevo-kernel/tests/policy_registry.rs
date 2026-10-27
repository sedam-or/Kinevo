use kinevo_kernel::policy::{PolicyType, PolicyValue, Scope, registry::all_specs};

const M1_KEYS: &[&str] = &[
    "auth.registration.mode",
    "auth.password.min_length",
    "auth.access_token.ttl",
    "auth.refresh_token.absolute_ttl",
    "auth.refresh_token.idle_ttl",
    "auth.lockout.max_attempts",
    "auth.lockout.window",
    "auth.lockout.duration",
    "auth.totp.required",
    "auth.refresh.grace",
    "identity.purge.delay",
    "api.idempotency.ttl",
    "auth.invite.ttl",
    "auth.password_reset.ttl",
    "ratelimit.auth_per_minute",
    "ratelimit.api_per_minute",
    "ratelimit.ai_per_minute",
    "ratelimit.upload_per_minute",
    "ratelimit.sync_per_minute",
    "ratelimit.export_per_minute",
    "audit.retention_days",
    "modules.cache_ttl_seconds",
    "jobs.lease_seconds",
    "jobs.retry.max_attempts",
    "jobs.retry.base_seconds",
    "jobs.retry.cap_seconds",
    "export.max_age_days",
];

const POLICY_SPEC: &str = include_str!("../../../spec/04-policies.md");

fn policy_row(key: &str) -> Option<Vec<&str>> {
    let prefix = format!("| `{key}` |");
    let mut rows = POLICY_SPEC
        .lines()
        .filter(|line| line.starts_with(&prefix))
        .map(|line| line.split('|').map(str::trim).collect::<Vec<_>>());
    let row = rows.next()?;
    rows.next().is_none().then_some(row)
}

fn number(value: &str) -> Option<i64> {
    value.split_whitespace().next()?.parse().ok()
}

fn cell<'a>(row: &[&'a str], index: usize) -> Option<&'a str> {
    row.get(index).copied()
}

fn duration_seconds(value: &str) -> Option<i64> {
    let mut parts = value.split_whitespace();
    let amount: i64 = parts.next()?.parse().ok()?;
    let unit = parts.next().unwrap_or("s").trim_end_matches('.');
    let multiplier = match unit {
        "s" | "second" | "seconds" => 1,
        "m" | "min" | "minute" | "minutes" => 60,
        "h" | "hour" | "hours" => 3_600,
        "d" | "day" | "days" => 86_400,
        _ => return None,
    };
    amount.checked_mul(multiplier)
}

fn range(value: &str, duration: bool) -> Option<(i64, i64)> {
    let (min, max) = value.split_once('–')?;
    let parse = if duration { duration_seconds } else { number };
    Some((parse(min)?, parse(max)?))
}

fn type_matches(policy_type: PolicyType, text: &str) -> bool {
    match policy_type {
        PolicyType::Int { min, max } => {
            text.strip_prefix("int ").and_then(|s| range(s, false)) == Some((min, max))
        }
        PolicyType::DurationSeconds { min, max } => {
            text.strip_prefix("duration ").and_then(|s| range(s, true)) == Some((min, max))
        }
        PolicyType::Bool => text == "bool",
        PolicyType::Enum { allowed } => {
            let values: Vec<_> = text
                .strip_prefix("enum ")
                .unwrap_or_default()
                .split(|ch: char| !(ch.is_ascii_alphanumeric() || ch == '_'))
                .filter(|part| !part.is_empty())
                .collect();
            values == allowed
        }
        _ => false,
    }
}

fn default_matches(default: &PolicyValue, text: &str) -> bool {
    match default {
        PolicyValue::Int(expected) | PolicyValue::Seconds(expected) => {
            number(text) == Some(*expected)
        }
        PolicyValue::Bool(expected) => text.parse::<bool>().ok() == Some(*expected),
        PolicyValue::Str(expected) => text.trim_matches('`') == *expected,
        _ => false,
    }
}

#[test]
fn pol_01_registry_matches_spec() {
    let specs = all_specs();
    assert_eq!(specs.len(), M1_KEYS.len());
    let mut seen = std::collections::BTreeSet::new();

    for spec in specs {
        assert!(seen.insert(spec.key), "duplicate registry key {}", spec.key);
        assert!(
            M1_KEYS.contains(&spec.key),
            "unexpected non-M1 key {}",
            spec.key
        );
        let row = policy_row(spec.key);
        assert!(
            row.is_some(),
            "missing or duplicate policy row for {}",
            spec.key
        );
        let Some(row) = row else {
            continue;
        };
        let type_cell = cell(&row, 2);
        assert!(type_cell.is_some(), "type cell missing for {}", spec.key);
        let Some(type_cell) = type_cell else {
            continue;
        };
        let default_cell = cell(&row, 3);
        assert!(
            default_cell.is_some(),
            "default cell missing for {}",
            spec.key
        );
        let Some(default_cell) = default_cell else {
            continue;
        };
        let scope_cell = cell(&row, 4);
        assert!(scope_cell.is_some(), "scope cell missing for {}", spec.key);
        let Some(scope_cell) = scope_cell else {
            continue;
        };
        assert!(
            type_matches(spec.ty, type_cell),
            "type mismatch for {}: {}",
            spec.key,
            type_cell
        );
        assert!(
            default_matches(&spec.default, default_cell),
            "default mismatch for {}: {}",
            spec.key,
            default_cell
        );
        assert_eq!(
            spec.scopes,
            &[Scope::Instance],
            "scope mismatch for {}",
            spec.key
        );
        assert_eq!(scope_cell, "inst", "scope source mismatch for {}", spec.key);
    }
    assert_eq!(seen.len(), M1_KEYS.len());
    for key in M1_KEYS {
        assert!(seen.contains(key), "missing M1 policy {key}");
    }
}

#[test]
fn policy_key_names_exclude_sensitive_segments() {
    for spec in all_specs() {
        let key = spec.key;
        assert!(
            !key.contains("secret"),
            "secret segment is forbidden in policy key {key}"
        );
        assert!(
            matches!(key, "auth.password.min_length" | "auth.password_reset.ttl")
                || !key.contains("password"),
            "password segment is forbidden in policy key {key}"
        );
        let is_ttl_key = key
            .split('.')
            .next_back()
            .is_some_and(|segment| segment.ends_with("ttl"));
        assert!(
            is_ttl_key || !key.contains("token"),
            "non-TTL token segment is forbidden in policy key {key}"
        );
        assert_ne!(
            key.rsplit('.').next(),
            Some("key"),
            "trailing key segment is forbidden in policy key {key}"
        );
    }
}
