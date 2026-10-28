//! Pure account-profile patch rules.

use kinevo_kernel::{
    errors::FieldCode,
    id::AccountId,
    validate::{
        bounded_int,
        text::ShortText,
        tz::{IanaTz, Locale},
    },
};
use serde::{Serialize, Serializer, ser::SerializeStruct};

/// Safe profile data returned by the authenticated account endpoints.
#[derive(Clone, Eq, PartialEq)]
pub struct Profile {
    pub id: AccountId,
    pub email: String,
    pub display_name: String,
    pub locale: Locale,
    pub timezone: IanaTz,
    pub week_start: u8,
    pub totp_enabled: bool,
    pub is_instance_admin: bool,
    pub version: i64,
}

impl Serialize for Profile {
    fn serialize<S>(&self, serializer: S) -> Result<S::Ok, S::Error>
    where
        S: Serializer,
    {
        let mut state = serializer.serialize_struct("Profile", 9)?;
        state.serialize_field("id", &self.id.to_string())?;
        state.serialize_field("email", &self.email)?;
        state.serialize_field("display_name", &self.display_name)?;
        state.serialize_field("locale", self.locale.as_str())?;
        state.serialize_field("timezone", self.timezone.as_str())?;
        state.serialize_field("week_start", &self.week_start)?;
        state.serialize_field("totp_enabled", &self.totp_enabled)?;
        state.serialize_field("is_instance_admin", &self.is_instance_admin)?;
        state.serialize_field("version", &self.version)?;
        state.end()
    }
}

/// Partial profile input; every supplied value is non-nullable.
#[derive(Default)]
pub struct ProfilePatch {
    pub display_name: Option<String>,
    pub locale: Option<String>,
    pub timezone: Option<String>,
    pub week_start: Option<i64>,
}

/// Applies every supplied patch field and returns all field errors together.
pub fn apply_patch(
    profile: &Profile,
    patch: ProfilePatch,
) -> Result<Profile, Vec<(String, FieldCode)>> {
    let mut next = profile.clone();
    let mut errors = Vec::new();

    if let Some(value) = patch.display_name {
        match ShortText::parse(&value, 1, 80) {
            Ok(value) => value.as_str().clone_into(&mut next.display_name),
            Err(code) => errors.push(("display_name".to_owned(), code)),
        }
    }
    if let Some(value) = patch.locale {
        match Locale::parse(&value) {
            Ok(value) => next.locale = value,
            Err(code) => errors.push(("locale".to_owned(), code)),
        }
    }
    if let Some(value) = patch.timezone {
        match IanaTz::parse(&value) {
            Ok(value) => next.timezone = value,
            Err(code) => errors.push(("timezone".to_owned(), code)),
        }
    }
    if let Some(value) = patch.week_start {
        match bounded_int(value, 1, 7)
            .and_then(|value| u8::try_from(value).map_err(|_| FieldCode::OutOfRange))
        {
            Ok(value) => next.week_start = value,
            Err(code) => errors.push(("week_start".to_owned(), code)),
        }
    }

    if errors.is_empty() {
        Ok(next)
    } else {
        Err(errors)
    }
}
