//! Optional SMTP adapter for identity email.

use std::{
    error::Error,
    fmt,
    str::FromStr,
    sync::Arc,
    time::{Duration, SystemTime},
};

use async_trait::async_trait;
use kinevo_http::i18n::Catalog;
use kinevo_kernel::{
    errors::{AppError, ErrorCode},
    secret::Secret,
    time::Instant,
    validate::tz::Locale,
};
use kinevo_ports::platform::{Delivery, MailMessage, MailTemplate, Mailer};
use lettre::{
    AsyncSmtpTransport, AsyncTransport, Message, Tokio1Executor,
    message::{
        Mailbox,
        header::{ContentType, HeaderName, HeaderValue},
    },
    transport::smtp::authentication::Credentials,
};
use tokio::time::timeout;
use url::Url;

const CONNECTION_TIMEOUT: Duration = Duration::from_secs(10);
const SEND_TIMEOUT: Duration = Duration::from_secs(30);

type SmtpTransport = AsyncSmtpTransport<Tokio1Executor>;

/// SMTP transport encryption mode.
#[derive(Clone, Copy, Debug, Eq, PartialEq)]
pub enum TlsMode {
    /// Upgrade the SMTP connection with STARTTLS and fail if it is unavailable.
    StartTls,
    /// Begin SMTP over implicit TLS.
    Implicit,
}

/// Parsed SMTP relay settings. Its debug output intentionally omits addresses and host details.
pub struct SmtpConfig {
    pub host: String,
    pub port: u16,
    pub tls: TlsMode,
    pub user: Option<String>,
    pub from: Mailbox,
    pub reply_to: Option<Mailbox>,
}

impl fmt::Debug for SmtpConfig {
    fn fmt(&self, formatter: &mut fmt::Formatter<'_>) -> fmt::Result {
        formatter
            .debug_struct("SmtpConfig")
            .field(
                "scheme",
                &match self.tls {
                    TlsMode::StartTls => "smtp",
                    TlsMode::Implicit => "smtps",
                },
            )
            .finish_non_exhaustive()
    }
}

/// Safe configuration error that never includes the supplied URL or password.
#[derive(Clone, Copy, Debug, Eq, PartialEq)]
pub struct ConfigError(&'static str);

impl fmt::Display for ConfigError {
    fn fmt(&self, formatter: &mut fmt::Formatter<'_>) -> fmt::Result {
        formatter.write_str(self.0)
    }
}

impl Error for ConfigError {}

impl SmtpConfig {
    /// Parses an SMTP URL and validates the required sender and authentication settings.
    #[allow(
        clippy::needless_pass_by_value,
        reason = "T-030 defines the resolved secret as an owned boundary input"
    )]
    pub fn parse(url: &str, password: Option<Secret<String>>) -> Result<Self, ConfigError> {
        Self::parse_with_password_configured(url, password.is_some())
    }

    /// Parses SMTP metadata while allowing the caller to keep its redacting password value.
    pub fn parse_with_password_configured(
        url: &str,
        password_configured: bool,
    ) -> Result<Self, ConfigError> {
        let parsed = Url::parse(url).map_err(|_| {
            ConfigError("KINEVO_SMTP_URL must be a valid smtp or smtps URL with a from address")
        })?;
        let tls = match parsed.scheme() {
            "smtp" => TlsMode::StartTls,
            "smtps" => TlsMode::Implicit,
            _ => {
                return Err(ConfigError(
                    "KINEVO_SMTP_URL must use smtp with required STARTTLS or smtps",
                ));
            }
        };
        let host = parsed
            .host_str()
            .filter(|host| !host.is_empty())
            .ok_or(ConfigError("KINEVO_SMTP_URL must include a host"))?
            .to_owned();
        if parsed.password().is_some() {
            return Err(ConfigError(
                "KINEVO_SMTP_URL must not contain a password; configure it separately",
            ));
        }
        let user = (!parsed.username().is_empty()).then(|| parsed.username().to_owned());
        if user.is_some() && !password_configured {
            return Err(ConfigError(
                "KINEVO_SMTP_URL has a user but no SMTP password is configured",
            ));
        }
        let mut from_values = parsed
            .query_pairs()
            .filter(|(key, _)| key == "from")
            .map(|(_, value)| value.into_owned());
        let from_text = from_values
            .next()
            .ok_or(ConfigError("KINEVO_SMTP_URL requires a from address"))?;
        if from_values.next().is_some() || contains_header_control(&from_text) {
            return Err(ConfigError(
                "KINEVO_SMTP_URL from address must be a single valid address",
            ));
        }
        let from = Mailbox::from_str(&from_text)
            .map_err(|_| ConfigError("KINEVO_SMTP_URL from address is invalid"))?;
        let mut reply_to_values = parsed
            .query_pairs()
            .filter(|(key, _)| key == "reply_to")
            .map(|(_, value)| value.into_owned());
        let reply_to = reply_to_values
            .next()
            .map(|value| {
                if contains_header_control(&value) || reply_to_values.next().is_some() {
                    return Err(ConfigError("KINEVO_SMTP_URL reply_to address is invalid"));
                }
                Mailbox::from_str(&value)
                    .map_err(|_| ConfigError("KINEVO_SMTP_URL reply_to address is invalid"))
            })
            .transpose()?;
        let default_port = match tls {
            TlsMode::StartTls => 587,
            TlsMode::Implicit => 465,
        };
        Ok(Self {
            host,
            port: parsed.port().unwrap_or(default_port),
            tls,
            user,
            from,
            reply_to,
        })
    }
}

