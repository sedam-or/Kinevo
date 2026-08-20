<?php

namespace App\Application\Imports;

use App\Domain\Identity\Contracts\ProfileRepository;
use App\Domain\Imports\Contracts\IcalImportRepository;
use App\Domain\Imports\IcalImport;
use Illuminate\Http\UploadedFile;
use InvalidArgumentException;

/**
 * Uploads and stages an iCalendar (.ics) import (FR-30). Validates the file
 * (.ics/.ical, ≤5 MB), parses VEVENTs into candidate rows in the owner's
 * profile timezone, flags conflicts against existing Hard Landscape, and stores
 * the result in a pending staging state. Nothing is persisted to the schedule
 * until the user confirms the preview.
 */
final readonly class UploadIcalImportUseCase
{
    public const MAX_BYTES = 5 * 1024 * 1024;

    private const ALLOWED_EXTENSIONS = ['ics', 'ical'];

    public function __construct(
        private IcsParser $parser,
        private IcalImportRepository $imports,
        private ProfileRepository $profiles,
        private IcalConflictResolver $conflicts,
    ) {}

    public function __invoke(int $userId, UploadedFile $file): IcalImport
    {
        if ($file->getSize() > self::MAX_BYTES) {
            throw new InvalidArgumentException('ICS file exceeds the 5 MB size limit.');
        }

        $extension = strtolower((string) $file->getClientOriginalExtension());
        if (! in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw new InvalidArgumentException('Only .ics calendar files are accepted.');
        }

        $timezone = $this->profiles->findForUser($userId)?->settings->timezone ?? config('app.timezone');

        $contents = (string) file_get_contents($file->getRealPath());
        $result = $this->parser->parse($contents, (string) $timezone);

        if (count($result['rows']) === 0) {
            throw new InvalidArgumentException('No events could be parsed from this calendar.');
        }

        $rows = $this->conflicts->resolve($userId, $result['rows']);

        return $this->imports->create(IcalImport::stage(
            $userId,
            $file->getClientOriginalName() ?: 'calendar.ics',
            $result['confidence'],
            $rows,
            $result['errors'],
            $result['warnings'],
        ));
    }
}
