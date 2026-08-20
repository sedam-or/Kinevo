<?php

namespace App\Application\Imports;

use App\Domain\Imports\Contracts\IcalImportRepository;
use App\Domain\Imports\IcalImport;
use InvalidArgumentException;

/**
 * Fetches a staged iCal import for preview (FR-30), scoped to the owner.
 */
final readonly class GetIcalImportUseCase
{
    public function __construct(
        private IcalImportRepository $imports,
    ) {}

    public function __invoke(int $userId, int $importId): IcalImport
    {
        $import = $this->imports->findForUser($userId, $importId);

        if ($import === null) {
            throw new InvalidArgumentException('Import not found.');
        }

        return $import;
    }
}
