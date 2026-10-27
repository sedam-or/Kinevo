//! Shared, injected HTTP application state.

use crate::{gate::Gate, i18n::Catalog};
use async_trait::async_trait;
use kinevo_kernel::{
    id::{IdGen, TokenGen},
    page::CursorCodec,
    time::Clock,
    validate::net::HttpsUrl,
};
use kinevo_ports::platform::InstanceState;
use std::{fmt, sync::Arc};

/// Database readiness probe used by `/readyz`.
#[async_trait]
pub trait ReadyCheck: Send + Sync {
    /// Succeeds only when the database, migrations and isolation checks are ready.
    async fn check(&self) -> Result<(), ReadyFailure>;
}

/// Opaque readiness failure; its details are never returned to HTTP clients.
#[derive(Clone, Copy, Debug, Eq, PartialEq)]
pub struct ReadyFailure;

impl fmt::Display for ReadyFailure {
    fn fmt(&self, formatter: &mut fmt::Formatter<'_>) -> fmt::Result {
        formatter.write_str("readiness check failed")
    }
}

impl std::error::Error for ReadyFailure {}

/// Dependencies shared by HTTP routes and middleware.
#[derive(Clone)]
pub struct HttpState {
    /// Authentication, module, and permission gate installed by `RouteTable`.
    pub gate: Arc<Gate>,
    /// Injected application clock.
    pub clock: Arc<dyn Clock>,
    /// Injected UUID source.
    pub ids: Arc<dyn IdGen>,
    /// Injected cryptographic token source.
    pub tokens: Arc<dyn TokenGen>,
    /// Embedded response catalogue.
    pub i18n: Arc<Catalog>,
    /// Cached instance settings and default locale.
    pub instance: Arc<dyn InstanceState>,
    /// Database, migration and isolation readiness probe.
    pub ready: Arc<dyn ReadyCheck>,
    /// Public URL; the HTTPS scheme controls HSTS.
    pub public_url: Option<HttpsUrl>,
    /// HMAC cursor codec for pagination extractors.
    pub cursor: Arc<CursorCodec>,
    /// Operator-configured global request body limit.
    pub max_body_bytes: usize,
}

impl HttpState {
    /// Creates state with all infrastructure dependencies explicitly injected.
    #[allow(clippy::too_many_arguments)]
    #[must_use]
    pub fn new(
        clock: Arc<dyn Clock>,
        ids: Arc<dyn IdGen>,
        tokens: Arc<dyn TokenGen>,
        i18n: Arc<Catalog>,
        instance: Arc<dyn InstanceState>,
        ready: Arc<dyn ReadyCheck>,
        public_url: Option<HttpsUrl>,
        cursor: Arc<CursorCodec>,
        max_body_bytes: usize,
        gate: Arc<Gate>,
    ) -> Self {
        Self {
            gate,
            clock,
            ids,
            tokens,
            i18n,
            instance,
            ready,
            public_url,
            cursor,
            max_body_bytes,
        }
    }
}
