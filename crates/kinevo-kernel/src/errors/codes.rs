//! Stable public and process-level error codes from the error registry.

use super::RetryClass;

macro_rules! define_error_codes {
    ($($variant:ident => ($code:literal, $status:literal, $retry:ident),)+) => {
        #[derive(Clone, Copy, Debug, Eq, Hash, PartialEq, serde::Serialize, serde::Deserialize, thiserror::Error)]
        #[serde(rename_all = "snake_case")]
        pub enum ErrorCode {
            $(#[error($code)] $variant,)+
        }

        impl ErrorCode {
            pub const ALL: &'static [Self] = &[$(Self::$variant,)+];

            pub const fn as_str(self) -> &'static str {
                match self {
                    $(Self::$variant => $code,)+
                }
            }

            pub const fn http_status(self) -> u16 {
                match self {
                    $(Self::$variant => $status,)+
                }
            }

            pub const fn retry(self) -> RetryClass {
                match self {
                    $(Self::$variant => RetryClass::$retry,)+
                }
            }
        }
    };
}

// SPEC: ERR-01, ERR-02, API-20
define_error_codes! {
    Unauthenticated => ("unauthenticated", 401, Reauth),
    Forbidden => ("forbidden", 403, Never),
    NotFound => ("not_found", 404, Never),
    ValidationFailed => ("validation_failed", 422, Never),
    UnknownField => ("unknown_field", 422, Never),
    FieldNotNullable => ("field_not_nullable", 422, Never),
    UnsupportedMediaType => ("unsupported_media_type", 415, Never),
    PayloadTooLarge => ("payload_too_large", 413, Never),
    CursorInvalid => ("cursor_invalid", 400, Never),
    SortInvalid => ("sort_invalid", 422, Never),
    QueryParamUnknown => ("query_param_unknown", 422, Never),
    WindowTooLarge => ("window_too_large", 422, Never),
    DateOutOfRange => ("date_out_of_range", 422, Never),
    VersionConflict => ("version_conflict", 409, AfterChange),
    PreconditionRequired => ("precondition_required", 428, Never),
    IdempotencyKeyReuse => ("idempotency_key_reuse", 422, Never),
    IdempotencyInProgress => ("idempotency_in_progress", 409, Backoff),
    RateLimited => ("rate_limited", 429, Backoff),
    InternalError => ("internal_error", 500, Backoff),
    ServiceUnavailable => ("service_unavailable", 503, Backoff),
    ModuleDisabled => ("module_disabled", 404, Never),
    InvalidTransition => ("invalid_transition", 409, AfterChange),
    SetupRequired => ("setup_required", 409, AfterChange),
    SetupAlreadyCompleted => ("setup_already_completed", 409, Never),
    SetupTokenInvalid => ("setup_token_invalid", 422, Never),
    TemplateInvalid => ("template_invalid", 422, Never),
    ModuleCoreLocked => ("module_core_locked", 409, Never),
    ModuleDependencyUnmet => ("module_dependency_unmet", 409, AfterChange),
    ModuleRequiredBy => ("module_required_by", 409, AfterChange),
    PolicyUnknown => ("policy_unknown", 404, Never),
    PolicyValueInvalid => ("policy_value_invalid", 422, Never),
    PolicyScopeInvalid => ("policy_scope_invalid", 422, Never),
    PolicyEffectiveInPast => ("policy_effective_in_past", 422, Never),
    PolicyEffectiveOrder => ("policy_effective_order", 422, Never),
    SecretNameUnknown => ("secret_name_unknown", 422, Never),
    NotDead => ("not_dead", 409, Never),
    RegistrationClosed => ("registration_closed", 403, Never),
    InviteInvalid => ("invite_invalid", 422, Never),
    EmailTaken => ("email_taken", 409, Never),
    InvalidCredentials => ("invalid_credentials", 401, Never),
    MfaRequired => ("mfa_required", 401, AfterChange),
    InvalidMfaCode => ("invalid_mfa_code", 401, Never),
    CurrentPasswordIncorrect => ("current_password_incorrect", 422, Never),
    TotpCodeInvalid => ("totp_code_invalid", 422, Never),
    AccountLocked => ("account_locked", 429, Backoff),
    AccountDisabled => ("account_disabled", 403, Never),
    RefreshReuseDetected => ("refresh_reuse_detected", 401, Reauth),
    CsrfCheckFailed => ("csrf_check_failed", 403, Never),
    ResetTokenInvalid => ("reset_token_invalid", 422, Never),
    TotpAlreadyEnabled => ("totp_already_enabled", 409, Never),
    TotpRequiredByPolicy => ("totp_required_by_policy", 409, Never),
    ExportInProgress => ("export_in_progress", 409, AfterChange),
    ExportNotReady => ("export_not_ready", 409, AfterChange),
    ExportExpired => ("export_expired", 410, Never),
    LastAdmin => ("last_admin", 409, Never),
    WorkspaceNameTaken => ("workspace_name_taken", 409, Never),
    WorkspaceLimitReached => ("workspace_limit_reached", 409, AfterChange),
    WorkspaceArchived => ("workspace_archived", 409, AfterChange),
    WorkspaceIsDefault => ("workspace_is_default", 409, Never),
    WorkspaceNotEmpty => ("workspace_not_empty", 409, AfterChange),
    GoalHasTasks => ("goal_has_tasks", 409, AfterChange),
    GoalNotActive => ("goal_not_active", 409, AfterChange),
    ReorderSetMismatch => ("reorder_set_mismatch", 422, Never),
    MilestoneAfterGoal => ("milestone_after_goal", 422, Never),
    TaskGoalWorkspaceMismatch => ("task_goal_workspace_mismatch", 422, Never),
    TaskMilestoneGoalMismatch => ("task_milestone_goal_mismatch", 422, Never),
    MilestoneRequiresGoal => ("milestone_requires_goal", 422, Never),
    AnchorExists => ("anchor_exists", 409, AfterChange),
    TaskHasRunningSession => ("task_has_running_session", 409, AfterChange),
    SubtaskLimitReached => ("subtask_limit_reached", 409, Never),
    RruleInvalid => ("rrule_invalid", 422, Never),
    OverrideRequiresRecurring => ("override_requires_recurring", 422, Never),
    OverrideRangeInvalid => ("override_range_invalid", 422, Never),
    IcsTooLarge => ("ics_too_large", 413, Never),
    IcsInvalid => ("ics_invalid", 422, Never),
    IcsTooManyEvents => ("ics_too_many_events", 422, Never),
    SchedulerBusy => ("scheduler_busy", 409, Backoff),
    NoEligibleTasks => ("no_eligible_tasks", 422, AfterChange),
    ScheduleVersionConflict => ("schedule_version_conflict", 409, AfterChange),
    DraftInfeasible => ("draft_infeasible", 409, AfterChange),
    DraftNotPending => ("draft_not_pending", 409, AfterChange),
    TaskIdsNotInDraft => ("task_ids_not_in_draft", 422, Never),
    PlacementInvalid => ("placement_invalid", 422, AfterChange),
    TaskNotSchedulable => ("task_not_schedulable", 409, AfterChange),
    TaskInProgress => ("task_in_progress", 409, AfterChange),
    SessionAlreadyRunning => ("session_already_running", 409, AfterChange),
    TaskNotStartable => ("task_not_startable", 409, AfterChange),
    SessionNotRunning => ("session_not_running", 409, AfterChange),
    EndedAtInvalid => ("ended_at_invalid", 422, Never),
    SyncProtocolUnsupported => ("sync_protocol_unsupported", 422, Reauth),
    SyncBatchTooLarge => ("sync_batch_too_large", 422, Never),
    SyncPayloadTooLarge => ("sync_payload_too_large", 413, Never),
    OperationIdReused => ("operation_id_reused", 422, Never),
    OpUnsupported => ("op_unsupported", 422, Never),
    EntityIdTaken => ("entity_id_taken", 409, Never),
    DependencyFailed => ("dependency_failed", 422, Never),
    AiNotConfigured => ("ai_not_configured", 409, AfterChange),
    AiLimitReached => ("ai_limit_reached", 429, AfterChange),
    AiProviderUnavailable => ("ai_provider_unavailable", 503, Backoff),
    AiProviderTimeout => ("ai_provider_timeout", 504, Backoff),
    AiOutputInvalid => ("ai_output_invalid", 422, AfterChange),
    AiPromptTooLarge => ("ai_prompt_too_large", 422, AfterChange),
    AiByokDisabled => ("ai_byok_disabled", 403, Never),
    AiHostNotAllowed => ("ai_host_not_allowed", 422, Never),
    ProposalNotPending => ("proposal_not_pending", 409, AfterChange),
    NoteDocInvalid => ("note_doc_invalid", 422, Never),
    NoteTooLarge => ("note_too_large", 413, Never),
    QueryTooShort => ("query_too_short", 422, Never),
    QueryTooLong => ("query_too_long", 422, Never),
    LinkTargetNotFound => ("link_target_not_found", 404, Never),
    LinkLimitReached => ("link_limit_reached", 409, Never),
    CanvasSceneInvalid => ("canvas_scene_invalid", 422, Never),
    CanvasTooLarge => ("canvas_too_large", 413, Never),
    CanvasFileInvalid => ("canvas_file_invalid", 422, Never),
    CanvasFileTooLarge => ("canvas_file_too_large", 413, Never),
    CanvasFilesLimit => ("canvas_files_limit", 409, Never),
    PlanUnknown => ("plan_unknown", 404, Never),
    BillingNotConfigured => ("billing_not_configured", 409, Never),
    GatewayUnavailable => ("gateway_unavailable", 503, Backoff),
    AlreadySubscribed => ("already_subscribed", 409, AfterChange),
    SignatureInvalid => ("signature_invalid", 401, Never),
    GatewayUnknown => ("gateway_unknown", 404, Never),
}

