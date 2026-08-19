<?php

namespace Tests\Feature;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

class AiCommandTest extends TestCase
{
    use RefreshDatabase;

    private function runCommand(string $command, array $parameters = []): array
    {
        $buffer = new BufferedOutput;

        $exitCode = $this->app[Kernel::class]
            ->call($command, $parameters, $buffer);

        return [$exitCode, $buffer->fetch()];
    }

    public function test_ai_status_reports_mock_provider(): void
    {
        config(['ai.driver' => 'mock']);

        [$exitCode, $output] = $this->runCommand('ai:status');

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('mock', $output);
        $this->assertStringContainsString('mock-1', $output);
        $this->assertStringContainsString('yes', $output);
    }

    public function test_ai_status_fails_when_provider_is_disabled(): void
    {
        config(['ai.driver' => 'disabled']);

        [$exitCode, $output] = $this->runCommand('ai:status');

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('disabled', $output);
        $this->assertStringContainsString('unavailable', $output);
    }

    public function test_ai_smoke_succeeds_through_mock_provider(): void
    {
        config(['ai.driver' => 'mock']);

        [$exitCode, $output] = $this->runCommand('ai:smoke', ['prompt' => 'hello']);

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Mock AI response [task_extraction]: hello', $output);
    }

    public function test_ai_smoke_fails_when_provider_is_disabled(): void
    {
        config(['ai.driver' => 'disabled']);

        [$exitCode, $output] = $this->runCommand('ai:smoke');

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('AI provider unavailable', $output);
    }
}
