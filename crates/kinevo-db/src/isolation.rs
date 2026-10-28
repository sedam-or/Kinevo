use sqlx::PgPool;
use std::collections::{BTreeMap, BTreeSet};
use thiserror::Error;

/// A safe identifier and fixed check describing one isolation finding.
#[derive(Clone, Debug, Eq, PartialEq)]
pub struct Finding {
    pub relation: String,
    pub policy: Option<String>,
    pub check: FindingCheck,
}

/// A fixed, non-sensitive reason an isolation check failed.
#[derive(Clone, Copy, Debug, Eq, Ord, PartialEq, PartialOrd)]
pub enum FindingCheck {
    RolePrivileges,
    OwnsTable,
    RowSecurityDisabled,
    RowSecurityNotForced,
    NoRlsTableHasRls,
    MissingPolicy,
    UnexpectedPolicy,
    PolicyExpression,
    PolicyScope,
    SystemTableGrant,
}

/// Failures found while verifying database isolation.
#[derive(Debug, Error)]
pub enum IsolationError {
    #[error("database isolation catalog query failed")]
    Database(#[source] sqlx::Error),
    #[error("database isolation findings: {0:?}")]
    Findings(Vec<Finding>),
}

#[derive(Debug)]
struct Policy {
    roles: String,
    command: String,
    using: String,
    check: String,
}

const NO_RLS_ALLOWLIST: &[(&str, &str)] = &[
    (
        "jobs",
        "system job queue; payloads contain ids and enums only (DOM-09)",
    ),
    (
        "outbox_events",
        "system outbox; payloads contain ids and enums only (DOM-09)",
    ),
    (
        "outbox_deliveries",
        "system delivery state for outbox events (DOM-09)",
    ),
    (
        "billing_webhook_events",
        "instance-level provider event deduplication (DOM-09)",
    ),
];

pub(crate) fn no_rls_allowlist_reason(table: &str) -> Option<&'static str> {
    NO_RLS_ALLOWLIST
        .iter()
        .find_map(|(name, reason)| (*name == table).then_some(*reason))
}

/// Verifies role restrictions, tenant RLS, exact policy names, and system grants.
pub async fn verify_isolation(pool: &PgPool) -> Result<(), IsolationError> {
    let mut findings = role_findings(pool).await?;
    let tables = tenant_tables(pool).await?;
    let mut policies = load_policies(pool).await?;

    for (table, rls_enabled, rls_forced) in tables {
        if no_rls_allowlist_reason(&table).is_some() {
            if rls_enabled || rls_forced {
                findings.push(Finding {
                    relation: table,
                    policy: None,
                    check: FindingCheck::NoRlsTableHasRls,
                });
            }
            continue;
        }
        findings.extend(table_findings(
            &table,
            rls_enabled,
            rls_forced,
            policies.remove(&table).unwrap_or_default(),
        ));
    }
    findings.extend(system_grant_findings(pool).await?);
    findings.sort_by(|left, right| {
        left.relation
            .cmp(&right.relation)
            .then_with(|| left.policy.cmp(&right.policy))
            .then_with(|| left.check.cmp(&right.check))
    });
    if findings.is_empty() {
        Ok(())
    } else {
        Err(IsolationError::Findings(findings))
    }
}

async fn role_findings(pool: &PgPool) -> Result<Vec<Finding>, IsolationError> {
    let mut findings = Vec::new();
    let role = sqlx::query_as::<_, (String, bool, bool, bool, bool)>(
        "SELECT current_user::text, r.rolsuper, r.rolbypassrls, r.rolcreatedb, r.rolcreaterole \
         FROM pg_roles AS r WHERE r.rolname = current_user",
    )
    .fetch_one(pool)
    .await
    .map_err(IsolationError::Database)?;
    let (role_name, superuser, bypass_rls, create_db, create_role) = role;
    if superuser || bypass_rls || create_db || create_role {
        findings.push(Finding {
            relation: role_name,
            policy: None,
            check: FindingCheck::RolePrivileges,
        });
    }

    let owned_tables = sqlx::query_scalar::<_, String>(
        "SELECT c.relname::text FROM pg_class AS c \
         JOIN pg_namespace AS n ON n.oid = c.relnamespace \
         WHERE n.nspname = 'public' AND c.relkind IN ('r', 'p') \
           AND c.relowner = (SELECT oid FROM pg_roles WHERE rolname = current_user) \
         ORDER BY c.relname",
    )
    .fetch_all(pool)
    .await
    .map_err(IsolationError::Database)?;
    findings.extend(owned_tables.into_iter().map(|table| Finding {
        relation: table,
        policy: None,
        check: FindingCheck::OwnsTable,
    }));
    Ok(findings)
}

