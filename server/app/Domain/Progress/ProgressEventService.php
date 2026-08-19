<?php

namespace App\Domain\Progress;

use App\Domain\Progress\ValueObjects\ProgressEventType;

/**
 * Semantic factories for meaningful progress events (SRS §6.8, domain-model
 * ProgressEventService). Maps a domain mutation to its progress event; the
 * operation id references the exact domain change that created the event
 * (§12.5). Informational only — never mutates domain state.
 */
final class ProgressEventService
{
    public function taskCompleted(int $userId, int $taskId, string $title, ?string $operationId = null): ProgressEvent
    {
        return ProgressEvent::create(
            $userId,
            ProgressEventType::taskCompleted(),
            'task',
            $taskId,
            $title,
            operationId: $operationId ?? "task:completed:{$taskId}",
            payload: ['completed' => true],
        );
    }

    public function milestoneAdvanced(int $userId, int $milestoneId, string $title, ?string $operationId = null): ProgressEvent
    {
        return ProgressEvent::create(
            $userId,
            ProgressEventType::milestoneAdvanced(),
            'milestone',
            $milestoneId,
            $title,
            operationId: $operationId ?? "milestone:advanced:{$milestoneId}",
            payload: ['status' => 'active'],
        );
    }

    public function milestoneCompleted(int $userId, int $milestoneId, string $title, ?string $operationId = null): ProgressEvent
    {
        return ProgressEvent::create(
            $userId,
            ProgressEventType::milestoneCompleted(),
            'milestone',
            $milestoneId,
            $title,
            operationId: $operationId ?? "milestone:completed:{$milestoneId}",
            payload: ['completed' => true],
        );
    }
}
