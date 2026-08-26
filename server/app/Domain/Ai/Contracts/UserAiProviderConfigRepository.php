<?php

namespace App\Domain\Ai\Contracts;

use App\Domain\Ai\Entities\AiProviderConfig;

/**
 * TASK-P25-008 — per-user BYOK provider configuration. One active config per
 * user; the API key is encrypted at rest (Laravel Crypt) and decrypted only
 * inside the resolver. Null result = user runs on the Kinevo-hosted path.
 */
interface UserAiProviderConfigRepository
{
    public function forUser(int $userId): ?AiProviderConfig;

    public function save(int $userId, AiProviderConfig $config): void;

    public function remove(int $userId): void;
}
