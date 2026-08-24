<?php

namespace Tests\Unit;

use App\Domain\Ai\AiOutputException;
use App\Domain\Ai\AiSchemaRegistry;
use App\Domain\Ai\AiSchemaRules;
use App\Domain\Ai\StructuredAiOutputParser;
use App\Domain\Ai\ValueObjects\AiProposalType;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class StructuredAiOutputTest extends TestCase
{
    private StructuredAiOutputParser $parser;

    protected function setUp(): void
    {
        $this->parser = new StructuredAiOutputParser(new AiSchemaRegistry, new AiSchemaRules);
    }

    private function expectInvalid(): void
    {
        $this->expectException(AiOutputException::class);
    }

    #[Test]
    public function valid_goal_breakdown_passes(): void
    {
        $proposal = $this->parser->parse(new AiProposalType('goal_breakdown'), json_encode([
            'type' => 'goal_breakdown_proposal',
            'goal_id' => 7,
            'milestones' => [
                ['title' => 'Research', 'target_date' => '2026-09-01', 'estimated_minutes' => 600],
                ['title' => 'Build'],
            ],
        ]));

        $this->assertSame(1, $proposal->schemaVersion);
        $this->assertSame(7, $proposal->payload['goal_id']);
        $this->assertCount(2, $proposal->payload['milestones']);
    }

    #[Test]
    public function malformed_json_is_rejected(): void
    {
        $this->expectInvalid();
        $this->parser->parse(new AiProposalType('summary'), '{"summary": "oops"');
    }

    #[Test]
    public function code_fence_wrapping_is_tolerated(): void
    {
        $proposal = $this->parser->parse(new AiProposalType('summary'), "```json\n{\"type\":\"summary_proposal\",\"summary\":\"S\",\"key_points\":[\"a\"]}\n```");

        $this->assertSame('S', $proposal->payload['summary']);
    }

    #[Test]
    public function missing_required_field_is_rejected(): void
    {
        $this->expectInvalid();
        $this->parser->parse(new AiProposalType('milestone'), json_encode([
            'type' => 'milestone_proposal',
            'title' => 'No goal id',
        ]));
    }

    #[Test]
    public function wrong_type_is_rejected(): void
    {
        $this->expectInvalid();
        $this->parser->parse(new AiProposalType('milestone'), json_encode([
            'type' => 'milestone_proposal',
            'goal_id' => 'seven',
            'title' => 'T',
        ]));
    }

    #[Test]
    public function invalid_discriminator_is_rejected(): void
    {
        $this->expectInvalid();
        $this->parser->parse(new AiProposalType('milestone'), json_encode([
            'type' => 'take_over_the_world',
            'goal_id' => 1,
            'title' => 'T',
        ]));
    }

    #[Test]
    public function empty_array_violates_min_items(): void
    {
        $this->expectInvalid();
        $this->parser->parse(new AiProposalType('task_extraction'), json_encode([
            'type' => 'task_extraction_proposal',
            'tasks' => [],
        ]));
    }

    #[Test]
    public function bad_date_format_is_rejected(): void
    {
        $this->expectInvalid();
        $this->parser->parse(new AiProposalType('milestone'), json_encode([
            'type' => 'milestone_proposal',
            'goal_id' => 1,
            'title' => 'T',
            'target_date' => '09/01/2026',
        ]));
    }

    #[Test]
    public function negative_estimated_minutes_are_rejected(): void
    {
        $this->expectInvalid();
        $this->parser->parse(new AiProposalType('milestone'), json_encode([
            'type' => 'milestone_proposal',
            'goal_id' => 1,
            'title' => 'T',
            'estimated_minutes' => -5,
        ]));
    }

    #[Test]
    public function non_object_array_item_is_rejected(): void
    {
        $this->expectInvalid();
        $this->parser->parse(new AiProposalType('canvas'), json_encode([
            'type' => 'canvas_proposal',
            'title' => 'T',
            'sections' => ['just-a-string'],
        ]));
    }

    #[Test]
    public function goal_breakdown_accepts_explanation_fields(): void
    {
        $proposal = $this->parser->parse(new AiProposalType('goal_breakdown'), json_encode([
            'type' => 'goal_breakdown_proposal',
            'goal_id' => 7,
            'rationale' => 'Research before build reduces rework.',
            'assumptions' => ['Stable team size', 'Quarterly deadline holds'],
            'inputs' => ['Deadline 2026-12-31', 'Weekly capacity 20h'],
            'constraints' => ['Hard landscape Monday 09:00', '30% recharge reserve'],
            'risks' => ['Scope creep around integrations.'],
            'milestones' => [
                ['title' => 'Research', 'target_date' => '2026-09-01', 'estimated_minutes' => 600],
                ['title' => 'Build'],
            ],
        ]));
        $this->assertSame('Research before build reduces rework.', $proposal->payload['rationale']);
        $this->assertSame(['Stable team size', 'Quarterly deadline holds'], $proposal->payload['assumptions']);
        $this->assertSame(['Deadline 2026-12-31', 'Weekly capacity 20h'], $proposal->payload['inputs']);
        $this->assertSame(['Hard landscape Monday 09:00', '30% recharge reserve'], $proposal->payload['constraints']);
    }

    #[Test]
    public function summary_key_points_are_scalar_strings(): void
    {
        $proposal = $this->parser->parse(new AiProposalType('summary'), json_encode([
            'type' => 'summary_proposal',
            'summary' => 'S',
            'key_points' => ['one', 'two'],
        ]));

        $this->assertSame(['one', 'two'], $proposal->payload['key_points']);
    }

    #[Test]
    public function non_object_top_level_json_is_rejected(): void
    {
        $this->expectInvalid();
        $this->parser->parse(new AiProposalType('summary'), '[1,2,3]');
    }
}
