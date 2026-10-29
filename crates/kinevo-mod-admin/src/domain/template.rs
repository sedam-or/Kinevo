//! Strict data-only setup templates.

use std::collections::{BTreeMap, BTreeSet};

use kinevo_kernel::{
    errors::{AppError, DetailValue, ErrorCode},
    policy::{PolicySpec, Scope, validate_scope, validate_value},
};
use kinevo_ports::module::{ModuleDescriptor, ModuleKind, validate_graph};
use serde::Deserialize;
use serde_json::Value;

/// First-account sample dataset selected by a setup template.
#[derive(Clone, Copy, Debug, Eq, PartialEq, Deserialize)]
#[serde(rename_all = "snake_case")]
pub enum SampleId {
    /// Synthetic student records.
    Student,
    /// Synthetic professional records.
    Professional,
    /// No sample records.
    Empty,
}

/// Registry inputs required to validate a template against the compiled application.
pub struct ValidationCtx<'a> {
    module_descriptors: &'a [&'a ModuleDescriptor],
    policy_specs: &'a [&'a PolicySpec],
    terminology_keys: &'a [&'a str],
}

impl<'a> ValidationCtx<'a> {
    /// Supplies the module, policy, and terminology registries used by validation.
    #[must_use]
    pub const fn new(
        module_descriptors: &'a [&'a ModuleDescriptor],
        policy_specs: &'a [&'a PolicySpec],
        terminology_keys: &'a [&'a str],
    ) -> Self {
        Self {
            module_descriptors,
            policy_specs,
            terminology_keys,
        }
    }
}

/// A validated setup template. Toggleable modules are normalized to explicit booleans.
#[derive(Clone, Debug, PartialEq)]
pub struct Template {
    /// Stable template identifier.
    pub id: String,
    /// Message key for the template name.
    pub name_key: String,
    /// Message key for the template description.
    pub description_key: String,
    /// Toggleable modules and their selected state.
    pub modules: BTreeMap<String, bool>,
    /// Registered policy presets.
    pub policies: BTreeMap<String, Value>,
    /// Registered terminology labels, represented by catalogue message keys.
    pub terms: BTreeMap<String, String>,
    /// Optional first-account sample dataset.
    pub sample: Option<SampleId>,
    pub(crate) policy_scopes: BTreeMap<String, Vec<Scope>>,
}

#[derive(Deserialize)]
#[serde(deny_unknown_fields)]
struct RawTemplate {
    id: String,
    name_key: String,
    description_key: String,
    #[serde(default)]
    modules: BTreeMap<String, bool>,
    #[serde(default)]
    policies: BTreeMap<String, Value>,
    #[serde(default)]
    terms: BTreeMap<String, String>,
    #[serde(default)]
    sample: Option<SampleId>,
}

/// Parses a strict TOML template and validates all registry-backed fields.
pub fn parse_template(src: &str, ctx: &ValidationCtx<'_>) -> Result<Template, AppError> {
    let deserializer = toml::Deserializer::parse(src).map_err(|_| invalid("template"))?;
    let raw: RawTemplate = serde_path_to_error::deserialize(deserializer)
        .map_err(|error| invalid(sanitize_serde_path(error.path().to_string())))?;

    validate_template_identity(&raw)?;
    let modules = validate_modules(&raw.modules, ctx)?;
    let policy_scopes = validate_policies(&raw.policies, ctx)?;
    validate_terms(&raw.terms, ctx)?;

    Ok(Template {
        id: raw.id,
        name_key: raw.name_key,
        description_key: raw.description_key,
        modules,
        policies: raw.policies,
        terms: raw.terms,
        sample: raw.sample,
        policy_scopes,
    })
}

fn validate_template_identity(raw: &RawTemplate) -> Result<(), AppError> {
    if !valid_id(&raw.id) {
        return Err(invalid("id"));
    }
    if !valid_message_key(&raw.name_key) {
        return Err(invalid("name_key"));
    }
    if !valid_message_key(&raw.description_key) {
        return Err(invalid("description_key"));
    }
    Ok(())
}

