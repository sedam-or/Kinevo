<?php

namespace App\Infrastructure\Saas;

use App\Domain\Saas\Contracts\UsageRepository as Contract;
use App\Domain\Saas\Usage;
use App\Models\SaasUsageCounter;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

final readonly class EloquentUsageRepository implements Contract
{
    public function forPeriod(int $userId, string $key, string $period): Usage
    {
        $consumed = (int) SaasUsageCounter::query()
            ->where('user_id', $userId)
            ->where('key', $key)
            ->where('period', $period)
            ->value('consumed');

        return new Usage($key, $period, $consumed);
    }

    public function increment(int $userId, string $key, string $period, int $by = 1): Usage
    {
        // Insert-or-increment: the unique (user,key,period) index makes a
        // racing insert fail once and fall through to a safe increment.
        $updated = DB::table('usage_counters')
            ->where('user_id', $userId)
            ->where('key', $key)
            ->where('period', $period)
            ->update(['consumed' => DB::raw('consumed + '.(int) $by), 'updated_at' => now()]);

        if ($updated === 0) {
            try {
                DB::table('usage_counters')->insert([
                    'user_id' => $userId, 'key' => $key, 'period' => $period,
                    'consumed' => $by, 'created_at' => now(), 'updated_at' => now(),
                ]);
            } catch (UniqueConstraintViolationException) {
                DB::table('usage_counters')
                    ->where('user_id', $userId)
                    ->where('key', $key)
                    ->where('period', $period)
                    ->update(['consumed' => DB::raw('consumed + '.(int) $by), 'updated_at' => now()]);
            }
        }

        return $this->forPeriod($userId, $key, $period);
    }
}
