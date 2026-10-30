//! Typed, single-pass configuration parsing for the application composition root.

use std::{
    io,
    net::{IpAddr, SocketAddr},
    path::Path,
    str::FromStr,
    time::Duration,
};

use kinevo_kernel::{
    crypto::MasterKey,
    secret::Secret,
    validate::net::{Email, HttpsUrl},
};

/// One safe-to-display configuration problem. Values and file paths are never retained.
#[derive(Clone, Debug, Eq, PartialEq)]
pub struct ConfigIssue {
    /// Environment key that needs attention.
    pub variable: String,
    /// Rule the configured value must satisfy.
    pub rule: &'static str,
}

/// Typed application configuration. Secret fields redact their debug representation.
pub struct Config {
    /// Database URL for ordinary tenant-scoped application work.
    pub database_url: Secret<String>,
    /// Optional restricted migration-role URL.
    pub migrator_database_url: Option<Secret<String>>,
    /// Database URL for system-scoped work.
    pub system_database_url: Secret<String>,
    /// Application master key decoded from its padded base64 representation.
    pub secret_key: MasterKey,
    /// Public origin, allowing HTTP only for loopback hosts.
    pub public_url: HttpsUrl,
    /// Address the HTTP server binds to.
    pub listen: SocketAddr,
    /// Explicit networks permitted to supply forwarded client addresses.
    pub trusted_proxies: Vec<Cidr>,
    /// Whether pending migrations are applied during startup.
    pub auto_migrate: bool,
    /// Maximum connections per role pool.
    pub pool_max: u32,
    /// Retries after the initial database connection attempt.
    pub db_connect_retries: u32,
    /// Initial delay between failed connection attempts.
    pub db_connect_backoff: Duration,
    /// Recurring-job materialisation cadence.
    pub jobs_tick: Duration,
    /// Logging configuration.
    pub logging: LoggingConfig,
    /// Optional metrics endpoint configuration.
    pub metrics: MetricsConfig,
    /// Whether the documented Android origin is accepted.
    pub android_origin: bool,
    /// First-account bootstrap configuration.
    pub setup: SetupBootstrap,
    /// Optional configured SMTP credentials, held as redacting secrets.
    pub smtp: Option<SmtpConfig>,
    /// Global request body ceiling.
    pub max_body_bytes: usize,
}

/// A validated IP address range from `KINEVO_TRUSTED_PROXIES` or metrics allow list.
#[derive(Clone, Copy, Debug, Eq, PartialEq)]
pub struct Cidr {
    address: IpAddr,
    prefix: u8,
}

impl FromStr for Cidr {
    type Err = ();

    fn from_str(value: &str) -> Result<Self, Self::Err> {
        let (address, prefix) = value.split_once('/').ok_or(())?;
        let address = address.parse::<IpAddr>().map_err(|_| ())?;
        let prefix = prefix.parse::<u8>().map_err(|_| ())?;
        let max_prefix = match address {
            IpAddr::V4(_) => 32,
            IpAddr::V6(_) => 128,
        };
        if prefix > max_prefix {
            return Err(());
        }
        Ok(Self { address, prefix })
    }
}

impl Cidr {
    /// Returns the canonical address/prefix text used by the HTTP proxy parser.
    #[must_use]
    pub fn as_cidr(&self) -> String {
        format!("{}/{}", self.address, self.prefix)
    }
}

/// Log level selected at process startup.
#[derive(Clone, Copy, Debug, Eq, PartialEq)]
pub enum LogLevel {
    Trace,
    Debug,
    Info,
    Warn,
    Error,
}

/// Log output encoding selected at process startup.
#[derive(Clone, Copy, Debug, Eq, PartialEq)]
pub enum LogFormat {
    Json,
    Pretty,
}

/// Logging settings parsed from `KINEVO_LOG_*`.
#[derive(Clone, Copy, Debug, Eq, PartialEq)]
pub struct LoggingConfig {
    pub level: LogLevel,
    pub format: LogFormat,
}

/// Metrics exposure settings.
#[derive(Clone, Debug, Eq, PartialEq)]
pub struct MetricsConfig {
    pub enabled: bool,
    pub allow: Vec<Cidr>,
}

/// Optional first-account settings supplied by the operator.
pub struct SetupBootstrap {
    pub email: Option<Email>,
    pub password: Option<Secret<String>>,
    pub template: String,
    pub session_ttl: Duration,
}

