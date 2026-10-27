//! Policy-backed fixed-window request limiting for the HTTP route table.

use axum::{
    extract::{ConnectInfo, MatchedPath, Request, State},
    http::{HeaderName, HeaderValue, Method},
    middleware::Next,
    response::{IntoResponse, Response},
};
use ipnet::IpNet;
use kinevo_kernel::{
    crypto::hash_token,
    errors::{AppError, DetailValue, ErrorCode, FieldCode},
    id::AccountId,
    policy::{PolicyKey, registry::http as http_policy},
    time::{Clock, Instant},
};
use kinevo_ports::platform::{PolicyReader, ResolvedPolicies};
use moka::future::Cache;
use std::{
    net::{IpAddr, Ipv6Addr, SocketAddr},
    str::FromStr,
    sync::Arc,
    time::Duration,
};
use tokio::sync::Mutex;

use crate::{
    auth::AuthCtx,
    problem::ApiError,
    route::{Access, RateClass, RouteSpec, route_path},
};

const WINDOW_MILLIS: i64 = 60_000;
const POLICY_REFRESH_MILLIS: i64 = 30_000;
const ENTRY_TTL_SECONDS: u64 = 120;
const MAX_COUNTERS: u64 = 100_000;

/// Trusted reverse proxies that may provide the client address in X-Forwarded-For.
#[derive(Clone, Debug, Default)]
pub struct TrustedProxies(Vec<IpNet>);

impl TrustedProxies {
    /// Parses a comma-separated list of proxy CIDRs. An empty list trusts no proxy.
    pub fn parse(csv: &str) -> Result<Self, FieldCode> {
        if csv.trim().is_empty() {
            return Ok(Self::default());
        }

        csv.split(',')
            .map(str::trim)
            .map(|range| {
                if range.is_empty() {
                    return Err(FieldCode::InvalidCharacters);
                }
                IpNet::from_str(range).map_err(|_| FieldCode::InvalidCharacters)
            })
            .collect::<Result<Vec<_>, _>>()
            .map(Self)
    }

    /// Resolves a client address, accepting X-Forwarded-For only from a trusted peer.
    /// Malformed chains fall back to the connecting peer. IPv6 addresses are reduced to /64.
    #[must_use]
    pub fn client_addr(&self, peer: IpAddr, x_forwarded_for: Option<&str>) -> IpAddr {
        let resolved = if self.is_trusted(peer) {
            x_forwarded_for
                .and_then(parse_forwarded_chain)
                .and_then(|forwarded| self.rightmost_untrusted(forwarded, peer))
                .unwrap_or(peer)
        } else {
            peer
        };
        truncate_ipv6(resolved)
    }

    fn is_trusted(&self, address: IpAddr) -> bool {
        self.0.iter().any(|network| network.contains(&address))
    }

    fn rightmost_untrusted(&self, forwarded: Vec<IpAddr>, peer: IpAddr) -> Option<IpAddr> {
        forwarded
            .into_iter()
            .chain(std::iter::once(peer))
            .rev()
            .find(|address| !self.is_trusted(*address))
    }
}

fn parse_forwarded_chain(header: &str) -> Option<Vec<IpAddr>> {
    header
        .split(',')
        .map(|value| value.trim().parse::<IpAddr>().ok())
        .collect()
}

fn truncate_ipv6(address: IpAddr) -> IpAddr {
    match address {
        IpAddr::V4(address) => IpAddr::V4(address),
        IpAddr::V6(address) => {
            let prefix = u128::from(address) & (u128::MAX << 64);
            IpAddr::V6(Ipv6Addr::from(prefix))
        }
    }
}

/// Returns the compiled policy key associated with a route's rate class.
#[must_use]
pub fn policy_key(class: RateClass) -> Option<&'static PolicyKey<i64>> {
    match class {
        RateClass::None => None,
        RateClass::Auth => Some(&http_policy::AUTH_PER_MINUTE),
        RateClass::Api => Some(&http_policy::API_PER_MINUTE),
        RateClass::Ai => Some(&http_policy::AI_PER_MINUTE),
        RateClass::Upload => Some(&http_policy::UPLOAD_PER_MINUTE),
        RateClass::Sync => Some(&http_policy::SYNC_PER_MINUTE),
        RateClass::Export => Some(&http_policy::EXPORT_PER_MINUTE),
    }
}

/// Per-request decision and the values rendered in the `RateLimit` response headers.
#[derive(Clone, Copy, Debug, Eq, PartialEq)]
pub struct Decision {
    /// Whether this request may continue to its handler.
    pub allowed: bool,
    /// Current policy quota for the window.
    pub limit: u32,
    /// Requests left after this request, never below zero.
    pub remaining: u32,
    /// Seconds until the fixed window ends.
    pub reset_seconds: u32,
}

#[derive(Clone, Copy)]
struct WindowCounter {
    started_at_millis: i64,
    count: u32,
}

impl WindowCounter {
    fn new(now: Instant) -> Self {
        Self {
            started_at_millis: now.unix_millis(),
            count: 0,
        }
    }

