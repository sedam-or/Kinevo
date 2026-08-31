<?php

namespace App\Application\OfflineSync;

use App\Application\Knowledge\CreateNoteUseCase;
use App\Application\Knowledge\UpdateNoteUseCase;
use App\Application\Tasks\AddSubtaskUseCase;
use App\Application\Tasks\CreateTaskUseCase;
use App\Application\Tasks\SetTaskStatusUseCase;
use App\Application\Tasks\UpdateTaskUseCase;
use App\Application\Workspaces\ResolveWorkspaceContext;
use App\Domain\Knowledge\NoteVersionConflict;
use App\Domain\OfflineSync\Contracts\OfflineOperationRepository;
use App\Domain\OfflineSync\OfflineOperationRecord;
use App\Domain\OfflineSync\OfflineOperationReused;
use App\Domain\OfflineSync\OperationApplyResult;
use App\Domain\OfflineSync\OperationEnvelope;
use App\Domain\OfflineSync\OperationOutcome;
use App\Domain\OfflineSync\ValueObjects\OperationType;
use App\Domain\Tasks\Contracts\TaskRepository;
use App\Domain\Tasks\TaskVersionConflict;
use App\Domain\Tasks\ValueObjects\TaskStatus;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

/**
 * ADR-017 §2.6 — the single Kinevo-owned reconciliation boundary. Dispatches
 * only the allowlisted operation types to the REAL application use cases; the
 * offline and online paths converge on the same use-case authority. No
 * business logic is copied here; each operation executes atomically (per-op
 * transaction) with its ledger row.
 */
