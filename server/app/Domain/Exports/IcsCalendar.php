<?php

namespace App\Domain\Exports;

use Carbon\CarbonImmutable;

/**
 * Deterministic RFC-5545 iCalendar serializer (FR-30 / TASK-143). Produces a
 * standards-compatible VCALENDAR with UTC datetime values, escaped text, and
 * folded content lines. Domain-owned so the export format is a stable contract
 * independent of any third-party calendar library.
 */
final class IcsCalendar
{
    private const PRODID = '-//Kinevo//Schedule//EN';

    /**
     * @var array<int, array{
     *   uid: string,
     *   summary: string,
     *   start: CarbonImmutable,
     *   end: CarbonImmutable,
     *   description: string|null,
     *   location: string|null,
     *   rrule: string|null,
     *   dtstamp: CarbonImmutable,
     * }>
     */
    private array $events = [];

    public function __construct(
        private readonly string $prodid = self::PRODID,
    ) {}

    public function addEvent(
        string $uid,
        string $summary,
        CarbonImmutable $start,
        CarbonImmutable $end,
        ?string $description = null,
        ?string $location = null,
        ?string $rrule = null,
        ?CarbonImmutable $dtstamp = null,
    ): self {
        if (! $end->greaterThan($start)) {
            throw new \InvalidArgumentException('Calendar event end must be after its start.');
        }

        $this->events[] = [
            'uid' => $uid,
            'summary' => $summary,
            'start' => $start,
            'end' => $end,
            'description' => $description,
            'location' => $location,
            'rrule' => $rrule,
            'dtstamp' => $dtstamp ?? CarbonImmutable::now('UTC'),
        ];

        return $this;
    }

    public function render(): string
    {
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            "PRODID:{$this->prodid}",
            'CALSCALE:GREGORIAN',
        ];

        foreach ($this->events as $event) {
            $lines[] = 'BEGIN:VEVENT';
            $lines[] = "UID:{$event['uid']}";
            $lines[] = 'DTSTAMP:'.self::formatDateTime($event['dtstamp']);
            $lines[] = 'DTSTART:'.self::formatDateTime($event['start']);
            $lines[] = 'DTEND:'.self::formatDateTime($event['end']);
            $lines[] = 'SUMMARY:'.self::escapeText($event['summary']);

            if ($event['description'] !== null) {
                $lines[] = 'DESCRIPTION:'.self::escapeText($event['description']);
            }

            if ($event['location'] !== null) {
                $lines[] = 'LOCATION:'.self::escapeText($event['location']);
            }

            if ($event['rrule'] !== null && $event['rrule'] !== '') {
                $lines[] = 'RRULE:'.$event['rrule'];
            }

            $lines[] = 'END:VEVENT';
        }

        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", array_map(self::fold(...), $lines))."\r\n";
    }

    /**
     * Format a datetime as UTC basic format `YYYYMMDDTHHMMSSZ` (RFC-5545
     * §3.3.5) so any standards-compatible client can parse it.
     */
    private static function formatDateTime(CarbonImmutable $dateTime): string
    {
        return $dateTime->utc()->format('Ymd\THis\Z');
    }

    /**
     * Escape a text value per RFC-5545 §3.3.11 (backslash, semicolon, comma,
     * and newlines are backslash-escaped).
     */
    public static function escapeText(string $value): string
    {
        $value = str_replace('\\', '\\\\', $value);
        $value = str_replace(';', '\;', $value);
        $value = str_replace(',', '\,', $value);
        $value = str_replace(["\r\n", "\r", "\n"], '\n', $value);

        return $value;
    }

    /**
     * Fold a content line at 75 octets (RFC-5545 §3.1). UTF-8 multibyte
     * sequences are never split; continuation lines begin with a single space.
     */
    public static function fold(string $line): string
    {
        if (strlen($line) <= 75) {
            return $line;
        }

        $parts = [];
        $current = '';
        $octets = 0;

        $length = mb_strlen($line);
        for ($i = 0; $i < $length; $i++) {
            $char = mb_substr($line, $i, 1);
            $charOctets = strlen($char);

            if ($octets + $charOctets > 75) {
                $parts[] = $current;
                $current = $char;
                $octets = $charOctets;
            } else {
                $current .= $char;
                $octets += $charOctets;
            }
        }

        if ($current !== '') {
            $parts[] = $current;
        }

        return implode("\r\n ", $parts);
    }
}
