<?php

namespace App\Application\Scheduling;

use App\Domain\Scheduling\ScheduleDraftRecord;

/**
 * ADR-016 §2.1 — outcome of one weekly preparation pass for a user.
 */
final class PrepareWeeklyDraftResult
{
    public const CREATED = 'created';

    public const REFRESHED = 'refreshed';

    public const SKIPPED = 'skipped';

    public function __construct(
        public readonly string $action,
        public readonly ?ScheduleDraftRecord $draft,
    ) {}

    public static function created(ScheduleDraftRecord $draft): self
    {
        return new self(self::CREATED, $draft);
    }

    public static function refreshed(ScheduleDraftRecord $draft): self
    {
        return new self(self::REFRESHED, $draft);
    }

    public static function skipped(?ScheduleDraftRecord $draft): self
    {
        return new self(self::SKIPPED, $draft);
    }
}
