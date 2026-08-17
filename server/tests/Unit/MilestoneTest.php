<?php

namespace Tests\Unit;

use App\Domain\Milestones\Milestone;
use App\Domain\Milestones\ValueObjects\MilestoneStatus;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class MilestoneTest extends TestCase
{
    #[Test]
    public function milestone_belongs_to_exactly_one_goal(): void
    {
        $milestone = Milestone::create(42, 7, 'Ship beta', null, 1, null, 60);

        $this->assertSame(42, $milestone->goalId);
        $this->assertSame(7, $milestone->userId);
        $this->assertSame(0, $milestone->id);
        $this->assertSame('planned', $milestone->status->value);
        $this->assertSame(1, $milestone->version);
    }

    #[Test]
    public function status_validates_allowed_values_and_terminals(): void
    {
        $this->assertTrue(MilestoneStatus::completed()->isTerminal());
        $this->assertTrue(MilestoneStatus::dropped()->isTerminal());
        $this->assertFalse(MilestoneStatus::planned()->isTerminal());
        $this->assertFalse(MilestoneStatus::active()->isTerminal());

        $this->expectException(InvalidArgumentException::class);
        new MilestoneStatus('paused');
    }

    #[Test]
    public function status_transitions_are_explicit(): void
    {
        $planned = MilestoneStatus::planned();
        $this->assertTrue($planned->canTransitionTo(MilestoneStatus::active()));
        $this->assertTrue($planned->canTransitionTo(MilestoneStatus::blocked()));
        $this->assertTrue($planned->canTransitionTo(MilestoneStatus::completed()));
        $this->assertTrue($planned->canTransitionTo(MilestoneStatus::dropped()));

        $this->assertTrue(MilestoneStatus::active()->canTransitionTo(MilestoneStatus::blocked()));
        $this->assertTrue(MilestoneStatus::active()->canTransitionTo(MilestoneStatus::completed()));
        $this->assertTrue(MilestoneStatus::blocked()->canTransitionTo(MilestoneStatus::active()));
        $this->assertFalse(MilestoneStatus::completed()->canTransitionTo(MilestoneStatus::active()));
        $this->assertFalse(MilestoneStatus::dropped()->canTransitionTo(MilestoneStatus::planned()));
    }

    #[Test]
    public function completing_milestone_stamps_completed_at(): void
    {
        $now = CarbonImmutable::parse('2026-08-17 10:00:00');
        $milestone = Milestone::create(1, 1, 'Launch', null, 1, null, null);
        $completed = $milestone->withStatus(MilestoneStatus::completed(), $now);

        $this->assertTrue($completed->isCompleted());
        $this->assertSame('2026-08-17 10:00:00', $completed->completedAt->toDateTimeString());
        $this->assertSame(2, $completed->version);
        $this->assertNull($milestone->completedAt);
    }

    #[Test]
    public function invalid_status_transition_throws(): void
    {
        $milestone = Milestone::create(1, 1, 'Launch', null, 1, null, null);
        $completed = $milestone->withStatus(MilestoneStatus::completed());

        $this->expectException(InvalidArgumentException::class);
        $completed->withStatus(MilestoneStatus::active());
    }

    #[Test]
    public function progress_is_bounded_to_0_100(): void
    {
        $milestone = Milestone::create(1, 1, 'Launch', null, 1, null, null);
        $this->assertSame(50, $milestone->withProgress(50)->progress);

        $this->expectException(InvalidArgumentException::class);
        $milestone->withProgress(101);
    }

    #[Test]
    public function estimated_minutes_cannot_be_negative(): void
    {
        $milestone = Milestone::create(1, 1, 'Launch', null, 1, null, null);

        $this->expectException(InvalidArgumentException::class);
        $milestone->withEstimatedMinutes(-5);
    }

    #[Test]
    public function with_id_and_editable_fields_preserve_identity(): void
    {
        $milestone = Milestone::create(1, 1, 'Launch', null, 1, null, null)->withId(9);

        $renamed = $milestone
            ->withTitle('Release')
            ->withDescription('v1.0')
            ->withSequence(3)
            ->withTargetDate(CarbonImmutable::parse('2026-12-31'))
            ->withEstimatedMinutes(120);

        $this->assertSame(9, $renamed->id);
        $this->assertSame(1, $renamed->goalId);
        $this->assertSame('Release', $renamed->title);
        $this->assertSame('v1.0', $renamed->description);
        $this->assertSame(3, $renamed->sequence);
        $this->assertSame('2026-12-31', $renamed->targetDate->toDateString());
        $this->assertSame(120, $renamed->estimatedMinutes);
        $this->assertSame('planned', $renamed->status->value);
    }

    #[Test]
    public function to_array_exposes_contract_fields(): void
    {
        $milestone = Milestone::create(1, 1, 'Launch', 'go', 2, CarbonImmutable::parse('2026-09-01'), 30);

        $this->assertSame([
            'id' => 0,
            'goal_id' => 1,
            'user_id' => 1,
            'title' => 'Launch',
            'description' => 'go',
            'sequence' => 2,
            'target_date' => '2026-09-01',
            'estimated_minutes' => 30,
            'status' => 'planned',
            'progress_mode' => 'derived',
            'progress' => 0,
            'completed_at' => null,
            'version' => 1,
        ], $milestone->toArray());
    }
}