/// Configured outbound mail credentials. A transport is selected only when present.
pub struct SmtpConfig {
    pub url: Secret<String>,
    pub password: Option<Secret<String>>,
}

impl Config {
    /// Parses all configuration once, collecting safe issues without retaining bad values.
    #[allow(
        clippy::too_many_lines,
        reason = "configuration is validated in one deterministic pass"
    )]
    pub fn from_env(
        env: &dyn Fn(&str) -> Option<String>,
        read_file: &dyn Fn(&Path) -> io::Result<String>,
    ) -> Result<Self, Vec<ConfigIssue>> {
        let mut issues = Vec::new();

        let database_url = secret_value(
            env,
            read_file,
            "KINEVO_DATABASE_URL",
            "KINEVO_DATABASE_URL_FILE",
            true,
            &mut issues,
        );
        let migrator_database_url = secret_value(
            env,
            read_file,
            "KINEVO_MIGRATOR_DATABASE_URL",
            "KINEVO_MIGRATOR_DATABASE_URL_FILE",
            false,
            &mut issues,
        );
        let system_database_url = secret_value(
            env,
            read_file,
            "KINEVO_SYSTEM_DATABASE_URL",
            "KINEVO_SYSTEM_DATABASE_URL_FILE",
            true,
            &mut issues,
        );
        let encoded_key = secret_value(
            env,
            read_file,
            "KINEVO_SECRET_KEY",
            "KINEVO_SECRET_KEY_FILE",
            true,
            &mut issues,
        );
        let secret_key = encoded_key.and_then(|encoded| {
            if let Ok(key) = MasterKey::from_base64(&encoded) {
                Some(key)
            } else {
                issues.push(issue(
                    "KINEVO_SECRET_KEY",
                    "must be padded base64 encoding of exactly 32 bytes",
                ));
                None
            }
        });

        let public_url = if let Some(value) = env("KINEVO_PUBLIC_URL") {
            if let Ok(url) = HttpsUrl::parse(&value) {
                Some(url)
            } else {
                issues.push(issue(
                    "KINEVO_PUBLIC_URL",
                    "must be HTTPS, except for a loopback HTTP URL",
                ));
                None
            }
        } else {
            issues.push(issue("KINEVO_PUBLIC_URL", "is required"));
            None
        };

        let listen = parsed_or_default(
            env,
            "KINEVO_LISTEN",
            SocketAddr::from(([0, 0, 0, 0], 8080)),
            "must be a socket address",
            &mut issues,
        );
        let auto_migrate = bool_or_default(env, "KINEVO_AUTO_MIGRATE", true, &mut issues);
        let pool_max = positive_or_default(
            env,
            "KINEVO_DB_POOL_MAX",
            10_u32,
            "must be a positive integer",
            &mut issues,
        );
        let db_connect_retries = retries_or_default(env, &mut issues);
        let jobs_tick_seconds = positive_or_default(
            env,
            "KINEVO_JOBS_TICK_SECONDS",
            300_u64,
            "must be a positive integer number of seconds",
            &mut issues,
        );
        let max_body_bytes = positive_or_default(
            env,
            "KINEVO_MAX_BODY_BYTES",
            1_048_576_usize,
            "must be a positive integer number of bytes",
            &mut issues,
        );
        let setup_ttl_seconds = positive_or_default(
            env,
            "KINEVO_SETUP_SESSION_TTL",
            86_400_u64,
            "must be a positive integer number of seconds",
            &mut issues,
        );
        let log_level = enum_or_default(
            env,
            "KINEVO_LOG_LEVEL",
            "info",
            &["trace", "debug", "info", "warn", "error"],
            &mut issues,
        );
        let log_format = enum_or_default(
            env,
            "KINEVO_LOG_FORMAT",
            "json",
            &["json", "pretty"],
            &mut issues,
        );
        let android_origin = bool_or_default(env, "KINEVO_ANDROID_ORIGIN", true, &mut issues);
        let metrics_enabled = bool_or_default(env, "KINEVO_METRICS_ENABLED", false, &mut issues);
        let trusted_proxies = cidr_list(
            env("KINEVO_TRUSTED_PROXIES"),
            "KINEVO_TRUSTED_PROXIES",
            &mut issues,
        );
        let metrics_allow = cidr_list(
            env("KINEVO_METRICS_ALLOW"),
            "KINEVO_METRICS_ALLOW",
            &mut issues,
        );

        let setup_email = env("KINEVO_SETUP_EMAIL").and_then(|value| {
            if let Ok(email) = Email::parse(&value) {
                Some(email)
            } else {
                issues.push(issue("KINEVO_SETUP_EMAIL", "must be a valid email address"));
                None
            }
        });
        let setup_password =
            optional_file_secret(env, read_file, "KINEVO_SETUP_PASSWORD_FILE", &mut issues);
        match (setup_email.is_some(), setup_password.is_some()) {
            (true, false) => issues.push(issue(
                "KINEVO_SETUP_PASSWORD_FILE",
                "is required when KINEVO_SETUP_EMAIL is set",
            )),
            (false, true) => issues.push(issue(
                "KINEVO_SETUP_EMAIL",
                "is required when KINEVO_SETUP_PASSWORD_FILE is set",
            )),
            _ => {}
        }
        let setup_template = env("KINEVO_SETUP_TEMPLATE").unwrap_or_else(|| "personal".to_owned());

        let smtp_url = env("KINEVO_SMTP_URL").map(Secret::new);
        let smtp_password =
            optional_file_secret(env, read_file, "KINEVO_SMTP_PASSWORD_FILE", &mut issues);
        if smtp_password.is_some() && smtp_url.is_none() {
            issues.push(issue(
                "KINEVO_SMTP_URL",
                "is required when KINEVO_SMTP_PASSWORD_FILE is set",
            ));
        }
        if let Some(url) = smtp_url.as_ref()
            && kinevo_mod_identity::infra::mail::SmtpConfig::parse_with_password_configured(
                url.expose(),
                smtp_password.is_some(),
            )
            .is_err()
        {
            issues.push(issue(
                "KINEVO_SMTP_URL",
                "must be a valid SMTP URL with a sender and separate credentials",
            ));
        }
        let smtp = smtp_url.map(|url| SmtpConfig {
            url,
            password: smtp_password,
        });

        if !issues.is_empty() {
            return Err(issues);
        }

        let Some(database_url) = database_url else {
            return Err(vec![issue("KINEVO_DATABASE_URL", "is required")]);
        };
        let Some(system_database_url) = system_database_url else {
            return Err(vec![issue("KINEVO_SYSTEM_DATABASE_URL", "is required")]);
        };
        let Some(secret_key) = secret_key else {
            return Err(vec![issue("KINEVO_SECRET_KEY", "must be valid")]);
        };
        let Some(public_url) = public_url else {
            return Err(vec![issue("KINEVO_PUBLIC_URL", "is required")]);
        };

        Ok(Self {
            database_url,
            migrator_database_url,
            system_database_url,
            secret_key,
            public_url,
            listen,
            trusted_proxies,
            auto_migrate,
            pool_max,
            db_connect_retries,
            db_connect_backoff: Duration::from_millis(100),
            jobs_tick: Duration::from_secs(jobs_tick_seconds),
            logging: LoggingConfig {
                level: match log_level.as_str() {
                    "trace" => LogLevel::Trace,
                    "debug" => LogLevel::Debug,
                    "warn" => LogLevel::Warn,
                    "error" => LogLevel::Error,
                    _ => LogLevel::Info,
                },
                format: if log_format == "pretty" {
                    LogFormat::Pretty
                } else {
                    LogFormat::Json
                },
            },
            metrics: MetricsConfig {
                enabled: metrics_enabled,
                allow: metrics_allow,
            },
            android_origin,
            setup: SetupBootstrap {
                email: setup_email,
                password: setup_password,
                template: setup_template,
                session_ttl: Duration::from_secs(setup_ttl_seconds),
            },
            smtp,
            max_body_bytes,
        })
    }
}

