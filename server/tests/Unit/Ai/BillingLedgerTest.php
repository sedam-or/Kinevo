<?php

namespace Tests\Unit\Ai;

use App\Domain\Ai\BillingLedger;
use Tests\TestCase;

/**
 * COMMERCIAL PRICING DELTA D-005 — ledger billing-source mapping. Locks the
 * persisted values so BYOK never bleeds into the hosted allowance bucket.
 */
final class BillingLedgerTest extends TestCase
{
    public function test_persisted_values_match_the_conceptual_sources(): void
    {
        $this->assertSame('kinevo', BillingLedger::INCLUDED_HOSTED);
        $this->assertSame('byok', BillingLedger::BYOK);
        $this->assertSame('prepaid_hosted', BillingLedger::PREPAID_HOSTED);
    }

    public function test_only_active_sources_are_supported(): void
    {
        $this->assertTrue(BillingLedger::isSupported(BillingLedger::INCLUDED_HOSTED));
        $this->assertTrue(BillingLedger::isSupported(BillingLedger::BYOK));
        // Prepaid is reserved, not yet wired into any flow.
        $this->assertFalse(BillingLedger::isSupported(BillingLedger::PREPAID_HOSTED));
    }
}
