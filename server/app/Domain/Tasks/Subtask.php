<?php

namespace App\Domain\Tasks;

use InvalidArgumentException;

/**
 * Subtask — checklist child of exactly one Task (FR-09). No deeper hierarchy (FR-45).
 * Immutable value semantics: state changes return a new instance.
 */
final class Subtask
{
    public function __construct(
        public readonly int $id,
        public readonly int $userId,
        public readonly int $taskId,
        public readonly string $title,
        public readonly ?string $notes,
        public readonly int $sequence,
        public readonly bool $completed,
        public readonly int $version,
    ) {}

    public static function create(
        int $userId,
        int $taskId,
        string $title,
        ?string $notes = null,
        int $sequence = 0,
    ): self {
        if (trim($title) === '') {
            throw new InvalidArgumentException('Subtask title is required.');
        }

        return new self(
            0,
            $userId,
            $taskId,
            trim($title),
            $notes,
            $sequence,
            false,
            1,
        );
    }

    public function withId(int $id): self
    {
        return $this->reborn(['id' => $id]);
    }

    public function withTitle(string $title): self
    {
        if (trim($title) === '') {
            throw new InvalidArgumentException('Subtask title is required.');
        }

        return $this->reborn(['title' => trim($title)]);
    }

    public function withNotes(?string $notes): self
    {
        return $this->reborn(['notes' => $notes]);
    }

    public function withSequence(int $sequence): self
    {
        return $this->reborn(['sequence' => $sequence]);
    }

    public function withCompleted(bool $completed): self
    {
        if ($completed === $this->completed) {
            return $this;
        }

        return $this->reborn(['completed' => $completed, 'version' => $this->version + 1]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->userId,
            'task_id' => $this->taskId,
            'title' => $this->title,
            'notes' => $this->notes,
            'sequence' => $this->sequence,
            'completed' => $this->completed,
            'version' => $this->version,
        ];
    }

    /**
     * @param  array<string, mixed>  $props
     */
    private function reborn(array $props): self
    {
        $merged = array_merge([
            'id' => $this->id,
            'userId' => $this->userId,
            'taskId' => $this->taskId,
            'title' => $this->title,
            'notes' => $this->notes,
            'sequence' => $this->sequence,
            'completed' => $this->completed,
            'version' => $this->version,
        ], $props);

        return new self(
            $merged['id'],
            $merged['userId'],
            $merged['taskId'],
            $merged['title'],
            $merged['notes'],
            $merged['sequence'],
            $merged['completed'],
            $merged['version'],
        );
    }
}
