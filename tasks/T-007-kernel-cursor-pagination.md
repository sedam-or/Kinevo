---
id: T-007
title: Add signed cursor pagination types to the kernel
milestone: M1
kind: feature
status: review
depends_on: [T-002, T-003, T-004]
requirements: [API-30, SEC-23]
branch: m1-t007-kernel-cursor-pagination
budget_lines: 250
assignee: "codex"
---

# T-007: Add signed cursor pagination types to the kernel

## Goal

List endpoints can produce and verify opaque, HMAC-signed cursors bound to an endpoint and sort order. A tampered cursor, or one replayed on another endpoint or sort, is rejected with `cursor_invalid`.

## Context

> **API-30** (`spec/07-api-conventions.md`): List endpoints use cursor pagination: `?limit=` (default 50, max 200) and `?cursor=`. Response `{ "items": [...], "next_cursor": string|null }`. Cursors are opaque, HMAC-signed, bound to the endpoint and sort; tampered or reused on another endpoint/sort → 400 `cursor_invalid`. No page numbers, no total counts.
> **Auth §2** (`spec/standards/auth.md`): the cursor key is the HKDF subkey with label `kinevo/cursor/v1` (T-004 `KeyLabel::Cursor`).
> **Contracts §2**: Page = `{ "items": [ … ], "next_cursor": string | null }`.
> **Technical constants**: HMAC-SHA256; limit default 50, max 200 are wire constants of API-30 (not policies).

## Scope

**In:**
- `kinevo_kernel::page`:
  - `PageRequest { limit: u16, cursor: Option<Cursor> }` with `PageRequest::parse(limit: Option<i64>, cursor: Option<&str>, codec, endpoint, sort) -> Result<PageRequest, AppError>`; a `limit` outside 1–200 is a field error `out_of_range` at path `limit` (422).
  - `CursorCodec::new(key: Secret<[u8;32]>)`, `encode(endpoint: &str, sort: &str, last: &CursorPosition) -> String`, `decode(...) -> Result<CursorPosition, AppError>` (`cursor_invalid`, 400).
  - `CursorPosition`: ordered list of typed parts (`Instant`, `Id`, `Int`, `Text`), serialised canonically. Format: base64url(no padding) of `version(1) || payload || mac(32)`, where the MAC covers `endpoint \0 sort \0 payload`.
  - `Page<T> { items: Vec<T>, next_cursor: Option<String> }` with `serde::Serialize` and a `fn from_rows(rows: Vec<T>, limit, make_cursor)` that expects `limit + 1` fetched rows and trims the extra one.

**Out:**
- SQL keyset predicates (each repository writes its own).
- The query-string extractor: T-016.

## Files

| Path | Action | Notes |
|---|---|---|
| `crates/kinevo-kernel/Cargo.toml` | modify | dep `hmac` |
| `crates/kinevo-kernel/src/lib.rs` | modify | `pub mod page;` |
| `crates/kinevo-kernel/src/page.rs` | create | everything above |
| `spec/standards/open-source-stack.md` | modify | `hmac` row if missing (CQ-16) |

## Interfaces

```rust
pub struct PageRequest { pub limit: u16, pub cursor: Option<CursorPosition> }
pub const DEFAULT_LIMIT: u16 = 50; pub const MAX_LIMIT: u16 = 200;
pub enum CursorPart { Instant(Instant), Id(uuid::Uuid), Int(i64), Text(String) }
pub struct CursorPosition(pub Vec<CursorPart>);
pub struct CursorCodec { /* key */ }
impl CursorCodec {
    pub fn new(key: Secret<[u8; 32]>) -> Self;
    pub fn encode(&self, endpoint: &str, sort: &str, pos: &CursorPosition) -> String;
    pub fn decode(&self, endpoint: &str, sort: &str, cursor: &str) -> Result<CursorPosition, AppError>;
}
pub struct Page<T> { pub items: Vec<T>, pub next_cursor: Option<String> }
```

## Business rules

1. Missing `limit` means 50; `limit` below 1 or above 200 is rejected (never clamped silently).
2. A cursor from `GET /a?sort=x` is invalid for `GET /b?sort=x` and for `GET /a?sort=y`.
3. The MAC comparison is constant-time (`ct_eq`).
4. A cursor never contains account data beyond the sort key values; text parts are limited to 200 scalars.

## Security

- S2 at most: cursor parts may contain a title-based sort key. Because the cursor is signed, not encrypted, endpoints must not put S3 data in it (document on `CursorPart::Text`).
- Decoding failures return the same error whatever the cause (bad base64, bad MAC, wrong endpoint) to give no oracle.

## Implementation notes

