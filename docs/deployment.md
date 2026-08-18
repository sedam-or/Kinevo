# Kinevo — Deployment & Operations

### Deployment target
Primary reference: Linux Docker-compatible host. Oracle Cloud Always Free is a supported personal deployment profile but not a software architecture dependency.

### Production containers
Recommended:
```text
reverse-proxy
app
queue-worker
scheduler
postgres
ollama (optional)
```

Redis is optional.

### Network exposure
Public:
- 80/443 only through reverse proxy.

Private:
- PostgreSQL.
- Redis.
- Ollama.
- internal application ports.

### Cloudflare
Recommended for:
- DNS;
- TLS edge;
- basic protection;
- origin shielding where appropriate.

### Environment separation
- local;
- staging;
- production.

Secrets MUST be environment-managed, never committed.

### Database
- PostgreSQL timezone-aware timestamps;
- migrations are versioned;
- backups are automated;
- restore must be tested.

### Backup strategy
At minimum:
- daily database backup;
- object storage backup/versioning where supported;
- periodic full JSON/CSV export;
- documented restore procedure.

### Disaster recovery
Exact RPO/RTO MUST be finalized from operational needs. Suggested personal deployment baseline may target RPO <= 24h and RTO <= 4h.

### Monitoring
Monitor:
- app health;
- DB health;
- scheduler runs;
- queue backlog;
- storage failures;
- offline sync failure rates;
- disk utilization;
- memory utilization;
- CPU utilization;
- backup success.

### Oracle profile
For Oracle Always Free personal deployment, size services conservatively around the 2 OCPU / 12 GB RAM baseline. Ollama should use a small quantized model and be loaded on demand where possible. Large coding models belong on the development workstation rather than the free production VPS.

### Upgrade path
When resource pressure appears:
1. profile;
2. reduce unnecessary resident processes;
3. separate workers;
4. add Redis if justified;
5. move object storage off-node;
6. upgrade VPS only after evidence.

### Deployment rules
- immutable container image where practical;
- migrations run as explicit release step;
- health checks before traffic switch;
- rollback plan documented;
- never deploy untested schema changes.

---