    fn decide(&mut self, now: Instant, limit: u32) -> Decision {
        let now_millis = now.unix_millis();
        let window_end = self.started_at_millis.saturating_add(WINDOW_MILLIS);
        if now_millis >= window_end {
            self.started_at_millis = now_millis;
            self.count = 0;
        }

        let reset_millis = self
            .started_at_millis
            .saturating_add(WINDOW_MILLIS)
            .saturating_sub(now_millis)
            .max(0);
        let reset_seconds = reset_millis
            .saturating_add(999)
            .checked_div(1_000)
            .unwrap_or(0)
            .clamp(1, i64::from(u32::MAX));
        let allowed = self.count < limit;
        if allowed {
            self.count = self.count.saturating_add(1);
        }
        Decision {
            allowed,
            limit,
            remaining: if allowed {
                limit.saturating_sub(self.count)
            } else {
                0
            },
            reset_seconds: u32::try_from(reset_seconds).unwrap_or(u32::MAX),
        }
    }
}

struct PolicySnapshot {
    fetched_at_millis: i64,
    values: ResolvedPolicies,
}

/// Fixed-window rate limiter backed by the resolved instance policy snapshot.
pub struct RateLimiter {
    policies: Arc<dyn PolicyReader>,
    clock: Arc<dyn Clock>,
    trusted_proxies: TrustedProxies,
    policy_snapshot: Mutex<Option<PolicySnapshot>>,
    counters: Cache<[u8; 16], Arc<Mutex<WindowCounter>>>,
}

impl RateLimiter {
    /// Creates a bounded limiter with an injected policy reader and clock.
    #[must_use]
    pub fn new(
        policies: Arc<dyn PolicyReader>,
        clock: Arc<dyn Clock>,
        trusted_proxies: TrustedProxies,
    ) -> Self {
        Self {
            policies,
            clock,
            trusted_proxies,
            policy_snapshot: Mutex::new(None),
            counters: Cache::builder()
                .max_capacity(MAX_COUNTERS)
                .time_to_live(Duration::from_secs(ENTRY_TTL_SECONDS))
                .build(),
        }
    }

    /// Evaluates one request. `account_id` selects an account key when authenticated.
    pub async fn decide(
        &self,
        class: RateClass,
        client_addr: IpAddr,
        account_id: Option<AccountId>,
    ) -> Result<Option<Decision>, AppError> {
        let Some(key) = policy_key(class) else {
            return Ok(None);
        };
        let now = self.clock.now();
        let policies = self.policy_values(now).await?;
        let raw_limit = policies
            .get(key)
            .ok_or_else(|| AppError::new(ErrorCode::InternalError))?;
        let limit = u32::try_from(raw_limit)
            .ok()
            .filter(|value| *value > 0)
            .ok_or_else(|| AppError::new(ErrorCode::InternalError))?;
        let cache_key = counter_key(class, client_addr, account_id);
        let now_for_entry = now;
        let counter = self
            .counters
            .get_with(cache_key, async move {
                Arc::new(Mutex::new(WindowCounter::new(now_for_entry)))
            })
            .await;
        let mut counter = counter.lock().await;
        Ok(Some(counter.decide(now, limit)))
    }

    /// Resolves an incoming peer and optional X-Forwarded-For header using configured proxies.
    #[must_use]
    pub fn client_addr(&self, peer: IpAddr, x_forwarded_for: Option<&str>) -> IpAddr {
        self.trusted_proxies.client_addr(peer, x_forwarded_for)
    }

    /// Returns the number of cached hashed counter keys after pending cache work is applied.
    pub async fn entry_count(&self) -> u64 {
        self.counters.run_pending_tasks().await;
        self.counters.entry_count()
    }

    async fn policy_values(&self, now: Instant) -> Result<ResolvedPolicies, AppError> {
        let mut cached = self.policy_snapshot.lock().await;
        let now_millis = now.unix_millis();
        let needs_refresh = cached.as_ref().is_none_or(|snapshot| {
            now_millis.saturating_sub(snapshot.fetched_at_millis) >= POLICY_REFRESH_MILLIS
        });
        if needs_refresh {
            let values = self.policies.snapshot_values(None, now).await?;
            *cached = Some(PolicySnapshot {
                fetched_at_millis: now_millis,
                values,
            });
        }
        cached
            .as_ref()
            .map(|snapshot| snapshot.values.clone())
            .ok_or_else(|| AppError::new(ErrorCode::InternalError))
    }
}

fn counter_key(class: RateClass, client_addr: IpAddr, account_id: Option<AccountId>) -> [u8; 16] {
    let mut material = Vec::with_capacity(19);
    material.push(rate_class_tag(class));
    if let Some(account_id) = account_id {
        material.push(1);
        material.extend_from_slice(account_id.as_uuid().as_bytes());
    } else {
        material.push(0);
        match truncate_ipv6(client_addr) {
            IpAddr::V4(address) => {
                material.push(4);
                material.extend_from_slice(&address.octets());
            }
            IpAddr::V6(address) => {
                material.push(6);
                material.extend_from_slice(&address.octets());
            }
        }
    }
    let digest = hash_token(&material);
    let mut truncated = [0_u8; 16];
    truncated.copy_from_slice(&digest[..16]);
    truncated
}

