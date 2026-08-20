<?php

namespace App\Application\Imports;

use App\Domain\Imports\Contracts\IcalImportRepository;
use App\Domain\Imports\IcalImport;
use App\Domain\Scheduling\Contracts\HardLandscapeRepository;
use App\Domain\Scheduling\HardLandscapeEvent;
use App\Domain\Scheduling\ValueObjects\HardLandscapeType;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Confirms a staged iCal import (FR-30): recomputes conflicts inside a single
 * transaction, persists each non-conflicting row as Hard Landscape (recurring
 * for RRULE-backed events, one-time otherwise), and marks the import confirmed.
 * Conflicting rows are never persisted — they stay visible with their conflict
 * reason so nothing is silently overwritten (FR-24 Business Rule / TASK-142).
 */
final readonly class ConfirmIcalImportUseCase
{
    public function __construct(
        private IcalImportRepository $imports,
        private HardLandscapeRepository $hardLandscape,
        private IcalConflictResolver $conflicts,
    ) {}

    public function __invoke(int $userId, int $importId): IcalImport
    {
        $import = $this->imports->findForUser($userId, $importId);

        if ($import === null) {
            throw new InvalidArgumentException('Import not found.');
        }

        if (! $import->isPending()) {
            throw new InvalidArgumentException('Import has already been resolved.');
        }

        return DB::transaction(function () use ($import) {
            $rows = $this->conflicts->resolve($import->userId, $import->rows);

            foreach ($rows as $row) {
                if (($row['conflict'] ?? false) === true) {
                    continue;
                }

                $this->hardLandscape->create(
                    HardLandscapeEvent::create(
                        $import->userId,
                        (string) ($row['summary'] ?? 'Imported event'),
                        ($row['type'] ?? 'one_time') === 'recurring'
                            ? HardLandscapeType::recurring()
                            : HardLandscapeType::oneTime(),
                        CarbonImmutable::parse($row['start_at']),
                        CarbonImmutable::parse($row['end_at']),
                        ($row['recurrence'] ?? null) ?: null,
                    ),
                );
            }

            return $this->imports->update($import->confirmed($rows));
        });
    }
}
