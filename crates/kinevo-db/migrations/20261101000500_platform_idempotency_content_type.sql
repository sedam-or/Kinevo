-- T-019 replay preserves the media type along with eligible response bytes.
ALTER TABLE public.idempotency_keys
  ADD COLUMN response_content_type text;
