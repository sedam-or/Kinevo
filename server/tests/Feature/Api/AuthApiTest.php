<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_user_can_register(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Owner',
            'email' => 'owner@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['user' => ['id', 'name', 'email'], 'token', 'profile']);

        $userId = $response->json('user.id');

        $this->assertDatabaseHas('users', ['email' => 'owner@example.com']);
        $this->assertDatabaseHas('profiles', ['user_id' => $userId, 'locale' => 'en']);
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_second_user_cannot_register(): void
    {
        User::factory()->create(['email' => 'first@example.com']);

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Second',
            'email' => 'second@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(409);
        $this->assertDatabaseMissing('users', ['email' => 'second@example.com']);
    }

    public function test_register_requires_valid_input(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => '',
            'email' => 'not-an-email',
            'password' => 'short',
        ]);

        $response->assertStatus(422);
    }

    public function test_user_can_login_and_get_token(): void
    {
        $user = User::factory()->create([
            'email' => 'owner@example.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'owner@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['user' => ['id', 'name', 'email'], 'token']);
        $this->assertNotNull($response->json('token'));
    }

    public function test_login_with_wrong_password_is_rejected(): void
    {
        User::factory()->create([
            'email' => 'owner@example.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'owner@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(401);
    }

    public function test_authenticated_user_can_get_identity(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('owner')->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/v1/auth/me');

        $response->assertStatus(200)
            ->assertJsonPath('user.email', $user->email);
    }

    public function test_me_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/auth/me');

        $response->assertStatus(401);
    }

    public function test_logout_revokes_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('owner')->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/v1/auth/logout');

        $response->assertStatus(200);
        $this->assertDatabaseCount('personal_access_tokens', 0);

        auth()->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/auth/me')->assertStatus(401);
    }
}
