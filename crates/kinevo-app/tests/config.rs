use std::{collections::BTreeMap, path::Path};

use kinevo_app::config::{Config, ConfigIssue};

fn from_values(values: &BTreeMap<String, String>) -> Result<Config, Vec<ConfigIssue>> {
    Config::from_env(&|name| values.get(name).cloned(), &|_: &Path| {
        Err(std::io::Error::new(
            std::io::ErrorKind::NotFound,
            "missing test file",
        ))
    })
}

fn errors(result: Result<Config, Vec<ConfigIssue>>) -> Vec<ConfigIssue> {
    match result {
        Err(issues) => issues,
        Ok(_) => Vec::new(),
    }
}

#[test]
fn ops_config_reports_all_issues_without_values() {
    let marker = "synthetic-invalid-secret-key-value";
    let values = BTreeMap::from([
        ("KINEVO_SECRET_KEY".to_owned(), marker.to_owned()),
        ("KINEVO_SMTP_URL".to_owned(), "not-an-smtp-url".to_owned()),
        (
            "KINEVO_SYSTEM_DATABASE_URL".to_owned(),
            "postgres://system:example@localhost/test".to_owned(),
        ),
        (
            "KINEVO_PUBLIC_URL".to_owned(),
            "https://example.test".to_owned(),
        ),
    ]);

    let issues = errors(from_values(&values));
    let report = format!("{issues:?}");

    assert!(report.contains("KINEVO_DATABASE_URL"));
    assert!(report.contains("KINEVO_SECRET_KEY"));
    assert!(report.contains("KINEVO_SMTP_URL"));
    assert!(!report.contains(marker));
    assert!(!report.contains("not-an-smtp-url"));
}

#[test]
fn file_and_value_conflict() {
    let values = BTreeMap::from([
        (
            "KINEVO_DATABASE_URL".to_owned(),
            "postgres://app:example@localhost/test".to_owned(),
        ),
        (
            "KINEVO_DATABASE_URL_FILE".to_owned(),
            "/tmp/synthetic-database-url".to_owned(),
        ),
    ]);

    let issues = errors(from_values(&values));
    let report = format!("{issues:?}");

    assert!(report.contains("KINEVO_DATABASE_URL"));
    assert!(report.contains("KINEVO_DATABASE_URL_FILE"));
    assert!(!report.contains("postgres://app:example@localhost/test"));
    assert!(!report.contains("/tmp/synthetic-database-url"));
}
