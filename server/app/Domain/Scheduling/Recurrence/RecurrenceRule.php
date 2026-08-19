<?php

namespace App\Domain\Scheduling\Recurrence;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Value object for a recurrence definition, supporting a bounded RFC-5545
 * subset used by recurring tasks and recurring Hard Landscape (FR-46/FR-25;
 * SRS §7.1 `task_templates`/`hard_landscape_events`).
 *
 * Supported:
 * - FREQ=DAILY | FREQ=WEEKLY
 * - INTERVAL=n (every n days/weeks)
 * - BYDAY=MO,TU,WE,TH,FR,SA,SU (weekday set for WEEKLY)
 * - COUNT=n | UNTIL=YYYYMMDD (bounding; generation is always additionally
 *   bounded by an explicit window and a max-occurrences guard)
 *
 * Generation is deterministic and timezone-aware (see
 * RecurrenceOccurrenceGenerator).
 */
final class RecurrenceRule
{
    public const FREQ_DAILY = 'DAILY';

    public const FREQ_WEEKLY = 'WEEKLY';

    public const WEEKDAYS = ['MO', 'TU', 'WE', 'TH', 'FR', 'SA', 'SU'];

    private const WEEKDAY_MAP = [
        'MO' => 1, 'TU' => 2, 'WE' => 3, 'TH' => 4, 'FR' => 5, 'SA' => 6, 'SU' => 7,
    ];

    /**
     * @param  array<int, string>  $byDay  uppercase weekday codes (MO..SU)
     */
    private function __construct(
        public readonly string $freq,
        public readonly int $interval,
        public readonly array $byDay,
        public readonly ?int $count,
        public readonly ?CarbonImmutable $until,
        public readonly CarbonImmutable $start,
    ) {}

    /**
     * @param  array<int, string>  $byDay
     */
    public static function create(
        string $freq,
        CarbonImmutable $start,
        int $interval = 1,
        array $byDay = [],
        ?int $count = null,
        ?CarbonImmutable $until = null,
    ): self {
        $freq = strtoupper($freq);

        if (! in_array($freq, [self::FREQ_DAILY, self::FREQ_WEEKLY], true)) {
            throw new InvalidArgumentException("Unsupported recurrence frequency: {$freq}");
        }

        if ($interval < 1) {
            throw new InvalidArgumentException('Recurrence interval must be >= 1.');
        }

        foreach ($byDay as $day) {
            if (! in_array(strtoupper($day), self::WEEKDAYS, true)) {
                throw new InvalidArgumentException("Invalid BYDAY value: {$day}");
            }
        }

        if ($count !== null && $count < 1) {
            throw new InvalidArgumentException('Recurrence COUNT must be >= 1.');
        }

        if ($until !== null && $until->lt($start)) {
            throw new InvalidArgumentException('Recurrence UNTIL cannot precede DTSTART.');
        }

        return new self(
            $freq,
            $interval,
            array_map('strtoupper', array_values(array_unique($byDay))),
            $count,
            $until,
            $start,
        );
    }

    /**
     * Parse an RFC-5545-style rule string (e.g. "FREQ=WEEKLY;BYDAY=MO,WE,FR").
     * Unknown properties are ignored defensively.
     *
     * @throws InvalidArgumentException when FREQ is missing or malformed
     */
    public static function parse(string $rule, CarbonImmutable $start): self
    {
        $parts = [];
        foreach (explode(';', $rule) as $segment) {
            $segment = trim($segment);
            if ($segment === '') {
                continue;
            }
            [$key, $value] = array_pad(explode('=', $segment, 2), 2, '');
            $parts[strtoupper($key)] = $value;
        }

        $freq = $parts['FREQ'] ?? throw new InvalidArgumentException('Recurrence rule is missing FREQ.');

        $interval = isset($parts['INTERVAL']) ? (int) $parts['INTERVAL'] : 1;
        $byDay = isset($parts['BYDAY'])
            ? array_values(array_filter(array_map('trim', explode(',', $parts['BYDAY']))))
            : [];

        $count = isset($parts['COUNT']) ? (int) $parts['COUNT'] : null;
        $until = null;
        if (isset($parts['UNTIL']) && $parts['UNTIL'] !== '') {
            $until = self::parseUntil($parts['UNTIL'], $start);
        }

        return self::create($freq, $start, $interval, $byDay, $count, $until);
    }

    /**
     * The start-of-day anchor this rule repeats from.
     */
    public function start(): CarbonImmutable
    {
        return $this->start;
    }

    /**
     * Whether a given calendar day is a recurrence day under this rule,
     * evaluated in the start's timezone. `daily` repeats every day.
     */
    public function matches(CarbonImmutable $day): bool
    {
        $day = $day->timezone($this->start->timezone);

        if ($this->freq === self::FREQ_DAILY) {
            return $this->intervalDayMatches($day);
        }

        // WEEKLY: default BYDAY is the DTSTART weekday when none is given
        // (RFC-5545), otherwise the configured weekday set.
        $targetDays = $this->byDay !== [] ? $this->byDay : [self::weekdayCode($this->start)];

        if (! in_array(self::weekdayCode($day), $targetDays, true)) {
            return false;
        }

        return $this->intervalWeekMatches($day);
    }

    private function intervalDayMatches(CarbonImmutable $day): bool
    {
        $diffDays = (int) $this->start->startOfDay()->diffInDays($day->startOfDay());

        return $diffDays >= 0 && $diffDays % $this->interval === 0;
    }

    private function intervalWeekMatches(CarbonImmutable $day): bool
    {
        $weeks = (int) $this->start->startOfWeek()->diffInWeeks($day->startOfWeek());

        return $weeks >= 0 && $weeks % $this->interval === 0;
    }

    private static function weekdayCode(CarbonImmutable $day): string
    {
        $iso = $day->dayOfWeekIso;

        return array_search($iso, self::WEEKDAY_MAP, true) ?: 'MO';
    }

    private static function parseUntil(string $value, CarbonImmutable $start): CarbonImmutable
    {
        // Accept YYYYMMDD or YYYYMMDDTHHMMSS(Z).
        $normalized = preg_replace('/\D/', '', $value);
        $len = strlen($normalized);

        if ($len >= 8) {
            $date = substr($normalized, 0, 8);
            $time = $len >= 14 ? substr($normalized, 8, 6) : '000000';
            $parsed = CarbonImmutable::createFromFormat(
                '!YmdHis',
                $date.$time,
                $start->timezone,
            );
            if ($parsed instanceof CarbonImmutable) {
                return $parsed;
            }
        }

        throw new InvalidArgumentException("Invalid recurrence UNTIL value: {$value}");
    }
}
