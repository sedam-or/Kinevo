<?php

namespace App\Domain\Saas;

use InvalidArgumentException;

/**
 * TASK-P23-002 — machine-readable plan definition resolved from config
 * (config/saas.php). Prices/names stay product data; code only reads keys.
 */
final readonly class Plan
{
    /**
     * @param  array<string, mixed>  $entitlements
     */
    public function __construct(
        public string $code,
        public string $name,
        public array $entitlements,
    ) {}

    public static function fromConfig(string $code): self
    {
        $plans = (array) config('saas.plans', []);
        if (! isset($plans[$code])) {
            throw new InvalidArgumentException("Unknown plan [{$code}].");
        }

        /** @var array{name: string, entitlements: array<string, mixed>} $def */
        $def = $plans[$code];

        return new self($code, $def['name'], (array) $def['entitlements']);
    }

    public static function exists(string $code): bool
    {
        return array_key_exists($code, (array) config('saas.plans', []));
    }

    public static function defaultCode(): string
    {
        return (string) config('saas.default_plan', 'free');
    }

    public static function default(): self
    {
        return self::fromConfig(self::defaultCode());
    }

    public function entitlement(string $key): mixed
    {
        return $this->entitlements[$key] ?? null;
    }
}
