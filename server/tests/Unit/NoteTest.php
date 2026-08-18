<?php

namespace Tests\Unit;

use App\Domain\Knowledge\Note;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class NoteTest extends TestCase
{
    #[Test]
    public function note_can_be_created_with_title_only(): void
    {
        $note = Note::create(1, 'My research notes');

        $this->assertSame('My research notes', $note->title);
        $this->assertSame(1, $note->userId);
        $this->assertSame(1, $note->version);
        $this->assertNull($note->documentJson);
    }

    #[Test]
    public function note_title_cannot_be_empty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Note::create(1, '  ');
    }

    #[Test]
    public function with_title_returns_new_instance_with_incremented_version(): void
    {
        $note = Note::create(1, 'Original');

        $updated = $note->withTitle('Updated');

        $this->assertSame('Updated', $updated->title);
        $this->assertSame(2, $updated->version);
        $this->assertSame('Original', $note->title);
        $this->assertSame(1, $note->version);
    }

    #[Test]
    public function with_content_returns_new_instance_with_incremented_version(): void
    {
        $note = Note::create(1, 'Title');

        $updated = $note->withContent(
            ['type' => 'doc', 'content' => []],
            '# Markdown',
            'Plain text',
        );

        $this->assertSame(['type' => 'doc', 'content' => []], $updated->documentJson);
        $this->assertSame('# Markdown', $updated->markdownCache);
        $this->assertSame('Plain text', $updated->plainTextCache);
        $this->assertSame(2, $updated->version);
    }

    #[Test]
    public function with_id_preserves_other_properties(): void
    {
        $note = Note::create(1, 'Title')->withContent(['doc' => true], '# MD', 'text');

        $withId = $note->withId(42);

        $this->assertSame(42, $withId->id);
        $this->assertSame($note->title, $withId->title);
        $this->assertSame($note->documentJson, $withId->documentJson);
        $this->assertSame($note->version, $withId->version);
    }

    #[Test]
    public function to_array_exposes_all_fields(): void
    {
        $note = Note::create(1, 'Title', ['doc' => true], '# MD', 'text');

        $array = $note->toArray();

        $this->assertSame('Title', $array['title']);
        $this->assertSame(['doc' => true], $array['document_json']);
        $this->assertSame('# MD', $array['markdown_cache']);
        $this->assertSame('text', $array['plain_text_cache']);
        $this->assertSame(1, $array['version']);
    }
}
