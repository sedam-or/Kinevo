<?php

namespace App\Application\Imports;

use Smalot\PdfParser\Parser as PdfParser;
use Throwable;

/**
 * Parses a KRS (study plan) PDF into candidate weekly schedule rows (FR-24).
 * KRS layouts vary by institution, so the parser is tolerant: it scans the
 * extracted text for lines that carry a day keyword and a HH:MM–HH:MM time
 * range, then maps them to day/time/course/location. Confidence reflects how
 * much of the extracted text was understood; a low-confidence result is treated
 * as a parse failure and the user falls back to manual entry.
 *
 * TASK-144: nothing is silently dropped. Lines that look like schedule entries
 * (day keyword) but cannot be fully parsed are reported as per-line errors, and
 * exact duplicate rows are reported as warnings — the preview always shows the
 * full picture before anything is persisted.
 */
final readonly class KrsPdfParser
{
    private const DAYS = [
        'senin' => 1,
        'selasa' => 2,
        'rabu' => 3,
        'kamis' => 4,
        'jumat' => 5,
        'sabtu' => 6,
        'minggu' => 0,
    ];

    public function __construct(
        private PdfParser $parser = new PdfParser,
    ) {}

    /**
     * @return array{rows: array<int, array<string, mixed>>, errors: array<int, array<string, mixed>>, warnings: array<int, array<string, mixed>>, confidence: float}
     */
    public function parse(string $pdfContents): array
    {
        $rows = [];
        $errors = [];
        $warnings = [];
        $seen = [];
        $matchedLines = 0;
        $totalLines = 0;

        try {
            $pdf = $this->parser->parseContent($pdfContents);
            $text = $pdf->getText();
        } catch (Throwable) {
            return ['rows' => [], 'errors' => [], 'warnings' => [], 'confidence' => 0.0];
        }

        $lines = preg_split('/\R+/u', trim($text)) ?: [];

        foreach ($lines as $raw) {
            $line = trim($raw);
            if ($line === '') {
                continue;
            }
            $totalLines++;

            $row = $this->extractRow($line);
            if ($row === null) {
                if ($this->looksLikeScheduleLine($line)) {
                    $errors[] = [
                        'line' => mb_substr($line, 0, 120),
                        'error' => 'Could not be read as a schedule row (expected a day, HH:MM–HH:MM time range, and course name).',
                    ];
                }

                continue;
            }

            if ($row['end_time'] <= $row['start_time']) {
                $errors[] = [
                    'line' => mb_substr($line, 0, 120),
                    'error' => "Invalid time range ({$row['start_time']}–{$row['end_time']}) — end must be after start.",
                ];

                continue;
            }

            $key = mb_strtolower($row['day'].'|'.$row['start_time'].'|'.$row['end_time'].'|'.$row['course']);
            if (isset($seen[$key])) {
                $warnings[] = [
                    'course' => $row['course'],
                    'warning' => 'Duplicate entry skipped — an identical row was already staged.',
                ];

                continue;
            }
            $seen[$key] = true;

            $rows[] = $row;
            $matchedLines++;
        }

        $confidence = $totalLines > 0 ? round($matchedLines / $totalLines, 4) : 0.0;

        return ['rows' => $rows, 'errors' => $errors, 'warnings' => $warnings, 'confidence' => $confidence];
    }

    /**
     * A line that mentions a day keyword is an attempted schedule entry; if it
     * could not be parsed it must be surfaced instead of silently dropped.
     */
    private function looksLikeScheduleLine(string $line): bool
    {
        $lower = mb_strtolower($line);

        foreach (self::DAYS as $key => $_dayNumber) {
            if (str_contains($lower, $key)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function extractRow(string $line): ?array
    {
        $lower = mb_strtolower($line);

        $dayKey = null;
        foreach (self::DAYS as $key => $_dayNumber) {
            if (str_contains($lower, $key)) {
                $dayKey = $key;
                break;
            }
        }

        if ($dayKey === null) {
            return null;
        }

        if (! preg_match('/(\d{1,2})[:.](\d{2})\s*[-–—]\s*(\d{1,2})[:.](\d{2})/', $line, $m)) {
            return null;
        }

        $startTime = $this->pad($m[1]).':'.$m[2];
        $endTime = $this->pad($m[3]).':'.$m[4];

        // Everything after the day keyword and time range is course / location.
        $rest = preg_replace('/\d{1,2}[:.]\d{2}\s*[-–—]\s*\d{1,2}[:.]\d{2}/', '|', $line, 1) ?? $line;
        $rest = trim(preg_replace('/^.*?'.$dayKey.'/iu', '', $rest) ?? '');
        $parts = array_values(array_filter(array_map('trim', explode('|', $rest)), static fn ($p) => $p !== ''));

        $course = $parts[0] ?? '';
        $location = $parts[1] ?? null;

        if ($course === '') {
            return null;
        }

        return [
            'day' => $dayKey,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'course' => $course,
            'location' => $location,
        ];
    }

    private function pad(string $value): string
    {
        return str_pad($value, 2, '0', STR_PAD_LEFT);
    }
}
