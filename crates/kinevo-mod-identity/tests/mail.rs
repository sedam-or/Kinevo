#![allow(
    clippy::expect_used,
    reason = "SMTP acceptance tests use expect for synthetic setup data"
)]

use std::{
    io::{BufRead, BufReader, Write},
    net::{SocketAddr, TcpListener},
    sync::{Arc, Mutex},
    thread::JoinHandle,
    time::Duration,
};

use kinevo_http::i18n::Catalog;
use kinevo_kernel::{
    secret::Secret,
    time::Instant,
    validate::{net::Email, tz::Locale},
};
use kinevo_mod_identity::infra::mail::{SmtpConfig, TlsMode, render, smtp_mailer};
use kinevo_ports::platform::{MailMessage, MailTemplate};

mod tests {
    use super::{Catalog, Locale, MailTemplate, Secret, SmtpConfig, TlsMode, render};

    #[test]
    fn d_27_url_parsing() {
        let smtps = SmtpConfig::parse(
            "smtps://u@h:465/?from=a@b.test",
            Some(Secret::new("synthetic-password".to_owned())),
        )
        .expect("smtps URL should parse");
        assert_eq!(smtps.host, "h");
        assert_eq!(smtps.port, 465);
        assert_eq!(smtps.tls, TlsMode::Implicit);
        assert_eq!(smtps.user.as_deref(), Some("u"));

        let smtp = SmtpConfig::parse("smtp://h:587/?from=a@b.test", None)
            .expect("smtp URL should parse with required STARTTLS");
        assert_eq!(smtp.host, "h");
        assert_eq!(smtp.port, 587);
        assert_eq!(smtp.tls, TlsMode::StartTls);

        let missing_from = SmtpConfig::parse("smtp://h/", None)
            .expect_err("missing from address should fail configuration");
        assert!(missing_from.to_string().contains("KINEVO_SMTP_URL"));

        assert!(SmtpConfig::parse("foo://h", None).is_err());
    }

    #[test]
    fn id_fr_13_body_has_only_the_link() {
        let catalog = Catalog::load();
        let link = "https://identity.example.test/reset?token=synthetic-token";
        let vars = [("reset_url", link.to_owned()), ("minutes", "60".to_owned())];

        let (english_subject, english_body) =
            render(MailTemplate::PasswordReset, Locale::En, &vars, &catalog)
                .expect("English reset template should render");
        assert_eq!(english_subject, "Reset your Nivaras password");
        assert!(english_body.contains(link));
        assert!(english_body.contains("60 minutes"));
        assert!(!english_body.contains("person@example.test"));
        assert!(!english_body.contains("Synthetic Account"));

        let (indonesian_subject, indonesian_body) =
            render(MailTemplate::PasswordReset, Locale::Id, &vars, &catalog)
                .expect("Indonesian reset template should render");
        assert_eq!(indonesian_subject, "Atur ulang kata sandi Nivaras");
        assert!(indonesian_body.contains(link));
        assert!(indonesian_body.contains("60 menit"));
        assert!(!indonesian_body.contains("person@example.test"));
        assert!(!indonesian_body.contains("Synthetic Account"));
    }

    #[test]
    fn catalog_has_email_keys() {
        let english: serde_json::Value =
            serde_json::from_str(include_str!("../../../web/src/i18n/en.json"))
                .expect("English catalogue should be valid JSON");
        let indonesian: serde_json::Value =
            serde_json::from_str(include_str!("../../../web/src/i18n/id.json"))
                .expect("Indonesian catalogue should be valid JSON");
        for key in ["email.password_reset.subject", "email.password_reset.body"] {
            assert!(
                english.get(key).is_some(),
                "English catalogue missing {key}"
            );
            assert!(
                indonesian.get(key).is_some(),
                "Indonesian catalogue missing {key}"
            );
        }
    }
}

#[tokio::test]
async fn no_tls_downgrade() {
    let server = FakeSmtpServer::start(false);
    let config = SmtpConfig::parse(
        &format!("smtp://{}/?from=sender@example.test", server.address),
        None,
    )
    .expect("synthetic SMTP configuration should parse");
    let mailer = smtp_mailer(config, None, Arc::new(Catalog::load()));
    let result = mailer.send(reset_message()).await;
    assert!(result.is_err(), "STARTTLS-less relay must fail");
    let transcript = server.finish();
    assert!(
        !transcript.contains("DATA\r\n"),
        "message must not be delivered"
    );
}

fn reset_message() -> MailMessage {
    MailMessage {
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
    }
}

struct FakeSmtpServer {
    address: SocketAddr,
    transcript: Arc<Mutex<String>>,
    worker: JoinHandle<()>,
}

impl FakeSmtpServer {
    fn start(starttls: bool) -> Self {
        let listener = TcpListener::bind("127.0.0.1:0").expect("SMTP test listener should bind");
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
            while reader.read_line(&mut line).is_ok() && !line.is_empty() {
                if let Ok(mut transcript) = captured.lock() {
                    transcript.push_str(&line);
                }
                if line.starts_with("EHLO ") {
                    let reply = if starttls {
                        "250-test.example.test\r\n250-STARTTLS\r\n250 SIZE 100000\r\n"
                    } else {
                        "250-test.example.test\r\n250 SIZE 100000\r\n"
                    };
                    let _ = stream.write_all(reply.as_bytes());
                } else if line.starts_with("QUIT") {
                    let _ = stream.write_all(b"221 bye\r\n");
                    break;
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
                } else {
                    let _ = stream.write_all(b"250 ok\r\n");
                }
                line.clear();
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
