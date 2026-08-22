<?php

namespace Tests\Feature\Api;

use App\Models\AiProviderConfig as AiProviderConfigModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class AiProviderSettingsApiTest extends TestCase
{
    use RefreshDatabase;

    private function token(): string
    {
        return User::factory()->create()->createToken('owner')->plainTextToken;
    }

    private function authHeaders(string $token): array
    {
        return ['Authorization' => 'Bearer '.$token];
    }

    public function test_config_show_returns_masked_config_and_never_the_key(): void
    {
        $token = $this->token();
        AiProviderConfigModel::query()->create([
            'provider' => 'openai',
            'enabled' => true,
            'model' => 'gpt-4o-mini',
            'base_url' => 'https://api.openai.com/v1',
            'api_key' => Crypt::encryptString('sk-super-secret-1234'),
        ]);

        $response = $this->withHeaders($this->authHeaders($token))->getJson('/api/v1/ai/config');

        $response->assertOk();
        $response->assertJsonPath('config.provider', 'openai');
        $response->assertJsonPath('config.has_api_key', true);
        $response->assertJsonMissing(['config' => ['api_key' => 'sk-super-secret-1234']]);
        // The raw secret must not appear anywhere in the payload.
        $this->assertStringNotContainsString('sk-super-secret-1234', $response->getContent());
    }

    public function test_config_update_encrypts_and_never_echoes_the_key(): void
    {
        $token = $this->token();

        $response = $this->withHeaders($this->authHeaders($token))
            ->putJson('/api/v1/ai/config', [
                'provider' => 'openai',
                'enabled' => true,
                'model' => 'gpt-4o-mini',
                'base_url' => 'https://api.openai.com/v1/',
                'api_key' => 'sk-live-secret-5678',
            ]);

        $response->assertOk();
        $this->assertStringNotContainsString('sk-live-secret-5678', $response->getContent());
        $response->assertJsonPath('config.has_api_key', true);
        $response->assertJsonPath('config.api_key_hint', '…5678');

        // Stored encrypted, never plaintext.
        $stored = AiProviderConfigModel::query()->first();
        $this->assertNotNull($stored);
        $this->assertNotSame('sk-live-secret-5678', $stored->getAttributes()['api_key'] ?? null);
        $this->assertSame('sk-live-secret-5678', Crypt::decryptString($stored->getAttributes()['api_key']));
    }

    public function test_config_update_replaces_and_removes_api_key(): void
    {
        $token = $this->token();
        AiProviderConfigModel::query()->create([
            'provider' => 'openai',
            'enabled' => true,
            'api_key' => Crypt::encryptString('sk-first'),
        ]);

        // Replace.
        $this->withHeaders($this->authHeaders($token))->putJson('/api/v1/ai/config', [
            'provider' => 'openai',
            'api_key' => 'sk-second',
        ])->assertOk();
        $this->assertSame(
            'sk-second',
            Crypt::decryptString(AiProviderConfigModel::query()->first()->getAttributes()['api_key']),
        );

        // Remove-only.
        $this->withHeaders($this->authHeaders($token))->putJson('/api/v1/ai/config', [
            'provider' => 'openai',
            'remove_api_key' => true,
        ])->assertJsonPath('config.has_api_key', false);
    }

    public function test_ollama_path_requires_no_api_key(): void
    {
        $token = $this->token();

        $this->withHeaders($this->authHeaders($token))->putJson('/api/v1/ai/config', [
            'provider' => 'ollama',
            'base_url' => 'http://localhost:11434',
            'model' => 'llama3.1',
        ])->assertOk()->assertJsonPath('config.has_api_key', false);
    }

    public function test_openai_without_api_key_is_rejected(): void
    {
        $token = $this->token();

        $this->withHeaders($this->authHeaders($token))->putJson('/api/v1/ai/config', [
            'provider' => 'openai',
        ])->assertStatus(422);
    }

    public function test_unsupported_provider_is_rejected(): void
    {
        $token = $this->token();

        $this->withHeaders($this->authHeaders($token))->putJson('/api/v1/ai/config', [
            'provider' => 'watson',
        ])->assertStatus(422);
    }

    public function test_config_test_with_disabled_provider_reports_not_available(): void
    {
        $token = $this->token();

        $this->withHeaders($this->authHeaders($token))->postJson('/api/v1/ai/config/test', [
            'provider' => 'disabled',
        ])->assertOk()->assertJsonPath('status.available', false);
    }

    public function test_config_endpoints_require_authentication(): void
    {
        $this->getJson('/api/v1/ai/config')->assertStatus(401);
        $this->putJson('/api/v1/ai/config', ['provider' => 'ollama'])->assertStatus(401);
        $this->postJson('/api/v1/ai/config/test', ['provider' => 'disabled'])->assertStatus(401);
    }
}