/// Builds the identity mailer with the configured, certificate-verified TLS mode.
#[must_use]
pub fn smtp_mailer(
    config: SmtpConfig,
    password: Option<Secret<String>>,
    catalog: Arc<Catalog>,
) -> Arc<dyn Mailer> {
    let transport = build_transport(&config, password);
    Arc::new(SmtpMailer {
        config,
        transport,
        catalog,
    })
}

struct SmtpMailer {
    config: SmtpConfig,
    transport: Result<SmtpTransport, ConfigError>,
    catalog: Arc<Catalog>,
}

#[async_trait]
impl Mailer for SmtpMailer {
    async fn send(&self, message: MailMessage) -> Result<Delivery, AppError> {
        let Ok(transport) = &self.transport else {
            return mail_failed("smtp_config_invalid");
        };
        let Ok((subject, body)) = render(
            message.template,
            message.locale,
            &message.vars,
            &self.catalog,
        ) else {
            return mail_failed("mail_template_invalid");
        };
        let Ok(email) = build_message(&self.config, &message, subject, body) else {
            return mail_failed("mail_message_invalid");
        };
        match timeout(SEND_TIMEOUT, transport.send(email)).await {
            Ok(Ok(_)) => {
                tracing::info!(event = "mail_sent");
                Ok(Delivery::Sent)
            }
            _ => mail_failed("smtp_send_failed"),
        }
    }
}

fn build_transport(
    config: &SmtpConfig,
    password: Option<Secret<String>>,
) -> Result<SmtpTransport, ConfigError> {
    let builder = match config.tls {
        TlsMode::StartTls => AsyncSmtpTransport::<Tokio1Executor>::starttls_relay(&config.host),
        TlsMode::Implicit => AsyncSmtpTransport::<Tokio1Executor>::relay(&config.host),
    }
    .map_err(|_| ConfigError("KINEVO_SMTP_URL host is invalid"))?;
    let mut builder = builder.port(config.port).timeout(Some(CONNECTION_TIMEOUT));
    if let Some(user) = config.user.as_deref() {
        let password = password.ok_or(ConfigError("SMTP user has no configured password"))?;
        builder = builder.credentials(Credentials::new(user.to_owned(), password.expose().clone()));
    }
    Ok(builder.build())
}

fn build_message(
    config: &SmtpConfig,
    message: &MailMessage,
    subject: String,
    body: String,
) -> Result<Message, MailBuildError> {
    validate_header_value(message.to.as_str())?;
    validate_header_value(&subject)?;
    validate_header_value(&message.message_id)?;
    validate_header_value(&config.host)?;
    let sender = config.from.to_string();
    validate_header_value(&sender)?;
    if let Some(reply_to) = &config.reply_to {
        validate_header_value(&reply_to.to_string())?;
    }
    let recipient = Mailbox::from_str(message.to.as_str())
        .map_err(|_| MailBuildError("recipient address is invalid"))?;
    let auto_submitted_name = HeaderName::new_from_ascii_str("Auto-Submitted");
    let auto_submitted = HeaderValue::new(auto_submitted_name, "auto-generated".to_owned());
    let mut builder = Message::builder()
        .from(config.from.clone())
        .to(recipient)
        .subject(subject)
        .date(system_time(message.sent_at)?)
        .message_id(Some(format!("<{}@{}>", message.message_id, config.host)))
        .raw_header(auto_submitted)
        .header(ContentType::TEXT_PLAIN);
    if let Some(reply_to) = &config.reply_to {
        builder = builder.reply_to(reply_to.clone());
    }
    builder
        .body(body)
        .map_err(|_| MailBuildError("message could not be built"))
}

