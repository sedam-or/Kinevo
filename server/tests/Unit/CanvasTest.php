<?php

namespace Tests\Unit;

use App\Domain\Canvas\Canvas;
use App\Domain\Canvas\CanvasDocument;
use PHPUnit\Framework\TestCase;

class CanvasTest extends TestCase
{
    public function test_canvas_can_be_created(): void
    {
        $canvas = Canvas::create(1, 'Research board');

        $this->assertSame(1, $canvas->userId);
        $this->assertSame('Research board', $canvas->title);
        $this->assertSame(1, $canvas->version);
        $this->assertNull($canvas->goalId);
        $this->assertNull($canvas->taskId);
    }

    public function test_canvas_title_cannot_be_empty(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Canvas::create(1, '');
    }

    public function test_with_id_preserves_other_properties(): void
    {
        $canvas = Canvas::create(1, 'Board')->withId(42);

        $this->assertSame(42, $canvas->id);
        $this->assertSame('Board', $canvas->title);
        $this->assertSame(1, $canvas->version);
    }

    public function test_with_title_returns_new_instance_with_incremented_version(): void
    {
        $canvas = Canvas::create(1, 'Original');
        $updated = $canvas->withTitle('Updated');

        $this->assertSame('Updated', $updated->title);
        $this->assertSame(2, $updated->version);
        $this->assertSame('Original', $canvas->title);
    }

    public function test_with_context_sets_optional_targets(): void
    {
        $canvas = Canvas::create(1, 'Board');
        $linked = $canvas->withContext(goalId: 7, taskId: 9);

        $this->assertSame(7, $linked->goalId);
        $this->assertSame(9, $linked->taskId);
        $this->assertSame(2, $linked->version);
        $this->assertNull($linked->milestoneId);
        $this->assertNull($linked->programId);
    }

    public function test_to_array_exposes_contract_fields(): void
    {
        $canvas = Canvas::create(1, 'Board')->withId(3);

        $this->assertSame([
            'id' => 3,
            'user_id' => 1,
            'title' => 'Board',
            'goal_id' => null,
            'milestone_id' => null,
            'program_id' => null,
            'task_id' => null,
            'version' => 1,
        ], $canvas->toArray());
    }

    public function test_canvas_document_can_be_created(): void
    {
        $document = CanvasDocument::create(5, ['type' => 'excalidraw', 'elements' => []]);

        $this->assertSame(5, $document->canvasId);
        $this->assertSame(1, $document->schemaVersion);
        $this->assertSame(['type' => 'excalidraw', 'elements' => []], $document->sceneJson);
        $this->assertSame(1, $document->version);
    }

    public function test_canvas_document_with_scene_bumps_version(): void
    {
        $document = CanvasDocument::create(5, ['type' => 'excalidraw']);
        $updated = $document->withScene(['type' => 'excalidraw', 'elements' => [['id' => 'a']]], 1);

        $this->assertSame(2, $updated->version);
        $this->assertSame([['id' => 'a']], $updated->sceneJson['elements']);
        $this->assertSame(1, $document->version);
    }

    public function test_canvas_document_to_array_exposes_contract_fields(): void
    {
        $document = CanvasDocument::create(5, ['elements' => []])->withScene(['elements' => []], 2);

        $this->assertSame([
            'id' => 0,
            'canvas_id' => 5,
            'schema_version' => 1,
            'scene_json' => ['elements' => []],
            'version' => 3,
        ], $document->toArray());
    }
}
