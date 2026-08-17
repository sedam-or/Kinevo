<?php

namespace Tests\Unit;

use App\Domain\Goals\Goal;
use App\Domain\Goals\ValueObjects\GoalHorizon;
use App\Domain\Goals\ValueObjects\GoalStatus;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class GoalTest extends TestCase
{
    #[Test]
    public function horizon_validates_allowed_values(): void
    {
        $this->assertSame('yearly', GoalHorizon::yearly()->value);
        $this->assertSame('quarterly', GoalHorizon::quarterly()->value);
        $this->assertSame('monthly', GoalHorizon::monthly()->value);
        $this->assertSame('custom', GoalHorizon::custom()->value);

        $this->expectException(InvalidArgumentException::class);
        new GoalHorizon('bimonthly');
    }

    #[Test]
    public function status_validates_allowed_values_and_terminals(): void
    {
        $this->assertTrue(GoalStatus::completed()->isTerminal());
        $this->assertTrue(GoalStatus::archived()->isTerminal());
        $this->assertTrue(GoalStatus::dropped()->isTerminal());
        $this->assertFalse(GoalStatus::draft()->isTerminal());

        $this->expectException(InvalidArgumentException::class);
        new GoalStatus('on_hold');
    }

    #[Test]
    public function status_transitions_are_explicit(): void
    {
        $draft = GoalStatus::draft();
        $this->assertTrue($draft->canTransitionTo(GoalStatus::active()));
        $this->assertTrue($draft->canTransitionTo(GoalStatus::archived()));
        $this->assertFalse($draft->canTransitionTo(GoalStatus::completed()));

        $this->assertTrue(GoalStatus::active()->canTransitionTo(GoalStatus::completed()));
        $this->assertTrue(GoalStatus::active()->canTransitionTo(GoalStatus::paused()));
        $this->assertFalse(GoalStatus::completed()->canTransitionTo(GoalStatus::active()));
        $this->assertFalse(GoalStatus::archived()->canTransitionTo(GoalStatus::draft()));
    }

    #[Test]
    public function goal_with_target_date_is_deadline_bound(): void
    {
        $today = CarbonImmutable::parse('2026-08-17');
        $goal = Goal::create(
            1,
            'Research goal',
            null,
            GoalHorizon::custom(),
            $today,
            $today->addMonths(4),
            null,
        );

        $this->assertTrue($goal->isDeadlineBound());
        $this->assertSame(122, $goal->remainingDays($today));
    }

    #[Test]
    public function goal_without_target_date_has_no_deadline(): void
    {
        $goal = Goal::create(
            1,
            'Open-ended goal',
            null,
            GoalHorizon::custom(),
            CarbonImmutable::now(),
            null,
            null,
        );

        $this->assertFalse($goal->isDeadlineBound());
        $this->assertNull($goal->remainingDays());
    }

    #[Test]
    public function overdue_goal_reports_negative_remaining_days(): void
    {
        $today = CarbonImmutable::parse('2026-08-17');
        $goal = Goal::create(
            1,
            'Late goal',
            null,
            GoalHorizon::custom(),
            $today->subMonths(1),
            $today->subDays(5),
            null,
        );

        $this->assertSame(-5, $goal->remainingDays($today));
    }

    #[Test]
    public function invalid_status_transition_throws(): void
    {
        $goal = Goal::create(1, 'Goal', null, GoalHorizon::custom(), null, null, null);

        $this->expectException(InvalidArgumentException::class);
        $goal->withStatus(GoalStatus::completed());
    }

    #[Test]
    public function valid_status_transition_returns_new_instance(): void
    {
        $goal = Goal::create(1, 'Goal', null, GoalHorizon::custom(), null, null, null);
        $activated = $goal->withStatus(GoalStatus::active());

        $this->assertSame('draft', $goal->status->value);
        $this->assertSame('active', $activated->status->value);
    }

    #[Test]
    public function custom_horizon_goal_needs_no_parent(): void
    {
        $goal = Goal::create(1, 'Standalone', null, GoalHorizon::custom(), null, null, null);
        $this->assertSame('custom', $goal->horizon->value);
    }
}