fn validate_modules(
    raw_modules: &BTreeMap<String, bool>,
    ctx: &ValidationCtx<'_>,
) -> Result<BTreeMap<String, bool>, AppError> {
    validate_graph(ctx.module_descriptors).map_err(|_| invalid("modules"))?;
    let descriptors = ctx
        .module_descriptors
        .iter()
        .map(|descriptor| (descriptor.key, *descriptor))
        .collect::<BTreeMap<_, _>>();
    let mut modules = BTreeMap::new();
    let mut enabled = ctx
        .module_descriptors
        .iter()
        .filter(|descriptor| descriptor.kind == ModuleKind::Core)
        .map(|descriptor| descriptor.key.to_owned())
        .collect::<BTreeSet<_>>();

    for (key, want_enabled) in raw_modules {
        let Some(descriptor) = descriptors.get(key.as_str()) else {
            return Err(invalid(map_path("modules", key)));
        };
        if descriptor.kind == ModuleKind::Core {
            if !want_enabled {
                return Err(invalid(map_path("modules", key)));
            }
            continue;
        }
        modules.insert(key.clone(), *want_enabled);
        if *want_enabled {
            enabled.insert(key.clone());
        }
    }

    for descriptor in ctx
        .module_descriptors
        .iter()
        .filter(|descriptor| descriptor.kind == ModuleKind::Toggleable)
    {
        modules.entry(descriptor.key.to_owned()).or_insert(false);
    }
    for (key, want_enabled) in raw_modules {
        if !want_enabled {
            continue;
        }
        let descriptor = descriptors
            .get(key.as_str())
            .copied()
            .ok_or_else(|| invalid(map_path("modules", key)))?;
        if descriptor
            .requires
            .iter()
            .any(|required| !enabled.contains(*required))
        {
            return Err(invalid(map_path("modules", key)));
        }
    }
    Ok(modules)
}

fn validate_policies(
    raw_policies: &BTreeMap<String, Value>,
    ctx: &ValidationCtx<'_>,
) -> Result<BTreeMap<String, Vec<Scope>>, AppError> {
    let policy_specs = ctx
        .policy_specs
        .iter()
        .map(|spec| (spec.key, *spec))
        .collect::<BTreeMap<_, _>>();
    let mut policy_scopes = BTreeMap::new();
    for (key, value) in raw_policies {
        let Some(spec) = policy_specs.get(key.as_str()) else {
            return Err(invalid(map_path("policies", key)));
        };
        validate_value(spec, value).map_err(|_| invalid(map_path("policies", key)))?;
        if spec.scopes.is_empty() {
            return Err(invalid(map_path("policies", key)));
        }
        let mut scopes = Vec::new();
        for scope in spec.scopes {
            validate_scope(spec, *scope).map_err(|_| invalid(map_path("policies", key)))?;
            if !scopes.contains(scope) {
                scopes.push(*scope);
            }
        }
        policy_scopes.insert(key.clone(), scopes);
    }
    Ok(policy_scopes)
}

fn validate_terms(
    terms: &BTreeMap<String, String>,
    ctx: &ValidationCtx<'_>,
) -> Result<(), AppError> {
    for (key, message_key) in terms {
        if !ctx.terminology_keys.contains(&key.as_str()) {
            return Err(invalid(map_path("terms", key)));
        }
        if !valid_message_key(message_key) {
            return Err(invalid(map_path("terms", key)));
        }
    }
    Ok(())
}

fn invalid(path: impl Into<String>) -> AppError {
    AppError::new(ErrorCode::TemplateInvalid)
        .with_detail("path", DetailValue::OwnedStr(path.into()))
}

fn valid_id(value: &str) -> bool {
    (2..=32).contains(&value.len())
        && value.is_ascii()
        && value.as_bytes().first().is_some_and(u8::is_ascii_lowercase)
        && value
            .bytes()
            .all(|byte| byte.is_ascii_lowercase() || byte.is_ascii_digit() || byte == b'-')
}

fn valid_message_key(value: &str) -> bool {
    !value.is_empty()
        && value.len() <= 160
        && value.split('.').all(|segment| {
            !segment.is_empty()
                && segment.is_ascii()
                && segment
                    .as_bytes()
                    .first()
                    .is_some_and(u8::is_ascii_lowercase)
                && segment
                    .bytes()
                    .all(|byte| byte.is_ascii_lowercase() || byte.is_ascii_digit() || byte == b'_')
        })
}

fn map_path(group: &str, key: &str) -> String {
    if valid_message_key(key) && !key.contains('.') {
        format!("{group}.{key}")
    } else if valid_message_key(key) {
        format!("{group}.\"{key}\"")
    } else {
        group.to_owned()
    }
}

fn sanitize_serde_path(path: String) -> String {
    if !path.is_empty()
        && path.len() <= 160
        && path
            .bytes()
            .all(|byte| byte.is_ascii_alphanumeric() || b"_.[]\"-".contains(&byte))
    {
        path
    } else {
        "template".to_owned()
    }
}
