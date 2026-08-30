<?php

namespace App\Domain\Scheduling\Resolution;

use InvalidArgumentException;

/**
 * Diagnostic for a recurring Hard Landscape source whose recurrence string
 * could not be parsed (ADR-015 failure semantics: degrade to the base single
 * occurrence — never silently drop — and surface a visible warning).
 *
 * The warning is domain data attached to a resolution result; rendering it as
 * an API `recurrence_warning` payload is the application layer's concern
 * (ES-IMPL-02).
 */
final class RecurrenceResolutionWarning
{
    public function __construct(
        public readonly int $sourceEventId,
        public readonly string $recurrence,
        public readonly string $reason,
    ) {
        if ($this->sourceEventId <= 0) {
            throw new InvalidArgumentException('Warning source event id must be positive.');
        }

        if (trim($this->recurrence) === '') {
            throw new InvalidArgumentException('Warning recurrence must not be empty.');
        }

        if (trim($this->reason) === '') {
            throw new InvalidArgumentException('Warning reason must not be empty.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'source_event_id' => $this->sourceEventId,
            'recurrence' => $this->recurrence,
            'reason' => $this->reason,
        ];
    }
}
