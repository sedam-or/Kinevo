CREATE TABLE public.instance_settings (
  id               smallint PRIMARY KEY CHECK (id = 1),
  setup_completed  boolean NOT NULL DEFAULT false,
  setup_token_hash bytea,
  base_url         text,
  default_locale   text NOT NULL DEFAULT 'en' CONSTRAINT instance_locale_ck CHECK (default_locale IN ('en','id')),
  template_id      text,
  created_at       timestamptz NOT NULL DEFAULT now(),
  updated_at       timestamptz NOT NULL DEFAULT now(),
  version          bigint NOT NULL DEFAULT 1
);

CREATE TABLE public.instance_modules (
  module_key  text PRIMARY KEY CHECK (module_key ~ '^[a-z][a-z0-9_]{1,23}$'),
  enabled     boolean NOT NULL,
  updated_at  timestamptz NOT NULL DEFAULT now(),
  updated_by  uuid REFERENCES public.accounts(id) ON DELETE SET NULL,
  version     bigint NOT NULL DEFAULT 1
);

CREATE TABLE public.policy_versions (
  id             uuid PRIMARY KEY,
  scope          text NOT NULL CONSTRAINT policy_scope_ck CHECK (scope IN ('instance','account')),
  account_id     uuid REFERENCES public.accounts(id) ON DELETE CASCADE,
  key            text NOT NULL CHECK (key ~ '^[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*)+$'),
  value          jsonb NOT NULL,
  effective_from timestamptz NOT NULL,
  created_by     uuid REFERENCES public.accounts(id) ON DELETE SET NULL,
  created_at     timestamptz NOT NULL DEFAULT now(),
  CONSTRAINT policy_scope_account_ck CHECK ((scope = 'instance') = (account_id IS NULL))
);
CREATE INDEX policy_versions_lookup ON public.policy_versions (key, scope, account_id, effective_from DESC, id DESC);

CREATE TABLE public.secrets (
  id          uuid PRIMARY KEY,
  account_id  uuid REFERENCES public.accounts(id) ON DELETE CASCADE,
  name        text NOT NULL,
  ciphertext  bytea NOT NULL,
  nonce       bytea NOT NULL,
  key_id      smallint NOT NULL,
  last4       text,
  created_at  timestamptz NOT NULL DEFAULT now(),
  updated_at  timestamptz NOT NULL DEFAULT now(),
  UNIQUE NULLS NOT DISTINCT (account_id, name)
);

ALTER TABLE public.policy_versions ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.policy_versions FORCE ROW LEVEL SECURITY;
CREATE POLICY tenant_isolation ON public.policy_versions
  USING (account_id IS NULL OR account_id = NULLIF(current_setting('app.account_id', true), '')::uuid)
  WITH CHECK (account_id = NULLIF(current_setting('app.account_id', true), '')::uuid
              OR (account_id IS NULL AND current_setting('app.instance_admin', true) = 'on'));

ALTER TABLE public.secrets ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.secrets FORCE ROW LEVEL SECURITY;
CREATE POLICY tenant_isolation ON public.secrets
  USING (account_id = NULLIF(current_setting('app.account_id', true), '')::uuid)
  WITH CHECK (account_id = NULLIF(current_setting('app.account_id', true), '')::uuid);
CREATE POLICY instance_secrets_read ON public.secrets FOR SELECT
  USING (account_id IS NULL);
CREATE POLICY instance_secrets_write ON public.secrets FOR ALL
  USING (account_id IS NULL AND current_setting('app.instance_admin', true) = 'on')
  WITH CHECK (account_id IS NULL AND current_setting('app.instance_admin', true) = 'on');

INSERT INTO public.instance_settings (id, setup_completed)
VALUES (1, false)
ON CONFLICT (id) DO NOTHING;

REVOKE ALL ON TABLE
  public.instance_settings,
  public.instance_modules,
  public.policy_versions,
  public.secrets
FROM PUBLIC, kinevo_app, kinevo_system;

GRANT SELECT, INSERT, UPDATE ON TABLE public.instance_settings, public.instance_modules TO kinevo_app;
GRANT SELECT ON TABLE public.instance_settings, public.instance_modules TO kinevo_system;
GRANT SELECT, INSERT ON TABLE public.policy_versions TO kinevo_app;
GRANT SELECT ON TABLE public.policy_versions TO kinevo_system;
GRANT SELECT, INSERT, UPDATE, DELETE ON TABLE public.secrets TO kinevo_app;
GRANT SELECT ON TABLE public.secrets TO kinevo_system;
