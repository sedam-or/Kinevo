<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string|null $display_name
 * @property string $locale
 * @property string $timezone
 * @property string $week_start_day
 */
#[Fillable(['user_id', 'display_name', 'locale', 'timezone', 'week_start_day'])]
class Profile extends Model
{
    protected $table = 'profiles';

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
