<?php

namespace App\Infrastructure\Knowledge;

use App\Domain\Knowledge\Contracts\NoteRepository;
use App\Domain\Knowledge\Note;
use App\Domain\Knowledge\NoteVersionConflict;
use App\Models\Note as NoteModel;

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
