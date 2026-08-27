<?php

namespace App\Domain\Ai;

/**
 * COMMERCIAL PRICING DELTA D-005 / revisi-finance §11 — billing-source
 * enrichment of the AI usage ledger. The persisted column is
 * `ai_runs.billing_ledger`; these constants ARE the persistence values (single
 * truth), mapped from the conceptual enumeration:
 *
 *   INCLUDED_HOSTED  -> `kinevo`   : Kinevo pays the provider from the
 *                                   included monthly allowance (hosted AI).
 *   BYOK             -> `byok`     : user's provider relationship owns spend;
 *                                   never consumes hosted allowance.
 *   PREPAID_HOSTED   -> reserved   : optional prepaid AI balance — NOT yet
 *                                   behind any flow; adding the flow and the
 *                                   credit accounting is D-006/D-009 decision,
 *                                   not invented here.
 *
 * Never charge a BYOK run against INCLUDED_HOSTED / PREPAID_HOSTED buckets.
 */
final class BillingLedger
{
    public const INCLUDED_HOSTED = 'kinevo';

    public const BYOK = 'byok';

    public const PREPAID_HOSTED = 'prepaid_hosted';

    /** @return list<string> */
    public static function supportedValues(): array
    {
        return [self::INCLUDED_HOSTED, self::BYOK];
    }

    public static function isSupported(string $value): bool
    {
        return in_array($value, self::supportedValues(), true);
    }
}
