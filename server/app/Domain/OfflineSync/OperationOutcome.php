<?php

namespace App\Domain\OfflineSync;

/**
 * ADR-017 §2.7 — one reconcile outcome per operation. A first-apply carries
 * the full canonical result shape; a replay carries the bounded recorded
 * result (entity_id, version) and `replay=true` so the caller rehydrates.
 */
final class OperationOutcome
{
    public const APPLIED = 'applied';

    public const CONFLICT = 'conflict';

    public const REJECTED = 'rejected';

    public const CODE_VERSION_CONFLICT = 'VERSION_CONFLICT';

    public const CODE_STATE_CONFLICT = 'STATE_CONFLICT';

    public const CODE_VALIDATION = 'VALIDATION';

    public const CODE_NOT_FOUND = 'NOT_FOUND';

    public const CODE_REUSED = 'REUSED';

    public const CODE_UNSUPPORTED = 'UNSUPPORTED';

    public const CODE_WORKSPACE = 'WORKSPACE';

    public const CODE_ENTITLEMENT = 'ENTITLEMENT';

    public const CODE_EXPIRED = 'EXPIRED';

    public function __construct(
        public readonly string $operationId,
        public readonly string $status,
        public readonly string $code,
        public readonly ?OperationApplyResult $result,
        public readonly bool $replay = false,
        public readonly ?string $error = null,
    ) {}

    public static function applied(string $operationId, OperationApplyResult $result): self
    {
        return new self($operationId, self::APPLIED, '', $result);
    }

    public static function replayed(string $operationId, OperationApplyResult $result): self
    {
        return new self($operationId, self::APPLIED, '', $result, replay: true);
    }

    public static function conflict(string $operationId, string $code, string $error): self
    {
        return new self($operationId, self::CONFLICT, $code, null, error: $error);
    }

    public static function rejected(string $operationId, string $code, string $error): self
    {
        return new self($operationId, self::REJECTED, $code, null, error: $error);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $payload = [
            'operation_id' => $this->operationId,
            'status' => $this->status,
            'code' => $this->code,
            'replay' => $this->replay,
        ];

        if ($this->result !== null) {
            $payload['result'] = $this->result->result;
            $payload['entity_id'] = $this->result->entityId;
            $payload['result_version'] = $this->result->version;
        }

        if ($this->error !== null) {
            $payload['error'] = $this->error;
        }

        return $payload;
    }
}
