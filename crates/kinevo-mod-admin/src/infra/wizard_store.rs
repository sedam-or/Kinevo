//! Bounded process-local setup sessions and setup-token attempt counters.

use std::{
    collections::VecDeque,
    sync::{Mutex, PoisonError},
};

use kinevo_kernel::{
    errors::{AppError, ErrorCode},
    time::{Clock, Instant},
};
use moka::sync::Cache;

use crate::app::setup::{SessionId, WizardState};

const MAX_SESSIONS: u64 = 8;
const MAX_ATTEMPT_SOURCES: u64 = 4096;
const MAX_TOKEN_ATTEMPTS: u8 = 3;

#[derive(Clone, Copy, Debug, Eq, PartialEq)]
enum SessionStatus {
    Active,
    Finished,
}

#[derive(Clone)]
struct StoredSession {
    state: WizardState,
    expires_at: Instant,
    status: SessionStatus,
}

#[derive(Clone, Copy)]
struct AttemptState {
    attempts: u8,
    expires_at: Instant,
    locked_until: Option<Instant>,
}

/// Process-local wizard sessions with injected expiry and a bounded attempt cache.
pub struct WizardStore {
    sessions: Cache<SessionId, StoredSession>,
    order: Mutex<VecDeque<SessionId>>,
    attempts: Cache<String, AttemptState>,
    attempts_lock: Mutex<()>,
    session_ttl_seconds: i64,
    lockout_seconds: i64,
    clock: std::sync::Arc<dyn Clock>,
}

impl WizardStore {
    /// Creates bounded storage using durations resolved by application composition.
    #[must_use]
    pub fn new(
        session_ttl_seconds: i64,
        lockout_seconds: i64,
        clock: std::sync::Arc<dyn Clock>,
    ) -> Self {
        Self {
            sessions: Cache::new(MAX_SESSIONS),
            order: Mutex::new(VecDeque::new()),
            attempts: Cache::builder().max_capacity(MAX_ATTEMPT_SOURCES).build(),
            attempts_lock: Mutex::new(()),
            session_ttl_seconds,
            lockout_seconds,
            clock,
        }
    }

    /// Stores a new wizard, evicting the oldest active session at capacity.
    pub fn insert(&self, state: WizardState) -> Result<(), AppError> {
        let now = self.clock.now();
        let expires_at = now
            .checked_add_seconds(self.session_ttl_seconds)
            .ok_or_else(|| AppError::new(ErrorCode::ServiceUnavailable))?;
        self.remove_expired(now);
        let id = state.session_id.clone();
        let mut order = self.order.lock().unwrap_or_else(PoisonError::into_inner);
        if let Some(position) = order.iter().position(|existing| existing == &id) {
            order.remove(position);
        }
        while order.len() >= usize::try_from(MAX_SESSIONS).unwrap_or(usize::MAX) {
            if let Some(oldest) = order.pop_front() {
                self.sessions.invalidate(&oldest);
            }
        }
        self.sessions.insert(
            id.clone(),
            StoredSession {
                state,
                expires_at,
                status: SessionStatus::Active,
            },
        );
        order.push_back(id);
        Ok(())
    }

    /// Returns an active session, removing it when its injected-clock TTL elapsed.
    pub fn get(&self, id: &SessionId) -> Result<WizardState, AppError> {
        let Some(stored) = self.sessions.get(id) else {
            return Err(AppError::new(ErrorCode::NotFound));
        };
        if stored.expires_at <= self.clock.now() {
            self.remove(id);
            return Err(AppError::new(ErrorCode::NotFound));
        }
        Ok(stored.state)
    }

    /// Replaces session state after checking expiry and the terminal finish state.
    pub fn update(&self, state: WizardState) -> Result<(), AppError> {
        let now = self.clock.now();
        let Some(mut stored) = self.sessions.get(&state.session_id) else {
            return Err(AppError::new(ErrorCode::NotFound));
        };
        if stored.expires_at <= now {
            self.remove(&state.session_id);
            return Err(AppError::new(ErrorCode::NotFound));
        }
        if stored.status == SessionStatus::Finished {
            return Err(AppError::new(ErrorCode::InvalidTransition));
        }
        stored.state = state;
        self.sessions
            .insert(stored.state.session_id.clone(), stored);
        Ok(())
    }

    /// Marks a session terminal so later edits cannot change it.
    pub fn mark_finished(&self, id: &SessionId) -> Result<(), AppError> {
        let Some(mut stored) = self.sessions.get(id) else {
            return Err(AppError::new(ErrorCode::NotFound));
        };
        if stored.expires_at <= self.clock.now() {
            self.remove(id);
            return Err(AppError::new(ErrorCode::NotFound));
        }
        stored.status = SessionStatus::Finished;
        self.sessions.insert(id.clone(), stored);
        Ok(())
    }