fn secret_value(
    env: &dyn Fn(&str) -> Option<String>,
    read_file: &dyn Fn(&Path) -> io::Result<String>,
    variable: &str,
    file_variable: &str,
    required: bool,
    issues: &mut Vec<ConfigIssue>,
) -> Option<Secret<String>> {
    let value = env(variable);
    let file = env(file_variable);
    match (value, file) {
        (Some(_), Some(_)) => {
            issues.push(issue(
                &format!("{variable} / {file_variable}"),
                "set only one of the value or file variants",
            ));
            None
        }
        (Some(value), None) if !value.is_empty() => Some(Secret::new(value)),
        (Some(_), None) => {
            issues.push(issue(variable, "must not be empty"));
            None
        }
        (None, Some(path)) if !path.is_empty() => {
            if let Ok(contents) = read_file(Path::new(&path)) {
                let contents = contents.trim_end_matches(['\r', '\n']).to_owned();
                if contents.is_empty() {
                    issues.push(issue(variable, "file must contain a value"));
                    None
                } else {
                    Some(Secret::new(contents))
                }
            } else {
                issues.push(issue(variable, "file could not be read"));
                None
            }
        }
        (None, Some(_)) => {
            issues.push(issue(file_variable, "must name a readable file"));
            None
        }
        (None, None) if required => {
            issues.push(issue(variable, "is required"));
            None
        }
        (None, None) => None,
    }
}

