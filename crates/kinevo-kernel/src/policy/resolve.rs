//! Pure policy resolution and snapshot access.

// SPEC: POL-01, POL-06, POL-08

use super::{PolicySpec, PolicyValue, Scope, validate_value};
use crate::{id::AccountId, time::Instant};
use uuid::Uuid;

#[derive(Clone, Debug, PartialEq)]
// SPEC: POL-01
pub struct PolicyVersion {
    pub id: Uuid,
    pub scope: Scope,
    pub account_id: Option<AccountId>,
    pub key: &'static str,
    pub value: PolicyValue,
    pub effective_from: Instant,
}

#[derive(Clone, Copy, Debug, Eq, PartialEq)]
// SPEC: POL-01
pub enum ResolutionSource {
    Default,
    Instance,
    Account,
}

#[derive(Clone, Debug, PartialEq)]
// SPEC: POL-01, POL-08
pub struct Resolved {
    pub value: PolicyValue,
    pub source: ResolutionSource,
    pub version_id: Option<Uuid>,
    pub out_of_bounds: bool,
}

#[derive(Clone, Debug, Default, PartialEq)]
// SPEC: POL-06
pub struct Snapshot {
    pub entries: Vec<(String, PolicyValue, Option<Uuid>)>,
}

/// Resolves account, instance, and default values at an injected instant.
// SPEC: POL-01, POL-08
#[must_use]
pub fn resolve(
    spec: &PolicySpec,
    versions: &[PolicyVersion],
    account: Option<AccountId>,
    at: Instant,
) -> Resolved {
    let mut out_of_bounds = false;

    if let (Some(account_id), true) = (account, spec.scopes.contains(&Scope::Account))
        && let Some(resolved) = resolve_scope(
            spec,
            versions,
            Scope::Account,
            Some(account_id),
            at,
            &mut out_of_bounds,
        )
    {
        return resolved;
    }

    if spec.scopes.contains(&Scope::Instance)
        && let Some(resolved) = resolve_scope(
            spec,
            versions,
            Scope::Instance,
            None,
            at,
            &mut out_of_bounds,
        )
    {
        return resolved;
    }

    Resolved {
        value: spec.default.clone(),
        source: ResolutionSource::Default,
        version_id: None,
        out_of_bounds,
    }
}

fn resolve_scope(
    spec: &PolicySpec,
    versions: &[PolicyVersion],
    scope: Scope,
    account: Option<AccountId>,
    at: Instant,
    out_of_bounds: &mut bool,
) -> Option<Resolved> {
    let mut candidates = versions
        .iter()
        .filter(|version| {
            version.key == spec.key
                && version.scope == scope
                && version.account_id == account
                && version.effective_from <= at
        })
        .collect::<Vec<_>>();
    candidates.sort_by(|left, right| {
        right
            .effective_from
            .cmp(&left.effective_from)
            .then_with(|| right.id.cmp(&left.id))
    });

    for version in candidates {
        match validate_value(spec, &version.value.as_json()) {
            Ok(value) => {
                return Some(Resolved {
                    value,
                    source: match scope {
                        Scope::Instance => ResolutionSource::Instance,
                        Scope::Account => ResolutionSource::Account,
                    },
                    version_id: Some(version.id),
                    out_of_bounds: *out_of_bounds,
                });
            }
            Err(_) => *out_of_bounds = true,
        }
    }
    None
}

/// Returns the value captured in a decision snapshot without consulting live versions.
// SPEC: POL-06
#[must_use]
pub fn resolve_with_snapshot<'a>(snapshot: &'a Snapshot, key: &str) -> Option<&'a PolicyValue> {
    snapshot
        .entries
        .iter()
        .find(|(snapshot_key, _, _)| snapshot_key == key)
        .map(|(_, value, _)| value)
}

#[cfg(test)]
mod tests {
    use super::super::{
        EffectiveDating, PolicySpec, PolicyType, PolicyValue, PolicyVersion, ResolutionSource,
        Scope, Snapshot, resolve, resolve_with_snapshot,
    };
    use crate::{id::AccountId, time::Instant};
    use uuid::Uuid;

    const BOTH_SCOPES: &[Scope] = &[Scope::Instance, Scope::Account];
    const INSTANCE_ONLY: &[Scope] = &[Scope::Instance];

    fn instant(milliseconds: i64) -> Instant {
        Instant::from_unix_millis(milliseconds).expect("valid instant")
    }

    fn integer_spec(scopes: &'static [Scope]) -> PolicySpec {
        PolicySpec {
            key: "test.integer",
            ty: PolicyType::Int { min: 0, max: 100 },
            default: PolicyValue::Int(5),
            scopes,
            effective_dating: EffectiveDating::Immediate,
            snapshot: false,
        }
    }