fn system_time(instant: Instant) -> Result<SystemTime, MailBuildError> {
    let milliseconds = instant.unix_millis();
    if milliseconds >= 0 {
        SystemTime::UNIX_EPOCH
            .checked_add(Duration::from_millis(milliseconds.unsigned_abs()))
            .ok_or(MailBuildError("message timestamp is out of range"))
    } else {
        SystemTime::UNIX_EPOCH
            .checked_sub(Duration::from_millis(milliseconds.unsigned_abs()))
            .ok_or(MailBuildError("message timestamp is out of range"))
    }
}

fn mail_failed(error_code: &'static str) -> Result<Delivery, AppError> {
    tracing::warn!(event = "mail_failed", error_code);
    Err(AppError::new(ErrorCode::ServiceUnavailable))
}

#[cfg(test)]
fn plaintext_transport_for_test(config: &SmtpConfig) -> SmtpTransport {
    AsyncSmtpTransport::<Tokio1Executor>::builder_dangerous(config.host.clone())
        .port(config.port)
        .timeout(Some(CONNECTION_TIMEOUT))
        .build()
}

fn contains_header_control(value: &str) -> bool {
    value
        .chars()
        .any(|character| character == '\r' || character == '\n')
}

fn validate_header_value(value: &str) -> Result<(), MailBuildError> {
    if contains_header_control(value) {
        Err(MailBuildError("mail header contains CR or LF"))
    } else {
        Ok(())
    }
}

/// Renders the password-reset subject and plain-text body from the selected catalogue.
pub fn render(
    template: MailTemplate,
    locale: Locale,
    vars: &[(&'static str, String)],
    catalog: &Catalog,
) -> Result<(String, String), RenderError> {
    match template {
        MailTemplate::PasswordReset => {
            let subject = catalog
                .text("email.password_reset.subject", locale)
                .ok_or(RenderError("password-reset subject is missing"))?;
            let body = catalog
                .text("email.password_reset.body", locale)
                .ok_or(RenderError("password-reset body is missing"))?;
            let link = variable(vars, "reset_url")?;
            let minutes = variable(vars, "minutes")?;
            Ok((
                subject.to_owned(),
                body.replace("{link}", link).replace("{minutes}", minutes),
            ))
        }
    }
}

fn variable<'a>(vars: &'a [(&str, String)], name: &str) -> Result<&'a str, RenderError> {
    vars.iter()
        .find_map(|(key, value)| (*key == name).then_some(value.as_str()))
        .ok_or(RenderError("password-reset template value is missing"))
}

