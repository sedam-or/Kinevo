<?php

namespace App\Domain\Ai\Contracts;

use App\Domain\Ai\Entities\AiProviderConfig;

interface AiProviderConfigRepository
{
    public function get(): ?AiProviderConfig;

    public function save(AiProviderConfig $config): void;
}