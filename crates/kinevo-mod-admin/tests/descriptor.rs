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

#[test]
fn mod_08_nav_keys_exist() {
    let english: serde_json::Value =
        serde_json::from_str(include_str!("../../../web/src/i18n/en.json"))
            .expect("English catalogue should be valid JSON");
    let indonesian: serde_json::Value =
        serde_json::from_str(include_str!("../../../web/src/i18n/id.json"))
            .expect("Indonesian catalogue should be valid JSON");

    assert_eq!(MODULE.nav.len(), 5);
    for item in MODULE.nav {
        assert!(
            MODULE
                .permissions
                .iter()
                .any(|permission| permission.key == item.required_permission),
            "unregistered admin nav permission: {}",
            item.required_permission
        );
        assert!(
            english.get(item.label_key).is_some(),
            "English key {}",
            item.label_key
        );
        assert!(
            indonesian.get(item.label_key).is_some(),
            "Indonesian key {}",
            item.label_key
        );
    }
}
