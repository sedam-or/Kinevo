<?php

namespace Tests\Unit;

use App\Domain\Canvas\CanvasFile;
use PHPUnit\Framework\TestCase;

class CanvasFileTest extends TestCase
{
    public function test_file_can_be_created(): void
    {
        $file = CanvasFile::create(
            5,
            'canvases/abc.png',
            'image/png',
            2048,
            str_repeat('a', 64),
        );

        $this->assertSame(5, $file->canvasId);
        $this->assertSame('canvases/abc.png', $file->storagePath);
        $this->assertSame('image/png', $file->contentType);
        $this->assertSame(2048, $file->sizeBytes);
        $this->assertSame(str_repeat('a', 64), $file->sha256);
    }

    public function test_storage_path_is_required(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        CanvasFile::create(5, '', 'image/png', 10);
    }

    public function test_content_type_is_required(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        CanvasFile::create(5, 'path', '', 10);
    }

    public function test_size_cannot_be_negative(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        CanvasFile::create(5, 'path', 'image/png', -1);
    }

    public function test_with_id_preserves_other_properties(): void
    {
        $file = CanvasFile::create(5, 'path', 'image/png', 10);
        $withId = $file->withId(9);

        $this->assertSame(9, $withId->id);
        $this->assertSame(5, $withId->canvasId);
        $this->assertSame('path', $withId->storagePath);
        $this->assertNull($withId->sha256);
    }

    public function test_to_array_exposes_contract_fields(): void
    {
        $file = CanvasFile::create(5, 'path', 'image/png', 10)->withId(3);

        $this->assertSame([
            'id' => 3,
            'canvas_id' => 5,
            'storage_path' => 'path',
            'content_type' => 'image/png',
            'size_bytes' => 10,
            'sha256' => null,
        ], $file->toArray());
    }
}
