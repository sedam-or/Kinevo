//! Account profile read and optimistic update use cases.

use kinevo_db::{Db, Tx};
use kinevo_http::concurrency::version_conflict;
use kinevo_kernel::{
    errors::{AppError, Details, FieldError},
    id::AccountId,
    time::Instant,
};

use crate::{
    domain::profile::{Profile, ProfilePatch, apply_patch},
    infra::accounts,
};

/// Owns account-scoped profile operations.
pub struct ProfileService {
    db: Db,
}

impl ProfileService {
    #[must_use]
    pub fn new(db: Db) -> Self {
        Self { db }
    }

    /// Reads the authenticated account's profile.
    pub async fn get_profile(&self, account: AccountId) -> Result<Profile, AppError> {
        let mut tx = Tx::begin(&self.db, account).await?;
        let profile = accounts::get_profile(&mut tx, account).await?;
        tx.commit().await?;
        Ok(profile)
    }

    /// Applies a partial profile patch only when the supplied version is current.
    pub async fn update_profile(
        &self,
        account: AccountId,
        patch: ProfilePatch,
        version: i64,
        now: Instant,
    ) -> Result<Profile, AppError> {
        let mut tx = Tx::begin(&self.db, account).await?;
        let current = accounts::get_profile(&mut tx, account).await?;
        if current.version != version {
            return Err(version_conflict(current.version, &current));
        }

        let next = apply_patch(&current, patch).map_err(|errors| {
            AppError::validation(
                errors
                    .into_iter()
                    .map(|(path, code)| FieldError {
                        path,
                        code,
                        details: Details::new(),
                    })
                    .collect(),
            )
        })?;
        if next == current {
            tx.commit().await?;
            return Ok(current);
        }

        let updated = accounts::update_profile(&mut tx, account, version, &next, now).await?;
        let Some(profile) = updated else {
            let current = accounts::get_profile(&mut tx, account).await?;
            return Err(version_conflict(current.version, &current));
        };
        tx.commit().await?;
        Ok(profile)
    }
}
