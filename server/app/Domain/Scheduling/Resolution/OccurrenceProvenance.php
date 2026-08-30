<?php

namespace App\Domain\Scheduling\Resolution;

use InvalidArgumentException;

/**
 * Closed value object for Effective Landscape occurrence provenance
 * (ADR-015). `base` is produced by the canonical resolver today;
 * `shifted:<overrideId>` / `excepted:<overrideId>` / `cancelled:<overrideId>`
 * are ADR-015 reserved values that later slices (ES-IMPL-04/05) produce when
 * override resolution lands. The string contract is stable for API exposure.
 */
final class OccurrenceProvenance
{
    public const BASE = 'base';

    public const SHIFTED = 'shifted';

    public const EXCEPTED = 'excepted';

    public const CANCELLED = 'cancelled';

    public function __construct(
        public readonly string $value,
    ) {
        [$kind] = array_pad(explode(':', $value, 2), 2, '');

        if (! in_array($kind, [self::BASE, self::SHIFTED, self::EXCEPTED, self::CANCELLED], true)) {
            throw new InvalidArgumentException("Invalid occurrence provenance: {$value}");
        }

        if ($kind !== self::BASE && $value === $kind) {
            throw new InvalidArgumentException("Occurrence provenance {$kind} requires an override id suffix.");
        }
    }

    public static function base(): self
    {
        return new self(self::BASE);
    }

    public static function shifted(int $overrideId): self
    {
        return new self(self::SHIFTED.':'.$overrideId);
    }

    public static function excepted(int $overrideId): self
    {
        return new self(self::EXCEPTED.':'.$overrideId);
    }

    public static function cancelled(int $overrideId): self
    {
        return new self(self::CANCELLED.':'.$overrideId);
    }

    public function isBase(): bool
    {
        return $this->value === self::BASE;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
