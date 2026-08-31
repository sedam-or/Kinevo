<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent persistence for `schedule_assignment_history` (ADR-015 history
 * model). Immutable archive of superseded/deleted schedule assignments —
 * rows are only ever inserted, never updated.
 *
 * @property int $id
 * @property int $user_id
 * @property int $assignment_id
 * @property int $task_id
 * @property CarbonImmutable $date
 * @property CarbonImmutable $start_at
 * @property CarbonImmutable $end_at
 * @property int $duration_minutes
 * @property string $status
 * @property string $source
 * @property int $schedule_version
 * @property bool $locked
 * @property int $version
 * @property int|null $superseded_by_schedule_version
 * @property string|null $superseded_by
 * @property string|null $reason
 * @property CarbonImmutable $acted_at
 * @property string|null $created_at
 * @property string|null $updated_at
 */
class ScheduleAssignmentHistory extends Model
{
    protected $table = 'schedule_assignment_history';

    protected $fillable = [
        'user_id',
        'assignment_id',
        'task_id',
        'date',
        'start_at',
        'end_at',
        'duration_minutes',
        'status',
        'source',
        'schedule_version',
        'locked',
        'version',
        'superseded_by_schedule_version',
        'superseded_by',
        'reason',
        'acted_at',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'duration_minutes' => 'integer',
            'schedule_version' => 'integer',
            'locked' => 'boolean',
            'version' => 'integer',
            'superseded_by_schedule_version' => 'integer',
            'acted_at' => 'datetime',
        ];
    }
}
