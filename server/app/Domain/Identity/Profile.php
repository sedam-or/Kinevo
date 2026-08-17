<?php

namespace App\Domain\Identity;

use App\Domain\Identity\ValueObjects\ProfileSettings;

/**
 * Profile entity — owner profile/settings (SRS §7.1).
 */
final class Profile
{
    public function __construct(
        public readonly int $id,
        public readonly int $userId,
        public readonly ProfileSettings $settings,
    ) {}

    public static function create(int $userId, ProfileSettings $settings): self
    {
        return new self(0, $userId, $settings);
    }

    public function withSettings(ProfileSettings $settings): self
    {
        return new self($this->id, $this->userId, $settings);
    }
}
