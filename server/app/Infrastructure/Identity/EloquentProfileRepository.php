<?php

namespace App\Infrastructure\Identity;

use App\Domain\Identity\Contracts\ProfileRepository;
use App\Domain\Identity\Profile;
use App\Domain\Identity\ValueObjects\ProfileSettings;
use App\Models\Profile as ProfileModel;

final class EloquentProfileRepository implements ProfileRepository
{
    public function findForUser(int $userId): ?Profile
    {
        $model = ProfileModel::query()->where('user_id', $userId)->first();

        return $model === null ? null : $this->toDomain($model);
    }

    public function create(int $userId, ProfileSettings $settings): Profile
    {
        $model = ProfileModel::query()->create([
            'user_id' => $userId,
            ...$settings->toArray(),
        ]);

        return $this->toDomain($model);
    }

    public function update(int $userId, ProfileSettings $settings): Profile
    {
        $model = ProfileModel::query()->where('user_id', $userId)->firstOrFail();
        $model->update($settings->toArray());
        $model->refresh();

        return $this->toDomain($model);
    }

    private function toDomain(ProfileModel $model): Profile
    {
        return new Profile(
            $model->id,
            $model->user_id,
            new ProfileSettings(
                $model->display_name,
                $model->locale,
                $model->timezone,
                $model->week_start_day,
            ),
        );
    }
}
