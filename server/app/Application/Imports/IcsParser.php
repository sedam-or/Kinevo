<?php

namespace App\Application\Imports;

use App\Domain\Scheduling\Recurrence\RecurrenceRule;
use Carbon\CarbonImmutable;
use DateTimeZone;
use InvalidArgumentException;

/**
 * Parses a bounded RFC-5545 subset of iCalendar (.ics) content into candidate
 * Hard Landscape rows (FR-30). Tolerant, deterministic and timezone-aware:
 *
 * - line folding (continuation lines) and value escaping are undone;
 * - each VEVENT is parsed independently — a malformed event produces a
 *   per-event error (FR-30 Exception Flow) without failing the whole file;
 * - DTSTART/DTEND are resolved to the target timezone: explicit TZID, UTC (Z),
 *   or floating local time (assumed to be the target timezone);
 * - supported RRULEs (FREQ=DAILY|WEEKLY; INTERVAL; BYDAY; COUNT/UNTIL) become
 *   recurring Hard Landscape; unsupported frequencies degrade to a one-time
 *   event with an explicit warning (TASK-144 — never silently import invalid
 *   data); EXDATE/RECURRENCE-ID exceptions are reported and not applied;
 * - all-day (date-only) events are skipped with a warning.
 *
 * No external parsing dependency is introduced; the subset is small, fully
 * unit-tested, and bounded by the Hard Landscape model (SRS §5.3.4 lists
 * iCal parsing as an infrastructure concern; the KRS parser precedent lives
 * here in Application).
 */
