<?php

namespace Tests\Feature\Api;

use App\Models\AiProviderConfig as AiProviderConfigModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
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

    public function test_config_show_reports_canonical_state(): void
    {
        $token = $this->token();
        // Seed through the singleton id exactly as the repository writes it;
        // plain create() would burn sequence values that survive rollback.
        AiProviderConfigModel::query()->updateOrCreate(
            ['id' => AiProviderConfigModel::SINGLETON_ID],
            [
                'provider' => 'ollama',
                'enabled' => true,
                'model' => 'llama3.1',
                'base_url' => 'http://localhost:11434',
            ],
        );
        // Probe fails: configured ≠ available (TASK-P17-007).
        Http::fake(['http://localhost:11434/api/tags' => Http::response('down', 500)]);

        $this->withHeaders($this->authHeaders($token))->getJson('/api/v1/ai/config')
            ->assertOk()
            ->assertJsonPath('config.enabled', true)
            ->assertJsonPath('config.status.state', 'unavailable')
            ->assertJsonPath('config.status.available', false);

        // Same mapper on /ai/status — one source of truth.
        $this->withHeaders($this->authHeaders($token))->getJson('/api/v1/ai/status')
            ->assertOk()
            ->assertJsonPath('status.state', 'unavailable');
    }

    public function test_enabled_openai_without_key_maps_to_not_configured(): void
    {
        $token = $this->token();
        AiProviderConfigModel::query()->updateOrCreate(
            ['id' => AiProviderConfigModel::SINGLETON_ID],
            [
                'provider' => 'openai',
                'enabled' => true,
                'model' => 'gpt-4o-mini',
                'base_url' => 'https://api.openai.com/v1',
            ],
        );

        $this->withHeaders($this->authHeaders($token))->getJson('/api/v1/ai/status')
            ->assertOk()
            ->assertJsonPath('status.state', 'not_configured');
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
        $stored = AiProviderConfigModel::query()->first();
        $this->assertNotNull($stored);
        $this->assertSame('sk-second', Crypt::decryptString($stored->getAttributes()['api_key']));

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

    // ------------------------------------------------------------------
    // TASK-P18-006 / P18-007 — canonical settings surface + safe response.
    // ------------------------------------------------------------------

    public function test_settings_show_returns_safe_shape_and_never_the_secret(): void
    {
        $token = $this->token();
        AiProviderConfigModel::query()->create([
            'provider' => 'openai',
            'enabled' => true,
            'model' => 'gpt-4o-mini',
            'api_key' => Crypt::encryptString('sk-super-secret-9012'),
        ]);

        $response = $this->withHeaders($this->authHeaders($token))
            ->getJson('/api/v1/ai/settings')->assertOk();

        $body = $response->getContent();
        $this->assertStringNotContainsString('sk-super-secret-9012', $body);
        // Forbidden surface: no raw key field, no ciphertext field.
        $this->assertStringNotContainsString('"api_key"', $body);
        $response->assertJsonPath('config.has_api_key', true);
        $response->assertJsonPath('config.api_key_hint', '…9012');
        $response->assertJsonPath('config.provider', 'openai');
        $response->assertJsonPath('config.protocol', 'openai-chat');
        $response->assertJsonPath('config.configured', true);
        $response->assertJsonPath('config.privacy_ok', true);
    }

    public function test_settings_patch_updates_partial_fields_without_losing_provider(): void
    {
        $token = $this->token();
        AiProviderConfigModel::query()->create([
            'provider' => 'ollama',
            'enabled' => true,
            'model' => 'llama3.1',
        ]);

        $this->withHeaders($this->authHeaders($token))
            ->patchJson('/api/v1/ai/settings', ['model' => 'qwen2.5'])
            ->assertOk()
            ->assertJsonPath('config.provider', 'ollama')
            ->assertJsonPath('config.model', 'qwen2.5')
            ->assertJsonPath('config.enabled', true);

        $stored = AiProviderConfigModel::query()->first();
        $this->assertSame('ollama', $stored->provider);
        $this->assertTrue((bool) $stored->enabled);
        $this->assertSame('qwen2.5', $stored->model);
    }

    public function test_credential_endpoints_store_and_remove_without_echoing(): void
    {
        $token = $this->token();

        $set = $this->withHeaders($this->authHeaders($token))
            ->postJson('/api/v1/ai/settings/credential', ['api_key' => 'sk-rotate-me-4242'])
            ->assertOk();
        $this->assertStringNotContainsString('sk-rotate-me-4242', $set->getContent());
        $set->assertJsonPath('config.has_api_key', true);
        $set->assertJsonPath('config.api_key_hint', '…4242');

        $stored = AiProviderConfigModel::query()->first();
        $this->assertNotNull($stored);
        $this->assertSame('sk-rotate-me-4242', Crypt::decryptString($stored->getAttributes()['api_key']));

        $missing = $this->withHeaders($this->authHeaders($token))
            ->postJson('/api/v1/ai/settings/credential', [])->assertStatus(422);

        $removed = $this->withHeaders($this->authHeaders($token))
            ->deleteJson('/api/v1/ai/settings/credential')->assertOk();
        $this->assertStringNotContainsString('sk-rotate-me-4242', $removed->getContent());
        $removed->assertJsonPath('config.has_api_key', false);

        $stored->refresh();
        $this->assertNull($stored->freshApiKey());
        $this->assertNotNull($missing);
    }

    public function test_enable_and_disable_preserve_configuration(): void
    {
        $token = $this->token();
        AiProviderConfigModel::query()->create([
            'provider' => 'ollama',
            'enabled' => true,
            'model' => 'llama3.1',
        ]);

        $this->withHeaders($this->authHeaders($token))
            ->postJson('/api/v1/ai/settings/disable')->assertOk()
            ->assertJsonPath('config.enabled', false)
            ->assertJsonPath('config.model', 'llama3.1');

        $this->withHeaders($this->authHeaders($token))
            ->postJson('/api/v1/ai/settings/enable')->assertOk()
            ->assertJsonPath('config.enabled', true);
    }

    public function test_enable_requires_a_configured_provider(): void
    {
        $token = $this->token();

        $this->withHeaders($this->authHeaders($token))
            ->postJson('/api/v1/ai/settings/enable')->assertStatus(422);
    }

    public function test_providers_catalog_lists_capabilities_and_protocols(): void
    {
        $token = $this->token();

        $response = $this->withHeaders($this->authHeaders($token))
            ->getJson('/api/v1/ai/providers')->assertOk();

        $providers = collect($response->json('providers'));
        $ids = $providers->pluck('id')->values()->all();
        $this->assertEqualsCanonicalizing(['mock', 'ollama', 'openai'], $ids);

        $openai = $providers->firstWhere('id', 'openai');
        $this->assertTrue($openai['requires_api_key']);
        $this->assertSame(['openai-chat'], $openai['protocols']);

        $ollama = $providers->firstWhere('id', 'ollama');
        $this->assertFalse($ollama['requires_api_key']);
        $this->assertTrue($ollama['supports_local']);
    }

    // ------------------------------------------------------------------
    // TASK-P18-008 — connection test proves model usability, not a ping.
    // ------------------------------------------------------------------

    public function test_connection_test_runs_minimal_inference_on_saved_settings(): void
    {
        $token = $this->token();
        AiProviderConfigModel::query()->create([
            'provider' => 'mock',
            'enabled' => true,
            'model' => 'mock-1',
        ]);

        $response = $this->withHeaders($this->authHeaders($token))
            ->postJson('/api/v1/ai/settings/test')->assertOk();

        $response->assertJsonPath('ok', true)
            ->assertJsonPath('code', null)
            ->assertJsonPath('status.available', true);

        $stored = AiProviderConfigModel::query()->first();
        $this->assertNotNull($stored->last_verified_at);
        $this->assertSame('connected', $stored->last_status);
        $this->assertNull($stored->last_error_code);
    }

    public function test_failed_connection_test_records_safe_error_code(): void
    {
        $token = $this->token();
        AiProviderConfigModel::query()->create([
            'provider' => 'openai',
            'enabled' => true,
            'model' => 'gpt-4o-mini',
            'base_url' => 'https://openai.example.test/v1',
            'api_key' => Crypt::encryptString('sk-bad'),
        ]);
        Http::fake([
            'openai.example.test/*' => Http::response(['error' => ['message' => 'upstream secret detail']], 401),
        ]);

        $response = $this->withHeaders($this->authHeaders($token))
            ->postJson('/api/v1/ai/settings/test')->assertOk();

        $response->assertJsonPath('ok', false)
            ->assertJsonPath('code', 'AI_PROVIDER_AUTH_FAILED');
        $this->assertStringNotContainsString('upstream secret detail', $response->getContent());

        $stored = AiProviderConfigModel::query()->first();
        $this->assertSame('failed', $stored->last_status);
        $this->assertSame('AI_PROVIDER_AUTH_FAILED', $stored->last_error_code);
    }

    public function test_new_settings_endpoints_require_authentication(): void
    {
        $this->getJson('/api/v1/ai/settings')->assertStatus(401);
        $this->patchJson('/api/v1/ai/settings', ['model' => 'x'])->assertStatus(401);
        $this->postJson('/api/v1/ai/settings/credential', ['api_key' => 'k'])->assertStatus(401);
        $this->deleteJson('/api/v1/ai/settings/credential')->assertStatus(401);
        $this->postJson('/api/v1/ai/settings/test')->assertStatus(401);
        $this->postJson('/api/v1/ai/settings/enable')->assertStatus(401);
        $this->postJson('/api/v1/ai/settings/disable')->assertStatus(401);
        $this->getJson('/api/v1/ai/providers')->assertStatus(401);
    }
}
