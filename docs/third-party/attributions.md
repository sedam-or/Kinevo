# Third-Party Attributions

### Purpose
Human-readable attribution list for OSS components used by Kinevo.

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
- **Version/Commit:** `^3.5.41` per `server/package-lock.json`
- **License:** MIT
- **Used for:** Frontend UI framework
- **Modifications:** None
- **Attribution text:** Copyright (c) 2013-present, Yuxi (Evan) You
- **License file retained at:** node_modules/vue/LICENSE

## Tiptap
- **Component:** Tiptap (headless editor engine)
- **Repository:** https://github.com/ueberdosis/tiptap
- **Version/Commit:** `@tiptap/core@3.30.1` per `server/package-lock.json`
- **License:** MIT
- **Used for:** Rich-text editor engine, behind the Kinevo `EditorAdapter` boundary
- **Modifications:** None (used as-is; bounded extension set configured by Kinevo)
- **Attribution text:** Copyright (c) Tiptap contributors
- **License file retained at:** node_modules/@tiptap/core/LICENSE

## TypeScript

- **Component:** TypeScript compiler and language service
- **Repository:** https://github.com/microsoft/TypeScript
- **Version/Commit:** `^5.9.3` per `server/package-lock.json`
- **License:** Apache-2.0
- **Used for:** Frontend static type checking
- **Modifications:** None
- **Attribution text:** Copyright (c) Microsoft Corporation
- **License file retained at:** node_modules/typescript/LICENSE.txt

## ProseMirror

- **Component:** ProseMirror document model (Tiptap dependency chain)
- **Repository:** https://github.com/ProseMirror
- **Version/Commit:** per `server/package-lock.json` (`@tiptap/pm@3.30.1`)
- **License:** MIT
- **Used for:** Canonical structured-JSON document model (SRS §10.2)
- **Modifications:** None
- **Attribution text:** Copyright (c) Marijn Haverbeke and contributors
- **License file retained at:** node_modules/@tiptap/pm/LICENSE

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
- **Ollama** — optional local AI runtime.