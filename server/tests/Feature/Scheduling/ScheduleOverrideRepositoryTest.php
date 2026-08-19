<?php

namespace Tests\Feature\Scheduling;

use App\Domain\Scheduling\Contracts\HardLandscapeRepository;
use App\Domain\Scheduling\Contracts\ScheduleOverrideRepository;
use App\Domain\Scheduling\HardLandscapeEvent;
use App\Domain\Scheduling\ScheduleOverride;
use App\Domain\Scheduling\ScheduleOverrideConflict;
use App\Domain\Scheduling\ValueObjects\HardLandscapeType;
use App\Domain\Scheduling\ValueObjects\ScheduleOverrideType;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

final class ScheduleOverrideRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private ScheduleOverrideRepository $repository;

    private HardLandscapeRepository $hardLandscape;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = app(ScheduleOverrideRepository::class);
        $this->hardLandscape = app(HardLandscapeRepository::class);
    }

    private function createUser(): User
    {
        return User::factory()->create();
    }

    private function createSource(int $userId, int $day = 17): int
    {
        $event = $this->hardLandscape->create(HardLandscapeEvent::create(
            $userId,
            "Daily Standup {$day}",
            HardLandscapeType::recurring(),
            "2026-08-{$day}T09:00:00",
            "2026-08-{$day}T09:30:00",
            'FREQ=DAILY',
        ));

        return $event->id;
    }

    private function override(int $userId, int $sourceId, string $date = '2026-08-19', string $start = '14:00', string $end = '14:30'): ScheduleOverride
    {
        return ScheduleOverride::create(
            $userId,
            $sourceId,
            ScheduleOverrideType::oneTime(),
            "{$date}T09:00:00",
            "{$date}T09:00:00",
            "{$date}T{$start}:00",
            "{$date}T{$end}:00",
        );
    }

    public function test_override_can_be_created_and_retrieved(): void
    {
        $user = $this->createUser();
        $sourceId = $this->createSource($user->id);

        $created = $this->repository->create($this->override($user->id, $sourceId));

        $this->assertGreaterThan(0, $created->id);
        $found = $this->repository->findForUser($user->id, $created->id);
        $this->assertNotNull($found);
        $this->assertSame($sourceId, $found->hardLandscapeEventId);
    }

    public function test_create_rejects_unknown_source(): void
    {
        $user = $this->createUser();

        $this->expectException(InvalidArgumentException::class);

        $this->repository->create($this->override($user->id, 999));
    }

    public function test_create_rejects_overlap_with_same_source(): void
    {
        $user = $this->createUser();
        $sourceId = $this->createSource($user->id);
        $this->repository->create($this->override($user->id, $sourceId, '2026-08-19', '14:00', '15:00'));

        $this->expectException(ScheduleOverrideConflict::class);

        $this->repository->create($this->override($user->id, $sourceId, '2026-08-19', '14:30', '15:30'));
    }

    public function test_overrides_for_different_sources_do_not_conflict(): void
    {
        $user = $this->createUser();
        $sourceA = $this->createSource($user->id, 17);
        $sourceB = $this->createSource($user->id, 18);
        $this->repository->create($this->override($user->id, $sourceA, '2026-08-19', '14:00', '15:00'));

        $second = $this->repository->create($this->override($user->id, $sourceB, '2026-08-19', '14:30', '15:30'));

        $this->assertGreaterThan(0, $second->id);
    }

    public function test_update_rejects_overlap_with_other_override(): void
    {
        $user = $this->createUser();
        $sourceId = $this->createSource($user->id);
        $a = $this->repository->create($this->override($user->id, $sourceId, '2026-08-19', '09:00', '10:00'));
        $this->repository->create($this->override($user->id, $sourceId, '2026-08-19', '14:00', '15:00'));

        $moved = new ScheduleOverride(
            $a->id,
            $user->id,
            $sourceId,
            ScheduleOverrideType::oneTime(),
            CarbonImmutable::parse('2026-08-19T09:00:00'),
            CarbonImmutable::parse('2026-08-19T09:00:00'),
            CarbonImmutable::parse('2026-08-19T14:30:00'),
            CarbonImmutable::parse('2026-08-19T15:30:00'),
        );

        $this->expectException(ScheduleOverrideConflict::class);

        $this->repository->update($moved);
    }

    public function test_list_for_source_filters_by_series(): void
    {
        $user = $this->createUser();
        $sourceA = $this->createSource($user->id, 17);
        $sourceB = $this->createSource($user->id, 18);
        $this->repository->create($this->override($user->id, $sourceA, '2026-08-19'));
        $this->repository->create($this->override($user->id, $sourceB, '2026-08-19'));

        $forA = $this->repository->listForSource($user->id, $sourceA);

        $this->assertCount(1, $forA);
        $this->assertSame($sourceA, $forA[0]->hardLandscapeEventId);
    }

    public function test_repository_is_scoped_to_owner(): void
    {
        $owner = $this->createUser();
        $other = $this->createUser();
        $sourceId = $this->createSource($other->id);
        $created = $this->repository->create($this->override($other->id, $sourceId));

        $this->assertNull($this->repository->findForUser($owner->id, $created->id));
        $this->assertCount(0, $this->repository->listForUser($owner->id));
    }

    public function test_delete_removes_override(): void
    {
        $user = $this->createUser();
        $sourceId = $this->createSource($user->id);
        $created = $this->repository->create($this->override($user->id, $sourceId));

        $this->repository->deleteForUser($user->id, $created->id);

        $this->assertNull($this->repository->findForUser($user->id, $created->id));
    }

    public function test_delete_missing_override_throws(): void
    {
        $user = $this->createUser();

        $this->expectException(InvalidArgumentException::class);

        $this->repository->deleteForUser($user->id, 999);
    }
}
