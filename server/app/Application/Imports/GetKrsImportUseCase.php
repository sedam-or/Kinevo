<?php

namespace App\Application\Imports;

use App\Domain\Imports\Contracts\KrsImportRepository;
use App\Domain\Imports\KrsImport;
use InvalidArgumentException;

/**
 * Fetches a staged KRS import for preview (FR-24), scoped to the owner.
 */
final readonly class GetKrsImportUseCase
{
    public function __construct(
        private KrsImportRepository $imports,
    ) {}

    public function __invoke(int $userId, int $importId): KrsImport
    {
        $import = $this->imports->findForUser($userId, $importId);

        if ($import === null) {
            throw new InvalidArgumentException('Import not found.');
        }

        return $import;
    }
}
