//! Permission registry and route-to-permission startup validation.

use kinevo_ports::module::{ModuleDescriptor, PermissionKey};
use std::{collections::BTreeMap, error::Error, fmt};

use crate::{
    auth::Role,
    route::{Access, RouteSpec},
};

/// Registry-derived permission table for both account roles.
pub struct PermissionTable {
    owner_permissions: BTreeMap<String, bool>,
}

impl PermissionTable {
    /// Collects permissions and rejects malformed, duplicate, or misowned keys.
    pub fn from_descriptors(descriptors: &[&ModuleDescriptor]) -> Result<Self, RbacError> {
        let mut owner_permissions = BTreeMap::new();
        for descriptor in descriptors {
            for permission in descriptor.permissions {
                let parsed = PermissionKey::parse(permission.key)
                    .map_err(|_| RbacError::InvalidPermissionKey(permission.key))?;
                if parsed.as_str().split('.').next() != Some(descriptor.key) {
                    return Err(RbacError::PermissionModuleMismatch(permission.key));
                }
                if owner_permissions
                    .insert(permission.key.to_owned(), permission.owner)
                    .is_some()
                {
                    return Err(RbacError::DuplicatePermission(permission.key));
                }
            }
        }
        Ok(Self { owner_permissions })
    }

    /// Returns whether a registered permission is granted to a role.
    #[must_use]
    pub fn allows(&self, role: Role, key: &str) -> bool {
        matches!(
            (role, self.owner_permissions.get(key)),
            (Role::InstanceAdmin, Some(_)) | (Role::AccountOwner, Some(true))
        )
    }

    /// Returns whether the key exists in the validated permission registry.
    #[must_use]
    pub fn contains(&self, key: &str) -> bool {
        self.owner_permissions.contains_key(key)
    }
}

/// A build-time error in module permission descriptors or route metadata.
#[derive(Clone, Copy, Debug, Eq, PartialEq)]
pub enum RbacError {
    InvalidPermissionKey(&'static str),
    PermissionModuleMismatch(&'static str),
    DuplicatePermission(&'static str),
    UnknownPermission {
        route: &'static str,
        key: &'static str,
    },
}

impl fmt::Display for RbacError {
    fn fmt(&self, formatter: &mut fmt::Formatter<'_>) -> fmt::Result {
        match self {
            Self::InvalidPermissionKey(key) => {
                write!(formatter, "invalid permission key in descriptor: {key}")
            }
            Self::PermissionModuleMismatch(key) => write!(
                formatter,
                "permission key has the wrong module owner: {key}"
            ),
            Self::DuplicatePermission(key) => {
                write!(formatter, "duplicate permission key in descriptors: {key}")
            }
            Self::UnknownPermission { route, key } => write!(
                formatter,
                "route {route} references unknown permission {key}"
            ),
        }
    }
}

impl Error for RbacError {}

/// Ensures every permission-bearing route names a registered key.
pub fn check_routes(table: &PermissionTable, specs: &[RouteSpec]) -> Result<(), RbacError> {
    for spec in specs {
        if let Access::Permission(key) = spec.access
            && !table.contains(key)
        {
            return Err(RbacError::UnknownPermission {
                route: spec.path,
                key,
            });
        }
    }
    Ok(())
}
