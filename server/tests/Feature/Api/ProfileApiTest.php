<?php

namespace Tests\Feature\Api;

use App\Domain\Identity\ValueObjects\ProfileSettings;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_requires_authentication(): void
    {
        $this->getJson('/api/v1/profile')->assertStatus(401);
        $this->putJson('/api/v1/profile', [])->assertStatus(401);
    }

    public function test_profile_returns_defaults_when_none_exist(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('owner')->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/v1/profile');

        $response->assertStatus(200)
            ->assertJsonPath('profile.locale', 'en')
            ->assertJsonPath('profile.timezone', 'UTC')
            ->assertJsonPath('profile.week_start_day', 'monday');
    }

    public function test_profile_can_be_updated(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('owner')->plainTextToken;

        $response = $this->withToken($token)->putJson('/api/v1/profile', [
            'display_name' => 'Owner Prime',
            'timezone' => 'Asia/Jakarta',
            'week_start_day' => 'sunday',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('profile.display_name', 'Owner Prime')
            ->assertJsonPath('profile.timezone', 'Asia/Jakarta')
            ->assertJsonPath('profile.week_start_day', 'sunday');

        $this->assertDatabaseHas('profiles', ['user_id' => $user->id, 'timezone' => 'Asia/Jakarta']);
    }

    public function test_partial_update_keeps_other_settings(): void
    {
        $user = User::factory()->create();
        Profile::query()->create([
            'user_id' => $user->id,
            'display_name' => 'Owner',
            'locale' => 'en',
            'timezone' => 'Asia/Jakarta',
            'week_start_day' => 'monday',
        ]);
        $token = $user->createToken('owner')->plainTextToken;

        $response = $this->withToken($token)->putJson('/api/v1/profile', [
            'timezone' => 'UTC',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('profile.display_name', 'Owner')
            ->assertJsonPath('profile.timezone', 'UTC')
            ->assertJsonPath('profile.locale', 'en');
    }

    public function test_invalid_timezone_is_rejected(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('owner')->plainTextToken;

        $response = $this->withToken($token)->putJson('/api/v1/profile', [
            'timezone' => 'Mars/Olympus',
        ]);

        $response->assertStatus(422);
    }

    public function test_invalid_week_start_day_is_rejected(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('owner')->plainTextToken;

        $response = $this->withToken($token)->putJson('/api/v1/profile', [
            'week_start_day' => 'tuesday',
        ]);

        $response->assertStatus(422);
    }

    public function test_settings_value_object_validates(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new ProfileSettings('Name', 'fr', 'UTC', 'monday');
    }
}