macro_rules! define_string_codes {
    ($name:ident { $($variant:ident => $code:literal,)+ }) => {
        #[derive(Clone, Copy, Debug, Eq, Hash, PartialEq, serde::Serialize, serde::Deserialize)]
        #[serde(rename_all = "snake_case")]
        pub enum $name { $($variant,)+ }

        impl $name {
            pub const ALL: &'static [Self] = &[$(Self::$variant,)+];

            pub const fn as_str(self) -> &'static str {
                match self { $(Self::$variant => $code,)+ }
            }
        }
    };
}

// SPEC: API-21, VAL-01
define_string_codes! {
    FieldCode {
        UnknownField => "unknown_field",
        Required => "required",
        TooLong => "too_long",
        TooShort => "too_short",
        InvalidCharacters => "invalid_characters",
        EmailInvalid => "email_invalid",
        UuidInvalid => "uuid_invalid",
        UrlInvalid => "url_invalid",
        TimezoneInvalid => "timezone_invalid",
        InstantInvalid => "instant_invalid",
        DateInvalid => "date_invalid",
        TimeInvalid => "time_invalid",
        IntInvalid => "int_invalid",
        OutOfRange => "out_of_range",
        MoneyInvalid => "money_invalid",
        EnumInvalid => "enum_invalid",
        BoolInvalid => "bool_invalid",
        DigitsInvalid => "digits_invalid",
        TokenInvalid => "token_invalid",
        TooManyItems => "too_many_items",
        DuplicateItem => "duplicate_item",
        SchemaInvalid => "schema_invalid",
        PasswordTooShort => "password_too_short",
        PasswordTooCommon => "password_too_common",
        KeyInvalid => "key_invalid",
    }
}

// SPEC: ERR-05
define_string_codes! {
    ProcessCode {
        ConfigInvalid => "config_invalid",
        IsolationCheckFailed => "isolation_check_failed",
        MigrationFailed => "migration_failed",
        UnmappedConstraint => "unmapped_constraint",
    }
}
