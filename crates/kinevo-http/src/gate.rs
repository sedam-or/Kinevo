//! Ordered setup, authentication, module, and permission request gate.

use axum::{
    extract::{MatchedPath, Request, State},
    http::{HeaderMap, Method, header},
    middleware::Next,
    response::{IntoResponse, Response},
};
use kinevo_kernel::{
    errors::{AppError, DetailValue, ErrorCode},
    time::Instant,
};
use kinevo_ports::{
    module::{ModuleDescriptor, ModuleKey, ModuleKind},
    platform::{AccountStatus, ModuleSwitches},
};
use std::{collections::BTreeMap, error::Error, fmt, sync::Arc};

use crate::{
    auth::{AccountCache, AuthCtx, Role},
    problem::ApiError,
    rbac::{PermissionTable, RbacError, check_routes},
    route::{Access, RouteSpec, route_path},
    state::HttpState,
    token::TokenCodec,
};

/// Build-time module-kind map; core modules bypass the runtime switch lookup.
pub struct ModuleKinds {
    kinds: BTreeMap<String, ModuleKind>,
}

impl ModuleKinds {
    /// Builds the map from the compiled module descriptors.
    pub fn from_descriptors(descriptors: &[&ModuleDescriptor]) -> Result<Self, GateBuildError> {
        let mut kinds = BTreeMap::new();
        for descriptor in descriptors {
            ModuleKey::parse(descriptor.key)
                .map_err(|_| GateBuildError::InvalidModule(descriptor.key))?;
            if kinds
                .insert(descriptor.key.to_owned(), descriptor.kind)
                .is_some()
            {
                return Err(GateBuildError::DuplicateModule(descriptor.key));
            }
        }
        Ok(Self { kinds })
    }

    fn get(&self, key: &str) -> Option<ModuleKind> {
        self.kinds.get(key).copied()
    }
}

