<?php

namespace Tests\Feature\Scheduling;

use App\Domain\Scheduling\Contracts\HardLandscapeRepository;
use App\Domain\Scheduling\HardLandscapeConflict;
use App\Domain\Scheduling\HardLandscapeEvent;
use App\Domain\Scheduling\ValueObjects\HardLandscapeType;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

final class HardLandscapeRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private HardLandscapeRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = app(HardLandscapeRepository::class);
    }

    private function createUser(): User
    {
        return User::factory()->create();
    }

    private function event(int $userId, string $title = 'Block', string $start = '2026-08-19T09:00:00', string $end = '2026-08-19T10:00:00'): HardLandscapeEvent
    {
        return HardLandscapeEvent::create($userId, $title, HardLandscapeType::oneTime(), $start, $end);
    }

    public function test_event_can_be_created_and_retrieved(): void
    {
        $user = $this->createUser();

        $created = $this->repository->create($this->event($user->id));

        $this->assertGreaterThan(0, $created->id);
        $found = $this->repository->findForUser($user->id, $created->id);
        $this->assertNotNull($found);
        $this->assertSame($created->id, $found->id);
        $this->assertSame('Block', $found->title);
    }

    public function test_create_rejects_overlapping_event(): void
    {
        $user = $this->createUser();
        $this->repository->create($this->event($user->id, 'First', '2026-08-19T09:00:00', '2026-08-19T10:00:00'));

        $this->expectException(HardLandscapeConflict::class);

        $this->repository->create($this->event($user->id, 'Second', '2026-08-19T09:30:00', '2026-08-19T10:30:00'));
    }

    public function test_adjacent_events_are_allowed(): void
    {
        $user = $this->createUser();
        $this->repository->create($this->event($user->id, 'A', '2026-08-19T09:00:00', '2026-08-19T10:00:00'));

        $second = $this->repository->create($this->event($user->id, 'B', '2026-08-19T10:00:00', '2026-08-19T11:00:00'));

        $this->assertGreaterThan(0, $second->id);
    }

    public function test_update_rejects_overlap_with_other_event(): void
    {
        $user = $this->createUser();
        $a = $this->repository->create($this->event($user->id, 'A', '2026-08-19T09:00:00', '2026-08-19T10:00:00'));
        $b = $this->repository->create($this->event($user->id, 'B', '2026-08-19T11:00:00', '2026-08-19T12:00:00'));

        $moved = new HardLandscapeEvent(
            $b->id,
            $user->id,
            'B',
            HardLandscapeType::oneTime(),
            CarbonImmutable::parse('2026-08-19T09:30:00'),
            CarbonImmutable::parse('2026-08-19T10:30:00'),
        );

        $this->expectException(HardLandscapeConflict::class);

        $this->repository->update($moved);
    }

    public function test_update_allows_self_overlap_ignored(): void
    {
        $user = $this->createUser();
        $a = $this->repository->create($this->event($user->id, 'A', '2026-08-19T09:00:00', '2026-08-19T10:00:00'));

        $renamed = new HardLandscapeEvent(
            $a->id,
            $user->id,
            'Renamed',
            HardLandscapeType::oneTime(),
            CarbonImmutable::parse('2026-08-19T09:00:00'),
            CarbonImmutable::parse('2026-08-19T10:00:00'),
        );

        $updated = $this->repository->update($renamed);

        $this->assertSame('Renamed', $updated->title);
    }

    public function test_list_for_user_on_date_filters_by_day(): void
    {
        $user = $this->createUser();
        $this->repository->create($this->event($user->id, 'Day1', '2026-08-19T09:00:00', '2026-08-19T10:00:00'));
        $this->repository->create($this->event($user->id, 'Day2', '2026-08-20T09:00:00', '2026-08-20T10:00:00'));

        $onDay = $this->repository->listForUserOnDate($user->id, CarbonImmutable::parse('2026-08-19'));

        $this->assertCount(1, $onDay);
        $this->assertSame('Day1', $onDay[0]->title);
    }

    public function test_repository_is_scoped_to_owner(): void
    {
        $owner = $this->createUser();
        $other = $this->createUser();
        $created = $this->repository->create($this->event($other->id));

        $this->assertNull($this->repository->findForUser($owner->id, $created->id));
        $this->assertCount(0, $this->repository->listForUser($owner->id));
    }

    public function test_delete_removes_event(): void
    {
        $user = $this->createUser();
        $created = $this->repository->create($this->event($user->id));

        $this->repository->deleteForUser($user->id, $created->id);

        $this->assertNull($this->repository->findForUser($user->id, $created->id));
    }

    public function test_delete_missing_event_throws(): void
    {
        $user = $this->createUser();

        $this->expectException(InvalidArgumentException::class);

        $this->repository->deleteForUser($user->id, 999);
    }
}
