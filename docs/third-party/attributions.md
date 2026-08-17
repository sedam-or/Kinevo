# Third-Party Attributions

### Purpose
Human-readable attribution list for OSS components used by LIFESYNC OS.

### Governance
This file is updated as dependencies are introduced. Attribution wording is copied
from the license/repository when required; the implementation MUST NOT invent
attribution text.

### Entry template
```text
Component:
Repository:
Version/Commit:
License:
Used for:
Modifications:
Attribution text:
License file retained at:
```

## Laravel

- **Component:** Laravel framework
- **Repository:** https://github.com/laravel/framework
- **Version/Commit:** `^13.17` per `server/composer.lock`
- **License:** MIT
- **Used for:** PHP backend, modular monolith application
- **Modifications:** None (skeleton scaffold used as project base)
- **Attribution text:** Copyright (c) Taylor Otwell and contributors
- **License file retained at:** vendor/laravel/framework/LICENSE.md

## Laravel Sanctum

- **Component:** Laravel Sanctum
- **Repository:** https://github.com/laravel/sanctum
- **Version/Commit:** `^4.3` per `server/composer.lock`
- **License:** MIT
- **Used for:** Bearer-token API authentication
- **Modifications:** None
- **Attribution text:** Copyright (c) Taylor Otwell and contributors
- **License file retained at:** vendor/laravel/sanctum/LICENSE.md

## Vue 3

- **Component:** Vue.js (v3)
- **Repository:** https://github.com/vuejs/core
- **Version/Commit:** pin at integration
- **License:** MIT
- **Used for:** Frontend UI framework
- **Modifications:** None
- **Attribution text:** Copyright (c) 2013-present, Yuxi (Evan) You
- **License file retained at:** node_modules/vue/LICENSE

## Tailwind CSS

- **Component:** Tailwind CSS
- **Repository:** https://github.com/tailwindlabs/tailwindcss
- **Version/Commit:** `^4.0` per `server/package.json`
- **License:** MIT
- **Used for:** Styling
- **Modifications:** None
- **Attribution text:** Copyright (c) Tailwind Labs, Inc.
- **License file retained at:** node_modules/tailwindcss/LICENSE

## PostgreSQL

- **Component:** PostgreSQL
- **Repository:** https://git.postgresql.org/gitweb/?p=postgresql.git
- **Version/Commit:** 17 (infrastructure/docker-compose.yml)
- **License:** PostgreSQL License (permissive)
- **Used for:** Canonical database
- **Modifications:** None
- **Attribution text:** PostgreSQL Global Development Group
- **License file retained at:** n/a (distribution image)

## Pending integrations

The following are planned but not yet integrated. Attribution entries MUST be
added when they are adopted:

- **Excalidraw** (MIT) — canvas engine, behind a bounded adapter.
- **Tiptap** (MIT) — editor engine, behind a LIFESYNC adapter.
- **ProseMirror** ecosystem (MIT/BSD) — Tiptap dependency chain.
- **Ollama** — optional local AI runtime.