fn optional_file_secret(
    env: &dyn Fn(&str) -> Option<String>,
    read_file: &dyn Fn(&Path) -> io::Result<String>,
    file_variable: &str,
    issues: &mut Vec<ConfigIssue>,
) -> Option<Secret<String>> {
    match env(file_variable) {
        Some(path) if !path.is_empty() => {
            if let Ok(contents) = read_file(Path::new(&path)) {
                let contents = contents.trim_end_matches(['\r', '\n']).to_owned();
                if contents.is_empty() {
                    issues.push(issue(file_variable, "file must contain a value"));
                    None
                } else {
                    Some(Secret::new(contents))
                }
            } else {
                issues.push(issue(file_variable, "file could not be read"));
                None
            }
        }
        Some(_) => {
            issues.push(issue(file_variable, "must name a readable file"));
            None
        }
        None => None,
    }
}

fn parsed_or_default<T: FromStr + Copy>(
    env: &dyn Fn(&str) -> Option<String>,
    variable: &str,
    default: T,
    rule: &'static str,
    issues: &mut Vec<ConfigIssue>,
) -> T {
    match env(variable) {
        Some(value) => {
            if let Ok(parsed) = value.parse() {
                parsed
            } else {
                issues.push(issue(variable, rule));
                default
            }
        }
        None => default,
    }
}

fn positive_or_default<T>(
    env: &dyn Fn(&str) -> Option<String>,
    variable: &str,
    default: T,
    rule: &'static str,
    issues: &mut Vec<ConfigIssue>,
) -> T
where
    T: FromStr + PartialEq + Default + Copy,
{
    match env(variable) {
        Some(value) => match value.parse::<T>() {
            Ok(parsed) if parsed != T::default() => parsed,
            _ => {
                issues.push(issue(variable, rule));
                default
            }
        },
        None => default,
    }
}

fn retries_or_default(env: &dyn Fn(&str) -> Option<String>, issues: &mut Vec<ConfigIssue>) -> u32 {
    match env("KINEVO_DB_CONNECT_RETRIES") {
        Some(value) => match value.parse::<u32>() {
            Ok(retries) if retries <= 30 => retries,
            _ => {
                issues.push(issue(
                    "KINEVO_DB_CONNECT_RETRIES",
                    "must be an integer from 0 through 30",
                ));
                10
            }
        },
        None => 10,
    }
}

fn bool_or_default(
    env: &dyn Fn(&str) -> Option<String>,
    variable: &str,
    default: bool,
    issues: &mut Vec<ConfigIssue>,
) -> bool {
    match env(variable).as_deref() {
        Some("true") => true,
        Some("false") => false,
        Some(_) => {
            issues.push(issue(variable, "must be `true` or `false`"));
            default
        }
        None => default,
    }
}

fn enum_or_default(
    env: &dyn Fn(&str) -> Option<String>,
    variable: &str,
    default: &str,
    allowed: &[&str],
    issues: &mut Vec<ConfigIssue>,
) -> String {
    match env(variable) {
        Some(value) if allowed.contains(&value.as_str()) => value,
        Some(_) => {
            issues.push(issue(variable, "must be one of the supported values"));
            default.to_owned()
        }
        None => default.to_owned(),
    }
}

fn cidr_list(value: Option<String>, variable: &str, issues: &mut Vec<ConfigIssue>) -> Vec<Cidr> {
    let mut networks = Vec::new();
    for value in value.unwrap_or_default().split(',').map(str::trim) {
        if value.is_empty() {
            continue;
        }
        match value.parse::<Cidr>() {
            Ok(network) => networks.push(network),
            Err(()) => issues.push(issue(
                variable,
                "must be a comma-separated list of CIDR ranges",
            )),
        }
    }
    networks
}

fn issue(variable: &str, rule: &'static str) -> ConfigIssue {
    ConfigIssue {
        variable: variable.to_owned(),
        rule,
    }
}
