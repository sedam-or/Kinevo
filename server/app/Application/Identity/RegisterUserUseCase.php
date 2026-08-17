<?php

namespace App\Application\Identity;

use App\Domain\Identity\Contracts\ProfileRepository;
use App\Domain\Identity\ValueObjects\ProfileSettings;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;

/**
 * Registers the first owner account and its default profile.
 */
final readonly class RegisterUserUseCase
{
    public function __construct(
        private ProfileRepository $profiles,
    ) {}

    /**
     * @return array{user: User, token: string, profile: array<string, mixed>}
     */
    public function __invoke(string $name, string $email, string $password): array
    {
        if (User::query()->exists()) {
            throw new InvalidArgumentException('Registration is only available for the first owner account.');
        }

        $user = User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
        ]);

        event(new Registered($user));

        $profile = $this->profiles->create($user->id, ProfileSettings::defaults());

        $token = $user->createToken('owner')->plainTextToken;

        return [
            'user' => $user,
            'token' => $token,
            'profile' => $profile->settings->toArray(),
        ];
    }
}
