<?php

namespace Tests\Unit;

use App\Domain\Ai\Entities\AiProposal;
use App\Domain\Ai\ValueObjects\AiProposalType;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class AiProposalTest extends TestCase
{
    #[Test]
    public function pending_proposal_is_pending_and_serializes(): void
    {
        $proposal = AiProposal::pending(
            1,
            new AiProposalType('goal_breakdown'),
            1,
            ['goal_id' => 7, 'milestones' => []],
            CarbonImmutable::parse('2026-08-18 12:00:00'),
        )->withId(9);

        $this->assertTrue($proposal->isPending());
        $this->assertSame('pending', $proposal->decision);
        $this->assertSame(9, $proposal->id);
        $this->assertSame('goal_breakdown', $proposal->toArray()['proposal_type']);
        $this->assertSame(7, $proposal->toArray()['payload']['goal_id']);
    }

    #[Test]
    public function decision_can_be_accepted_with_operation_reference(): void
    {
        $proposal = AiProposal::pending(1, new AiProposalType('goal_breakdown'), 1, [])
            ->withDecision('accepted', 'goal:breakdown:9');

        $this->assertFalse($proposal->isPending());
        $this->assertSame('accepted', $proposal->decision);
        $this->assertSame('goal:breakdown:9', $proposal->operationId);
    }

    #[Test]
    public function decision_can_be_rejected_without_operation(): void
    {
        $proposal = AiProposal::pending(1, new AiProposalType('goal_breakdown'), 1, [])
            ->withDecision('rejected');

        $this->assertSame('rejected', $proposal->decision);
        $this->assertNull($proposal->operationId);
    }
}
