<?php

namespace App\Infrastructure\Workspaces;

use App\Domain\Workspaces\Contracts\WorkspaceRepository as WorkspaceRepositoryContract;
use App\Domain\Workspaces\ValueObjects\WorkspaceStatus;
use App\Domain\Workspaces\ValueObjects\WorkspaceType;
use App\Domain\Workspaces\Workspace;
use App\Models\Workspace as WorkspaceModel;
use Illuminate\Support\Facades\DB;

final readonly class EloquentWorkspaceRepository implements WorkspaceRepositoryContract
{
    public function findForUser(int $userId, int $workspaceId): ?Workspace
    {
        $model = WorkspaceModel::query()
            ->where('user_id', $userId)
            ->where('id', $workspaceId)
            ->first();

        return $model !== null ? self::toEntity($model) : null;
    }

    public function listForUser(int $userId, ?bool $archived = null): array
    {
        $query = WorkspaceModel::query()->where('user_id', $userId);
        if ($archived !== null) {
            $query->where('status', $archived ? WorkspaceStatus::ARCHIVED : WorkspaceStatus::ACTIVE);
        }

        return $query
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get()
            ->map(self::toEntity(...))
            ->all();
    }

    public function findBySlug(int $userId, string $slug): ?Workspace
    {
        $model = WorkspaceModel::query()
            ->where('user_id', $userId)
            ->where('slug', $slug)
            ->first();

        return $model !== null ? self::toEntity($model) : null;
    }

    public function defaultForUser(int $userId): ?Workspace
    {
        $model = WorkspaceModel::query()
            ->where('user_id', $userId)
            ->where('is_default', true)
            ->first();

        return $model !== null ? self::toEntity($model) : null;
    }

    public function create(Workspace $workspace): Workspace
    {
        [$name, $slug] = $this->uniqueIdentity($workspace->userId, $workspace->name, $workspace->slug);

        $model = new WorkspaceModel([
            'user_id' => $workspace->userId,
            'name' => $name,
            'slug' => $slug,
            'description' => $workspace->description,
            'icon' => $workspace->icon,
            'accent' => $workspace->accent,
            'type' => $workspace->type->value,
            'is_default' => $workspace->isDefault,
            'status' => $workspace->status->value,
        ]);

        DB::transaction(function () use ($model): void {
            $model->save();
            // Exactly-one-default invariant (TASK-P19-001).
            if ($model->is_default) {
                WorkspaceModel::query()
                    ->where('user_id', $model->user_id)
                    ->whereKeyNot($model->getKey())
                    ->update(['is_default' => false]);
            }
        });

        return self::toEntity($model);
    }

    public function update(Workspace $workspace): Workspace
    {
        $model = WorkspaceModel::query()->lockForUpdate()->find($workspace->id);
        if ($model === null || $model->user_id !== $workspace->userId) {
            throw new \RuntimeException('Workspace not found.');
        }

        $identityChanged = $workspace->name !== $model->name || $workspace->slug !== $model->slug;
        if ($identityChanged) {
            [$name, $slug] = $this->uniqueIdentity(
                $workspace->userId,
                $workspace->name,
                $workspace->slug,
                ignoreId: $model->id,
            );
        } else {
            $name = $model->name;
            $slug = $model->slug;
        }

        $model->name = $name;
        $model->slug = $slug;
        $model->description = $workspace->description;
        $model->icon = $workspace->icon;
        $model->accent = $workspace->accent;
        $model->type = $workspace->type->value;
        $model->status = $workspace->status->value;

        DB::transaction(function () use ($model): void {
            $model->save();
            if ($model->is_default && $model->status === WorkspaceStatus::ARCHIVED) {
                throw new \RuntimeException('The default workspace cannot be archived.');
            }
        });

        return self::toEntity($model);
    }

    public function setDefault(int $userId, int $workspaceId): void
    {
        DB::transaction(function () use ($userId, $workspaceId): void {
            $target = WorkspaceModel::query()
                ->where('user_id', $userId)
                ->lockForUpdate()
                ->find($workspaceId);
            if ($target === null) {
                throw new \RuntimeException('Workspace not found.');
            }
            if ($target->status === WorkspaceStatus::ARCHIVED) {
                throw new \RuntimeException('An archived workspace cannot become the default.');
            }
            WorkspaceModel::query()
                ->where('user_id', $userId)
                ->update(['is_default' => false]);
            $target->is_default = true;
            $target->save();
        });
    }

    public function adoptUnassigned(int $userId, int $workspaceId): void
    {
        // TASK-P19-002 scoping candidates. Parent-inherited entities
        // (milestones, subtasks, assignments, canvas files) are NOT scoped
        // directly — they follow their parent. Hard Landscape and
        // notifications stay global by explicit decision (P19-002).
        DB::transaction(function () use ($userId, $workspaceId): void {
            foreach (['goals', 'programs', 'tasks', 'notes', 'canvases'] as $table) {
                DB::table($table)
                    ->where('user_id', $userId)
                    ->whereNull('workspace_id')
                    ->update(['workspace_id' => $workspaceId]);
            }
        });
    }

    /**
     * Slug uniqueness per user (TASK-P19-001). Deterministic suffixing keeps
     * user-visible names stable; only the URL slug disambiguates.
     *
     * @return array{0: string, 1: string} [final name, final slug]
     */
    private function uniqueIdentity(int $userId, string $name, string $baseSlug, ?int $ignoreId = null): array
    {
        $exists = function (string $slug) use ($userId, $ignoreId): bool {
            $query = WorkspaceModel::query()->where('user_id', $userId)->where('slug', $slug);
            if ($ignoreId !== null) {
                $query->whereKeyNot($ignoreId);
            }

            return $query->exists();
        };

        $candidate = $baseSlug;
        $suffix = 2;
        while ($exists($candidate)) {
            $candidate = $baseSlug.'-'.$suffix;
            $suffix++;
        }

        return [$name, $candidate];
    }

    private static function toEntity(WorkspaceModel $model): Workspace
    {
        return new Workspace(
            id: (int) $model->id,
            userId: (int) $model->user_id,
            name: (string) $model->name,
            slug: (string) $model->slug,
            description: $model->description,
            icon: $model->icon,
            accent: $model->accent,
            type: WorkspaceType::from($model->type),
            isDefault: (bool) $model->is_default,
            status: WorkspaceStatus::from((string) $model->status),
        );
    }
}
