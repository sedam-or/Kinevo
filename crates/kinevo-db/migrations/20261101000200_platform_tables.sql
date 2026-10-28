-- D-44: platform tables follow the identity schema and its accounts table.

CREATE TABLE public.audit_log (
  id            uuid PRIMARY KEY,
  account_id    uuid REFERENCES public.accounts(id) ON DELETE SET NULL,
  actor_id      uuid,
  action        text NOT NULL,
  target_type   text,
  target_id     text,
  details       jsonb NOT NULL DEFAULT '{}'::jsonb,
  request_id    text,
  occurred_at   timestamptz NOT NULL
);
CREATE INDEX audit_log_by_account ON public.audit_log (account_id, occurred_at DESC);

CREATE TABLE public.outbox_events (
  seq         bigserial PRIMARY KEY,
  id          uuid NOT NULL UNIQUE,
  account_id  uuid REFERENCES public.accounts(id) ON DELETE CASCADE,
  kind        text NOT NULL,
  payload     jsonb NOT NULL,
  created_at  timestamptz NOT NULL
);
CREATE TABLE public.outbox_deliveries (
  event_id        uuid NOT NULL REFERENCES public.outbox_events(id) ON DELETE CASCADE,
  subscriber      text NOT NULL,
  state           text NOT NULL DEFAULT 'pending' CONSTRAINT outbox_state_ck CHECK (state IN ('pending','delivered','dead')),
  attempts        int  NOT NULL DEFAULT 0,
  next_attempt_at timestamptz NOT NULL,
  last_error_code text,
  delivered_at    timestamptz,
  PRIMARY KEY (event_id, subscriber)
);
CREATE INDEX outbox_deliveries_due ON public.outbox_deliveries (next_attempt_at) WHERE state = 'pending';

CREATE TABLE public.jobs (
  id              uuid PRIMARY KEY,
  account_id      uuid REFERENCES public.accounts(id) ON DELETE CASCADE,
  kind            text NOT NULL,
  unique_key      text,
  payload         jsonb NOT NULL DEFAULT '{}'::jsonb,
  state           text NOT NULL DEFAULT 'queued' CONSTRAINT job_state_ck CHECK (state IN ('queued','running','succeeded','failed','dead')),
  run_at          timestamptz NOT NULL,
  attempts        int NOT NULL DEFAULT 0,
  max_attempts    int NOT NULL,
  locked_by       text,
  locked_until    timestamptz,
  last_error_code text,
  created_at      timestamptz NOT NULL,
  finished_at     timestamptz,
  UNIQUE (kind, unique_key)
);
CREATE INDEX jobs_claim ON public.jobs (run_at, id) WHERE state = 'queued';

CREATE TABLE public.idempotency_keys (
  account_id    uuid NOT NULL REFERENCES public.accounts(id) ON DELETE CASCADE,
  key           text NOT NULL CHECK (char_length(key) BETWEEN 8 AND 128),
  method        text NOT NULL,
  path          text NOT NULL,
  request_hash  bytea NOT NULL,
  status_code   smallint,
  response_body bytea,
  created_at    timestamptz NOT NULL,
  expires_at    timestamptz NOT NULL,
  PRIMARY KEY (account_id, key)
);

ALTER TABLE public.audit_log ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.audit_log FORCE ROW LEVEL SECURITY;
CREATE POLICY tenant_isolation ON public.audit_log
  USING (account_id = NULLIF(current_setting('app.account_id', true), '')::uuid)
  WITH CHECK (account_id IS NULL OR account_id = NULLIF(current_setting('app.account_id', true), '')::uuid);
CREATE POLICY admin_context ON public.audit_log FOR SELECT
  USING (current_setting('app.instance_admin', true) = 'on');
CREATE POLICY system_insert ON public.audit_log FOR INSERT TO kinevo_system
  WITH CHECK (true);
CREATE POLICY retention ON public.audit_log FOR DELETE TO kinevo_migrator
  USING (true);

ALTER TABLE public.idempotency_keys ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.idempotency_keys FORCE ROW LEVEL SECURITY;
CREATE POLICY tenant_isolation ON public.idempotency_keys
  USING (account_id = NULLIF(current_setting('app.account_id', true), '')::uuid)
  WITH CHECK (account_id = NULLIF(current_setting('app.account_id', true), '')::uuid);
CREATE POLICY retention ON public.idempotency_keys FOR DELETE TO kinevo_migrator
  USING (expires_at < NULLIF(current_setting('app.retention_cutoff', true), '')::timestamptz);

CREATE FUNCTION public.audit_log_append_only() RETURNS trigger
LANGUAGE plpgsql
SET search_path = pg_catalog, public
AS $$
BEGIN
  IF current_user <> 'kinevo_migrator' THEN
    RAISE EXCEPTION 'audit_log is append-only' USING ERRCODE = '42501';
  END IF;
  IF TG_OP = 'DELETE' THEN
    RETURN OLD;
  END IF;
  RETURN NEW;
END;
$$;
CREATE TRIGGER audit_log_append_only
  BEFORE UPDATE OR DELETE ON public.audit_log
  FOR EACH ROW EXECUTE FUNCTION public.audit_log_append_only();