final readonly class OfflineReconciliationService
{
    public function __construct(
        private OfflineOperationRepository $ledger,
        private CreateTaskUseCase $createTask,
        private UpdateTaskUseCase $updateTask,
        private SetTaskStatusUseCase $setTaskStatus,
        private AddSubtaskUseCase $addSubtask,
        private CreateNoteUseCase $createNote,
        private UpdateNoteUseCase $updateNote,
        private TaskRepository $tasks,
        private ResolveWorkspaceContext $workspaceContext,
    ) {}

    /**
     * Reconcile ONE operation. Shared by the online controllers' optional
     * X-Operation-Id path and the batch endpoint.
     *
     * Idempotency (ADR-017 §2.3):
     *  - first request → execute at most once, record the outcome;
     *  - same id + identical payload → replay the recorded outcome (no
     *    re-execution) — the response-loss safety net;
     *  - same id + different payload → OfflineOperationReused (deterministic).
     *
     * Known domain conflicts are caught, recorded, and returned as conflict
     * outcomes. Validation/not-found exceptions propagate so the online
     * controller maps them exactly as today (the batch endpoint catches them).
     */
    public function reconcileOne(int $userId, OperationEnvelope $envelope, callable $execute): OperationOutcome
    {
        $hash = self::canonicalHash($envelope->payload);
        $existing = $this->ledger->find($userId, $envelope->operationId);

        if ($existing !== null) {
            return $this->replay($envelope->operationId, $existing, $hash);
        }

        try {
            $applied = DB::transaction(function () use ($userId, $envelope, $hash, $execute): OperationApplyResult {
                $result = $execute();

                $this->ledger->record(
                    $userId,
                    $envelope->operationId,
                    $envelope->operationType->value,
                    $envelope->entityType,
                    $result->entityId ?? $this->extractEntityId($result->result),
                    $hash,
                    'applied',
                    $this->boundedResult($result),
                );

                return $result;
            });

            return OperationOutcome::applied($envelope->operationId, $applied);
        } catch (TaskVersionConflict|NoteVersionConflict $conflict) {
            $this->recordConflict($userId, $envelope, $hash, $conflict->getMessage());

            return OperationOutcome::conflict(
                $envelope->operationId,
                OperationOutcome::CODE_VERSION_CONFLICT,
                $conflict->getMessage(),
            );
        }
    }

    /**
     * Reconcile a bounded batch (ADR-017 §2.7/§2.8): sequential, FIFO,
     * transaction-per-operation; one outcome per operation; a bad operation
     * never corrupts its neighbours.
     *
     * @param  array<int, OperationEnvelope>  $envelopes
     * @return array<int, OperationOutcome>
     */
    public function reconcileBatch(int $userId, array $envelopes): array
    {
        $outcomes = [];

        foreach ($envelopes as $envelope) {
            try {
                $outcome = $this->reconcileOne($userId, $envelope, function () use ($userId, $envelope): OperationApplyResult {
                    return $this->dispatch($userId, $envelope);
                });

                $outcomes[] = $outcome;
            } catch (OfflineOperationReused $reused) {
                $outcomes[] = OperationOutcome::rejected(
                    $envelope->operationId,
                    OperationOutcome::CODE_REUSED,
                    $reused->getMessage(),
                );
            } catch (InvalidArgumentException $invalid) {
                $code = $this->codeForInvalid($invalid->getMessage());
                $this->recordRejected($userId, $envelope, $code, $invalid->getMessage());

                $outcomes[] = OperationOutcome::rejected($envelope->operationId, $code, $invalid->getMessage());
            } catch (Throwable $error) {
                $this->recordRejected($userId, $envelope, OperationOutcome::CODE_VALIDATION, $error->getMessage());

                $outcomes[] = OperationOutcome::rejected($envelope->operationId, OperationOutcome::CODE_VALIDATION, $error->getMessage());
            }
        }

        return $outcomes;
    }

    /**
     * Closed switch over the allowlist — dispatches to the canonical use
     * cases. Never dynamic dispatch from client strings.
     */
    public function dispatch(int $userId, OperationEnvelope $envelope): OperationApplyResult
    {
        $payload = $envelope->payload;

        return match ($envelope->operationType->value) {
            OperationType::TASK_CREATE => new OperationApplyResult(
                ['task' => $this->createTask->__invoke(
                    $userId,
                    (string) ($payload['title'] ?? ''),
                    $payload['description'] ?? null,
                    isset($payload['program_id']) ? (int) $payload['program_id'] : null,
                    isset($payload['goal_id']) ? (int) $payload['goal_id'] : null,
                    isset($payload['milestone_id']) ? (int) $payload['milestone_id'] : null,
                    (int) ($payload['priority_tier'] ?? 3),
                    isset($payload['estimated_minutes']) ? (int) $payload['estimated_minutes'] : null,
                    isset($payload['due_at']) ? CarbonImmutable::parse((string) $payload['due_at']) : null,
                    $payload['workspace_id'] ?? $envelope->workspaceId,
                    (bool) ($payload['is_sacred_anchor'] ?? false),
                )->toArray()],
                null,
                null,
            ),

            OperationType::TASK_UPDATE => new OperationApplyResult(
                ['task' => $this->updateTask->__invoke(
                    $userId,
                    (int) $envelope->entityId,
                    $payload['title'] ?? null,
                    $payload['description'] ?? null,
                    isset($payload['program_id']) ? (int) $payload['program_id'] : null,
                    isset($payload['goal_id']) ? (int) $payload['goal_id'] : null,
                    isset($payload['milestone_id']) ? (int) $payload['milestone_id'] : null,
                    isset($payload['priority_tier']) ? (int) $payload['priority_tier'] : null,
                    isset($payload['estimated_minutes']) ? (int) $payload['estimated_minutes'] : null,
                    isset($payload['due_at']) ? CarbonImmutable::parse((string) $payload['due_at']) : null,
                    $payload['is_sacred_anchor'] ?? null,
                    $envelope->baseVersion,
                )->toArray()],
                null,
                null,
            ),

            OperationType::TASK_STATUS => $this->dispatchTaskStatus($userId, $envelope),

            OperationType::SUBTASK_CREATE => new OperationApplyResult(
                ['subtask' => $this->addSubtask->__invoke(
                    $userId,
                    (int) $envelope->entityId,
                    (string) ($payload['title'] ?? ''),
                    $payload['notes'] ?? null,
                    isset($payload['sequence']) ? (int) $payload['sequence'] : null,
                )->toArray()],
                null,
                null,
            ),

            OperationType::NOTE_CREATE => new OperationApplyResult(
                ['note' => $this->createNote->__invoke(
                    $userId,
                    (string) ($payload['title'] ?? ''),
                    $payload['document_json'] ?? null,
                    $payload['markdown_cache'] ?? null,
                    $payload['plain_text_cache'] ?? null,
                    // ADR-017 §2.13/§2.20 — workspace ownership is verified
                    // through the SAME resolver the online endpoint uses.
                    $this->workspaceContext->forWrite($userId, $payload['workspace_id'] ?? $envelope->workspaceId),
                )->toArray()],
                null,
                null,
            ),

            OperationType::NOTE_UPDATE => new OperationApplyResult(
                ['note' => $this->updateNote->__invoke(
                    $userId,
                    (int) $envelope->entityId,
                    (int) ($payload['base_version'] ?? $envelope->baseVersion),
                    $payload['title'] ?? null,
                    $payload['document_json'] ?? null,
                    $payload['markdown_cache'] ?? null,
                    $payload['plain_text_cache'] ?? null,
                )->toArray()],
                null,
                null,
            ),

            default => throw new InvalidArgumentException('Unsupported offline operation type.'),
        };
    }

    /**
     * Semantic idempotency (ADR-017 §2.9): a task already in the target status
     * is an applied no-op (offline completion replays safely).
     */
    private function dispatchTaskStatus(int $userId, OperationEnvelope $envelope): OperationApplyResult
    {
        $target = new TaskStatus((string) ($envelope->payload['status'] ?? ''));
        $task = $this->tasks->findForUser($userId, (int) $envelope->entityId);

        if ($task === null) {
            throw new InvalidArgumentException('Task not found.');
        }

        if ($task->status->equals($target)) {
            return new OperationApplyResult(['task' => $task->toArray()], null, null);
        }

        $updated = $this->setTaskStatus->__invoke($userId, $task->id, $target);

        return new OperationApplyResult(['task' => $updated->toArray()], null, null);
    }

    private function replay(string $operationId, OfflineOperationRecord $existing, string $hash): OperationOutcome
    {
        if (! hash_equals($existing->payloadHash, $hash)) {
            throw new OfflineOperationReused($existing->operationId);
        }

        $result = $existing->result ?? ['entity_id' => $existing->entityId, 'version' => null];

        return OperationOutcome::replayed(
            $operationId,
            new OperationApplyResult($result, $existing->entityId, isset($result['version']) ? (int) $result['version'] : null),
        );
    }

    private function recordConflict(int $userId, OperationEnvelope $envelope, string $hash, string $message): void
    {
        DB::transaction(function () use ($userId, $envelope, $hash, $message): void {
            $this->ledger->record(
                $userId,
                $envelope->operationId,
                $envelope->operationType->value,
                $envelope->entityType,
                $envelope->entityId,
                $hash,
                'conflict',
                ['entity_id' => $envelope->entityId, 'version' => null, 'code' => OperationOutcome::CODE_VERSION_CONFLICT, 'error' => $message],
            );
        });
    }

    private function recordRejected(int $userId, OperationEnvelope $envelope, string $code, string $message): void
    {
        DB::transaction(function () use ($userId, $envelope, $code, $message): void {
            $this->ledger->record(
                $userId,
                $envelope->operationId,
                $envelope->operationType->value,
                $envelope->entityType,
                $envelope->entityId,
                self::canonicalHash($envelope->payload),
                'rejected',
                ['entity_id' => $envelope->entityId, 'version' => null, 'code' => $code, 'error' => $message],
            );
        });
    }

    private function codeForInvalid(string $message): string
    {
        if (str_contains($message, 'not found')) {
            return OperationOutcome::CODE_NOT_FOUND;
        }
        if (str_contains($message, 'workspace') || str_contains($message, 'Workspace')) {
            return OperationOutcome::CODE_WORKSPACE;
        }
        if (str_contains($message, 'entitlement') || str_contains($message, 'Entitlement')) {
            return OperationOutcome::CODE_ENTITLEMENT;
        }

        return OperationOutcome::CODE_VALIDATION;
    }

    /**
     * Canonical payload hash: SHA-256 of key-sorted canonical JSON.
     */
    public static function canonicalHash(array $payload): string
    {
        return hash('sha256', json_encode(self::sortRecursive($payload), JSON_UNESCAPED_SLASHES));
    }

    private static function sortRecursive(mixed $value): mixed
    {
        if (is_array($value)) {
            $sorted = [];
            foreach ($value as $key => $item) {
                $sorted[(string) $key] = self::sortRecursive($item);
            }
            ksort($sorted, SORT_STRING);

            return $sorted;
        }

        return $value;
    }

    /**
     * Bounded ledger result (ADR-017 §2.20): entity id + version only, never
     * full note/task content.
     *
     * @return array<string, mixed>
     */
    private function boundedResult(OperationApplyResult $result): array
    {
        return [
            'entity_id' => $result->entityId ?? $this->extractEntityId($result->result),
            'version' => $result->version ?? $this->extractVersion($result->result),
        ];
    }

    private function extractEntityId(array $result): ?int
    {
        foreach (['task', 'note', 'subtask'] as $key) {
            $entity = $result[$key] ?? null;
            if (is_array($entity) && isset($entity['id'])) {
                return (int) $entity['id'];
            }
        }

        return null;
    }

    private function extractVersion(array $result): ?int
    {
        foreach (['task', 'note', 'subtask'] as $key) {
            $entity = $result[$key] ?? null;
            if (is_array($entity) && isset($entity['version'])) {
                return (int) $entity['version'];
            }
        }

        return null;
    }
}