    #[test]
    fn pol_resolution_account_over_instance() {
        let account = AccountId::from_uuid(Uuid::from_u128(100));
        let t0 = instant(1_800_000_000_000);
        let t1 = instant(1_800_000_001_000);
        let spec = integer_spec(BOTH_SCOPES);
        let versions = vec![
            PolicyVersion {
                id: Uuid::from_u128(1),
                scope: Scope::Instance,
                account_id: None,
                key: spec.key,
                value: PolicyValue::Int(10),
                effective_from: t0,
            },
            PolicyVersion {
                id: Uuid::from_u128(2),
                scope: Scope::Account,
                account_id: Some(account),
                key: spec.key,
                value: PolicyValue::Int(20),
                effective_from: t1,
            },
        ];

        assert_eq!(
            resolve(&spec, &versions, Some(account), t0).value,
            PolicyValue::Int(10)
        );
        let at_t1 = resolve(&spec, &versions, Some(account), t1);
        assert_eq!(at_t1.value, PolicyValue::Int(20));
        assert_eq!(at_t1.source, ResolutionSource::Account);
        assert_eq!(at_t1.version_id, Some(Uuid::from_u128(2)));

        let tied = vec![
            PolicyVersion {
                id: Uuid::from_u128(3),
                scope: Scope::Instance,
                account_id: None,
                key: spec.key,
                value: PolicyValue::Int(30),
                effective_from: t0,
            },
            versions[0].clone(),
        ];
        let tie_result = resolve(&spec, &tied, None, t0);
        assert_eq!(tie_result.value, PolicyValue::Int(30));
        assert_eq!(tie_result.version_id, Some(Uuid::from_u128(3)));
    }

    #[test]
    fn scope_not_allowed_is_ignored() {
        let account = AccountId::from_uuid(Uuid::from_u128(100));
        let now = instant(1_800_000_000_000);
        let spec = integer_spec(INSTANCE_ONLY);
        let versions = vec![
            PolicyVersion {
                id: Uuid::from_u128(1),
                scope: Scope::Instance,
                account_id: None,
                key: spec.key,
                value: PolicyValue::Int(10),
                effective_from: now,
            },
            PolicyVersion {
                id: Uuid::from_u128(2),
                scope: Scope::Account,
                account_id: Some(account),
                key: spec.key,
                value: PolicyValue::Int(99),
                effective_from: now,
            },
        ];

        let resolved = resolve(&spec, &versions, Some(account), now);
        assert_eq!(resolved.value, PolicyValue::Int(10));
        assert_eq!(resolved.source, ResolutionSource::Instance);
    }

    #[test]
    fn pol_08_out_of_bounds_falls_back() {
        let account = AccountId::from_uuid(Uuid::from_u128(100));
        let now = instant(1_800_000_000_000);
        let spec = integer_spec(BOTH_SCOPES);
        let versions = vec![PolicyVersion {
            id: Uuid::from_u128(1),
            scope: Scope::Account,
            account_id: Some(account),
            key: spec.key,
            value: PolicyValue::Int(101),
            effective_from: now,
        }];

        let resolved = resolve(&spec, &versions, Some(account), now);
        assert_eq!(resolved.value, PolicyValue::Int(5));
        assert_eq!(resolved.source, ResolutionSource::Default);
        assert!(resolved.out_of_bounds);

        let with_instance = vec![
            versions[0].clone(),
            PolicyVersion {
                id: Uuid::from_u128(2),
                scope: Scope::Instance,
                account_id: None,
                key: spec.key,
                value: PolicyValue::Int(10),
                effective_from: now,
            },
        ];
        let fallback = resolve(&spec, &with_instance, Some(account), now);
        assert_eq!(fallback.value, PolicyValue::Int(10));
        assert_eq!(fallback.source, ResolutionSource::Instance);
        assert!(fallback.out_of_bounds);
    }

    #[test]
    fn pol_06_snapshot_replay_is_stable() {
        let account = AccountId::from_uuid(Uuid::from_u128(100));
        let now = instant(1_800_000_000_000);
        let spec = integer_spec(INSTANCE_ONLY);
        let current = [PolicyVersion {
            id: Uuid::from_u128(2),
            scope: Scope::Instance,
            account_id: None,
            key: spec.key,
            value: PolicyValue::Int(9),
            effective_from: now,
        }];
        let snapshot = Snapshot {
            entries: vec![(
                spec.key.to_owned(),
                PolicyValue::Int(7),
                Some(Uuid::from_u128(1)),
            )],
        };

        assert_eq!(
            resolve(&spec, &current, Some(account), now).value,
            PolicyValue::Int(9)
        );
        assert_eq!(
            resolve_with_snapshot(&snapshot, spec.key),
            Some(&PolicyValue::Int(7))
        );
    }
}
