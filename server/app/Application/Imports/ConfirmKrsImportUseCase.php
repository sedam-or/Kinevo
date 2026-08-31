<?php

namespace App\Application\Imports;

use App\Application\Scheduling\ScheduleImpactService;
use App\Domain\Identity\Contracts\ProfileRepository;
use App\Domain\Imports\Contracts\KrsImportRepository;
use App\Domain\Imports\KrsImport;
use App\Domain\Scheduling\Contracts\HardLandscapeRepository;
use App\Domain\Scheduling\HardLandscapeEvent;
use App\Domain\Scheduling\ValueObjects\HardLandscapeType;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Confirms a staged KRS import (FR-24): persists each parsed row as a weekly
 * recurring Hard Landscape event inside a single transaction and marks the
 * import confirmed. The import never silently overwrites an existing schedule —
 * it only adds the parsed events after explicit user confirmation.
 */
final readonly class ConfirmKrsImportUseCase
{
    public function __construct(
        private KrsImportRepository $imports,
        private HardLandscapeRepository $hardLandscape,
        private ScheduleImpactService $impact,
        private ProfileRepository $profiles,
    ) {}

    public function __invoke(int $userId, int $importId): KrsImport
    {
        $import = $this->imports->findForUser($userId, $importId);

        if ($import === null) {
            throw new InvalidArgumentException('Import not found.');
        }

        if (! $import->isPending()) {
            throw new InvalidArgumentException('Import has already been resolved.');
        }

        $confirmed = DB::transaction(function () use ($import) {
            foreach ($import->rows as $row) {
                $start = $this->nextOccurrence((string) ($row['day'] ?? ''), (string) ($row['start_time'] ?? '09:00'));
                $end = $start->setTimeFromTimeString($row['end_time'] ?? '10:00');

                $this->hardLandscape->create(
                    HardLandscapeEvent::create(
                        $import->userId,
                        (string) ($row['course'] ?? 'Imported'),
                        HardLandscapeType::recurring(),
                        $start,
                        $end,
                        'FREQ=WEEKLY',
                    ),
                );
            }

            return $this->imports->update($import->confirmed());
        });

        // ADR-016 §2.3 — confirmed KRS reality may impact accepted work
        // (bounded window; failure-isolated; never auto-applies).
        $localToday = CarbonImmutable::now($this->userTimezone($userId))->startOfDay();
        $this->impact->assess($userId, $localToday->subDays(7), $localToday->addDays(14), 'krs_import_confirmed', [$importId]);

        return $confirmed;
    }

    private function userTimezone(int $userId): string
    {
        return $this->profiles->findForUser($userId)?->settings->timezone ?? config('app.timezone');
    }

    private function nextOccurrence(string $dayKey, string $time): CarbonImmutable
    {
        $dayNumber = match ($dayKey) {
            'senin' => CarbonImmutable::MONDAY,
            'selasa' => CarbonImmutable::TUESDAY,
            'rabu' => CarbonImmutable::WEDNESDAY,
            'kamis' => CarbonImmutable::THURSDAY,
            'jumat' => CarbonImmutable::FRIDAY,
            'sabtu' => CarbonImmutable::SATURDAY,
            'minggu' => CarbonImmutable::SUNDAY,
            default => CarbonImmutable::MONDAY,
        };

        $today = CarbonImmutable::now()->startOfDay();

        return $today->next($dayNumber)->setTimeFromTimeString($time);
    }
}
