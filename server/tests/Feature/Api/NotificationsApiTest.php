<?php

namespace Tests\Feature\Api;

use App\Domain\Notifications\Contracts\NotificationRepository;
use App\Domain\Notifications\Notification;
use App\Domain\Notifications\ValueObjects\NotificationType;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationsApiTest extends TestCase
{
    use RefreshDatabase;

    private function userWithToken(): array
    {
        $user = User::factory()->create();
        $token = $user->createToken('owner')->plainTextToken;

        return [$user, $token];
    }

    private function createNotification(int $userId, array $overrides = []): Notification
    {
        /** @var NotificationRepository $repository */
        $repository = app(NotificationRepository::class);

        return $repository->create(Notification::create(
            $userId,
            NotificationType::reconciliation(),
            $overrides['scheduled_for'] ?? CarbonImmutable::parse('2026-08-18'),
            'End-of-day reconciliation',
            $overrides['payload'] ?? [['task_id' => 1, 'title' => 'Ship', 'status' => 'scheduled']],
        ));
    }

    public function test_notifications_require_authentication(): void
    {
        $this->getJson('/api/v1/notifications')->assertStatus(401);
        $this->postJson('/api/v1/notifications/1/read')->assertStatus(401);
    }

    public function test_lists_owned_notifications(): void
    {
        [$user, $token] = $this->userWithToken();
        $this->createNotification($user->id);

        $this->withToken($token)->getJson('/api/v1/notifications')
            ->assertStatus(200)
            ->assertJsonPath('notifications.0.type', 'reconciliation')
            ->assertJsonPath('notifications.0.scheduled_for', '2026-08-18')
            ->assertJsonPath('notifications.0.read_at', null);
    }

    public function test_unread_filter_only_returns_unread(): void
    {
        [$user, $token] = $this->userWithToken();
        $this->createNotification($user->id, ['scheduled_for' => CarbonImmutable::parse('2026-08-17')]);
        $read = $this->createNotification($user->id, ['scheduled_for' => CarbonImmutable::parse('2026-08-16')]);
        $this->createNotification($user->id, ['scheduled_for' => CarbonImmutable::parse('2026-08-15')]);

        // Mark the 08-16 notification read directly.
        app(NotificationRepository::class)->markRead($user->id, $read->id);

        $this->withToken($token)->getJson('/api/v1/notifications?unread=1')
            ->assertStatus(200)
            ->assertJsonCount(2, 'notifications');
    }

    public function test_mark_read_is_owner_scoped(): void
    {
        [$user, $token] = $this->userWithToken();
        $notification = $this->createNotification($user->id);

        [$other] = $this->userWithToken();

        $response = $this->withToken($token)->postJson("/api/v1/notifications/{$notification->id}/read");
        $response->assertStatus(200);
        $this->assertNotNull($response->json('notification.read_at'));

        // Cross-user read of the same notification must 404 (SRS §15.1).
        $otherToken = $other->createToken('other')->plainTextToken;
        $this->app['auth']->forgetGuards();
        $this->withToken($otherToken)->postJson("/api/v1/notifications/{$notification->id}/read")
            ->assertStatus(404);
    }

    public function test_mark_read_on_missing_notification_returns_404(): void
    {
        [$user, $token] = $this->userWithToken();

        $this->withToken($token)->postJson('/api/v1/notifications/99999/read')
            ->assertStatus(404);
    }
}