    /// Tests whether a source is still locked after the configured number of failures.
    pub fn is_locked(&self, source: &str) -> bool {
        let _lock = self
            .attempts_lock
            .lock()
            .unwrap_or_else(PoisonError::into_inner);
        self.is_locked_locked(source)
    }

    fn is_locked_locked(&self, source: &str) -> bool {
        let now = self.clock.now();
        let Some(state) = self.attempts.get(source) else {
            return false;
        };
        if state.expires_at <= now {
            self.attempts.invalidate(source);
            return false;
        }
        state.locked_until.is_some_and(|until| now < until)
    }

    /// Records one failed token attempt and starts the lockout on the third failure.
    pub fn record_failure(&self, source: &str) -> Result<bool, AppError> {
        let now = self.clock.now();
        let expires_at = now
            .checked_add_seconds(self.lockout_seconds)
            .ok_or_else(|| AppError::new(ErrorCode::ServiceUnavailable))?;
        let _lock = self
            .attempts_lock
            .lock()
            .unwrap_or_else(PoisonError::into_inner);
        let mut state = self.attempts.get(source).unwrap_or(AttemptState {
            attempts: 0,
            expires_at: now,
            locked_until: None,
        });
        if state.expires_at <= now {
            state = AttemptState {
                attempts: 0,
                expires_at,
                locked_until: None,
            };
        }
        state.attempts = state.attempts.saturating_add(1);
        state.expires_at = expires_at;
        if state.attempts >= MAX_TOKEN_ATTEMPTS {
            state.locked_until = Some(expires_at);
        }
        let locked = state.attempts >= MAX_TOKEN_ATTEMPTS;
        self.attempts.insert(source.to_owned(), state);
        Ok(locked)
    }

    /// Clears failures after a valid token unless the source became locked meanwhile.
    pub fn accept_attempt(&self, source: &str) -> bool {
        let _lock = self
            .attempts_lock
            .lock()
            .unwrap_or_else(PoisonError::into_inner);
        if self.is_locked_locked(source) {
            return false;
        }
        self.attempts.invalidate(source);
        true
    }

    fn remove_expired(&self, now: Instant) {
        let mut order = self.order.lock().unwrap_or_else(PoisonError::into_inner);
        order.retain(|id| {
            if self
                .sessions
                .get(id)
                .is_some_and(|session| session.expires_at > now)
            {
                true
            } else {
                self.sessions.invalidate(id);
                false
            }
        });
    }

    fn remove(&self, id: &SessionId) {
        self.sessions.invalidate(id);
        self.order
            .lock()
            .unwrap_or_else(PoisonError::into_inner)
            .retain(|current| current != id);
    }
}

#[cfg(test)]
#[allow(clippy::expect_used, reason = "store tests use fixed synthetic state")]
mod tests {
    use std::{collections::BTreeMap, sync::Arc};

    use kinevo_kernel::time::{FixedClock, Instant};

    use crate::app::setup::{SessionId, WizardState};

    use super::WizardStore;

    fn now() -> Instant {
        Instant::parse_rfc3339("2026-11-08T00:00:00Z").expect("fixed instant is valid")
    }

    fn state(id: u128) -> WizardState {
        WizardState {
            session_id: SessionId::parse(&format!("{id:032x}")).expect("session id is valid"),
            step: 1,
            template_id: "personal".to_owned(),
            locale: "en".to_owned(),
            base_url: None,
            modules: BTreeMap::new(),
            policies: BTreeMap::new(),
        }
    }

    #[test]
    fn setup_sessions_expire_using_the_injected_clock() {
        let clock = Arc::new(FixedClock::new(now()));
        let store = WizardStore::new(5, 30, clock.clone());
        let session = state(1);
        store.insert(session.clone()).expect("session inserts");
        clock.advance_seconds(6).expect("clock advance is valid");
        assert!(store.get(&session.session_id).is_err());
    }

    #[test]
    fn setup_store_evicts_the_oldest_session_at_capacity() {
        let clock = Arc::new(FixedClock::new(now()));
        let store = WizardStore::new(60, 30, clock);
        for id in 1..=9 {
            store.insert(state(id)).expect("session inserts");
        }
        assert!(store.get(&state(1).session_id).is_err());
        assert!(store.get(&state(2).session_id).is_ok());
    }

    #[test]
    fn setup_store_locks_a_source_after_three_failures() {
        let clock = Arc::new(FixedClock::new(now()));
        let store = WizardStore::new(60, 30, clock.clone());
        for attempt in 1..=3 {
            let locked = store
                .record_failure("192.0.2.7")
                .expect("failure is recorded");
            assert_eq!(locked, attempt == 3);
        }
        assert!(store.is_locked("192.0.2.7"));
        clock.advance_seconds(31).expect("clock advance is valid");
        assert!(!store.is_locked("192.0.2.7"));
    }
}