final readonly class IcsParser
{
    private const MAX_RECURRENCE_LENGTH = 500;

    /**
     * @return array{rows: array<int, array<string, mixed>>, errors: array<int, array<string, mixed>>, warnings: array<int, array<string, mixed>>, confidence: float}
     */
    public function parse(string $icsContents, string $targetTimezone): array
    {
        $targetTimezone = $this->validTimezone($targetTimezone) ? $targetTimezone : 'UTC';

        $events = $this->extractEvents($icsContents);
        $rows = [];
        $errors = [];
        $warnings = [];

        foreach ($events as $index => $properties) {
            $summary = trim($this->unescape(($properties['SUMMARY'][0]['value'] ?? '') ?: ''));

            $result = $this->parseEvent($properties, $targetTimezone);

            if ($result['ok'] === true) {
                foreach ($result['warnings'] as $warning) {
                    $warnings[] = ['index' => $index, 'summary' => $summary ?: null, 'warning' => $warning];
                }
                $rows[] = $result['row'];
            } elseif (isset($result['error'])) {
                $errors[] = ['index' => $index, 'summary' => $summary ?: null, 'error' => $result['error']];
            } else {
                $warnings[] = ['index' => $index, 'summary' => $summary ?: null, 'warning' => $result['skip']];
            }
        }

        $total = count($events);
        $confidence = $total > 0 ? round(count($rows) / $total, 4) : 0.0;

        return ['rows' => $rows, 'errors' => $errors, 'warnings' => $warnings, 'confidence' => $confidence];
    }

    /**
     * Splits the raw content into unfolded lines, then groups them into
     * VEVENT blocks (VTIMEZONE and other components are ignored).
     *
     * @return array<int, array<string, list<array{params: array<string, string>, value: string}>>>
     */
    private function extractEvents(string $ics): array
    {
        $normalized = preg_replace('/\r\n?/', "\n", $ics) ?? $ics;
        $lines = preg_split('/\n/', $normalized) ?: [];

        $unfolded = [];
        $current = '';
        foreach ($lines as $line) {
            $first = $line[0] ?? '';
            if ($first === ' ' || $first === "\t") {
                $current .= substr($line, 1);
            } else {
                if ($current !== '') {
                    $unfolded[] = $current;
                }
                $current = $line;
            }
        }
        if ($current !== '') {
            $unfolded[] = $current;
        }

        $events = [];
        $currentEvent = null;
        foreach ($unfolded as $line) {
            $upper = strtoupper($line);
            if (str_starts_with($upper, 'BEGIN:VEVENT')) {
                $currentEvent = [];
            } elseif ($currentEvent !== null && str_starts_with($upper, 'END:VEVENT')) {
                $events[] = $currentEvent;
                $currentEvent = null;
            } elseif ($currentEvent !== null) {
                $property = $this->parseProperty($line);
                if ($property !== null) {
                    $currentEvent[$property['name']][] = $property;
                }
            }
        }

        return $events;
    }

    /**
     * @return array{name: string, params: array<string, string>, value: string}|null
     */
    private function parseProperty(string $line): ?array
    {
        if (! preg_match('/^([^:;]+)((?:;[^:]*)?):(.*)$/s', $line, $m)) {
            return null;
        }

        $params = [];
        $paramsRaw = $m[2];
        if ($paramsRaw !== '') {
            foreach (explode(';', $paramsRaw) as $segment) {
                $segment = trim($segment);
                if ($segment === '') {
                    continue;
                }
                if (str_contains($segment, '=')) {
                    [$key, $value] = explode('=', $segment, 2);
                    $params[strtoupper(trim($key))] = trim($value, '"');
                } else {
                    $params[strtoupper($segment)] = '';
                }
            }
        }

        return [
            'name' => strtoupper(trim($m[1])),
            'params' => $params,
            'value' => $m[3],
        ];
    }

    /**
     * @param  array<string, list<array{params: array<string, string>, value: string}>>  $properties
     * @return array{ok: true, row: array<string, mixed>, warnings: array<int, string>}|array{ok: false, error: string}|array{ok: false, skip: string}
     */
    private function parseEvent(array $properties, string $targetTimezone): array
    {
        $warnings = [];

        $summary = trim($this->unescape(($properties['SUMMARY'][0]['value'] ?? '') ?: '')) ?: 'Imported event';
        $summary = mb_substr($summary, 0, 200);

        if (isset($properties['RECURRENCE-ID'])) {
            return ['ok' => false, 'skip' => 'Recurrence exceptions (RECURRENCE-ID) are not imported.'];
        }

        if (! isset($properties['DTSTART'])) {
            return ['ok' => false, 'error' => 'Missing DTSTART.'];
        }

        $startResult = $this->resolveDateTime($properties['DTSTART'][0], $targetTimezone);
        if ($startResult['ok'] !== true) {
            if (isset($startResult['all_day'])) {
                return ['ok' => false, 'skip' => 'All-day events are not imported.'];
            }

            return ['ok' => false, 'error' => $startResult['error']];
        }

        $start = $startResult['dt'];

        if (isset($properties['DTEND'])) {
            $endResult = $this->resolveDateTime($properties['DTEND'][0], $targetTimezone);
            if ($endResult['ok'] !== true) {
                if (isset($endResult['all_day'])) {
                    return ['ok' => false, 'skip' => 'All-day events are not imported.'];
                }

                return ['ok' => false, 'error' => $endResult['error']];
            }
            $end = $endResult['dt'];
        } elseif (isset($properties['DURATION'])) {
            $durationMinutes = $this->parseDuration($properties['DURATION'][0]['value']);
            if ($durationMinutes === null) {
                return ['ok' => false, 'error' => 'Malformed DURATION.'];
            }
            $end = $start->addMinutes($durationMinutes);
        } else {
            return ['ok' => false, 'error' => 'Missing DTEND or DURATION.'];
        }

        if (! $end->greaterThan($start)) {
            return ['ok' => false, 'error' => 'Event end is not after its start.'];
        }

        $type = 'one_time';
        $recurrence = null;

        if (isset($properties['RRULE'])) {
            $rrule = trim($properties['RRULE'][0]['value']);

            try {
                RecurrenceRule::parse($rrule, $start);

                if (strlen($rrule) > self::MAX_RECURRENCE_LENGTH) {
                    $warnings[] = 'Recurrence rule is too long; imported as a one-time event.';
                } else {
                    $type = 'recurring';
                    $recurrence = $rrule;
                }
            } catch (InvalidArgumentException) {
                $warnings[] = 'Unsupported recurrence rule; imported as a one-time event.';
            }

            if (isset($properties['EXDATE'])) {
                $warnings[] = 'Recurrence exceptions (EXDATE) are not applied.';
            }
        }

        $row = [
            'uid' => isset($properties['UID']) ? $this->unescape($properties['UID'][0]['value']) : null,
            'summary' => $summary,
            'location' => trim($this->unescape(($properties['LOCATION'][0]['value'] ?? '') ?: '')) ?: null,
            'start_at' => $start->timezone($targetTimezone)->toIso8601String(),
            'end_at' => $end->timezone($targetTimezone)->toIso8601String(),
            'type' => $type,
            'recurrence' => $recurrence,
            'tzid' => $targetTimezone,
            'conflict' => false,
            'conflict_with' => null,
        ];

        return ['ok' => true, 'row' => $row, 'warnings' => $warnings];
    }

    /**
     * Resolves a DTSTART/DTEND property to a concrete instant in the target
     * timezone. `all_day` signals a date-only value (skipped by the caller).
     *
     * @param  array{params: array<string, string>, value: string}  $property
     * @return array{ok: true, dt: CarbonImmutable}|array{ok: false, all_day: true}|array{ok: false, error: string}
     */
    private function resolveDateTime(array $property, string $targetTimezone): array
    {
        $value = $property['value'];
        $params = $property['params'];

        if (isset($params['VALUE']) && strtoupper($params['VALUE']) === 'DATE') {
            return ['ok' => false, 'all_day' => true];
        }

        $isUtc = str_ends_with($value, 'Z');
        $raw = rtrim($value, 'Z');

        if (isset($params['TZID']) && $params['TZID'] !== '') {
            $timezoneName = $params['TZID'];
            if (! $this->validTimezone($timezoneName)) {
                return ['ok' => false, 'error' => "Unknown timezone: {$timezoneName}"];
            }
        } elseif ($isUtc) {
            $timezoneName = 'UTC';
        } else {
            $timezoneName = $targetTimezone;
        }

        if (preg_match('/^\d{8}$/', $raw)) {
            return ['ok' => false, 'all_day' => true];
        }

        if (! preg_match('/^(\d{4})(\d{2})(\d{2})T(\d{2})(\d{2})(\d{2})?$/', $raw, $m)) {
            return ['ok' => false, 'error' => 'Malformed date-time value.'];
        }

        $dt = CarbonImmutable::create((int) $m[1], (int) $m[2], (int) $m[3], (int) $m[4], (int) $m[5], (int) ($m[6] ?? 0), $timezoneName);

        return ['ok' => true, 'dt' => $dt];
    }

    private function parseDuration(string $value): ?int
    {
        if (! preg_match('/^P(?:(\d+)W)?(?:(\d+)D)?(?:T(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?)?$/i', $value, $m)) {
            return null;
        }

        $weeks = (int) ($m[1] ?? 0);
        $days = (int) ($m[2] ?? 0);
        $hours = (int) ($m[3] ?? 0);
        $minutes = (int) ($m[4] ?? 0);
        $seconds = (int) ($m[5] ?? 0);

        $total = ($weeks * 7 * 1440) + ($days * 1440) + ($hours * 60) + $minutes + (int) round($seconds / 60);

        return $total >= 1 ? $total : null;
    }

    private function unescape(string $value): string
    {
        $value = preg_replace('/\\\\([nN])/', ' ', $value) ?? $value;
        $value = preg_replace('/\\\\([\\\\;,])/', '$1', $value) ?? $value;

        return $value;
    }

    private function validTimezone(string $timezone): bool
    {
        return in_array($timezone, DateTimeZone::listIdentifiers(), true);
    }
}
