<?php

namespace App\Infrastructure\Imports;

use App\Domain\Imports\Contracts\IcalImportRepository;
use App\Domain\Imports\IcalImport;
use App\Models\Import as ImportModel;
use Carbon\CarbonImmutable;

final class EloquentIcalImportRepository implements IcalImportRepository
{
    public function create(IcalImport $import): IcalImport
    {
        $model = ImportModel::query()->create([
            'user_id' => $import->userId,
            'type' => 'ical',
            'filename' => $import->filename,
            'status' => $import->status,
            'confidence' => $import->confidence,
            'rows' => $this->payload($import),
        ]);

        return $this->toDomain($model);
    }

    public function update(IcalImport $import): IcalImport
    {
        $model = ImportModel::query()->findOrFail($import->id);
        $model->status = $import->status;
        $model->confidence = $import->confidence;
        $model->rows = $this->payload($import);
        $model->save();

        return $this->toDomain($model->refresh());
    }

    public function findForUser(int $userId, int $importId): ?IcalImport
    {
        $model = ImportModel::query()
            ->where('user_id', $userId)
            ->where('id', $importId)
            ->where('type', 'ical')
            ->first();

        return $model !== null ? $this->toDomain($model) : null;
    }

    /**
     * @return array{rows: array<int, mixed>, errors: array<int, mixed>, warnings: array<int, mixed>}
     */
    private function payload(IcalImport $import): array
    {
        return [
            'rows' => $import->rows,
            'errors' => $import->errors,
            'warnings' => $import->warnings,
        ];
    }

    private function toDomain(ImportModel $model): IcalImport
    {
        $data = $model->rows;

        return new IcalImport(
            $model->id,
            $model->user_id,
            $model->filename,
            $model->status,
            $model->confidence,
            $data['rows'] ?? [],
            $data['errors'] ?? [],
            $data['warnings'] ?? [],
            CarbonImmutable::parse($model->created_at),
        );
    }
}
