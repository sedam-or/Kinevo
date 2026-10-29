//! Embedded setup-template registry and deterministic application plans.

use std::collections::BTreeSet;

use kinevo_kernel::policy::Scope;
use rust_embed::RustEmbed;
use serde::Serialize;
use serde_json::Value;

use crate::domain::template::{Template, ValidationCtx, parse_template};

#[derive(RustEmbed)]
#[folder = "../../templates/"]
struct EmbeddedTemplates;

/// Read-only listing returned by setup status.
#[derive(Clone, Debug, Eq, PartialEq, Serialize)]
pub struct TemplateListing {
    /// Stable template identifier.
    pub id: String,
    /// Message key for the template name.
    pub name_key: String,
    /// Message key for the template description.
    pub description_key: String,
}

/// Validated templates embedded in the server binary.
#[derive(Clone, Debug, PartialEq)]
pub struct TemplateRegistry {
    templates: Vec<Template>,
}

impl TemplateRegistry {
    /// Loads and validates every embedded TOML template against the supplied registries.
    pub fn embedded(ctx: &ValidationCtx<'_>) -> Result<Self, kinevo_kernel::errors::AppError> {
        let mut assets = EmbeddedTemplates::iter()
            .filter(|name| name.ends_with(".toml"))
            .collect::<Vec<_>>();
        assets.sort();
        let mut templates = Vec::with_capacity(assets.len());
        for asset in assets {
            let Some(file) = EmbeddedTemplates::get(asset.as_ref()) else {
                continue;
            };
            let source = String::from_utf8(file.data.into_owned()).map_err(|_| {
                kinevo_kernel::errors::AppError::new(
                    kinevo_kernel::errors::ErrorCode::TemplateInvalid,
                )
                .with_detail("path", kinevo_kernel::errors::DetailValue::Str("template"))
            })?;
            templates.push(parse_template(&source, ctx)?);
        }
        templates.sort_by(|left, right| left.id.cmp(&right.id));
        let mut ids = BTreeSet::new();
        for template in &templates {
            if !ids.insert(template.id.as_str()) {
                return Err(kinevo_kernel::errors::AppError::new(
                    kinevo_kernel::errors::ErrorCode::TemplateInvalid,
                )
                .with_detail(
                    "path",
                    kinevo_kernel::errors::DetailValue::OwnedStr("id".to_owned()),
                ));
            }
        }
        Ok(Self { templates })
    }

    /// Lists only the fields required by the public setup-status contract.
    #[must_use]
    pub fn list(&self) -> Vec<TemplateListing> {
        self.templates
            .iter()
            .map(|template| TemplateListing {
                id: template.id.clone(),
                name_key: template.name_key.clone(),
                description_key: template.description_key.clone(),
            })
            .collect()
    }

    /// Looks up one template by its stable identifier.
    #[must_use]
    pub fn get(&self, id: &str) -> Option<&Template> {
        self.templates.iter().find(|template| template.id == id)
    }
}

/// Changes needed to apply a validated template to current module and policy state.
#[derive(Clone, Debug, PartialEq)]
pub struct ApplyPlan {
    /// Toggleable module state changes, sorted by module key.
    pub module_changes: Vec<(String, bool)>,
    /// Policy values to write at each registered scope, sorted by policy key.
    pub policy_writes: Vec<(String, Scope, Value)>,
}

/// Produces only module and policy writes that differ from the current state.
#[must_use]
pub fn apply_plan(
    template: &Template,
    enabled: &BTreeSet<String>,
    current: &dyn Fn(&str, Scope) -> Option<Value>,
) -> ApplyPlan {
    let module_changes = template
        .modules
        .iter()
        .filter(|(key, want_enabled)| enabled.contains(*key) != **want_enabled)
        .map(|(key, want_enabled)| (key.clone(), *want_enabled))
        .collect();

    let mut policy_writes = Vec::new();
    for (key, value) in &template.policies {
        if let Some(scopes) = template.policy_scopes.get(key) {
            for scope in scopes {
                if current(key, *scope).as_ref() != Some(value) {
                    policy_writes.push((key.clone(), *scope, value.clone()));
                }
            }
        }
    }

    ApplyPlan {
        module_changes,
        policy_writes,
    }
}
