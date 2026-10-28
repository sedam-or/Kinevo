# Standard — Open-source stack map ("adopt, don't build")

Rule (CQ-16): every dependency appears here with its reason; a dependency not on this map needs a task that adds it (and an entry) first. **Versions marked ✔ were read from the official registries (crates.io / npm) on 2026-10-25**; every other version is chosen by `cargo add` / `npm install` in the first task that needs it and pinned by the lockfile. Licences are **expected** values from the project's own metadata and must be confirmed by `cargo-deny` / `license-checker` in M1 (the repository is AGPL-3.0-only; all entries below are AGPL-compatible: MIT, Apache-2.0, ISC, BSD, MPL-2.0 for tools only, OFL-1.1 for fonts).

## 1. Rust (server, scheduler)

| Capability | Choice | Version ✔ | Licence (expected) | Why / alternatives considered | Main risk |
|---|---|---|---|---|---|
| Language / toolchain | Rust stable, edition 2024, `rust-toolchain.toml` pinning | 1.99.x present locally | MIT/Apache-2.0 | strongest typing for the domain (D-02) | contributor pool smaller than PHP/TS |
| HTTP framework | **axum** | 0.8.9 ✔ | MIT | tokio-native, tower middleware, widely used; actix-web is comparable (own runtime), rejected for tower reuse | minor-version churn (0.x) |
| Async runtime | **tokio** | 1.54.1 ✔ (`macros`, `rt-multi-thread` in `kinevo-db` tests) | MIT | de-facto standard | — |
| Async traits | **async-trait** | 0.1.92 ✔ | MIT/Apache-2.0 | object-safe async methods in `kinevo-ports` | proc-macro expansion and allocation |
| Async utilities | **futures** | 0.3.34 ✔ | MIT/Apache-2.0 | boxed async callbacks in the database test kit | — |
| HTTP middleware | **tower**, **tower-http** (trace, compression, cors, limit, timeout, set-header) | tower-http 0.7.1 ✔ | MIT | avoid hand-written middleware | — |
| Database | **PostgreSQL** 17/18 via **sqlx** (rustls, postgres, migrate, uuid, time off, json) | sqlx 0.9.0 ✔ (`postgres`, `runtime-tokio`, `tls-rustls-ring-native-roots`, `migrate`, `macros`, `uuid` in `kinevo-db`) | MIT/Apache-2.0 | compile-time checked SQL, no ORM hiding RLS/transactions; diesel/sea-orm rejected (heavier abstraction over RLS/transactions) | offline metadata (`cargo sqlx prepare`) must be committed |
| Time & zones | **jiff** (+ embedded tzdb) | 0.2.38 ✔ | Unlicense/MIT | correct DST semantics, IANA zones, pure Rust (no OS tz dependency in Alpine); chrono/time rejected: weaker tz story | 0.x API |
| Serialisation | **serde**, **serde_json** | 1.0.229 ✔ / 1.0.154 ✔ | MIT/Apache-2.0 | standard | — |
| JSON path errors | **serde_path_to_error** | 0.1.20 | MIT/Apache-2.0 | preserve the nested field path when serde rejects a request body | — |
| Canonical JSON (JCS) | **serde_jcs** (or small audited implementation if unmaintained) | resolve in M1 | MIT | RFC 8785 for hashes/goldens | maintenance — fallback to an in-repo ≤ 150-line implementation covered by RFC test vectors |
| Errors | **thiserror** (libraries), **anyhow** (binary glue only) | 2.0.21 ✔ | MIT/Apache-2.0 | CQ-09 | — |
| Logging/tracing | **tracing**, **tracing-subscriber** (json) | 0.1.44 ✔ / 0.3.23 ✔ | MIT | structured logs, span context (request id) | — |
| OpenAPI | **utoipa**, **utoipa-axum** | 6.0.0 ✔ / 0.3.0 ✔ | MIT/Apache-2.0 | generate the contract from handlers (API-06) | schema attribute verbosity |
| IDs | **uuid** (v7) | 1.29.0 ✔ | Apache-2.0/MIT | time-ordered ids (ARCH-25) | — |
| Password hashing | **argon2** (RustCrypto) | 0.6.0 ✔ | MIT/Apache-2.0 | RFC 9106, parameters in §4; embedded SecLists common-password data is MIT licensed (see `crates/kinevo-mod-identity/data/PROVENANCE.md`) | CPU/memory DoS ⇒ concurrency cap |
| Hash/MAC/KDF | **sha2**, **hmac**, **hkdf** | sha2 0.11.1 ✔, hmac 0.13.0 ✔ | MIT/Apache-2.0 | tokens, cursors, HKDF subkeys | — |
| AEAD | **chacha20poly1305** (XChaCha20-Poly1305) | 0.11.0 ✔ | MIT/Apache-2.0 | secrets at rest, random 24-byte nonce | key management ⇒ SEC-03 |
| Encoding | **base64** | 0.23.1 ✔ | MIT/Apache-2.0 | master key and opaque token encoding | — |
| Randomness | **rand_core** `OsRng` / **getrandom** | rand_core 0.10.1 ✔ | MIT/Apache-2.0 | SEC-23 | — |
| Secret hygiene | **zeroize**, **subtle**; own `Secret<T>` wrapper (the `secrecy` crate 0.10.3 ✔ is acceptable if it meets CQ-10; own wrapper selected in T-004) | zeroize 1.9.1 ✔, subtle 2.6.1 ✔ | MIT/Apache-2.0, BSD-3 | zeroise, constant-time compare | — |
| Signed access tokens | **jsonwebtoken** (HS256, ADR-0007) | 11.1.0 ✔ | MIT | single issuer/verifier, no key distribution; EdDSA only if third parties verify (not in 1.0) | alg-confusion ⇒ allow-list algorithm in code |
| TOTP | **totp-rs** | 6.0.0 ✔ | MIT | RFC 6238 | — |
| Rate limiting | **governor** (in a tower layer) | 0.10.4 ✔ | MIT | keyed limiter, in-process; `tower_governor` 0.8.0 ✔ not used (less maintained) | single-node only |
| In-process cache | **moka** | 0.12.16 ✔ | MIT/Apache-2.0 | bounded TTL caches (containers-and-caching) | — |
| IP network parsing | **ipnet** | 2.12.2 ✔ | MIT/Apache-2.0 | parse trusted reverse-proxy CIDR ranges | — |
| Embedded web assets | **rust-embed** | 8.13.0 ✔ | MIT | single binary (ARCH-02) | — |
| CLI | **clap** (derive) | 4.6.7 ✔ | MIT/Apache-2.0 | `kinevo` subcommands | — |
| Outbound HTTP | **reqwest** (rustls, no default features, no redirects for AI) | 0.13.5 ✔ | MIT/Apache-2.0 | AI/gateway/SMTP-adjacent calls | SSRF controls are ours (SEC-15) |
| TLS | **rustls** (via sqlx/reqwest; optional built-in listener) | resolve in M1 | MIT/Apache-2.0 | no OpenSSL dependency (static musl) | — |
| Email | **lettre** (SMTP, rustls) — optional | 0.11.23 ✔ | MIT | D-27 | — |
| JSON Schema | **jsonschema** | 0.60.1 ✔ | MIT | validate AI/imports/docs against `contracts/*.schema.json` | schema-draft support — pin draft 2020-12 |
| ICS | **icalendar** for building/parsing basics; recurrence expansion is **own code** per LAND-R3/R4 (the `rrule` crate 0.14.0 ✔ was evaluated and not adopted: determinism and DST rules must match this spec exactly) | icalendar 0.17.15 ✔ | MIT/Apache-2.0 | adopt for the format, not for semantics | parser leniency ⇒ our bounds and fuzzing |
| Unicode | **unicode-normalization** (NFC/NFKC) | 0.1.26 ✔ | MIT/Apache-2.0 | VAL-02 | — |
| Regex | **regex** (linear time) | 1.13.1 ✔ | MIT/Apache-2.0 | VAL-10 | — |
| URL parsing | **url** | 2.5.8 ✔ | MIT/Apache-2.0 | Standards-compliant parsing and canonicalization for `https_url` validation; hand-written parsing rejected | Keep host and credential edge cases covered by fixtures |
| WASM bindings | **wasm-bindgen** (+ `wasm-pack` tool) | 0.2.129 ✔ | MIT/Apache-2.0 | scheduler preview (D-22) | — |
| Kotlin bindings (deferred) | **uniffi** | 0.32.2 ✔ | MPL-2.0 | only if native screens are justified | — |
| Tests | **nextest**, **proptest**, **insta** | proptest 1.12.0 ✔, insta 1.49.0 ✔ | MIT/Apache-2.0 | `12-testing.md`; one shared PostgreSQL server with a disposable database per test | — |
| Supply-chain tools | **cargo-deny** 0.20.2 ✔, **cargo-audit**, **cargo-llvm-cov**, **cargo-fuzz**, **cargo-mutants**, **cargo-sweep** 0.8.0, **wasm-bindgen-cli**, **typos**, **shellcheck** (system package), **criterion** | tools, not linked | MIT/Apache-2.0; cargo-sweep MIT | SEC-13, TST, build-cache maintenance (D-104) | — |

