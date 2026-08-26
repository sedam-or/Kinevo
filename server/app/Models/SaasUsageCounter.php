<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * TASK-P23-005 — usage counter row (allowance vs consumption are separate).
 *
 * @property int $id
 * @property int $user_id
 * @property string $key
 * @property string $period
 * @property int $consumed
 */
class SaasUsageCounter extends Model
{
    protected $table = 'usage_counters';

    public $timestamps = true;

    protected $fillable = ['user_id', 'key', 'period', 'consumed'];
}
