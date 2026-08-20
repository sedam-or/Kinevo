<?php

namespace App\Domain\Analytics;

use InvalidArgumentException;

/**
 * The four life pillars (FR-12): Karier, Kesehatan, Bahasa, Branding, plus
 * Uncategorized for tasks without a program mapping. A program's category is
 * matched against the canonical pillar names; unknown markers fall back to
 * Uncategorized (SRS FR-12 Business Rules / Exception Flows).
 */
final readonly class Pillar
{
    public const KARIER = 'karier';

    public const KESEHATAN = 'kesehatan';

    public const BAHASA = 'bahasa';

    public const BRANDING = 'branding';

    public const UNCATEGORIZED = 'uncategorized';

    private const PILLARS = [
        self::KARIER,
        self::KESEHATAN,
        self::BAHASA,
        self::BRANDING,
    ];

    private const LABELS = [
        self::KARIER => 'Karier',
        self::KESEHATAN => 'Kesehatan',
        self::BAHASA => 'Bahasa',
        self::BRANDING => 'Branding',
        self::UNCATEGORIZED => 'Uncategorized',
    ];

    public function __construct(public readonly string $value)
    {
        if (! isset(self::LABELS[$value])) {
            throw new InvalidArgumentException("Unsupported pillar: {$value}");
        }
    }

    /**
     * Map a program category to a pillar. Case-insensitive match against the
     * four canonical pillars; anything else falls back to Uncategorized.
     */
    public static function fromCategory(?string $category): self
    {
        if ($category === null || trim($category) === '') {
            return new self(self::UNCATEGORIZED);
        }

        $normalized = mb_strtolower(trim($category));
        if (in_array($normalized, self::PILLARS, true)) {
            return new self($normalized);
        }

        return new self(self::UNCATEGORIZED);
    }

    public function label(): string
    {
        return self::LABELS[$this->value];
    }

    public function isUncategorized(): bool
    {
        return $this->value === self::UNCATEGORIZED;
    }

    /**
     * @return array<int, string> the four canonical pillars (excluding Uncategorized)
     */
    public static function canonical(): array
    {
        return self::PILLARS;
    }
}
