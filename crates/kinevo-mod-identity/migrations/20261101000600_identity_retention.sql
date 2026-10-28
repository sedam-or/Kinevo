-- System jobs prune only auth rows covered by the existing system_auth RLS policy.
GRANT DELETE ON TABLE
  public.refresh_token_families,
  public.password_resets
TO kinevo_system;
