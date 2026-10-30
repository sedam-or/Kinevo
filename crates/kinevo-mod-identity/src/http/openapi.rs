//! Identity transport schema fragment used by the application `OpenAPI` contract.

use serde_json::{Map, Value, json};

fn component<T: utoipa::ToSchema>(name: &str) -> (String, Value) {
    (
        name.to_owned(),
        serde_json::to_value(T::schema()).unwrap_or_else(|_| json!({"type": "object"})),
    )
}

/// Returns schemas for identity HTTP request and response DTOs.
#[must_use]
pub fn schemas() -> Map<String, Value> {
    Map::from_iter([
        component::<crate::app::login::AccountProfile>("AccountProfile"),
        component::<super::dto::LoginDto>("LoginDto"),
        component::<super::dto::LoginResponse>("LoginResponse"),
        component::<super::account::PasswordChangeBody>("PasswordChangeBody"),
        component::<super::account::SessionsQuery>("SessionsQuery"),
        component::<super::account::SessionsPage>("SessionsPage"),
        component::<super::admin::AccountQuery>("AccountQuery"),
        component::<super::admin::StatusBody>("AccountStatusBody"),
        component::<super::admin::InviteBody>("InviteBody"),
        component::<super::admin::InviteResponse>("InviteResponse"),
        component::<super::admin::ResetResponse>("ResetResponse"),
        component::<super::deletion::DeletionBody>("DeletionBody"),
        component::<super::export::ExportRequestResponse>("ExportRequestResponse"),
        component::<super::refresh::RefreshBody>("RefreshBody"),
        component::<super::register::RegisterBody>("RegisterBody"),
        component::<super::reset::RequestBody>("PasswordResetRequest"),
        component::<super::reset::ConfirmBody>("PasswordResetConfirm"),
        component::<super::session_response::RefreshResponse>("RefreshResponse"),
        component::<super::totp::EnrolmentResponse>("TotpEnrolmentResponse"),
        component::<super::totp::CodeBody>("TotpCodeBody"),
        component::<super::totp::DisableBody>("TotpDisableBody"),
    ])
}
