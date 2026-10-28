use kinevo_kernel::time::Instant;

/// Policy values used by the lockout state machine.
#[derive(Clone, Copy, Debug, Eq, PartialEq)]
pub struct LockoutPolicy {
    pub max_attempts: u32,
    pub window_seconds: u32,
    pub duration_seconds: u32,
}

/// Persisted lockout state for one account.
#[derive(Clone, Copy, Debug, Default, Eq, PartialEq)]
pub struct LockoutState {
    pub failed_count: u32,
    pub window_start: Option<Instant>,
    pub locked_until: Option<Instant>,
}

/// Returns whether the account is still inside an active lockout.
#[must_use]
pub fn is_locked(state: LockoutState, now: Instant) -> bool {
    state.locked_until.is_some_and(|until| now < until)
}

/// Adds one failed credential attempt, restarting an expired window.
#[must_use]
pub fn on_failure(mut state: LockoutState, now: Instant, policy: LockoutPolicy) -> LockoutState {
    if is_locked(state, now) {
        return state;
    }

    let window_expired = state.window_start.is_none_or(|started| {
        started
            .checked_add_seconds(i64::from(policy.window_seconds))
            .is_none_or(|end| now >= end)
    });
    if window_expired || state.locked_until.is_some() {
        state = LockoutState::default();
    }

    state.failed_count = state.failed_count.saturating_add(1);
    state.window_start = Some(state.window_start.unwrap_or(now));
    if state.failed_count >= policy.max_attempts {
        state.locked_until = now.checked_add_seconds(i64::from(policy.duration_seconds));
    }
    state
}

/// Clears the lockout state after a successful login.
#[must_use]
pub const fn on_success() -> LockoutState {
    LockoutState {
        failed_count: 0,
        window_start: None,
        locked_until: None,
    }
}

#[cfg(test)]
mod tests {
    #![allow(
        clippy::expect_used,
        reason = "the state table uses fixed instant fixtures"
    )]

    use super::{LockoutPolicy, LockoutState, is_locked, on_failure, on_success};
    use kinevo_kernel::time::Instant;

    #[derive(Clone, Copy)]
    enum Event {
        Failure,
        Success,
    }

    fn instant(value: &str) -> Instant {
        Instant::parse_rfc3339(value).expect("fixture instant is valid")
    }

    fn state(
        failed_count: u32,
        window_start: Option<Instant>,
        locked_until: Option<Instant>,
    ) -> LockoutState {
        LockoutState {
            failed_count,
            window_start,
            locked_until,
        }
    }

    #[test]
    fn id_fr_04_state_machine_table() {
        let now = instant("2026-11-03T00:00:00Z");
        let window_start = instant("2026-11-02T23:50:00Z");
        let expired_window = instant("2026-11-02T23:44:59Z");
        let locked_until = instant("2026-11-03T00:15:00Z");
        let expired_lock = instant("2026-11-02T23:59:59Z");
        let policy = LockoutPolicy {
            max_attempts: 5,
            window_seconds: 900,
            duration_seconds: 900,
        };
        let cases = [
            (
                "idle failure starts a window",
                LockoutState::default(),
                Event::Failure,
                state(1, Some(now), None),
            ),
            (
                "counting failure increments",
                state(2, Some(window_start), None),
                Event::Failure,
                state(3, Some(window_start), None),
            ),
            (
                "threshold failure locks",
                state(4, Some(window_start), None),
                Event::Failure,
                state(5, Some(window_start), Some(locked_until)),
            ),
            (
                "failure outside the window restarts counting",
                state(4, Some(expired_window), None),
                Event::Failure,
                state(1, Some(now), None),
            ),
            (
                "failure during a lock leaves state unchanged",
                state(5, Some(window_start), Some(locked_until)),
                Event::Failure,
                state(5, Some(window_start), Some(locked_until)),
            ),
            (
                "success resets counting",
                state(3, Some(window_start), None),
                Event::Success,
                LockoutState::default(),
            ),
            (
                "failure after a lock expires starts a new window",
                state(5, Some(window_start), Some(expired_lock)),
                Event::Failure,
                state(1, Some(now), None),
            ),
            (
                "success after a lock clears it",
                state(5, Some(window_start), Some(locked_until)),
                Event::Success,
                LockoutState::default(),
            ),
        ];

        for (name, current, event, expected) in cases {
            let actual = match event {
                Event::Failure => on_failure(current, now, policy),
                Event::Success => on_success(),
            };
            assert_eq!(actual, expected, "{name}");
        }

        assert!(is_locked(
            state(5, Some(window_start), Some(locked_until)),
            now
        ));
        assert!(!is_locked(state(5, Some(window_start), Some(now)), now));
    }
}
