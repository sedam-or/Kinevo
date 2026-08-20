<?php

namespace App\Application\Imports;

use App\Domain\Imports\Contracts\IcalImportRepository;
use App\Domain\Imports\IcalImport;
use InvalidArgumentException;

/**
 * Discards a staged iCal import (FR-30) without persisting anything.
 */
final readonly class DiscardIcalImportUseCase
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

        if (! $import->isPending()) {
            throw new InvalidArgumentException('Import has already been resolved.');
        }

        return $this->imports->update($import->discarded());
    }
}
