CREATE TABLE public.accounts (
  id                  uuid PRIMARY KEY,
  email               citext NOT NULL UNIQUE,
  email_verified_at   timestamptz,
  display_name        text NOT NULL CHECK (char_length(display_name) BETWEEN 1 AND 80),
  password_hash       text NOT NULL,
  locale              text NOT NULL DEFAULT 'en' CONSTRAINT account_locale_ck CHECK (locale IN ('en','id')),
  timezone            text NOT NULL,
  week_start          smallint NOT NULL DEFAULT 1 CHECK (week_start BETWEEN 1 AND 7),
  is_instance_admin   boolean NOT NULL DEFAULT false,
  status              text NOT NULL DEFAULT 'active' CONSTRAINT account_status_ck CHECK (status IN ('active','disabled','deleting')),
  totp_enabled        boolean NOT NULL DEFAULT false,
  last_login_at       timestamptz,
  failed_login_count  int NOT NULL DEFAULT 0,
  failed_window_start timestamptz,
  locked_until        timestamptz,
  created_at          timestamptz NOT NULL DEFAULT now(),
  updated_at          timestamptz NOT NULL DEFAULT now(),
  version             bigint NOT NULL DEFAULT 1
);

CREATE TABLE public.refresh_token_families (
  id                  uuid PRIMARY KEY,
  account_id          uuid NOT NULL REFERENCES public.accounts(id) ON DELETE CASCADE,
  device_label        text,
  mfa                 boolean NOT NULL DEFAULT false,
  created_at          timestamptz NOT NULL,
  last_used_at        timestamptz NOT NULL,
  absolute_expires_at timestamptz NOT NULL,
  revoked_at          timestamptz,
  revoked_reason      text CONSTRAINT rtf_reason_ck CHECK (revoked_reason IN ('logout','reuse_detected','password_changed','admin','expired'))
);

CREATE TABLE public.refresh_tokens (
  id              uuid PRIMARY KEY,
  family_id       uuid NOT NULL REFERENCES public.refresh_token_families(id) ON DELETE CASCADE,
  account_id      uuid NOT NULL REFERENCES public.accounts(id) ON DELETE CASCADE,
  token_hash      bytea NOT NULL UNIQUE,
  issued_at       timestamptz NOT NULL,
  idle_expires_at timestamptz NOT NULL,
  used_at         timestamptz,
  replaced_by     uuid REFERENCES public.refresh_tokens(id)
);
CREATE INDEX refresh_tokens_family ON public.refresh_tokens (family_id);

CREATE TABLE public.recovery_codes (
  id         uuid PRIMARY KEY,
  account_id uuid NOT NULL REFERENCES public.accounts(id) ON DELETE CASCADE,
  code_hash  bytea NOT NULL,
  used_at    timestamptz,
  created_at timestamptz NOT NULL
);

CREATE TABLE public.password_resets (
  id         uuid PRIMARY KEY,
  account_id uuid NOT NULL REFERENCES public.accounts(id) ON DELETE CASCADE,
  token_hash bytea NOT NULL UNIQUE,
  expires_at timestamptz NOT NULL,
  used_at    timestamptz,
  created_by uuid,
  created_at timestamptz NOT NULL
);

CREATE TABLE public.invites (
  id         uuid PRIMARY KEY,
  token_hash bytea NOT NULL UNIQUE,
  email      citext,
  created_by uuid REFERENCES public.accounts(id) ON DELETE SET NULL,
  expires_at timestamptz NOT NULL,
  used_at    timestamptz,
  created_at timestamptz NOT NULL
);

CREATE TABLE public.account_exports (
  id         uuid PRIMARY KEY,
  account_id uuid NOT NULL REFERENCES public.accounts(id) ON DELETE CASCADE,
  state      text NOT NULL CONSTRAINT export_state_ck CHECK (state IN ('queued','running','ready','failed','expired')),
  bytes      bytea,
  size_bytes bigint,
  created_at timestamptz NOT NULL,
  expires_at timestamptz NOT NULL
);

ALTER TABLE public.accounts ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.accounts FORCE ROW LEVEL SECURITY;
CREATE POLICY tenant_isolation ON public.accounts
  USING (id = NULLIF(current_setting('app.account_id', true), '')::uuid)
  WITH CHECK (id = NULLIF(current_setting('app.account_id', true), '')::uuid);
CREATE POLICY system_auth ON public.accounts TO kinevo_system USING (true) WITH CHECK (true);

ALTER TABLE public.refresh_token_families ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.refresh_token_families FORCE ROW LEVEL SECURITY;
CREATE POLICY tenant_isolation ON public.refresh_token_families
  USING (account_id = NULLIF(current_setting('app.account_id', true), '')::uuid)
  WITH CHECK (account_id = NULLIF(current_setting('app.account_id', true), '')::uuid);
CREATE POLICY system_auth ON public.refresh_token_families TO kinevo_system USING (true) WITH CHECK (true);

ALTER TABLE public.refresh_tokens ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.refresh_tokens FORCE ROW LEVEL SECURITY;
CREATE POLICY tenant_isolation ON public.refresh_tokens
  USING (account_id = NULLIF(current_setting('app.account_id', true), '')::uuid)
  WITH CHECK (account_id = NULLIF(current_setting('app.account_id', true), '')::uuid);
CREATE POLICY system_auth ON public.refresh_tokens TO kinevo_system USING (true) WITH CHECK (true);

ALTER TABLE public.recovery_codes ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.recovery_codes FORCE ROW LEVEL SECURITY;
CREATE POLICY tenant_isolation ON public.recovery_codes
  USING (account_id = NULLIF(current_setting('app.account_id', true), '')::uuid)
  WITH CHECK (account_id = NULLIF(current_setting('app.account_id', true), '')::uuid);

ALTER TABLE public.password_resets ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.password_resets FORCE ROW LEVEL SECURITY;
CREATE POLICY tenant_isolation ON public.password_resets
  USING (account_id = NULLIF(current_setting('app.account_id', true), '')::uuid)
  WITH CHECK (account_id = NULLIF(current_setting('app.account_id', true), '')::uuid);
CREATE POLICY system_auth ON public.password_resets TO kinevo_system USING (true) WITH CHECK (true);

ALTER TABLE public.account_exports ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.account_exports FORCE ROW LEVEL SECURITY;
CREATE POLICY tenant_isolation ON public.account_exports
  USING (account_id = NULLIF(current_setting('app.account_id', true), '')::uuid)
  WITH CHECK (account_id = NULLIF(current_setting('app.account_id', true), '')::uuid);

GRANT SELECT, INSERT, UPDATE, DELETE ON TABLE
  public.accounts,
  public.refresh_token_families,
  public.refresh_tokens,
  public.recovery_codes,
  public.password_resets,
  public.account_exports
TO kinevo_app;
GRANT SELECT, INSERT, UPDATE ON TABLE public.invites TO kinevo_app;

GRANT SELECT, INSERT, UPDATE ON TABLE
  public.accounts,
  public.refresh_token_families,
  public.refresh_tokens,
  public.password_resets
TO kinevo_system;
GRANT SELECT ON TABLE public.invites TO kinevo_system;
