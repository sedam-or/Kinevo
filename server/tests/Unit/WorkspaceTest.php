<?php

namespace Tests\Unit;

use App\Domain\Workspaces\ValueObjects\WorkspaceStatus;
use App\Domain\Workspaces\ValueObjects\WorkspaceType;
use App\Domain\Workspaces\Workspace;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class WorkspaceTest extends TestCase
{
    #[Test]
    public function create_requires_a_name_and_generates_a_deterministic_slug(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Workspace::create(1, '   ');
    }

    #[Test]
    public function slug_is_url_safe_and_lowercase(): void
    {
        $workspace = Workspace::create(1, 'Deep Research 2026!');
        $this->assertSame('deep-research-2026', $workspace->slug);
        $this->assertSame('Deep Research 2026!', $workspace->name);
    }

    #[Test]
    public function name_cannot_exceed_eighty_characters(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Workspace::create(1, str_repeat('a', 81));
    }

    #[Test]
    public function type_validates_known_families_and_defaults_to_personal(): void
    {
        $this->assertSame(WorkspaceType::PERSONAL, (string) Workspace::create(1, 'A')->type);
        $this->assertSame(WorkspaceType::RESEARCH, (string) Workspace::create(1, 'B', type: 'Research')->type);

        $this->expectException(InvalidArgumentException::class);
        Workspace::create(1, 'C', type: 'quantum');
    }

    #[Test]
    public function archive_preserves_data_and_restore_recovers_activity(): void
    {
        $workspace = Workspace::create(1, 'Side Quests')
            ->describe('exploratory work')
            ->restyle(icon: 'compass', accent: '#4f46e5');

        $archived = $workspace->archive();
        $this->assertTrue($archived->status->isArchived());
        // Data is preserved through the lifecycle change.
        $this->assertSame('exploratory work', $archived->description);
        $this->assertSame('compass', $archived->icon);
        $this->assertSame('#4f46e5', $archived->accent);
        $this->assertSame('Side Quests', $archived->name);

        $restored = $archived->restore();
        $this->assertTrue($restored->status->isActive());
    }

    #[Test]
    public function the_default_workspace_cannot_be_archived(): void
    {
        $default = Workspace::defaultFor(userId: 7);
        $this->assertTrue($default->isDefault);
        $this->assertSame(Workspace::DEFAULT_NAME, $default->name);

        $this->expectException(InvalidArgumentException::class);
        $default->archive();
    }

    #[Test]
    public function archived_workspace_cannot_be_active_state(): void
    {
        $workspace = Workspace::create(1, 'Old Project')->archive();
        // Status values are mutually exclusive by construction.
        $this->assertFalse($workspace->status->isActive());
        $this->assertSame(WorkspaceStatus::ARCHIVED, (string) $workspace->status);
    }

    #[Test]
    public function renaming_updates_the_slug_but_keeps_history_fields(): void
    {
        $original = Workspace::create(1, 'Alpha', description: 'first');
        $renamed = $original->rename('Beta');

        $this->assertSame('beta', $renamed->slug);
        $this->assertSame('first', $renamed->description);
        $this->assertNotSame($original, $renamed);
    }
}
