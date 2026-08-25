---
layout: default
title: Kinevo — a personal operating system you actually own
---

<div align="center">

<img src="assets/banner-kinevo-light.svg" alt="Kinevo — Plan. Schedule. Focus. Adapt." width="720">

**A personal operating system you actually own.**
Goals in, chaos out: Kinevo turns long-horizon goals into scheduled, executable
work — with a deterministic scheduling core, an offline-first PWA shell, and
optional local AI that proposes, validates, and never decides.

`GOAL → BREAKDOWN → MILESTONE → TASK → SCHEDULE → TODAY → FOCUS → PROGRESS → ADAPT`

[![Get started on GitHub](https://img.shields.io/badge/Get%20started-github.com%2Fsedam-or%2FKinevo-1B1B18)](https://github.com/sedam-or/Kinevo)
[![CI](https://github.com/sedam-or/Kinevo/actions/workflows/ci.yml/badge.svg)](https://github.com/sedam-or/Kinevo/actions/workflows/ci.yml)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](https://github.com/sedam-or/Kinevo/blob/main/LICENSE)

</div>

## Why Kinevo

- **Deterministic scheduling** — same inputs, same plan, every time. Explainable, testable, never a black box.
- **Offline-first PWA** — a queued mutation outbox keeps you working through bad connections; sync reconciles through the server contract.
- **AI that knows its place** — optional local (Ollama) or remote providers break goals into reviewable milestones. Schema-validated, human-approved.
- **Self-hosted and yours** — single-user, Docker-deployable, data in your PostgreSQL. No cloud lock-in.

## Explore

| | |
| --- | --- |
| [README](https://github.com/sedam-or/Kinevo#readme) | Positioning, features, quick start |
| [Requirements (SRS)](https://github.com/sedam-or/Kinevo/blob/main/docs/SRS.md) | Normative single source of truth |
| [Architecture](https://github.com/sedam-or/Kinevo/blob/main/docs/architecture.md) | System structure and boundaries |
| [API contract](https://github.com/sedam-or/Kinevo/blob/main/docs/api/openapi.yaml) | Versioned OpenAPI |
| [Contributing](https://github.com/sedam-or/Kinevo/blob/main/CONTRIBUTING.md) | Branches, commits, PR rules |
| [Security policy](https://github.com/sedam-or/Kinevo/blob/main/SECURITY.md) | Private vulnerability disclosure |

## Run it

```bash
git clone https://github.com/sedam-or/Kinevo.git
cd Kinevo && make up && make migrate
# → http://localhost:8000
```

---

*Kinevo is pre-1.0 and actively developed — see the [task board](https://github.com/sedam-or/Kinevo/blob/main/TASK.md). MIT licensed.*