Deliberately **not** adopted: an ORM, a DI framework, a job-queue crate (the DB queue is ~200 lines over `FOR UPDATE SKIP LOCKED`; Redis/SQS rejected by D-13), a validation-derive crate (newtypes with validating constructors suffice, CQ-06), a cron crate (tick + unique key, ARCH §7), `tower_governor`.

### Technical constants (not business values; changing them is a code change with a test)

| Constant | Value | Reason |
|---|---|---|
| Argon2id | m = 64 MiB, t = 3, p = 1, salt 16 B, output 32 B | OWASP/RFC 9106 second recommended profile range; verify against current OWASP guidance in M1 |
| Access token alg | HS256, `kid` versioned | ADR-0007 |
| AEAD | XChaCha20-Poly1305, 24-byte random nonce | nonce-misuse resistance by size |
| HKDF labels | `kinevo/jwt/v1`, `kinevo/cursor/v1`, `kinevo/secrets/v1`, `kinevo/csrf/v1` | domain separation |
| AI sampling temperature | 0.2 | stable structured output |
| Scheduler algorithm version | `sched-1` | determinism contract |
| Max JSON nesting depth (all parsers) | 64 (notes 12) | DoS bound |

## 2. Web (`web/`)

| Capability | Choice | Version ✔ | Licence (expected) | Why / alternatives | Main risk |
|---|---|---|---|---|---|
| Package manager | **pnpm** (via corepack) | resolve in T-043 | MIT | D-48; strict dependency tree, committed lockfile (SEC-13) | — |
| UI library | **React** + react-dom | 19.3.1 ✔ | MIT | Excalidraw and Tiptap React bindings are first-class (D-07); Vue/Svelte need bridges | — |
| Build | **Vite** | 8.3.5 ✔ | MIT | fast dev, WASM + PWA plugin support | major upgrades |
| Language | **TypeScript** — start on **6.0.3** (latest 6.x ✔); evaluate 7.x (7.0.2 ✔ exists) in a dedicated upgrade task once tooling (Biome, Vite, vue-tsc-equivalents, vitest) is confirmed compatible | 6.0.3 ✔ | Apache-2.0 | stability first | tooling lag for 7 |
| Routing | **@tanstack/react-router** | 1.170.46 ✔ | MIT | typed routes + search params as state | pre-2.0 API churn |
| Server state | **@tanstack/react-query** (+ persist) | 5.104.5 ✔ | MIT | caching, retries, offline persistence | — |
| Accessible primitives | **react-aria-components** | 1.22.1 ✔ | Apache-2.0 | WCAG-focused behaviours, i18n, date/time pickers; Radix/Headless UI considered | bundle size ⇒ code split |
| Styling | **Tailwind CSS 4** + CSS variables (tokens) | 4.3.3 ✔ | MIT | design-token driven utilities | — |
| Forms / validation | **react-hook-form** + **zod** | zod 4.6.7 ✔ | MIT | VAL-C1 | zod major changes |
| i18n | **i18next**, **react-i18next** (ICU via `i18next-icu`) | 26.5.10 ✔ / 17.1.8 ✔ | MIT | FE-05 | — |
| Offline storage | **idb** (IndexedDB wrapper) | 8.0.4 ✔ | ISC | queue + cache persister | — |
| PWA | **vite-plugin-pwa** (Workbox) | 2.0.0 ✔ | MIT | manifest + service worker | SW caching bugs ⇒ tests |
| API client | **openapi-typescript** + **openapi-fetch** | 7.13.0 ✔ / 0.17.0 ✔ | MIT | generated types, no hand-written DTOs | — |
| Rich text | **Tiptap** (`@tiptap/react`, starter-kit, link, task-list, task-item) | 3.32.0 ✔ | MIT | D-07, legacy ADR-004/009 | schema drift ⇒ `note_doc_v1` validation |
| Drawing | **@excalidraw/excalidraw** | 0.18.1 ✔ | MIT | legacy ADR-005/010 | large bundle ⇒ lazy route; React 19 compat verified in the M8 task |
| Icons | **lucide-react** | resolve in M1 | ISC | DS §11 | — |
| Lint/format | **Biome** | 2.6.3 ✔ | MIT/Apache-2.0 | one fast tool | rule coverage for custom restrictions ⇒ small custom checks |
| Tokens | **culori** (build-time colour conversion), own script | 4.0.2 ✔ | MIT | OKLCH → hex fallbacks | — |
| Unit tests | **Vitest**, Testing Library, **MSW**, fake-indexeddb | vitest 5.0.5 ✔, @testing-library/react 16.3.3 ✔ | MIT | FE-16 | — |
| E2E / a11y | **Playwright**, **@axe-core/playwright** | 1.64.1 ✔ / 4.14.0 ✔ | Apache-2.0 / MPL-2.0 (dev-only) | 3 engines, axe tags | browsers need install in CI |
| Fonts | **Fraunces**, **Inter**, **JetBrains Mono** (self-hosted subsets) | — | SIL OFL-1.1 | DS §4 | provenance ledger entries |

