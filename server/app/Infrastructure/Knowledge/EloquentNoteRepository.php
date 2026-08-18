<?php

namespace App\Infrastructure\Knowledge;

use App\Domain\Knowledge\Contracts\NoteRepository;
use App\Domain\Knowledge\Note;
use App\Domain\Knowledge\NoteVersionConflict;
use App\Models\Note as NoteModel;
use Illuminate\Support\Facades\DB;

final class EloquentNoteRepository implements NoteRepository
{
    public function findForUser(int $userId, int $noteId): ?Note
    {
        $model = NoteModel::query()->where('user_id', $userId)->find($noteId);

        return $model === null ? null : $this->toDomain($model);
    }

    public function listForUser(int $userId): array
    {
        return NoteModel::query()
            ->where('user_id', $userId)
            ->orderByDesc('updated_at')
            ->get()
            ->map($this->toDomain(...))
            ->all();
    }

    public function create(int $userId, Note $note): Note
    {
        $model = NoteModel::query()->create([
            'user_id' => $userId,
            'title' => $note->title,
            'document_json' => $note->documentJson,
            'markdown_cache' => $note->markdownCache,
            'plain_text_cache' => $note->plainTextCache,
            'version' => 1,
        ]);

        return $this->toDomain($model);
    }

    public function update(Note $note, int $baseVersion): Note
    {
        $model = NoteModel::query()
            ->where('user_id', $note->userId)
            ->where('id', $note->id)
            ->where('version', $baseVersion)
            ->first();

        if ($model === null) {
            $current = NoteModel::query()->where('user_id', $note->userId)->find($note->id);
            $actualVersion = $current !== null ? $current->version : 0;
            throw new NoteVersionConflict($baseVersion, $actualVersion);
        }

        $model->update([
            'title' => $note->title,
            'document_json' => $note->documentJson,
            'markdown_cache' => $note->markdownCache,
            'plain_text_cache' => $note->plainTextCache,
            'version' => $note->version,
        ]);
        $model->refresh();

        return $this->toDomain($model);
    }

    public function searchForUser(int $userId, string $query): array
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            return $this->fullTextSearch($userId, $query);
        }

        return $this->likeSearch($userId, $query);
    }

    private function fullTextSearch(int $userId, string $query): array
    {
        return NoteModel::query()
            ->where('user_id', $userId)
            ->whereRaw("search_vector @@ plainto_tsquery('english', ?)", [$query])
            ->orderByDesc('updated_at')
            ->get()
            ->map($this->toDomain(...))
            ->all();
    }

    private function likeSearch(int $userId, string $query): array
    {
        $likePattern = '%'.$query.'%';

        return NoteModel::query()
            ->where('user_id', $userId)
            ->where(function ($q) use ($likePattern) {
                $q->where('title', 'LIKE', $likePattern)
                    ->orWhere('plain_text_cache', 'LIKE', $likePattern);
            })
            ->orderByDesc('updated_at')
            ->get()
            ->map($this->toDomain(...))
            ->all();
    }

    private function toDomain(NoteModel $model): Note
    {
        return new Note(
            $model->id,
            $model->user_id,
            $model->title,
            $model->document_json,
            $model->markdown_cache,
            $model->plain_text_cache,
            $model->version,
        );
    }
}
