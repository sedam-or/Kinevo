<?php

namespace Tests\Unit;

use App\Domain\Ai\ValueObjects\AiProposalType;
use App\Domain\Ai\ValueObjects\AiRequest;
use App\Domain\Ai\ValueObjects\AiRole;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class NoteAiContextTest extends TestCase
{
    #[Test]
    public function note_types_map_to_roles(): void
    {
        $this->assertSame('note_summarization', (new AiProposalType('summary'))->role()->value);
        $this->assertSame('task_extraction', (new AiProposalType('task_extraction'))->role()->value);
    }

    #[Test]
    public function prompt_is_required_and_bounded(): void
    {
        $request = new AiRequest(new AiRole('task_extraction'), str_repeat('x', 8000));

        $this->assertSame(8000, mb_strlen($request->prompt));

        // A request with an empty prompt is rejected by the VO.
        $this->expectException(\InvalidArgumentException::class);
        new AiRequest(new AiRole('task_extraction'), '');
    }
}
