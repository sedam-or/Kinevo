<?php

namespace App\Application\Boosts;

use App\Domain\Boosts\BoostTarget;

/**
 * Result of saving a Boost target (FR-37). The proposed percent is capped at
 * the 70% safety limit with an explicit warning; the saved target carries the
 * capped value.
 */
final readonly class SetBoostTargetResult
{
    public function __construct(
        public BoostTarget $target,
        public bool $capped,
        public ?string $warning,
        public string $explanation,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'target' => $this->target->toArray(),
            'capped' => $this->capped,
            'warning' => $this->warning,
            'explanation' => $this->explanation,
        ];
    }
}
