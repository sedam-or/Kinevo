#![allow(
    clippy::expect_used,
    clippy::unwrap_used,
    reason = "descriptor acceptance checks use static module metadata"
)]

use kinevo_http::{auth::Role, rbac::PermissionTable};
use kinevo_kernel::policy::registry::all_specs;
use kinevo_mod_admin::MODULE;
use kinevo_mod_identity::MODULE as IDENTITY;
use kinevo_ports::module::validate_graph;

#[test]
fn mod_08_admin_descriptor_valid() {
    let descriptors = [&IDENTITY, &MODULE];
    validate_graph(&descriptors).expect("identity and admin descriptors should form a valid graph");
    let permissions = PermissionTable::from_descriptors(&descriptors)
        .expect("identity and admin permissions should build");
    let owner_keys = MODULE
        .permissions
        .iter()
        .filter(|permission| permission.owner)
        .collect::<Vec<_>>();

    assert_eq!(owner_keys.len(), 5);
    assert_eq!(MODULE.permissions.len(), 11);
    for permission in owner_keys {
        assert!(permissions.allows(Role::AccountOwner, permission.key));
    }
    for permission in MODULE.permissions {
        assert!(permissions.allows(Role::InstanceAdmin, permission.key));
    }

    let registered = all_specs()
        .iter()
        .map(|policy| policy.key)
        .collect::<Vec<_>>();
    for key in MODULE.policy_keys {
        assert!(
            registered.contains(key),
            "unregistered admin policy key: {key}"
        );
    }
}
