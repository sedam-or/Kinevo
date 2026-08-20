<?php

namespace App\Application\Imports;

use App\Domain\Imports\Contracts\KrsImportRepository;
use App\Domain\Imports\KrsImport;
use InvalidArgumentException;

/**
 * Discards a staged KRS import (FR-24) without persisting anything.
 */
final readonly class DiscardKrsImportUseCase
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

        if (! $import->isPending()) {
            throw new InvalidArgumentException('Import has already been resolved.');
        }

        return $this->imports->update($import->discarded());
    }
}
