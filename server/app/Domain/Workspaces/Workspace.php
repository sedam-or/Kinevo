<?php

namespace App\Domain\Workspaces;

use App\Domain\Workspaces\ValueObjects\WorkspaceStatus;
use App\Domain\Workspaces\ValueObjects\WorkspaceType;
use InvalidArgumentException;

/**
 * Workspace aggregate — the top-level context container for goals, programs,
 * tasks, notes and canvas (TASK-P19-001).
 *
 * Invariants:
 * - owner scoped: every workspace belongs to exactly one user;
 * - name required;
 * - slug unique per user (enforced at the persistence boundary + generated
 *   here deterministically from the name);
 * - exactly one default workspace per user;
 * - archived workspace cannot be active (mutually exclusive states);
 * - archive preserves data and is always reversible.
 *
 * Immutable value semantics: state changes return a new instance.
 */
final class Workspace
{
    public const DEFAULT_NAME = 'Personal';

    public function __construct(
        public readonly int $id,
        public readonly int $userId,
        public readonly string $name,
        public readonly string $slug,
        public readonly ?string $description,
        public readonly ?string $icon,
        public readonly ?string $accent,
        public readonly WorkspaceType $type,
        public readonly bool $isDefault,
        public readonly WorkspaceStatus $status,
    ) {}

    public static function create(
        int $userId,
        string $name,
        ?string $description = null,
        ?string $icon = null,
        ?string $accent = null,
        ?string $type = null,
    ): self {
        $trimmedName = trim($name);
        if ($trimmedName === '') {
            throw new InvalidArgumentException('Workspace name is required.');
        }
        if (mb_strlen($trimmedName) > 80) {
            throw new InvalidArgumentException('Workspace name must be at most 80 characters.');
        }

        return new self(
            id: 0,
            userId: $userId,
            name: $trimmedName,
            slug: self::slugify($trimmedName),
            description: self::optionalText($description, 500),
            icon: self::optionalText($icon, 32),
            accent: self::optionalText($accent, 16),
            type: WorkspaceType::from($type),
            isDefault: false,
            status: WorkspaceStatus::active(),
        );
    }

    /** The auto-provisioned per-user default (TASK-P19-003). */
    public static function defaultFor(int $userId): self
    {
        return new self(
            id: 0,
            userId: $userId,
            name: self::DEFAULT_NAME,
            slug: self::slugify(self::DEFAULT_NAME),
            description: 'Your default workspace.',
            icon: null,
            accent: null,
            type: WorkspaceType::from(WorkspaceType::PERSONAL),
            isDefault: true,
            status: WorkspaceStatus::active(),
        );
    }

    public function rename(string $name): self
    {
        return $this->rebuild($name, $this->description, $this->icon, $this->accent, $this->type, $this->status);
    }

    /** Setting a field to null clears it. */
    public function describe(?string $description): self
    {
        return $this->rebuild(
            $this->name,
            self::optionalText($description, 500),
            $this->icon,
            $this->accent,
            $this->type,
            $this->status,
        );
    }

    /** Setting a field to null clears it. */
    public function restyle(?string $icon, ?string $accent): self
    {
        return $this->rebuild(
            $this->name,
            $this->description,
            self::optionalText($icon, 32),
            self::optionalText($accent, 16),
            $this->type,
            $this->status,
        );
    }

    public function changeType(?string $type): self
    {
        return $this->rebuild($this->name, $this->description, $this->icon, $this->accent, WorkspaceType::from($type), $this->status);
    }

    /** Archive preserves all data; removes the workspace from active switching. */
    public function archive(): self
    {
        if ($this->isDefault) {
            throw new InvalidArgumentException('The default workspace cannot be archived.');
        }

        return $this->rebuild($this->name, $this->description, $this->icon, $this->accent, $this->type, WorkspaceStatus::archived());
    }

    public function restore(): self
    {
        return $this->rebuild($this->name, $this->description, $this->icon, $this->accent, $this->type, WorkspaceStatus::active());
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->userId,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'icon' => $this->icon,
            'accent' => $this->accent,
            'type' => $this->type->value,
            'is_default' => $this->isDefault,
            'status' => $this->status->value,
        ];
    }

    private function rebuild(
        string $name,
        ?string $description,
        ?string $icon,
        ?string $accent,
        WorkspaceType $type,
        WorkspaceStatus $status,
    ): self {
        $trimmed = trim($name);
        if ($trimmed === '') {
            throw new InvalidArgumentException('Workspace name is required.');
        }
        if (mb_strlen($trimmed) > 80) {
            throw new InvalidArgumentException('Workspace name must be at most 80 characters.');
        }

        return new self(
            id: $this->id,
            userId: $this->userId,
            name: $trimmed,
            slug: $trimmed === $this->name ? $this->slug : self::slugify($trimmed),
            description: $description,
            icon: $icon,
            accent: $accent,
            type: $type,
            isDefault: $this->isDefault,
            status: $status,
        );
    }

    private static function optionalText(?string $value, int $max): ?string
    {
        if ($value === null) {
            return null;
        }
        $trimmed = trim($value);

        return $trimmed === '' ? null : mb_substr($trimmed, 0, $max);
    }

    /** Deterministic URL-safe slug; uniqueness per user enforced by repository. */
    public static function slugify(string $name): string
    {
        $base = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $name) ?? '', '-'));
        if ($base === '') {
            $base = 'workspace';
        }

        return mb_substr($base, 0, 64);
    }
}
