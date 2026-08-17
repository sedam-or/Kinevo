<?php

namespace App\Application\Identity;

use App\Domain\Identity\Contracts\ProfileRepository;
use App\Domain\Identity\ValueObjects\ProfileSettings;

final readonly class GetProfileUseCase
{
    public function __construct(
        private ProfileRepository $profiles,
    ) {}

    public function __invoke(int $userId): ProfileSettings
    {
        $profile = $this->profiles->findForUser($userId);

        if ($profile === null) {
            return ProfileSettings::defaults();
        }

        return $profile->settings;
    }
}
