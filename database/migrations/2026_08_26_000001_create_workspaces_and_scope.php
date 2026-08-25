<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * TASK-P19-001/002/003 — workspace tables + scoping columns + idempotent
 * backfill of all existing workspace-aware data into the per-user default
 * "Personal" workspace.
 *
 * Safety contract:
 * - non-destructive: only ADDS a table, nullable FK columns and indexes;
 * - idempotent: backfill touches only NULL workspace_id rows; the default
 *   workspace is created once per user (unique index guards duplicates);
 * - data-preserving / reversible: down() drops exactly what up() created.
 */
return new class extends Migration
{
    /** Tables that are directly workspace-aware (TASK-P19-002). */
    private const SCOPED_TABLES = ['goals', 'programs', 'tasks', 'notes', 'canvases'];

    public function up(): void
    {
        Schema::create('workspaces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('slug', 80);
            $table->string('description', 500)->nullable();
            $table->string('icon', 32)->nullable();
            $table->string('accent', 16)->nullable();
            $table->string('type', 20)->default('personal')->index();
            $table->boolean('is_default')->default(false);
            $table->string('status', 20)->default('active')->index();
            $table->timestamps();
            $table->unique(['user_id', 'slug']);
        });

        foreach (self::SCOPED_TABLES as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (! Schema::hasColumn($tableName, 'workspace_id')) {
                    // Nullable during transition; backfilled below.
                    $table->foreignId('workspace_id')->nullable()->index();
                }
            });
        }

        $this->backfill();
    }

    /**
     * Assign every existing workspace-aware row to its owner's default
     * "Personal" workspace (TASK-P19-003). Deterministic: no historical
     * context exists in MVP data, so Personal is the explicit default.
     */
    private function backfill(): void
    {
        // One default "Personal" workspace per user that lacks one.
        $users = DB::table('users')->select('id')->orderBy('id')->get()->pluck('id');
        foreach ($users as $userId) {
            DB::table('workspaces')->insertOrIgnore([
                'user_id' => $userId,
                'name' => 'Personal',
                'slug' => 'personal',
                'description' => 'Your default workspace.',
                'type' => 'personal',
                'is_default' => true,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $defaults = DB::table('workspaces')
            ->where('is_default', true)
            ->select('id', 'user_id')
            ->get()
            ->keyBy('user_id');

        foreach (self::SCOPED_TABLES as $tableName) {
            $hasUserId = Schema::hasColumn($tableName, 'user_id');
            $rows = DB::table($tableName)
                ->whereNull('workspace_id')
                ->select('id', ...($hasUserId ? ['user_id'] : []))
                ->orderBy('id')
                ->get();

            foreach ($rows as $row) {
                $ownerId = $hasUserId ? $row->user_id : $this->resolveOwnerId($tableName, $row->id);
                if ($ownerId === null || ! isset($defaults[$ownerId])) {
                    continue;
                }
                DB::table($tableName)
                    ->where('id', $row->id)
                    ->update(['workspace_id' => $defaults[$ownerId]->id]);
            }
        }
    }

    /**
     * Owner resolution for scoped tables without a direct user_id column.
     */
    private function resolveOwnerId(string $tableName, int $id): ?int
    {
        return match ($tableName) {
            'canvases' => DB::table('canvases')->where('id', $id)->value('user_id'),
            default => null,
        };
    }

    public function down(): void
    {
        foreach (self::SCOPED_TABLES as $tableName) {
            if (Schema::hasColumn($tableName, 'workspace_id')) {
                Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                    // Drop the generated index name first where present.
                    try {
                        $table->dropIndex([$tableName.'.workspace_id']);
                    } catch (Throwable) {
                        // index may not exist on partial rollbacks
                    }
                    $table->dropConstrainedForeignId('workspace_id');
                });
            }
        }

        Schema::dropIfExists('workspaces');
    }
};
