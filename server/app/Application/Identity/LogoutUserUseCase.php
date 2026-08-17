<?php

namespace App\Application\Identity;

use App\Models\User;

final readonly class LogoutUserUseCase
{
    public function __invoke(User $user): void
    {
        $user->currentAccessToken()->delete();
    }
}