/// A startup-time route or module gate configuration error.
#[derive(Debug)]
pub enum GateBuildError {
    InvalidModule(&'static str),
    DuplicateModule(&'static str),
    UnknownRouteModule(&'static str),
    InvalidAllowDisabledRoute(&'static str),
    Rbac(RbacError),
}

impl fmt::Display for GateBuildError {
    fn fmt(&self, formatter: &mut fmt::Formatter<'_>) -> fmt::Result {
        match self {
            Self::InvalidModule(module) => {
                write!(formatter, "invalid module descriptor key: {module}")
            }
            Self::DuplicateModule(module) => {
                write!(formatter, "duplicate module descriptor: {module}")
            }
            Self::UnknownRouteModule(module) => {
                write!(formatter, "route names unknown module: {module}")
            }
            Self::InvalidAllowDisabledRoute(path) => write!(
                formatter,
                "allow_disabled is only valid for GET /instance: {path}"
            ),
            Self::Rbac(error) => write!(formatter, "{error}"),
        }
    }
}

impl Error for GateBuildError {}

impl From<RbacError> for GateBuildError {
    fn from(error: RbacError) -> Self {
        Self::Rbac(error)
    }
}

/// Dependencies required to enforce route metadata on each request.
pub struct Gate {
    tokens: Arc<TokenCodec>,
    accounts: Arc<AccountCache>,
    modules: Arc<dyn ModuleSwitches>,
    permissions: Arc<PermissionTable>,
    module_kinds: Arc<ModuleKinds>,
}

impl Gate {
    /// Creates a gate from token, account, module-switch, and permission registries.
    #[must_use]
    pub fn new(
        tokens: Arc<TokenCodec>,
        accounts: Arc<AccountCache>,
        modules: Arc<dyn ModuleSwitches>,
        permissions: Arc<PermissionTable>,
        module_kinds: Arc<ModuleKinds>,
    ) -> Self {
        Self {
            tokens,
            accounts,
            modules,
            permissions,
            module_kinds,
        }
    }

    pub(crate) fn validate_routes(&self, specs: &[RouteSpec]) -> Result<(), GateBuildError> {
        check_routes(&self.permissions, specs)?;
        for spec in specs {
            if self.module_kinds.get(spec.module).is_none() {
                return Err(GateBuildError::UnknownRouteModule(spec.module));
            }
            if spec.allow_disabled
                && !(spec.method == Method::GET && route_path(spec.path) == "/api/v1/instance")
            {
                return Err(GateBuildError::InvalidAllowDisabledRoute(spec.path));
            }
        }
        Ok(())
    }

    fn route_allowed_when_setup_complete(path: &str) -> bool {
        path == "/api/v1/setup" || path.starts_with("/api/v1/setup/")
    }

    fn is_health_path(path: &str) -> bool {
        matches!(path, "/livez" | "/readyz")
    }

    async fn authenticate(
        &self,
        bearer: String,
        now: Instant,
    ) -> Result<(AuthCtx, AccountStatus), GateFailure> {
        let claims = self
            .tokens
            .verify(&bearer, now)
            .map_err(|_| GateFailure::Unauthenticated)?;
        let account = self
            .accounts
            .get(claims.sub)
            .await
            .map_err(|_| GateFailure::InternalError)?
            .ok_or(GateFailure::Unauthenticated)?;
        let role = if account.is_instance_admin {
            Role::InstanceAdmin
        } else {
            Role::AccountOwner
        };
        Ok((
            AuthCtx::new(account.id, claims.sid, role, claims.mfa),
            account.status,
        ))
    }

    fn module_enabled(&self, module: &str) -> Result<bool, GateFailure> {
        match self.module_kinds.get(module) {
            Some(ModuleKind::Core) => Ok(true),
            Some(ModuleKind::Toggleable) => {
                let key = ModuleKey::parse(module).map_err(|_| GateFailure::InternalError)?;
                Ok(self.modules.is_enabled(&key))
            }
            None => Err(GateFailure::InternalError),
        }
    }
}

#[derive(Clone, Copy)]
enum GateFailure {
    Unauthenticated,
    AccountDisabled,
    ModuleDisabled(&'static str),
    Forbidden,
    InternalError,
}

impl GateFailure {
    fn into_response(self) -> Response {
        match self {
            Self::Unauthenticated => api_error(AppError::new(ErrorCode::Unauthenticated)),
            Self::AccountDisabled => api_error(AppError::new(ErrorCode::AccountDisabled)),
            Self::ModuleDisabled(module) => api_error(
                AppError::new(ErrorCode::ModuleDisabled)
                    .with_detail("module", DetailValue::OwnedStr(module.to_owned())),
            ),
            Self::Forbidden => api_error(AppError::new(ErrorCode::Forbidden)),
            Self::InternalError => internal_error(),
        }
    }
}

#[derive(Clone)]
pub(crate) struct GateRequestState {
    pub state: HttpState,
    pub specs: Arc<Vec<RouteSpec>>,
}

pub(crate) async fn gate_request(
    State(context): State<GateRequestState>,
    mut request: Request,
    next: Next,
) -> Response {
    let Some(matched_path) = request
        .extensions()
        .get::<MatchedPath>()
        .map(|matched_path| matched_path.as_str().to_owned())
    else {
        return next.run(request).await;
    };
    let method = if request.method() == Method::HEAD {
        &Method::GET
    } else {
        request.method()
    };
    let Some(spec) = context
        .specs
        .iter()
        .find(|spec| spec.method == *method && route_path(spec.path) == matched_path)
        .cloned()
    else {
        return next.run(request).await;
    };
    let path = route_path(spec.path);
    if Gate::is_health_path(&path) {
        return next.run(request).await;
    }

    if let Some(response) = setup_response(&context.state, &path).await {
        return response;
    }

    let auth_context = match spec.access {
        Access::Public => match context.state.gate.module_enabled(spec.module) {
            Ok(true) => None,
            Ok(false) => return GateFailure::ModuleDisabled(spec.module).into_response(),
            Err(failure) => return failure.into_response(),
        },
        Access::Permission(_) => {
            let Some(bearer) = bearer_token(request.headers()) else {
                return GateFailure::Unauthenticated.into_response();
            };
            match authorize_route(
                &context.state.gate,
                &spec,
                bearer,
                context.state.clock.now(),
            )
            .await
            {
                Ok(auth) => Some(auth),
                Err(failure) => return failure.into_response(),
            }
        }
    };
    if let Some(auth) = auth_context {
        request.extensions_mut().insert(auth);
    }
    next.run(request).await
}

fn api_error(error: AppError) -> Response {
    ApiError(error).into_response()
}

async fn setup_response(state: &HttpState, path: &str) -> Option<Response> {
    let Ok(instance) = state.instance.get().await else {
        return Some(internal_error());
    };
    let is_setup_route = Gate::route_allowed_when_setup_complete(path);
    if instance.setup_completed && is_setup_route {
        Some(api_error(AppError::new(ErrorCode::SetupAlreadyCompleted)))
    } else if !instance.setup_completed && !is_setup_route {
        Some(api_error(AppError::new(ErrorCode::SetupRequired)))
    } else {
        None
    }
}

fn bearer_token(headers: &HeaderMap) -> Option<String> {
    headers
        .get(header::AUTHORIZATION)
        .and_then(|value| value.to_str().ok())
        .and_then(|value| {
            let (scheme, token) = value.split_once(' ')?;
            scheme.eq_ignore_ascii_case("bearer").then_some(token)
        })
        .filter(|token| !token.is_empty())
        .map(str::to_owned)
}

async fn authorize_route(
    gate: &Gate,
    spec: &RouteSpec,
    bearer: String,
    now: Instant,
) -> Result<AuthCtx, GateFailure> {
    let (auth, account_status) = gate.authenticate(bearer, now).await?;
    if matches!(
        account_status,
        AccountStatus::Disabled | AccountStatus::Deleting
    ) && !spec.allow_disabled
    {
        return Err(GateFailure::AccountDisabled);
    }
    if !gate
        .module_enabled(spec.module)
        .map_err(|_| GateFailure::InternalError)?
    {
        return Err(GateFailure::ModuleDisabled(spec.module));
    }
    let Access::Permission(permission) = spec.access else {
        return Err(GateFailure::InternalError);
    };
    if !gate.permissions.allows(auth.role(), permission) {
        return Err(GateFailure::Forbidden);
    }
    Ok(auth)
}

fn internal_error() -> Response {
    api_error(AppError::new(ErrorCode::InternalError))
}
