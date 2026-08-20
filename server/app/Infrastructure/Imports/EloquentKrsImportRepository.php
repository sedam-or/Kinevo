<?php

namespace App\Infrastructure\Imports;

use App\Domain\Imports\Contracts\KrsImportRepository;
use App\Domain\Imports\KrsImport;
use App\Models\Import as ImportModel;
use Carbon\CarbonImmutable;

final class EloquentKrsImportRepository implements KrsImportRepository
{
    public function create(KrsImport $import): KrsImport
    {
        $model = ImportModel::query()->create([
            'user_id' => $import->userId,
            'type' => 'krs_pdf',
            'filename' => $import->filename,
            'status' => $import->status,
            'confidence' => $import->confidence,
            'rows' => $import->rows,
        ]);

        return $this->toDomain($model);
    }

    public function update(KrsImport $import): KrsImport
    {
        $model = ImportModel::query()->findOrFail($import->id);
        $model->status = $import->status;
        $model->confidence = $import->confidence;
        $model->rows = $import->rows;
        $model->save();

        return $this->toDomain($model->refresh());
    }

    public function findForUser(int $userId, int $importId): ?KrsImport
    {
        $model = ImportModel::query()
            ->where('user_id', $userId)
            ->where('id', $importId)
            ->first();

        return $model !== null ? $this->toDomain($model) : null;
    }

    private function toDomain(ImportModel $model): KrsImport
    {
        return new KrsImport(
            $model->id,
            $model->user_id,
            $model->filename,
            $model->status,
            $model->confidence,
            $model->rows ?? [],
            CarbonImmutable::parse($model->created_at),
        );
    }
}