- Use `hmac::Hmac<Sha256>` with `verify_slice` (already constant-time) or `ct_eq`.
- Serialise parts as `tag(1 byte) || len(u16 BE) || bytes`; keep the encoder and decoder together so the format is documented in one place.

## Acceptance

| # | Given / When / Then | Test name |
|---|---|---|
| AC-1 | **Given** a position **When** encoded and decoded for the same endpoint and sort **Then** it round-trips | `page::tests::api_30_cursor_round_trip` |
| AC-2 | **Given** a cursor for endpoint A **When** decoded for endpoint B or another sort **Then** `cursor_invalid` | `page::tests::api_30_cursor_bound_to_endpoint_and_sort` |
| AC-3 | **Given** a cursor with one flipped byte or truncated **When** decoded **Then** `cursor_invalid` | `page::tests::api_30_tampered_cursor_rejected` |
| AC-4 | **Given** `limit=0`, `limit=201`, `limit=200`, none **When** parsed **Then** 422 `out_of_range` (path `limit`), 422, 200, 50 | `page::tests::api_30_limit_bounds` |
| AC-5 | **Given** 51 rows and limit 50 **When** `Page::from_rows` runs **Then** 50 items and a `next_cursor`; with 50 rows `next_cursor` is `None` | `page::tests::next_cursor_only_when_more_rows` |

## Tests

```
cargo nextest run -p kinevo-kernel
just check
```

## Definition of done

- [ ] Every acceptance criterion has its named test, and it passes.
- [ ] `just check` passes locally.
- [ ] Only the files listed under *Files* changed; diff within budget.
- [ ] Report below is filled in.

## Report

### Summary

Added API-30 cursor signing and verification with typed canonical parts, HMAC-SHA256 binding to endpoint and sort, stable `cursor_invalid` errors, limit validation, and page trimming with a next cursor only when an extra row exists. All five acceptance tests and the text-boundary check pass.

### Acceptance results

| Criterion | Named test | Result |
|---|---|---|
| AC-1 | `page::tests::api_30_cursor_round_trip` | pass |
| AC-2 | `page::tests::api_30_cursor_bound_to_endpoint_and_sort` | pass |
| AC-3 | `page::tests::api_30_tampered_cursor_rejected` | pass |
| AC-4 | `page::tests::api_30_limit_bounds` | pass |
| AC-5 | `page::tests::next_cursor_only_when_more_rows` | pass |
| Text bound | `page::tests::cursor_text_is_limited_to_200_scalars` | pass |

### Commands run

| Command | Exit | Result |
|---|---:|---|
| Pre-implementation `cargo fmt --all && cargo nextest run -p kinevo-kernel` | 101 (expected red phase) | The named tests failed to compile because the cursor and page types were not implemented yet. |
| Initial implementation `cargo nextest run -p kinevo-kernel` | 101 (fixed before final run) | HMAC's `KeyInit` trait was missing from imports; imported it and reran successfully. |
| `cargo fmt --all` | 0 | Formatting completed. |
| `cargo nextest run -p kinevo-kernel` | 0 | 28 passed, 0 skipped, including all five T-007 acceptance tests. |
| Initial `just check` | 101 (fixed before final run) | Clippy rejected a guarded integer cast and `assert!(is_empty())`; replaced them with checked conversion and `assert_eq!`. |
| `just check` | 0 | 31 passed, 0 skipped; format, Clippy, cargo-deny, spec, contrast, and task-index gates passed. Clippy reports the existing unreachable `uuid::Uuid::new_v4` configuration warning; cargo-deny emitted 6 unmatched license-allowance warnings. |
| `python3 scripts/spec_tool.py check` | 0 | 0 findings in 60 spec files. |
| `python3 scripts/tasks_index.py --check` | 0 | 0 problems in 132 tasks. |
| `python3 scripts/tasks_index.py` | 0 | Regenerated `tasks/index.md` with T-007 at `review`; 0 problems in 132 tasks. |
| `git diff --check` | 0 | No whitespace errors. |

### Files changed

`crates/kinevo-kernel/Cargo.toml`, `crates/kinevo-kernel/src/lib.rs`, `crates/kinevo-kernel/src/page.rs`, `Cargo.lock`; this task file and generated `tasks/index.md`.

### Deviations

The implementation changes 367 non-lockfile lines across implementation files, exceeding the 250-line guidance by 117; recorded per D-55. Since `encode` has no error return, an invalid position with a text part above 200 Unicode scalars fails closed as an empty cursor, and `Page::from_rows` omits an empty next cursor. `Cargo.lock` adds 1 line and is excluded per D-55.

### Spec issues noticed

None.

### Environment-limited

None.

### Not done / not verified

No T-007 acceptance criteria remain unverified. HTTP query extraction and SQL keyset predicates are out of scope for this task.

## Questions

_None yet._