CREATE FUNCTION public.platform_prune_audit(cutoff timestamptz) RETURNS bigint
LANGUAGE plpgsql SECURITY DEFINER
SET search_path = pg_catalog, public
AS $$
DECLARE deleted_count bigint;
BEGIN
  PERFORM pg_catalog.set_config('app.instance_admin', 'on', true);
  DELETE FROM public.audit_log WHERE occurred_at < cutoff;
  GET DIAGNOSTICS deleted_count = ROW_COUNT;
  RETURN deleted_count;
END;
$$;
CREATE FUNCTION public.platform_prune_idempotency(cutoff timestamptz) RETURNS bigint
LANGUAGE plpgsql SECURITY DEFINER
SET search_path = pg_catalog, public
AS $$
DECLARE deleted_count bigint;
BEGIN
  PERFORM pg_catalog.set_config('app.retention_cutoff', cutoff::text, true);
  DELETE FROM public.idempotency_keys;
  GET DIAGNOSTICS deleted_count = ROW_COUNT;
  RETURN deleted_count;
END;
$$;
CREATE FUNCTION public.platform_prune_jobs(cutoff timestamptz) RETURNS bigint
LANGUAGE plpgsql SECURITY DEFINER
SET search_path = pg_catalog, public
AS $$
DECLARE deleted_count bigint;
BEGIN
  DELETE FROM public.jobs
  WHERE state IN ('succeeded', 'failed', 'dead') AND finished_at < cutoff;
  GET DIAGNOSTICS deleted_count = ROW_COUNT;
  RETURN deleted_count;
END;
$$;
CREATE FUNCTION public.platform_prune_outbox(cutoff timestamptz) RETURNS bigint
LANGUAGE plpgsql SECURITY DEFINER
SET search_path = pg_catalog, public
AS $$
DECLARE deleted_deliveries bigint;
DECLARE deleted_events bigint;
BEGIN
  DELETE FROM public.outbox_deliveries AS delivery
  USING public.outbox_events AS event
  WHERE delivery.event_id = event.id
    AND event.created_at < cutoff
    AND NOT EXISTS (
      SELECT 1 FROM public.outbox_deliveries AS pending
      WHERE pending.event_id = event.id AND pending.state <> 'delivered'
    );
  GET DIAGNOSTICS deleted_deliveries = ROW_COUNT;

  DELETE FROM public.outbox_events AS event
  WHERE event.created_at < cutoff
    AND NOT EXISTS (
      SELECT 1 FROM public.outbox_deliveries AS pending
      WHERE pending.event_id = event.id AND pending.state <> 'delivered'
    );
  GET DIAGNOSTICS deleted_events = ROW_COUNT;
  RETURN deleted_deliveries + deleted_events;
END;
$$;

ALTER FUNCTION public.platform_prune_audit(timestamptz) OWNER TO kinevo_migrator;
ALTER FUNCTION public.platform_prune_idempotency(timestamptz) OWNER TO kinevo_migrator;
ALTER FUNCTION public.platform_prune_jobs(timestamptz) OWNER TO kinevo_migrator;
ALTER FUNCTION public.platform_prune_outbox(timestamptz) OWNER TO kinevo_migrator;
REVOKE ALL ON FUNCTION public.platform_prune_audit(timestamptz) FROM PUBLIC;
REVOKE ALL ON FUNCTION public.platform_prune_idempotency(timestamptz) FROM PUBLIC;
REVOKE ALL ON FUNCTION public.platform_prune_jobs(timestamptz) FROM PUBLIC;
REVOKE ALL ON FUNCTION public.platform_prune_outbox(timestamptz) FROM PUBLIC;
GRANT EXECUTE ON FUNCTION public.platform_prune_audit(timestamptz) TO kinevo_system;
GRANT EXECUTE ON FUNCTION public.platform_prune_idempotency(timestamptz) TO kinevo_system;
GRANT EXECUTE ON FUNCTION public.platform_prune_jobs(timestamptz) TO kinevo_system;
GRANT EXECUTE ON FUNCTION public.platform_prune_outbox(timestamptz) TO kinevo_system;

REVOKE ALL ON TABLE
  public.audit_log,
  public.outbox_events,
  public.outbox_deliveries,
  public.jobs,
  public.idempotency_keys
FROM PUBLIC, kinevo_app, kinevo_system;
GRANT SELECT, INSERT ON TABLE public.audit_log TO kinevo_app;
GRANT INSERT ON TABLE public.audit_log TO kinevo_system;
GRANT SELECT, INSERT ON TABLE
  public.jobs,
  public.outbox_events,
  public.outbox_deliveries
TO kinevo_app;
GRANT SELECT, INSERT, UPDATE ON TABLE public.jobs, public.outbox_deliveries TO kinevo_system;
GRANT SELECT, INSERT ON TABLE public.outbox_events TO kinevo_system;
GRANT SELECT, INSERT, UPDATE ON TABLE public.idempotency_keys TO kinevo_app;
REVOKE ALL ON SEQUENCE public.outbox_events_seq_seq FROM PUBLIC, kinevo_app, kinevo_system;
GRANT USAGE ON SEQUENCE public.outbox_events_seq_seq TO kinevo_app, kinevo_system;
