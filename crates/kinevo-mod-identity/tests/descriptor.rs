#![allow(
    clippy::expect_used,
    clippy::unwrap_used,
    reason = "descriptor acceptance checks use static module metadata"
)]

use kinevo_http::{auth::Role, rbac::PermissionTable};
use kinevo_kernel::policy::registry::all_specs;
use kinevo_mod_identity::MODULE;
use kinevo_ports::module::validate_graph;

#[test]
fn mod_08_descriptor_keys_exist() {
    validate_graph(&[&MODULE]).expect("identity descriptor graph should be valid");
    let permissions =
        PermissionTable::from_descriptors(&[&MODULE]).expect("identity permissions should build");
    let owner_keys = MODULE
        .permissions
        .iter()
        .filter(|permission| permission.owner)
        .collect::<Vec<_>>();

    assert_eq!(owner_keys.len(), 6);
    assert_eq!(MODULE.permissions.len(), 10);
    for permission in owner_keys {
        assert!(permissions.allows(Role::AccountOwner, permission.key));
    }
    for permission in MODULE.permissions {
        assert!(permissions.allows(Role::InstanceAdmin, permission.key));
    }
}

#[test]
fn mod_08_policy_keys_registered() {
    let registered = all_specs()
        .iter()
        .map(|policy| policy.key)
        .collect::<Vec<_>>();
    assert_eq!(MODULE.policy_keys.len(), 14);
    for key in MODULE.policy_keys {
        assert!(
            registered.contains(key),
            "unregistered identity policy key: {key}"
        );
    }
}
