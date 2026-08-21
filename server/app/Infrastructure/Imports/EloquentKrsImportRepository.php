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
            'rows' => $this->payload($import),
        ]);

        return $this->toDomain($model);
    }

    public function update(KrsImport $import): KrsImport
    {
        $model = ImportModel::query()->findOrFail($import->id);
        $model->status = $import->status;
        $model->confidence = $import->confidence;
        $model->rows = $this->payload($import);
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

    /**
     * @return array{rows: array<int, mixed>, errors: array<int, mixed>, warnings: array<int, mixed>}
     */
    private function payload(KrsImport $import): array
    {
        return [
            'rows' => $import->rows,
            'errors' => $import->errors,
            'warnings' => $import->warnings,
        ];
    }

    private function toDomain(ImportModel $model): KrsImport
    {
        $data = $model->rows ?? [];

        // Imports staged before TASK-144 stored the row list directly.
        $rows = $data['rows'] ?? $data;
        $errors = $data['errors'] ?? [];
        $warnings = $data['warnings'] ?? [];

        return new KrsImport(
            $model->id,
            $model->user_id,
            $model->filename,
            $model->status,
            $model->confidence,
            is_array($rows) ? $rows : [],
            is_array($errors) ? $errors : [],
            is_array($warnings) ? $warnings : [],
            CarbonImmutable::parse($model->created_at),
        );
    }
}
