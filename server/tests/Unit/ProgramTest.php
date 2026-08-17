<?php

namespace Tests\Unit;

use App\Domain\Programs\Program;
use App\Domain\Programs\ValueObjects\ProgramStatus;
use App\Domain\Programs\ValueObjects\ProgramWorkloadType;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ProgramTest extends TestCase
{
    #[Test]
    public function workload_type_validates_allowed_values(): void
    {
        $this->assertSame('structured', ProgramWorkloadType::structured()->value);
        $this->assertSame('range', ProgramWorkloadType::range()->value);
        $this->assertSame('flexible', ProgramWorkloadType::flexible()->value);

        $this->expectException(InvalidArgumentException::class);
        new ProgramWorkloadType('open_ended');
    }

    #[Test]
    public function status_validates_allowed_values_and_terminals(): void
    {
        $this->assertTrue(ProgramStatus::completed()->isTerminal());
        $this->assertTrue(ProgramStatus::dropped()->isTerminal());
        $this->assertFalse(ProgramStatus::active()->isTerminal());
        $this->assertFalse(ProgramStatus::paused()->isTerminal());

        $this->expectException(InvalidArgumentException::class);
        new ProgramStatus('archived');
    }

    #[Test]
    public function status_transitions_are_explicit(): void
    {
        $active = ProgramStatus::active();
        $this->assertTrue($active->canTransitionTo(ProgramStatus::paused()));
        $this->assertTrue($active->canTransitionTo(ProgramStatus::completed()));
        $this->assertTrue($active->canTransitionTo(ProgramStatus::dropped()));

        $this->assertTrue(ProgramStatus::paused()->canTransitionTo(ProgramStatus::active()));
        $this->assertFalse(ProgramStatus::completed()->canTransitionTo(ProgramStatus::active()));
        $this->assertFalse(ProgramStatus::dropped()->canTransitionTo(ProgramStatus::active()));
    }

    #[Test]
    public function structured_program_requires_weekly_target(): void
    {
        $program = Program::create(
            1,
            'Daily writing',
            null,
            'Growth',
            ProgramWorkloadType::structured(),
            weeklyTargetMinutes: 300,
        );

        $this->assertSame('structured', $program->workloadType->value);
        $this->assertSame(300, $program->weeklyTargetMinutes);
        $this->assertTrue($program->affectsWeeklyCapacity());
        $this->assertSame('active', $program->status->value);

        $this->expectException(InvalidArgumentException::class);
        Program::create(1, 'Broken', null, null, ProgramWorkloadType::structured());
    }

    #[Test]
    public function range_program_requires_min_and_max_and_rejects_min_gt_max(): void
    {
        $program = Program::create(
            1,
            'Strength training',
            null,
            'Fitness',
            ProgramWorkloadType::range(),
            minWeeklyMinutes: 60,
            maxWeeklyMinutes: 120,
        );

        $this->assertSame(60, $program->minWeeklyMinutes);
        $this->assertSame(120, $program->maxWeeklyMinutes);
        $this->assertTrue($program->affectsWeeklyCapacity());

        $this->expectException(InvalidArgumentException::class);
        Program::create(1, 'Broken range', null, null, ProgramWorkloadType::range(), minWeeklyMinutes: 120, maxWeeklyMinutes: 60);
    }

    #[Test]
    public function range_program_requires_both_bounds(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Program::create(1, 'Missing max', null, null, ProgramWorkloadType::range(), minWeeklyMinutes: 30);
    }

    #[Test]
    public function flexible_program_has_no_weekly_capacity(): void
    {
        $program = Program::create(1, 'Reading habit', null, 'Learning', ProgramWorkloadType::flexible());

        $this->assertNull($program->weeklyTargetMinutes);
        $this->assertFalse($program->affectsWeeklyCapacity());

        $this->expectException(InvalidArgumentException::class);
        Program::create(1, 'Broken flexible', null, null, ProgramWorkloadType::flexible(), weeklyTargetMinutes: 60);
    }

    #[Test]
    public function invalid_status_transition_throws(): void
    {
        $program = Program::create(1, 'Reading', null, null, ProgramWorkloadType::flexible());
        $completed = $program->withStatus(ProgramStatus::completed());

        $this->expectException(InvalidArgumentException::class);
        $completed->withStatus(ProgramStatus::active());
    }

    #[Test]
    public function valid_status_transition_returns_new_instance_with_bumped_version(): void
    {
        $program = Program::create(1, 'Reading', null, null, ProgramWorkloadType::flexible());
        $paused = $program->withStatus(ProgramStatus::paused());

        $this->assertSame('active', $program->status->value);
        $this->assertSame('paused', $paused->status->value);
        $this->assertSame(2, $paused->version);
    }

    #[Test]
    public function priority_tier_must_be_within_1_3(): void
    {
        $program = Program::create(1, 'Reading', null, null, ProgramWorkloadType::flexible(), priorityTier: 1);
        $this->assertSame(1, $program->priorityTier);

        $this->expectException(InvalidArgumentException::class);
        $program->withPriorityTier(4);
    }

    #[Test]
    public function with_id_and_editable_fields_preserve_identity(): void
    {
        $program = Program::create(1, 'Reading', 'Habit', 'Learning', ProgramWorkloadType::flexible())->withId(9);

        $renamed = $program
            ->withName('Reading habit')
            ->withDescription('Daily 20 minutes')
            ->withCategory('Growth');

        $this->assertSame(9, $renamed->id);
        $this->assertSame(1, $renamed->userId);
        $this->assertSame('Reading habit', $renamed->name);
        $this->assertSame('Daily 20 minutes', $renamed->description);
        $this->assertSame('Growth', $renamed->category);
    }

    #[Test]
    public function to_array_exposes_contract_fields(): void
    {
        $program = Program::create(1, 'Writing', 'go', 'Growth', ProgramWorkloadType::structured(), weeklyTargetMinutes: 300);

        $this->assertSame([
            'id' => 0,
            'user_id' => 1,
            'name' => 'Writing',
            'description' => 'go',
            'category' => 'Growth',
            'workload_type' => 'structured',
            'weekly_target_minutes' => 300,
            'min_weekly_minutes' => null,
            'max_weekly_minutes' => null,
            'status' => 'active',
            'priority_tier' => 3,
            'version' => 1,
        ], $program->toArray());
    }
}
