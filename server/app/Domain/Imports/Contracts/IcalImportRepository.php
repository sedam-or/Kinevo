<?php

namespace App\Domain\Imports\Contracts;

use App\Domain\Imports\IcalImport;

interface IcalImportRepository
{
    public function create(IcalImport $import): IcalImport;

    public function update(IcalImport $import): IcalImport;

    public function findForUser(int $userId, int $importId): ?IcalImport;
}
