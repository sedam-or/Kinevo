//! Admin transport schema fragment used by the application `OpenAPI` contract.

use serde_json::{Map, Value, json};

fn component<T: utoipa::ToSchema>(name: &str) -> (String, Value) {
    (
        name.to_owned(),
        serde_json::to_value(T::schema()).unwrap_or_else(|_| json!({"type": "object"})),
    )
}

/// Returns schemas for admin HTTP request and response DTOs.
#[must_use]
pub fn schemas() -> Map<String, Value> {
    Map::from_iter([
        component::<super::audit::AuditQuery>("AuditQuery"),
        component::<super::modules::ModuleList>("ModuleList"),
        component::<super::modules::SwitchBody>("ModuleSwitchBody"),
        component::<super::policies::ListQuery>("PolicyListQuery"),
        component::<super::policies::ListResponse>("PolicyListResponse"),
        component::<super::policies::PutBody>("PolicyPutBody"),
        component::<super::secrets::PutBody>("SecretPutBody"),
        component::<super::setup::TokenInput>("SetupTokenInput"),
        component::<super::setup::FinishRequest>("FinishRequest"),
        component::<super::setup::FinishAccountRequest>("FinishAccountRequest"),
        component::<super::setup::FinishResponse>("FinishResponse"),
    ])
}
