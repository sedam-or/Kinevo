<?php

namespace App\Application\ActivityLogs;

use App\Domain\ActivityLogs\ActivityLog;
use App\Domain\ActivityLogs\Contracts\ActivityLogRepository;
use Carbon\CarbonImmutable;

/**
 * Exports activity logs as JSON or CSV (FR-34). References task/subtask ids
 * only; note contents are excluded per privacy policy.
 */
final readonly class ExportActivityLogsUseCase
{
    public function __construct(
        private ActivityLogRepository $logs,
    ) {}

    /**
     * @return array{format: string, filename: string, content: string}
     */
    public function __invoke(
        int $userId,
        string $format,
        ?CarbonImmutable $from = null,
        ?CarbonImmutable $to = null,
    ): array {
        $entries = $this->logs->exportForUser($userId, $from, $to);

        if ($format === 'json') {
            return [
                'format' => 'json',
                'filename' => 'activity_logs.json',
                'content' => json_encode(
                    array_map(static fn ($log) => $log->toArray(), $entries),
                    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
                ),
            ];
        }

        return [
            'format' => 'csv',
            'filename' => 'activity_logs.csv',
            'content' => $this->toCsv($entries),
        ];
    }

    /**
     * @param  array<int, ActivityLog>  $entries
     */
    private function toCsv(array $entries): string
    {
        $rows = [
            ['id', 'event_type', 'entity_type', 'entity_id', 'title', 'event_at', 'payload'],
        ];

        foreach ($entries as $log) {
            $rows[] = [
                $log->id,
                $log->eventType->value,
                $log->entityType,
                $log->entityId,
                $log->title,
                $log->eventAt->toISOString(),
                json_encode($log->payload, JSON_UNESCAPED_SLASHES),
            ];
        }

        $stream = fopen('php://temp', 'r+');
        foreach ($rows as $row) {
            fputcsv($stream, $row);
        }
        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return $csv;
    }
}
