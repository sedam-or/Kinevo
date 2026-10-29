//! HTTP handlers for registered instance secrets.

use std::sync::Arc;

use axum::{
    Json,
    extract::Path,
    http::{Method, StatusCode},
    routing::{delete, get, put},
};
use kinevo_http::{
    auth::Auth,
    extract::{Validate, ValidatedJson},
    problem::ApiError,
    route::{Access, IdemMode, RateClass, RouteSpec, RouteTable},
};
use kinevo_kernel::{errors::FieldCode, validate::Collector};
use serde::{Deserialize, Deserializer};

use crate::app::secrets::SecretsService;

/// Adds the instance secret list, put and delete routes.
#[must_use]
pub fn routes(service: Arc<SecretsService>) -> RouteTable {
    let list_service = Arc::clone(&service);
    let list = get(move |Auth(_auth): Auth| {
        let service = Arc::clone(&list_service);
        async move { service.list().await.map(Json).map_err(ApiError) }
    });

    let put_service = Arc::clone(&service);
    let put_secret = put(
        move |Auth(auth): Auth,
              Path(name): Path<String>,
              ValidatedJson(body): ValidatedJson<PutBody>| {
            let service = Arc::clone(&put_service);
            async move {
                service
                    .put(auth.account_id(), &name, body.value.0)
                    .await
                    .map_err(ApiError)?;
                Ok::<_, ApiError>(StatusCode::NO_CONTENT)
            }
        },
    );

    let delete_service = service;
    let delete_secret = delete(move |Auth(auth): Auth, Path(name): Path<String>| {
        let service = Arc::clone(&delete_service);
        async move {
            service
                .delete(auth.account_id(), &name)
                .await
                .map_err(ApiError)?;
            Ok::<_, ApiError>(StatusCode::NO_CONTENT)
        }
    });

    RouteTable::new()
        .route(
            spec(
                Method::GET,
                "/admin/secrets",
                IdemMode::Off,
                "List instance secrets",
            ),
            list,
        )
        .route(
            spec(
                Method::PUT,
                "/admin/secrets/{name}",
                IdemMode::Optional,
                "Set an instance secret",
            ),
            put_secret,
        )
        .route(
            spec(
                Method::DELETE,
                "/admin/secrets/{name}",
                IdemMode::Optional,
                "Delete an instance secret",
            ),
            delete_secret,
        )
}

fn spec(
    method: Method,
    path: &'static str,
    idempotency: IdemMode,
    summary: &'static str,
) -> RouteSpec {
    RouteSpec {
        method,
        path,
        module: "admin",
        access: Access::Permission("admin.secrets.manage"),
        rate: RateClass::Api,
        idempotency,
        store_response_body: false,
        cookie_endpoint: false,
        summary,
        allow_disabled: false,
    }
}

#[derive(Deserialize)]
#[serde(deny_unknown_fields)]
struct PutBody {
    value: SecretInput,
}

struct SecretInput(kinevo_kernel::secret::Secret<String>);

impl<'de> Deserialize<'de> for SecretInput {
    fn deserialize<D>(deserializer: D) -> Result<Self, D::Error>
    where
        D: Deserializer<'de>,
    {
        String::deserialize(deserializer)
            .map(|value| Self(kinevo_kernel::secret::Secret::new(value)))
    }
}

impl Validate for PutBody {
    fn validate(&self) -> Collector {
        let raw = self.value.0.expose();
        let validation = if raw.is_empty() {
            Err(FieldCode::Required)
        } else if raw.chars().count() > 4096 {
            Err(FieldCode::TooLong)
        } else {
            Ok(())
        };
        let mut collector = Collector::new();
        let _: Option<()> = collector.check("value", validation);
        collector
    }
}