## 3. Android (`android/`)

| Capability | Choice | Version | Licence | Notes |
|---|---|---|---|---|
| Shell | **Capacitor** (`@capacitor/core`, `/android`, `/cli`) | 8.5.3 ✔ | MIT | plugin API supports Kotlin (documented: "Java by default but you can use Kotlin") |
| Language/build | Kotlin, Gradle, Android Gradle Plugin | resolve in M10 | Apache-2.0 | minSdk per Capacitor 8 requirements — verify in M10 |
| Secure storage | AndroidX Security (`EncryptedSharedPreferences`/Keystore) or the Keystore API directly | resolve in M10 | Apache-2.0 | D-37 |
| Tests | JUnit, Espresso, AndroidX accessibility checks | resolve in M10 | Apache-2.0 | `12-testing.md` |

## 4. Platform, CI and delivery

| Capability | Choice | Notes |
|---|---|---|
| Database | PostgreSQL 17 (min), tested on 18; extensions `citext`, `btree_gist` (both trusted extensions in PG ≥ 13) | no `pg_trgm`, no PostGIS |
| Containers | Docker/OCI; base images pinned by digest | `containers-and-caching.md` |
| CI | GitHub Actions, all actions pinned by SHA; aggregate `ci-ok` | `12-testing.md` §3 |
| Dependency updates | Dependabot (cargo, npm, actions, docker, gradle), weekly, grouped | SEC-13 |
| Releases | release-please (versions + changelog from Conventional Commits) + cosign (keyless) + Syft (SBOM, CycloneDX) + SLSA provenance via `actions/attest-build-provenance` | Phase 5 |
| Secret scanning | gitleaks | SEC-13 |
| Docs/link/typo checks | lychee (links), typos, actionlint, markdownlint-cli2 | CI |
| Container scan | Trivy (or Grype) | CI |
| Task runner | just (`justfile` at the repository root; recipes `setup`, `dev`, `fmt`, `lint`, `test`, `check`, `spec`) | D-38; CI calls the same recipes |
| Repo scripts | Python 3 stdlib only (`scripts/*.py`) so they run anywhere without installs | `contrast.py`, `spec_tool.py`, `tasks_index.py` |
