<?php

namespace App\Domain\OfflineSync;

use App\Domain\OfflineSync\ValueObjects\OperationType;
use InvalidArgumentException;

/**
 * ADR-017 §2.2 — versioned offline operation envelope. `client_created_at` is
 * observability metadata and NEVER decides write precedence (§2.3).
 */
final class OperationEnvelope
{
    public const PROTOCOL_VERSION = 1;

    public function __construct(
        public readonly string $operationId,
        public readonly OperationType $operationType,
        public readonly string $entityType,
        public readonly ?int $entityId,
        public readonly array $payload,
        public readonly ?int $baseVersion = null,
        public readonly ?string $clientReferenceId = null,
        public readonly ?int $workspaceId = null,
        public readonly ?string $clientCreatedAt = null,
        public readonly int $protocolVersion = self::PROTOCOL_VERSION,
    ) {}

    /**
     * Parse and validate a raw client envelope. Rejects malformed shapes,
     * unknown operation types, and oversized operation ids.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $protocolVersion = (int) ($data['protocol_version'] ?? self::PROTOCOL_VERSION);
        if ($protocolVersion !== self::PROTOCOL_VERSION) {
            throw new InvalidArgumentException('Unsupported offline protocol version.');
        }

        $operationId = (string) ($data['operation_id'] ?? '');
        if ($operationId === '' || strlen($operationId) > 64) {
            throw new InvalidArgumentException('Invalid operation_id.');
        }

        try {
            $operationType = new OperationType((string) ($data['operation_type'] ?? ''));
        } catch (InvalidArgumentException $e) {
            throw new InvalidArgumentException('Unsupported offline operation type.');
        }

        $payload = $data['payload'] ?? [];
        if (! is_array($payload)) {
            throw new InvalidArgumentException('Operation payload must be an object.');
        }

        return new self(
            $operationId,
            $operationType,
            (string) ($data['entity_type'] ?? ''),
            isset($data['entity_id']) ? (int) $data['entity_id'] : null,
            $payload,
            isset($data['base_version']) ? (int) $data['base_version'] : null,
            isset($data['client_reference_id']) ? (string) $data['client_reference_id'] : null,
            isset($data['workspace_id']) ? (int) $data['workspace_id'] : null,
            isset($data['client_created_at']) ? (string) $data['client_created_at'] : null,
            $protocolVersion,
        );
    }
}
