<?php

namespace Tests\Feature;

use App\NativeComponents\GoalScreen;
use Illuminate\Support\Facades\Http;
use Native\Mobile\Testing\TestableComponent;
use Tests\TestCase;

/**
 * P27-012 — mobile state branches reachable from component actions.
 * KinevoApi reads the token from app storage, so tests seed a throwaway
 * token file and fake the HTTP transport; no real backend is touched.
 */
final class NativeStateTest extends TestCase
{
    private string $tokenPath = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->tokenPath = storage_path('app/kinevo_token.txt');
        file_put_contents($this->tokenPath, 'test-token');
    }

    protected function tearDown(): void
    {
        @unlink($this->tokenPath);
        parent::tearDown();
    }

    public function test_breakdown_entitlement_surfaces_plan_limit_state(): void
    {
        Http::fake([
            '*' => Http::response(['error' => 'Plan limit reached.', 'code' => 'ENTITLEMENT_LIMIT'], 403),
        ]);

        TestableComponent::test(GoalScreen::class)
            ->call('proposeBreakdown', 1)
            ->assertSet('state', 'entitlement')
            ->assertSee('Plan limit');
    }

    public function test_breakdown_ai_unavailable_surfaces_provider_message(): void
    {
        Http::fake([
            '*' => Http::response(['error' => 'AI provider is disabled.', 'code' => 'AI_PROVIDER_UNAVAILABLE'], 503),
        ]);

        TestableComponent::test(GoalScreen::class)
            ->call('proposeBreakdown', 1)
            ->assertSet('state', 'error')
            ->assertSee('AI is not ready');
    }

    public function test_breakdown_network_failure_stays_retryable(): void
    {
        Http::fake([
            '*' => Http::response(['error' => 'boom'], 500),
        ]);

        TestableComponent::test(GoalScreen::class)
            ->call('proposeBreakdown', 1)
            ->assertSet('state', 'error')
            ->assertSee('retry on the web app');
    }
}