async fn tenant_tables(pool: &PgPool) -> Result<Vec<(String, bool, bool)>, IsolationError> {
    sqlx::query_as::<_, (String, bool, bool)>(
        "SELECT c.relname::text, c.relrowsecurity, c.relforcerowsecurity \
         FROM pg_class AS c JOIN pg_namespace AS n ON n.oid = c.relnamespace \
         WHERE n.nspname = 'public' AND c.relkind IN ('r', 'p') \
           AND (c.relname = 'accounts' OR EXISTS ( \
             SELECT 1 FROM pg_attribute AS a WHERE a.attrelid = c.oid \
               AND a.attname = 'account_id' AND NOT a.attisdropped)) \
         ORDER BY c.relname",
    )
    .fetch_all(pool)
    .await
    .map_err(IsolationError::Database)
}

async fn load_policies(
    pool: &PgPool,
) -> Result<BTreeMap<String, BTreeMap<String, Policy>>, IsolationError> {
    let policies = sqlx::query_as::<_, (String, String, String, String, String, String)>(
        "SELECT c.relname::text, p.polname::text, \
           COALESCE((SELECT string_agg(r.rolname, ',' ORDER BY r.rolname) \
             FROM unnest(p.polroles) AS granted_role(oid) \
             JOIN pg_roles AS r ON r.oid = granted_role.oid), '')::text, \
           p.polcmd::text, COALESCE(pg_get_expr(p.polqual, p.polrelid), '')::text, \
           COALESCE(pg_get_expr(p.polwithcheck, p.polrelid), '')::text \
         FROM pg_policy AS p JOIN pg_class AS c ON c.oid = p.polrelid \
         JOIN pg_namespace AS n ON n.oid = c.relnamespace \
         WHERE n.nspname = 'public' ORDER BY c.relname, p.polname",
    )
    .fetch_all(pool)
    .await
    .map_err(IsolationError::Database)?;

    let mut by_table = BTreeMap::<String, BTreeMap<String, Policy>>::new();
    for (table, name, roles, command, using, check) in policies {
        by_table.entry(table).or_default().insert(
            name,
            Policy {
                roles,
                command,
                using,
                check,
            },
        );
    }
    Ok(by_table)
}

fn table_findings(
    table: &str,
    rls_enabled: bool,
    rls_forced: bool,
    actual: BTreeMap<String, Policy>,
) -> Vec<Finding> {
    let mut findings = Vec::new();
    if !rls_enabled {
        findings.push(Finding {
            relation: table.to_owned(),
            policy: None,
            check: FindingCheck::RowSecurityDisabled,
        });
    }
    if !rls_forced {
        findings.push(Finding {
            relation: table.to_owned(),
            policy: None,
            check: FindingCheck::RowSecurityNotForced,
        });
    }

    let expected = allowed_policies(table);
    for policy in &expected {
        if !actual.contains_key(*policy) {
            findings.push(Finding {
                relation: table.to_owned(),
                policy: Some((*policy).to_owned()),
                check: FindingCheck::MissingPolicy,
            });
        }
    }
    for (name, policy) in actual {
        if !expected.contains(name.as_str()) {
            findings.push(Finding {
                relation: table.to_owned(),
                policy: Some(name),
                check: FindingCheck::UnexpectedPolicy,
            });
        } else if let Some(check) = policy_finding_check(table, &name, &policy) {
            findings.push(Finding {
                relation: table.to_owned(),
                policy: Some(name),
                check,
            });
        }
    }
    findings
}

async fn system_grant_findings(pool: &PgPool) -> Result<Vec<Finding>, IsolationError> {
    let system_grants = sqlx::query_scalar::<_, String>(
        "SELECT DISTINCT c.relname::text FROM pg_class AS c \
         JOIN pg_namespace AS n ON n.oid = c.relnamespace \
         CROSS JOIN LATERAL aclexplode(COALESCE(c.relacl, acldefault('r', c.relowner))) AS a \
         JOIN pg_roles AS r ON r.oid = a.grantee \
         WHERE n.nspname = 'public' AND c.relkind IN ('r', 'p') \
           AND r.rolname = 'kinevo_system' ORDER BY c.relname::text",
    )
    .fetch_all(pool)
    .await
    .map_err(IsolationError::Database)?;
    let allowed_system_tables = system_table_grants();
    Ok(system_grants
        .into_iter()
        .filter(|table| !allowed_system_tables.contains(table.as_str()))
        .map(|table| Finding {
            relation: table,
            policy: None,
            check: FindingCheck::SystemTableGrant,
        })
        .collect())
}

