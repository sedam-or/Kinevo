<?php

namespace App\Application\Imports;

use App\Domain\Imports\Contracts\KrsImportRepository;
use App\Domain\Imports\KrsImport;
use Illuminate\Http\UploadedFile;
use InvalidArgumentException;

/**
 * Uploads and stages a KRS PDF import (FR-24). Validates the file (PDF, ≤5 MB),
 * parses it into candidate rows, and stores them in a pending staging state —
 * nothing is persisted to the schedule until the user confirms the preview.
 */
final readonly class UploadKrsImportUseCase
{
    public const MAX_BYTES = 5 * 1024 * 1024;

    public function __construct(
        private KrsPdfParser $parser,
        private KrsImportRepository $imports,
    ) {}

    public function __invoke(int $userId, UploadedFile $file): KrsImport
    {
        if ($file->getSize() > self::MAX_BYTES) {
            throw new InvalidArgumentException('PDF exceeds the 5 MB size limit.');
        }

        if (strtolower((string) $file->getClientOriginalExtension()) !== 'pdf') {
            throw new InvalidArgumentException('Only PDF files are accepted.');
        }

        $contents = (string) file_get_contents($file->getRealPath());
        $result = $this->parser->parse($contents);

        if (count($result['rows']) === 0) {
            throw new InvalidArgumentException('No schedule could be parsed from this PDF.');
        }

        return $this->imports->create(KrsImport::stage(
            $userId,
            $file->getClientOriginalName() ?: 'krs.pdf',
            $result['confidence'],
            $result['rows'],
        ));
    }
}
