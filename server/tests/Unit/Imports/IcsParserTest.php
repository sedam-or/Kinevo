<?php

namespace Tests\Unit\Imports;

use App\Application\Imports\IcsParser;
use PHPUnit\Framework\TestCase;

class IcsParserTest extends TestCase
{
    private IcsParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new IcsParser;
    }

    private function wrap(string $eventLines): string
    {
        return "BEGIN:VCALENDAR\nVERSION:2.0\nPRODID:-//Kinevo//Test//EN\n"
            ."BEGIN:VEVENT\n".$eventLines."\nEND:VEVENT\n"
            ."END:VCALENDAR\n";
    }

    public function test_parses_single_vevent_with_tzid(): void
    {
        $ics = $this->wrap("SUMMARY:Team Standup\nLOCATION:Room 1\nDTSTART;TZID=Asia/Jakarta:20260819T090000\nDTEND;TZID=Asia/Jakarta:20260819T093000\nUID:1");

        $result = $this->parser->parse($ics, 'Asia/Jakarta');

        $this->assertCount(1, $result['rows']);
        $this->assertSame([], $result['errors']);
        $this->assertSame([], $result['warnings']);
        $this->assertSame(1.0, $result['confidence']);

        $row = $result['rows'][0];
        $this->assertSame('Team Standup', $row['summary']);
        $this->assertSame('Room 1', $row['location']);
        $this->assertSame('one_time', $row['type']);
        $this->assertNull($row['recurrence']);
        $this->assertSame('2026-08-19T09:00:00+07:00', $row['start_at']);
        $this->assertSame('2026-08-19T09:30:00+07:00', $row['end_at']);
    }

    public function test_utc_event_is_converted_to_target_timezone(): void
    {
        $ics = $this->wrap("SUMMARY:Call\nDTSTART:20260819T090000Z\nDTEND:20260819T093000Z\nUID:2");

        $result = $this->parser->parse($ics, 'Asia/Jakarta');

        $this->assertCount(1, $result['rows']);
        // 09:00 UTC == 16:00 WIB.
        $this->assertSame('2026-08-19T16:00:00+07:00', $result['rows'][0]['start_at']);
    }

    public function test_floating_event_uses_target_timezone(): void
    {
        $ics = $this->wrap("SUMMARY:No TZ\nDTSTART:20260819T090000\nDTEND:20260819T100000\nUID:3");

        $result = $this->parser->parse($ics, 'Asia/Makassar');

        $this->assertCount(1, $result['rows']);
        $this->assertSame('2026-08-19T09:00:00+08:00', $result['rows'][0]['start_at']);
    }

    public function test_folded_lines_and_escaping_are_undone(): void
    {
        // "SUMMARY:Team \n  Standup" folds to "Team Standup"; escaped chars unescape.
        $ics = $this->wrap("SUMMARY:Team \n  Standup (\\, check \\\\ code)\nDTSTART;TZID=Asia/Jakarta:20260819T090000\nDTEND;TZID=Asia/Jakarta:20260819T091500\nUID:4");

        $result = $this->parser->parse($ics, 'Asia/Jakarta');

        $this->assertCount(1, $result['rows']);
        $this->assertSame('Team  Standup (, check \\ code)', $result['rows'][0]['summary']);
    }

    public function test_duration_is_used_when_dtend_missing(): void
    {
        $ics = $this->wrap("SUMMARY:Focus\nDTSTART;TZID=Asia/Jakarta:20260819T100000\nDURATION:PT1H30M\nUID:5");

        $result = $this->parser->parse($ics, 'Asia/Jakarta');

        $this->assertCount(1, $result['rows']);
        $this->assertSame('2026-08-19T10:00:00+07:00', $result['rows'][0]['start_at']);
        $this->assertSame('2026-08-19T11:30:00+07:00', $result['rows'][0]['end_at']);
    }

    public function test_weekly_rrule_becomes_recurring(): void
    {
        $ics = $this->wrap("SUMMARY:Weekly Class\nDTSTART;TZID=Asia/Jakarta:20260819T130000\nDTEND;TZID=Asia/Jakarta:20260819T153000\nRRULE:FREQ=WEEKLY;BYDAY=WE,FR;COUNT=12\nUID:6");

        $result = $this->parser->parse($ics, 'Asia/Jakarta');

        $this->assertCount(1, $result['rows']);
        $this->assertSame('recurring', $result['rows'][0]['type']);
        $this->assertSame('FREQ=WEEKLY;BYDAY=WE,FR;COUNT=12', $result['rows'][0]['recurrence']);
    }

    public function test_unsupported_frequency_degrades_to_one_time_with_warning(): void
    {
        $ics = $this->wrap("SUMMARY:Monthly\nDTSTART;TZID=Asia/Jakarta:20260819T130000\nDTEND;TZID=Asia/Jakarta:20260819T140000\nRRULE:FREQ=MONTHLY\nUID:7");

        $result = $this->parser->parse($ics, 'Asia/Jakarta');

        $this->assertCount(1, $result['rows']);
        $this->assertSame('one_time', $result['rows'][0]['type']);
        $this->assertNull($result['rows'][0]['recurrence']);
        $this->assertCount(1, $result['warnings']);
        $this->assertStringContainsString('Unsupported recurrence', $result['warnings'][0]['warning']);
    }

    public function test_all_day_event_is_skipped_with_warning(): void
    {
        $ics = $this->wrap("SUMMARY:Holiday\nDTSTART;VALUE=DATE:20260819\nDTEND;VALUE=DATE:20260820\nUID:8");

        $result = $this->parser->parse($ics, 'Asia/Jakarta');

        $this->assertCount(0, $result['rows']);
        $this->assertCount(0, $result['errors']);
        $this->assertCount(1, $result['warnings']);
        $this->assertSame('All-day events are not imported.', $result['warnings'][0]['warning']);
        $this->assertSame(0.0, $result['confidence']);
    }

    public function test_malformed_event_reports_per_event_error(): void
    {
        $ics = $this->wrap("SUMMARY:Broken\nDTSTART;TZID=Asia/Jakarta:notadate\nDTEND;TZID=Asia/Jakarta:20260819T100000\nUID:9");

        $result = $this->parser->parse($ics, 'Asia/Jakarta');

        $this->assertCount(0, $result['rows']);
        $this->assertCount(1, $result['errors']);
        $this->assertSame('Malformed date-time value.', $result['errors'][0]['error']);
    }

    public function test_unknown_timezone_is_a_per_event_error(): void
    {
        $ics = $this->wrap("SUMMARY:TZ?\nDTSTART;TZID=Mars/Olympus:20260819T090000\nDTEND;TZID=Mars/Olympus:20260819T100000\nUID:10");

        $result = $this->parser->parse($ics, 'Asia/Jakarta');

        $this->assertCount(0, $result['rows']);
        $this->assertCount(1, $result['errors']);
        $this->assertStringContainsString('Unknown timezone', $result['errors'][0]['error']);
    }

    public function test_end_before_start_is_an_error(): void
    {
        $ics = $this->wrap("SUMMARY:Reversed\nDTSTART;TZID=Asia/Jakarta:20260819T100000\nDTEND;TZID=Asia/Jakarta:20260819T090000\nUID:11");

        $result = $this->parser->parse($ics, 'Asia/Jakarta');

        $this->assertCount(0, $result['rows']);
        $this->assertCount(1, $result['errors']);
        $this->assertSame('Event end is not after its start.', $result['errors'][0]['error']);
    }

    public function test_missing_end_is_an_error(): void
    {
        $ics = $this->wrap("SUMMARY:No End\nDTSTART;TZID=Asia/Jakarta:20260819T100000\nUID:12");

        $result = $this->parser->parse($ics, 'Asia/Jakarta');

        $this->assertCount(0, $result['rows']);
        $this->assertCount(1, $result['errors']);
        $this->assertSame('Missing DTEND or DURATION.', $result['errors'][0]['error']);
    }

    public function test_recurrence_id_exception_is_skipped_with_warning(): void
    {
        $ics = $this->wrap("SUMMARY:Exception\nDTSTART;TZID=Asia/Jakarta:20260819T090000\nDTEND;TZID=Asia/Jakarta:20260819T100000\nRECURRENCE-ID;TZID=Asia/Jakarta:20260819T090000\nUID:13");

        $result = $this->parser->parse($ics, 'Asia/Jakarta');

        $this->assertCount(0, $result['rows']);
        $this->assertCount(1, $result['warnings']);
        $this->assertStringContainsString('RECURRENCE-ID', $result['warnings'][0]['warning']);
    }

    public function test_exdate_present_warns_and_mixed_results_are_reported(): void
    {
        $ics = $this->wrap(
            "SUMMARY:Recurring with EXDATE\nDTSTART;TZID=Asia/Jakarta:20260819T090000\nDTEND;TZID=Asia/Jakarta:20260819T100000\nRRULE:FREQ=WEEKLY\nEXDATE:20260826T090000\nUID:14"
        );

        $result = $this->parser->parse($ics, 'Asia/Jakarta');

        $this->assertCount(1, $result['rows']);
        $this->assertCount(1, $result['warnings']);
        $this->assertStringContainsString('EXDATE', $result['warnings'][0]['warning']);
    }

    public function test_valid_and_invalid_events_are_reported_separately(): void
    {
        $ics = $this->wrap(
            "SUMMARY:Good\nDTSTART;TZID=Asia/Jakarta:20260819T090000\nDTEND;TZID=Asia/Jakarta:20260819T100000\nUID:15"
        ).$this->wrap(
            "SUMMARY:Bad\nDTSTART;TZID=Asia/Jakarta:garbage\nDTEND;TZID=Asia/Jakarta:20260819T100000\nUID:16"
        );

        $result = $this->parser->parse($ics, 'Asia/Jakarta');

        $this->assertCount(1, $result['rows']);
        $this->assertSame('Good', $result['rows'][0]['summary']);
        $this->assertCount(1, $result['errors']);
        $this->assertSame('Bad', $result['errors'][0]['summary']);
        $this->assertSame(0.5, $result['confidence']);
    }

    public function test_empty_or_garbage_input_produces_empty_result(): void
    {
        $result = $this->parser->parse('not ical at all', 'UTC');

        $this->assertSame([], $result['rows']);
        $this->assertSame([], $result['errors']);
        $this->assertSame([], $result['warnings']);
        $this->assertSame(0.0, $result['confidence']);
    }

    public function test_crlf_line_endings_are_supported(): void
    {
        $ics = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nBEGIN:VEVENT\r\nSUMMARY:Windows\r\nDTSTART;TZID=UTC:20260819T090000\r\nDTEND;TZID=UTC:20260819T100000\r\nUID:17\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n";

        $result = $this->parser->parse($ics, 'UTC');

        $this->assertCount(1, $result['rows']);
        $this->assertSame('Windows', $result['rows'][0]['summary']);
    }
}
