<?php

namespace Tests\Unit\Exports;

use App\Domain\Exports\IcsCalendar;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

final class IcsCalendarTest extends TestCase
{
    public function test_render_produces_valid_vcalendar_header(): void
    {
        $calendar = new IcsCalendar;

        $rendered = $calendar->render();

        $this->assertStringContainsString('BEGIN:VCALENDAR', $rendered);
        $this->assertStringContainsString('VERSION:2.0', $rendered);
        $this->assertStringContainsString('PRODID:-//Kinevo//Schedule//EN', $rendered);
        $this->assertStringContainsString('CALSCALE:GREGORIAN', $rendered);
        $this->assertStringEndsWith("END:VCALENDAR\r\n", $rendered);
    }

    public function test_render_writes_a_vevent_in_utc_basic_format(): void
    {
        $calendar = new IcsCalendar;
        $calendar->addEvent(
            uid: 'kinevo-test@kinevo',
            summary: 'Team Standup',
            start: CarbonImmutable::parse('2026-08-19 09:00:00', 'Asia/Jakarta'),
            end: CarbonImmutable::parse('2026-08-19 09:30:00', 'Asia/Jakarta'),
            dtstamp: CarbonImmutable::parse('2026-08-20T00:00:00Z', 'UTC'),
        );

        $rendered = $calendar->render();

        $this->assertStringContainsString('BEGIN:VEVENT', $rendered);
        $this->assertStringContainsString("UID:kinevo-test@kinevo\r\n", $rendered);
        $this->assertStringContainsString("DTSTAMP:20260820T000000Z\r\n", $rendered);
        $this->assertStringContainsString("DTSTART:20260819T020000Z\r\n", $rendered);
        $this->assertStringContainsString("DTEND:20260819T023000Z\r\n", $rendered);
        $this->assertStringContainsString("SUMMARY:Team Standup\r\n", $rendered);
        $this->assertStringContainsString('END:VEVENT', $rendered);
    }

    public function test_render_includes_rrule_description_and_location(): void
    {
        $calendar = new IcsCalendar;
        $calendar->addEvent(
            uid: 'kinevo-r@kinevo',
            summary: 'Weekly Class',
            start: CarbonImmutable::parse('2026-08-19 13:00:00', 'UTC'),
            end: CarbonImmutable::parse('2026-08-19 15:30:00', 'UTC'),
            description: "Math\nAdvanced",
            location: 'Room 1',
            rrule: 'FREQ=WEEKLY;BYDAY=WE;COUNT=12',
        );

        $rendered = $calendar->render();

        $this->assertStringContainsString("RRULE:FREQ=WEEKLY;BYDAY=WE;COUNT=12\r\n", $rendered);
        $this->assertStringContainsString("LOCATION:Room 1\r\n", $rendered);
        $this->assertStringContainsString("DESCRIPTION:Math\\nAdvanced\r\n", $rendered);
    }

    public function test_escape_text_escapes_ical_reserved_characters(): void
    {
        $this->assertSame('A\\\\B', IcsCalendar::escapeText('A\\B'));
        $this->assertSame('Semi\\;colon', IcsCalendar::escapeText('Semi;colon'));
        $this->assertSame('Comma\\,list', IcsCalendar::escapeText('Comma,list'));
        $this->assertSame('Line\\nBreak', IcsCalendar::escapeText("Line\nBreak"));
    }

    public function test_fold_splits_long_lines_at_75_octets(): void
    {
        $line = 'SUMMARY:'.str_repeat('a', 100);

        $folded = IcsCalendar::fold($line);

        $this->assertStringContainsString("\r\n ", $folded);
        foreach (explode("\r\n ", $folded) as $segment) {
            $this->assertLessThanOrEqual(75, strlen($segment));
        }
        $this->assertSame($line, str_replace("\r\n ", '', $folded));
    }

    public function test_fold_does_not_split_multibyte_sequences(): void
    {
        $line = 'SUMMARY:'.str_repeat('é', 60);

        $folded = IcsCalendar::fold($line);

        $this->assertSame($line, str_replace("\r\n ", '', $folded));
        $this->assertStringContainsString("\r\n ", $folded);
    }

    public function test_add_event_rejects_end_before_start(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $calendar = new IcsCalendar;
        $calendar->addEvent(
            uid: 'kinevo-bad@kinevo',
            summary: 'Bad',
            start: CarbonImmutable::parse('2026-08-19 10:00:00', 'UTC'),
            end: CarbonImmutable::parse('2026-08-19 09:00:00', 'UTC'),
        );
    }
}