const fn rate_class_tag(class: RateClass) -> u8 {
    match class {
        RateClass::None => 0,
        RateClass::Auth => 1,
        RateClass::Api => 2,
        RateClass::Ai => 3,
        RateClass::Upload => 4,
        RateClass::Sync => 5,
        RateClass::Export => 6,
    }
}

/// State shared by the pre-gate and post-gate route limiter middleware.
#[derive(Clone)]
pub(crate) struct RateLimitRequestState {
    pub limiter: Option<Arc<RateLimiter>>,
    pub specs: Arc<Vec<RouteSpec>>,
}

/// Limits public routes before authentication/setup gates, using the client address.
pub(crate) async fn rate_limit_public_before_gate(
    State(state): State<RateLimitRequestState>,
    request: Request,
    next: Next,
) -> Response {
    let Some(spec) = matched_spec(&request, &state.specs) else {
        return next.run(request).await;
    };
    if spec.access != Access::Public || spec.rate == RateClass::None {
        return next.run(request).await;
    }
    limit_request(&state, request, next, spec.rate, None).await
}

/// Limits authenticated routes after the gate has inserted their account context.
pub(crate) async fn rate_limit_after_gate(
    State(state): State<RateLimitRequestState>,
    request: Request,
    next: Next,
) -> Response {
    let Some(spec) = matched_spec(&request, &state.specs) else {
        return next.run(request).await;
    };
    if !matches!(spec.access, Access::Permission(_)) || spec.rate == RateClass::None {
        return next.run(request).await;
    }
    let account_id = request
        .extensions()
        .get::<AuthCtx>()
        .map(AuthCtx::account_id);
    limit_request(&state, request, next, spec.rate, account_id).await
}

fn matched_spec<'a>(request: &Request, specs: &'a [RouteSpec]) -> Option<&'a RouteSpec> {
    let path = request.extensions().get::<MatchedPath>()?.as_str();
    let method = if request.method() == Method::HEAD {
        &Method::GET
    } else {
        request.method()
    };
    specs
        .iter()
        .find(|spec| spec.method == *method && route_path(spec.path) == path)
}

async fn limit_request(
    state: &RateLimitRequestState,
    request: Request,
    next: Next,
    class: RateClass,
    account_id: Option<AccountId>,
) -> Response {
    let Some(limiter) = &state.limiter else {
        return internal_error();
    };
    let Some(peer) = request
        .extensions()
        .get::<ConnectInfo<SocketAddr>>()
        .map(|ConnectInfo(address)| address.ip())
    else {
        return internal_error();
    };
    let forwarded = request
        .headers()
        .get(HeaderName::from_static("x-forwarded-for"))
        .and_then(|value| value.to_str().ok());
    let client_addr = limiter.client_addr(peer, forwarded);
    let decision = match limiter.decide(class, client_addr, account_id).await {
        Ok(Some(decision)) => decision,
        Ok(None) => return next.run(request).await,
        Err(_) => return internal_error(),
    };
    let mut response = if decision.allowed {
        next.run(request).await
    } else {
        limited_response(decision)
    };
    add_rate_headers(&mut response, decision);
    response
}

fn limited_response(decision: Decision) -> Response {
    ApiError(AppError::new(ErrorCode::RateLimited).with_detail(
        "retry_after_seconds",
        DetailValue::Int(i64::from(decision.reset_seconds)),
    ))
    .into_response()
}

fn internal_error() -> Response {
    ApiError(AppError::new(ErrorCode::InternalError)).into_response()
}

fn add_rate_headers(response: &mut Response, decision: Decision) {
    for (name, value) in [
        ("RateLimit-Limit", decision.limit),
        ("RateLimit-Remaining", decision.remaining),
        ("RateLimit-Reset", decision.reset_seconds),
    ] {
        if let Ok(value) = HeaderValue::from_str(&value.to_string()) {
            response.headers_mut().insert(name, value);
        }
    }
}

#[cfg(test)]
mod tests {
    use super::{TrustedProxies, truncate_ipv6};
    use std::{
        error::Error,
        net::{IpAddr, Ipv6Addr},
    };

    #[test]
    fn trusted_proxy_ipv6_is_reduced_to_a_64_bit_prefix() -> Result<(), Box<dyn Error>> {
        let proxies = TrustedProxies::parse("10.0.0.0/8").map_err(|_| "proxy CIDR is invalid")?;
        let peer = "10.0.0.1".parse::<IpAddr>()?;
        let first = proxies.client_addr(peer, Some("2001:db8:1:2::1, 10.0.0.2"));
        let second = truncate_ipv6("2001:db8:1:2::feed".parse::<IpAddr>()?);
        assert_eq!(first, second);
        assert_eq!(
            first,
            IpAddr::V6(Ipv6Addr::from(
                0x2001_0db8_0001_0002_0000_0000_0000_0000_u128
            ))
        );
        Ok(())
    }
}
