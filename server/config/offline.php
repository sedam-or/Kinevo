<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Offline Reconciliation (ADR-017)
    |--------------------------------------------------------------------------
    |
    | Server-authoritative operation ledger for offline mutations.
    |
    */

    // How long an operation_id stays replayable in the ledger. Generous enough
    // for any realistic offline period; the client clears applied items from
    // its queue immediately after reconciliation, so this covers safety replays
    // (response-loss retries). Replays older than this are rejected with
    // `expired` and the client regenerates a new operation_id.
    'ledger_retention_days' => (int) env('OFFLINE_LEDGER_RETENTION_DAYS', 90),

    // Reconcile batch + payload bounds (ADR-017 §2.7, §2.20).
    'max_operations_per_batch' => (int) env('OFFLINE_MAX_OPERATIONS_PER_BATCH', 50),
    'max_payload_bytes_per_operation' => 64 * 1024,
    'max_request_bytes' => 512 * 1024,
];
