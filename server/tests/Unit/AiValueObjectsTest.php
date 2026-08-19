<?php

namespace Tests\Unit;

use App\Domain\Ai\ValueObjects\AiRequest;
use App\Domain\Ai\ValueObjects\AiRole;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class AiValueObjectsTest extends TestCase
{
    #[Test]
    public function role_is_a_closed_set(): void
    {
        $this->assertSame('goal_decomposition', (new AiRole('goal_decomposition'))->value);
        $this->assertSame('canvas_proposal', (new AiRole('canvas_proposal'))->value);
        $this->assertTrue((new AiRole('task_extraction'))->equals(new AiRole('task_extraction')));
    }

    #[Test]
    public function unknown_role_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new AiRole('take_over_the_world');
    }

    #[Test]
    public function request_requires_non_empty_prompt(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new AiRequest(new AiRole('task_extraction'), '   ');
    }

    #[Test]
    public function request_rejects_out_of_range_temperature(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new AiRequest(new AiRole('task_extraction'), 'prompt', temperature: 2.5);
    }
}
