<?php

namespace App\Application\Identity;

use App\Domain\Identity\Contracts\ProfileRepository;
use App\Domain\Identity\ValueObjects\ProfileSettings;

final readonly class UpdateProfileUseCase
{
    public function __construct(
        private ProfileRepository $profiles,
    ) {}

    public function __invoke(int $userId, ProfileSettings $settings): ProfileSettings
    {
        $existing = $this->profiles->findForUser($userId);

        if ($existing === null) {
            return $this->profiles->create($userId, $settings)->settings;
        }

        return $this->profiles->update($userId, $settings)->settings;
    }
}
