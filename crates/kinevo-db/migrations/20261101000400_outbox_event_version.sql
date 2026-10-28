-- Store the event schema version required to route subscribers by (kind, v).
ALTER TABLE public.outbox_events
    ADD COLUMN v smallint NOT NULL DEFAULT 1
        CONSTRAINT outbox_events_v_ck CHECK (v > 0);

ALTER TABLE public.outbox_events
    ALTER COLUMN v DROP DEFAULT;
