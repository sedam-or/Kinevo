<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class QuickCaptureApiTest extends TestCase
{
    use RefreshDatabase;

    private function userWithToken(): array
    {
        $user = User::factory()->create();
        $token = $user->createToken('owner')->plainTextToken;

        return [$user, $token];
    }

    public function test_quick_capture_requires_authentication(): void
    {
        $this->postJson('/api/v1/quick-capture', ['title' => 'X'])->assertStatus(401);
    }

    public function test_quick_capture_places_task_when_slot_available(): void
    {
        [$user, $token] = $this->userWithToken();

        $this->withToken($token)
            ->postJson('/api/v1/quick-capture', [
                'title' => 'Write report',
                'priority_tier' => 2,
                'size' => 'sedang',
            ])
            ->assertStatus(201)
            ->assertJsonPath('placed', true)
            ->assertJsonPath('code', 'PLACED')
            ->assertJsonPath('task.title', 'Write report')
            ->assertJsonPath('assignment.duration_minutes', 45);

        $this->assertDatabaseHas('tasks', ['user_id' => $user->id, 'title' => 'Write report']);
    }

    public function test_quick_capture_validates_input(): void
    {
        [$user, $token] = $this->userWithToken();

        $this->withToken($token)
            ->postJson('/api/v1/quick-capture', ['title' => '', 'size' => 'bogus'])
            ->assertStatus(422);
    }
}
