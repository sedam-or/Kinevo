<?php

namespace App\Domain\Identity\Contracts;

use App\Domain\Identity\Profile;
use App\Domain\Identity\ValueObjects\ProfileSettings;

interface ProfileRepository
{
    public function findForUser(int $userId): ?Profile;

    public function create(int $userId, ProfileSettings $settings): Profile;

    public function update(int $userId, ProfileSettings $settings): Profile;
}
