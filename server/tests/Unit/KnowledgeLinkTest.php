<?php

namespace Tests\Unit;

use App\Domain\Knowledge\KnowledgeLink;
use App\Domain\Knowledge\ValueObjects\KnowledgeLinkType;
use App\Domain\Knowledge\ValueObjects\KnowledgeTargetType;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class KnowledgeLinkTest extends TestCase
{
    #[Test]
    public function link_can_be_created(): void
    {
        $link = KnowledgeLink::create(
            1,
            KnowledgeLink::SOURCE_NOTE,
            10,
            KnowledgeTargetType::goal(),
            5,
            KnowledgeLinkType::supports(),
        );

        $this->assertSame(1, $link->userId);
        $this->assertSame('note', $link->sourceType);
        $this->assertSame(10, $link->sourceId);
        $this->assertSame('goal', $link->targetType->value);
        $this->assertSame(5, $link->targetId);
        $this->assertSame('supports', $link->linkType->value);
    }

    #[Test]
    public function link_can_target_canvas(): void
    {
        $link = KnowledgeLink::create(
            1,
            KnowledgeLink::SOURCE_NOTE,
            10,
            KnowledgeTargetType::canvas(),
            5,
            KnowledgeLinkType::evidenceFor(),
        );

        $this->assertSame('canvas', $link->targetType->value);
        $this->assertSame(5, $link->targetId);
    }

    #[Test]
    public function canvas_can_be_a_link_source(): void
    {
        $link = KnowledgeLink::create(
            1,
            KnowledgeLink::SOURCE_CANVAS,
            12,
            KnowledgeTargetType::note(),
            30,
            KnowledgeLinkType::relatedTo(),
        );

        $this->assertSame('canvas', $link->sourceType);
        $this->assertSame(12, $link->sourceId);
        $this->assertSame('note', $link->targetType->value);
        $this->assertSame(30, $link->targetId);
    }

    #[Test]
    public function source_id_must_be_positive(): void
    {
        $this->expectException(InvalidArgumentException::class);
        KnowledgeLink::create(1, 'note', 0, KnowledgeTargetType::goal(), 5, KnowledgeLinkType::supports());
    }

    #[Test]
    public function target_id_must_be_positive(): void
    {
        $this->expectException(InvalidArgumentException::class);
        KnowledgeLink::create(1, 'note', 10, KnowledgeTargetType::goal(), 0, KnowledgeLinkType::supports());
    }

    #[Test]
    public function with_id_preserves_other_properties(): void
    {
        $link = KnowledgeLink::create(
            1,
            KnowledgeLink::SOURCE_NOTE,
            10,
            KnowledgeTargetType::task(),
            7,
            KnowledgeLinkType::references(),
        );

        $withId = $link->withId(42);

        $this->assertSame(42, $withId->id);
        $this->assertSame($link->sourceType, $withId->sourceType);
        $this->assertSame($link->targetType->value, $withId->targetType->value);
        $this->assertSame($link->linkType->value, $withId->linkType->value);
    }

    #[Test]
    public function to_array_exposes_all_fields(): void
    {
        $link = KnowledgeLink::create(
            1,
            KnowledgeLink::SOURCE_NOTE,
            10,
            KnowledgeTargetType::milestone(),
            3,
            KnowledgeLinkType::derivedFrom(),
        )->withId(9);

        $array = $link->toArray();

        $this->assertSame(9, $array['id']);
        $this->assertSame('note', $array['source_type']);
        $this->assertSame(10, $array['source_id']);
        $this->assertSame('milestone', $array['target_type']);
        $this->assertSame(3, $array['target_id']);
        $this->assertSame('derived_from', $array['link_type']);
    }

    #[Test]
    public function link_type_rejects_unknown_values(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new KnowledgeLinkType('random');
    }

    #[Test]
    public function target_type_rejects_unknown_values(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new KnowledgeTargetType('contact');
    }

    #[Test]
    public function link_type_all_exposes_supported_set(): void
    {
        $this->assertSame(
            ['supports', 'references', 'derived_from', 'evidence_for', 'related_to'],
            KnowledgeLinkType::all(),
        );
    }

    #[Test]
    public function target_type_all_exposes_supported_set(): void
    {
        $this->assertSame(
            ['goal', 'milestone', 'program', 'task', 'canvas', 'note'],
            KnowledgeTargetType::all(),
        );
    }
}