/// Safe rendering error that never includes template data.
#[derive(Clone, Copy, Debug, Eq, PartialEq)]
pub struct RenderError(&'static str);

impl fmt::Display for RenderError {
    fn fmt(&self, formatter: &mut fmt::Formatter<'_>) -> fmt::Result {
        formatter.write_str(self.0)
    }
}

impl Error for RenderError {}

#[derive(Clone, Copy, Debug, Eq, PartialEq)]
struct MailBuildError(&'static str);

impl fmt::Display for MailBuildError {
    fn fmt(&self, formatter: &mut fmt::Formatter<'_>) -> fmt::Result {
        formatter.write_str(self.0)
    }
}

impl Error for MailBuildError {}

#[cfg(test)]
mod tests {
    #![allow(
        clippy::expect_used,
        clippy::unwrap_used,
        clippy::indexing_slicing,
        clippy::panic,
        reason = "synthetic SMTP acceptance tests use explicit setup assertions"
    )]

    use std::{
        io::{self, BufRead, BufReader, Write},
        net::{SocketAddr, TcpListener},
        sync::{Arc, Mutex},
        thread::JoinHandle,
        time::Duration,
    };

    use kinevo_kernel::{
        secret::Secret,
        time::Instant,
        validate::{net::Email, tz::Locale},
    };
    use kinevo_ports::platform::{Delivery, MailMessage, MailTemplate};

    use super::{
        Catalog, Mailer, SmtpConfig, SmtpMailer, build_transport, plaintext_transport_for_test,
        validate_header_value,
    };

    #[test]
    fn header_injection_rejected() {
        assert!(validate_header_value("person@example.test\r\nBcc: victim@example.test").is_err());
        assert!(validate_header_value("Reset password\nBcc: victim@example.test").is_err());
        assert!(validate_header_value("Reset password").is_ok());
    }

    #[test]
    fn tls_transport_builder_keeps_verified_modes() {
        for url in [
            "smtp://relay.example.test:587/?from=sender@example.test",
            "smtps://relay.example.test:465/?from=sender@example.test",
        ] {
            let config = SmtpConfig::parse(url, None).expect("synthetic SMTP URL should parse");
            assert!(build_transport(&config, None).is_ok());
        }
    }

    #[tokio::test(flavor = "current_thread")]
    async fn message_headers() {
        let server = PlainSmtpServer::start();
        let config = SmtpConfig::parse(
            &format!("smtp://{}/?from=sender@example.test", server.address),
            None,
        )
        .expect("synthetic SMTP configuration should parse");
        let mailer = SmtpMailer {
            transport: Ok(plaintext_transport_for_test(&config)),
            config,
            catalog: Arc::new(Catalog::load()),
        };
        let message = MailMessage {
            to: Email::parse("person@example.test").expect("synthetic email should parse"),
            template: MailTemplate::PasswordReset,
            locale: Locale::En,
            vars: vec![
                (
                    "reset_url",
                    "https://identity.example.test/reset?token=synthetic-token".to_owned(),
                ),
                ("minutes", "60".to_owned()),
            ],
            sent_at: Instant::parse_rfc3339("2026-01-02T03:04:05Z")
                .expect("synthetic timestamp should parse"),
            message_id: "00000000-0000-7000-8000-000000000001".to_owned(),
        };
        assert_eq!(
            mailer.send(message).await.expect("message should send"),
            Delivery::Sent
        );
        drop(mailer);
        let transcript = server.finish();
        assert!(transcript.contains("From: sender@example.test\r\n"));
        assert!(transcript.contains("To: person@example.test\r\n"));
        assert!(transcript.contains("Subject: Reset your Nivaras password\r\n"));
        assert!(transcript.contains("Date: "));
        assert!(
            transcript.contains("Message-ID: <00000000-0000-7000-8000-000000000001@127.0.0.1>\r\n")
        );
        assert!(transcript.contains("Auto-Submitted: auto-generated\r\n"));
        assert!(transcript.contains("Content-Type: text/plain"));
    }

    #[tokio::test(flavor = "current_thread")]
    async fn sec_ac_45_mail_logs_are_clean() {
        let output = Arc::new(Mutex::new(Vec::new()));
        let subscriber = tracing_subscriber::fmt()
            .with_ansi(false)
            .with_level(false)
            .with_target(false)
            .without_time()
            .with_writer(LogBuffer(Arc::clone(&output)))
            .finish();
        let dispatch = tracing::Dispatch::new(subscriber);
        let _guard = tracing::dispatcher::set_default(&dispatch);

        let server = PlainSmtpServer::start();
        let config = SmtpConfig::parse(
            &format!("smtp://{}/?from=sender@example.test", server.address),
            None,
        )
        .expect("synthetic SMTP configuration should parse");
        let mailer = SmtpMailer {
            transport: Ok(plaintext_transport_for_test(&config)),
            config,
            catalog: Arc::new(Catalog::load()),
        };
        let success = mailer
            .send(log_test_message(
                "https://identity.example.test/reset?token=mail-link-canary",
            ))
            .await;
        assert_eq!(success.expect("synthetic mail should send"), Delivery::Sent);
        drop(mailer);
        let _ = server.finish();

        let listener = std::net::TcpListener::bind("127.0.0.1:0")
            .expect("closed SMTP test port should be allocated");
        let address = listener
            .local_addr()
            .expect("closed SMTP test listener should have an address");
        drop(listener);
        let password = "smtp-password-canary".to_owned();
        let config = SmtpConfig::parse(
            &format!("smtp://smtp-user-canary@{address}/?from=sender@example.test"),
            Some(Secret::new(password.clone())),
        )
        .expect("synthetic authenticated SMTP configuration should parse");
        let mailer = super::smtp_mailer(
            config,
            Some(Secret::new(password)),
            Arc::new(Catalog::load()),
        );
        let failure = mailer
            .send(log_test_message(
                "https://identity.example.test/reset?token=mail-link-canary",
            ))
            .await;
        assert!(failure.is_err(), "closed relay should fail");

        let logs = String::from_utf8(
            output
                .lock()
                .expect("log buffer should be readable")
                .clone(),
        )
        .expect("captured logs should be UTF-8");
        assert!(logs.contains("mail_sent"));
        assert!(logs.contains("mail_failed"));
        assert!(logs.contains("smtp_send_failed"));
        for canary in [
            "person@example.test",
            "mail-link-canary",
            "smtp-user-canary",
            "smtp-password-canary",
        ] {
            assert!(!logs.contains(canary), "logs included {canary}");
        }
    }

    fn log_test_message(link: &str) -> MailMessage {
        MailMessage {
            to: Email::parse("person@example.test").expect("synthetic email should parse"),
            template: MailTemplate::PasswordReset,
            locale: Locale::En,
            vars: vec![("reset_url", link.to_owned()), ("minutes", "60".to_owned())],
            sent_at: Instant::parse_rfc3339("2026-01-02T03:04:05Z")
                .expect("synthetic timestamp should parse"),
            message_id: "00000000-0000-7000-8000-000000000002".to_owned(),
        }
    }

    #[derive(Clone)]
    struct LogBuffer(Arc<Mutex<Vec<u8>>>);

    struct LogWriter(Arc<Mutex<Vec<u8>>>);

    impl<'a> tracing_subscriber::fmt::MakeWriter<'a> for LogBuffer {
        type Writer = LogWriter;

        fn make_writer(&'a self) -> Self::Writer {
            LogWriter(Arc::clone(&self.0))
        }
    }

    impl Write for LogWriter {
        fn write(&mut self, bytes: &[u8]) -> io::Result<usize> {
            self.0
                .lock()
                .map_err(|_| io::Error::other("log buffer lock poisoned"))?
                .extend_from_slice(bytes);
            Ok(bytes.len())
        }

        fn flush(&mut self) -> io::Result<()> {
            Ok(())
        }
    }

    struct PlainSmtpServer {
        address: SocketAddr,
        transcript: Arc<Mutex<String>>,
        worker: JoinHandle<()>,
    }

    impl PlainSmtpServer {
        fn start() -> Self {
            let listener =
                TcpListener::bind("127.0.0.1:0").expect("SMTP test listener should bind");
            let address = listener
                .local_addr()
                .expect("SMTP test listener should have an address");
            let transcript = Arc::new(Mutex::new(String::new()));
            let captured = Arc::clone(&transcript);
            let worker = std::thread::spawn(move || {
                let Ok((mut stream, _)) = listener.accept() else {
                    return;
                };
                let _ = stream.set_read_timeout(Some(Duration::from_secs(5)));
                let Ok(reader_stream) = stream.try_clone() else {
                    return;
                };
                let mut reader = BufReader::new(reader_stream);
                let _ = stream.write_all(b"220 test.example.test ESMTP\r\n");
                let mut line = String::new();
                loop {
                    line.clear();
                    match reader.read_line(&mut line) {
                        Ok(0) | Err(_) => break,
                        Ok(_) => {}
                    }
                    if let Ok(mut transcript) = captured.lock() {
                        transcript.push_str(&line);
                    }
                    if line.starts_with("EHLO ") {
                        let _ = stream.write_all(b"250-test.example.test\r\n250 SIZE 100000\r\n");
                    } else if line.starts_with("MAIL FROM:") || line.starts_with("RCPT TO:") {
                        let _ = stream.write_all(b"250 ok\r\n");
                    } else if line.starts_with("DATA") {
                        let _ = stream.write_all(b"354 continue\r\n");
                        loop {
                            line.clear();
                            if reader.read_line(&mut line).is_err() || line.is_empty() {
                                break;
                            }
                            if let Ok(mut transcript) = captured.lock() {
                                transcript.push_str(&line);
                            }
                            if line == ".\r\n" {
                                break;
                            }
                        }
                        let _ = stream.write_all(b"250 queued\r\n");
                    } else if line.starts_with("QUIT") {
                        let _ = stream.write_all(b"221 bye\r\n");
                        break;
                    } else {
                        let _ = stream.write_all(b"250 ok\r\n");
                    }
                }
            });
            Self {
                address,
                transcript,
                worker,
            }
        }

        fn finish(self) -> String {
            self.worker.join().expect("SMTP test server should stop");
            self.transcript
                .lock()
                .expect("SMTP transcript should be available")
                .clone()
        }
    }
}
