<?php

namespace App\Domain\Identity\ValueObjects;

use InvalidArgumentException;

/**
 * Immutable owner settings (docs/environment.md, SRS FR-10/FR-13 timezone/locale rules).
 */
final class ProfileSettings
{
    private const LOCALES = ['en'];

    private const TIMEZONES = [
        'UTC',
        'Asia/Jakarta',
        'Asia/Makassar',
        'Asia/Jayapura',
        'Asia/Singapore',
        'America/New_York',
        'Europe/London',
    ];

    private const WEEK_START_DAYS = ['monday', 'sunday', 'saturday'];

    public function __construct(
        public readonly ?string $displayName,
        public readonly string $locale,
        public readonly string $timezone,
        public readonly string $weekStartDay,
    ) {
        if (! in_array($locale, self::LOCALES, true)) {
            throw new InvalidArgumentException("Unsupported locale: {$locale}");
        }
        if (! in_array($timezone, self::TIMEZONES, true)) {
            throw new InvalidArgumentException("Unsupported timezone: {$timezone}");
        }
        if (! in_array($weekStartDay, self::WEEK_START_DAYS, true)) {
            throw new InvalidArgumentException("Unsupported week start day: {$weekStartDay}");
        }
    }

    public static function defaults(): self
    {
        return new self(null, 'en', 'UTC', 'monday');
    }

    public function toArray(): array
    {
        return [
            'display_name' => $this->displayName,
            'locale' => $this->locale,
            'timezone' => $this->timezone,
            'week_start_day' => $this->weekStartDay,
        ];
    }
}
