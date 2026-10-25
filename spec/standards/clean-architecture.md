# Standard — Clean architecture in a modular monolith (CA)

Complements `02-architecture.md`. Each rule has a mechanical check; rules without a check are marked *review*.

| ID | Rule | Check |
|---|---|---|
| CA-01 | **Business decisions live only in pure domain code** (`domain/` modules and `kinevo-scheduler`): invariants, state transitions, rankings, validation of domain values. Handlers, repositories and React components contain none. | architecture tests (CA-05) + review |
| CA-02 | **Layering inside a module crate:** `http → app → domain`, `app → ports`, `infra → ports/domain`. `domain` imports nothing from `app`, `infra`, `http`, `sqlx`, `axum`, `tokio`, `serde_json::Value` (typed serde derives allowed), time/rand sources. | `cargo-deny` bans per crate + `cargo-modules`/custom test that parses `use` paths |
| CA-03 | **Modules never import each other.** `kinevo-mod-*` crates depend only on `kinevo-kernel`, `kinevo-ports`, `kinevo-db`, `kinevo-http` and (for the scheduler module) `kinevo-scheduler`. Cross-module reads/commands use traits in `kinevo-ports`; reactions use outbox events. | `cargo metadata` test (CA-05) |
| CA-04 | **SQL only in repositories (`infra/`) and only on the module's own tables.** Joins across tables of different modules are forbidden; foreign keys across modules are allowed for integrity but never queried across. | grep test: SQL keywords outside `infra/` and table names outside the owning module's list (`06-domain-model.md` ownership) |
| CA-05 | **Architecture tests exist and run in CI**: (a) crate dependency graph equals the allowed graph; (b) no forbidden imports in `domain`; (c) every table is referenced by exactly one module's `infra`; (d) every route is registered by a module descriptor; (e) every descriptor permission/policy/message key exists in its registry. | `tests/architecture` |
| CA-06 | `kinevo-kernel` and `kinevo-scheduler` are **dependency-light**: no async runtime, no sqlx, no axum; `kinevo-scheduler` additionally no serde_json beyond the wire types and no `std::time`. They compile for `wasm32`. | `cargo build --target wasm32-unknown-unknown` + deny |
| CA-07 | **Use cases are recipes:** validate input → authorise → load via repository traits → call domain → persist → emit events → return a typed result. They contain no `if` that encodes a business rule (it belongs in domain). | review |
| CA-08 | **Transactions:** a use case that changes more than one row or emits events opens exactly one `Tx` (account context) and passes `&mut Tx` down; no nested transactions; no network call (AI, payment, email) inside a transaction. | review + lint for `.await` on outbound clients inside `Tx` scope (clippy `await_holding_*` + custom test) |
| CA-09 | **Tenant context cannot be forgotten:** repository constructors/methods take `&mut Tx` (which carries the account id and has run `SET LOCAL`); there is no API to obtain a pooled connection for tenant tables outside `kinevo-db`. | compile (visibility) + architecture test |
| CA-10 | **Ports are narrow and owned by the consumer's need** but live in `kinevo-ports` so the provider can implement them without importing the consumer. Ports return DTOs (plain data), never domain entities of another module. | review |
| CA-11 | **Events are facts in the past tense** with ids and enums only (no content), versioned (`kind` + `v`), idempotent to handle, defined in `kinevo-ports::events`. | architecture test over event types |
| CA-12 | **Adapters at the edge:** AI providers, payment gateways, SMTP, ICS parsing/serialisation, clock, id generation are traits in `ports`; real and fake implementations live in `infra`. Tests use fakes. | review |
| CA-13 | **Configuration is read once** at the composition root (`kinevo-app`) into typed structs; modules receive what they need through constructors, not globals. No `static mut`, no lazy globals except `OnceLock` for immutable data (tz database, common-password list). | clippy `disallowed` + review |
| CA-14 | **HTTP layer is thin:** extract → call use case → map result/error. DTOs are separate from domain types; OpenAPI is derived from the DTOs. | review |
| CA-15 | **Frontend mirrors the rule:** `components/` are presentational (props in, events out); `features/<module>/` hooks talk to the API; `lib/` is pure helpers. Business rules (status transitions, scheduling, permission decisions) are never encoded in React; the UI asks the server (e.g., available transitions come from the task representation: `allowed_transitions[]`). | review + lint rule restricting imports (`components` → no `api`) |
| CA-16 | **Pure functions for anything decision-like** (resolution, ranking, state machines, validation) so they are testable without a database and reusable in WASM where useful. | review |
| CA-17 | **Failure is a first-class result:** use cases return `Result<T, AppError>`; partial failures in fan-out (e.g., Today enrichments) are modelled as data (`warnings`) not hidden exceptions. | review |
| CA-18 | **Migrations are append-only and forward-only in production**: a released migration file is never edited; fixes are new migrations. Each migration runs in a transaction unless it uses `CREATE INDEX CONCURRENTLY` (then it is in its own file marked non-transactional). Each module owns its migration directory; global ordering by timestamp prefix `YYYYMMDDHHMMSS_<module>_<name>.sql`. | CI migration checksum test |

## Dependency picture (allowed edges)

```
kinevo-app ──▶ every crate
kinevo-mod-* ──▶ kinevo-http, kinevo-db, kinevo-ports, kinevo-kernel          (mod-scheduler also ▶ kinevo-scheduler)
kinevo-http ──▶ kinevo-kernel, kinevo-ports
kinevo-db ──▶ kinevo-kernel
kinevo-ports ──▶ kinevo-kernel
kinevo-scheduler ──▶ kinevo-kernel
```
Any other edge is a CA-05 failure.