fn allowed_policies(table: &str) -> BTreeSet<&'static str> {
    let mut policies = BTreeSet::from(["tenant_isolation"]);
    match table {
        "accounts" | "refresh_token_families" | "refresh_tokens" | "password_resets" => {
            policies.insert("system_auth");
        }
        "policy_versions" => {
            // SEC-06 permits an admin-context policy; its name is not fixed in the spec.
            policies.insert("admin_context");
        }
        "audit_log" => {
            policies.extend(["admin_context", "system_insert", "retention"]);
        }
        "secrets" => {
            policies.extend(["instance_secrets_read", "instance_secrets_write"]);
        }
        "idempotency_keys" => {
            policies.insert("retention");
        }
        _ => {}
    }
    policies
}

fn known_policy_scope(table: &str, name: &str, policy: &Policy) -> bool {
    match name {
        "system_auth" => {
            matches!(
                table,
                "accounts" | "refresh_token_families" | "refresh_tokens" | "password_resets"
            ) && policy.roles == "kinevo_system"
                && policy.command == "*"
                && same_sql_expression(&policy.using, "true")
                && same_sql_expression(&policy.check, "true")
        }
        "system_insert" => {
            table == "audit_log"
                && policy.roles == "kinevo_system"
                && policy.command == "a"
                && policy.using.is_empty()
                && same_sql_expression(&policy.check, "true")
        }
        "retention" => {
            let expected_using = match table {
                "audit_log" => "true",
                "idempotency_keys" => {
                    "expires_at < NULLIF(current_setting('app.retention_cutoff', true), '')::timestamptz"
                }
                _ => return false,
            };
            policy.roles == "kinevo_migrator"
                && policy.command == "d"
                && same_sql_expression(&policy.using, expected_using)
                && policy.check.is_empty()
        }
        "instance_secrets_read" => {
            table == "secrets"
                && policy.roles.is_empty()
                && policy.command == "r"
                && same_sql_expression(&policy.using, "account_id IS NULL")
                && policy.check.is_empty()
        }
        "instance_secrets_write" => {
            table == "secrets"
                && policy.roles.is_empty()
                && policy.command == "*"
                && same_sql_expression(
                    &policy.using,
                    "account_id IS NULL AND current_setting('app.instance_admin', true) = 'on'",
                )
                && same_sql_expression(
                    &policy.check,
                    "account_id IS NULL AND current_setting('app.instance_admin', true) = 'on'",
                )
        }
        "admin_context" => {
            if table == "policy_versions" {
                policy.roles.is_empty() && policy.command == "*"
            } else {
                table == "audit_log"
                    && policy.roles.is_empty()
                    && policy.command == "r"
                    && same_sql_expression(
                        &policy.using,
                        "current_setting('app.instance_admin', true) = 'on'",
                    )
                    && policy.check.is_empty()
            }
        }
        _ => false,
    }
}

fn policy_finding_check(table: &str, name: &str, policy: &Policy) -> Option<FindingCheck> {
    if name == "tenant_isolation" {
        let column = if table == "accounts" {
            "id"
        } else {
            "account_id"
        };
        let expression =
            format!("{column} = NULLIF(current_setting('app.account_id', true), '')::uuid");
        let check_expression = if table == "audit_log" {
            "account_id IS NULL OR account_id = NULLIF(current_setting('app.account_id', true), '')::uuid"
        } else {
            expression.as_str()
        };
        if !same_sql_expression(&policy.using, &expression)
            || !same_sql_expression(&policy.check, check_expression)
        {
            return Some(FindingCheck::PolicyExpression);
        }
        if !policy.roles.is_empty() || policy.command != "*" {
            return Some(FindingCheck::PolicyScope);
        }
        return None;
    }
    (!known_policy_scope(table, name, policy)).then_some(FindingCheck::PolicyScope)
}

fn system_table_grants() -> BTreeSet<&'static str> {
    BTreeSet::from([
        "accounts",
        "refresh_token_families",
        "refresh_tokens",
        "password_resets",
        "outbox_events",
        "outbox_deliveries",
        "jobs",
        "audit_log",
        "instance_settings",
        "instance_modules",
        "policy_versions",
        "invites",
        "secrets",
    ])
}

fn same_sql_expression(actual: &str, expected: &str) -> bool {
    normalize_sql_expression(actual) == normalize_sql_expression(expected)
}

fn normalize_sql_expression(expression: &str) -> String {
    expression
        .to_ascii_lowercase()
        .chars()
        .filter(|character| !character.is_ascii_whitespace() && !matches!(character, '(' | ')'))
        .collect::<String>()
        .replace("::text", "")
        .replace("::timestampwithtimezone", "::timestamptz")
}
