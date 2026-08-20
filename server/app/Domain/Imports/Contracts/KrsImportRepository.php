<?php

namespace App\Domain\Imports\Contracts;

use App\Domain\Imports\KrsImport;

interface KrsImportRepository
{
    public function create(KrsImport $import): KrsImport;

    public function update(KrsImport $import): KrsImport;

    public function findForUser(int $userId, int $importId): ?KrsImport;
}
