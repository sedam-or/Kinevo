<?php

namespace App\Domain\Ai;

use App\Domain\Ai\ValueObjects\AiProposalType;
use InvalidArgumentException;

/**
 * Versioned schema registry for structured AI proposals (SRS FR-61, §13.3).
 * Schemas are plain rule arrays consumed by AiSchemaRules. Bumping a schema
 * version is a breaking contract change and requires a migration note.
 */
final class AiSchemaRegistry
{
    /** @return array{version: int, fields: array<string, mixed>} */
    public function schemaFor(AiProposalType $type): array
    {
        return match ($type->value) {
            AiProposalType::GOAL_BREAKDOWN => $this->goalBreakdown(),
            AiProposalType::MILESTONE => $this->milestone(),
            AiProposalType::TASK_EXTRACTION => $this->taskExtraction(),
            AiProposalType::CANVAS => $this->canvas(),
            AiProposalType::SUMMARY => $this->summary(),
            default => throw new InvalidArgumentException("Unsupported AI proposal type: {$type->value}"),
        };
    }

    public function versionFor(AiProposalType $type): int
    {
        return $this->schemaFor($type)['version'];
    }

    private function goalBreakdown(): array
    {
        return [
            'version' => 1,
            'fields' => [
                'type' => ['required' => true, 'type' => 'string', 'enum' => ['goal_breakdown_proposal']],
                'goal_id' => ['required' => true, 'type' => 'int', 'min' => 1],
                'milestones' => [
                    'required' => true,
                    'type' => 'array',
                    'min_items' => 1,
                    'items' => [
                        'title' => ['required' => true, 'type' => 'string', 'max_length' => 200],
                        'target_date' => ['type' => 'date'],
                        'estimated_minutes' => ['type' => 'int', 'min' => 0],
                    ],
                ],
            ],
        ];
    }

    private function milestone(): array
    {
        return [
            'version' => 1,
            'fields' => [
                'type' => ['required' => true, 'type' => 'string', 'enum' => ['milestone_proposal']],
                'goal_id' => ['required' => true, 'type' => 'int', 'min' => 1],
                'title' => ['required' => true, 'type' => 'string', 'max_length' => 200],
                'target_date' => ['type' => 'date'],
                'estimated_minutes' => ['type' => 'int', 'min' => 0],
            ],
        ];
    }

    private function taskExtraction(): array
    {
        return [
            'version' => 1,
            'fields' => [
                'type' => ['required' => true, 'type' => 'string', 'enum' => ['task_extraction_proposal']],
                'tasks' => [
                    'required' => true,
                    'type' => 'array',
                    'min_items' => 1,
                    'items' => [
                        'title' => ['required' => true, 'type' => 'string', 'max_length' => 200],
                        'due_at' => ['type' => 'date'],
                        'estimated_minutes' => ['type' => 'int', 'min' => 0],
                    ],
                ],
            ],
        ];
    }

    private function canvas(): array
    {
        return [
            'version' => 1,
            'fields' => [
                'type' => ['required' => true, 'type' => 'string', 'enum' => ['canvas_proposal']],
                'title' => ['required' => true, 'type' => 'string', 'max_length' => 200],
                'sections' => [
                    'required' => true,
                    'type' => 'array',
                    'min_items' => 1,
                    'items' => [
                        'name' => ['required' => true, 'type' => 'string', 'max_length' => 100],
                        'description' => ['type' => 'string', 'max_length' => 2000],
                    ],
                ],
            ],
        ];
    }

    private function summary(): array
    {
        return [
            'version' => 1,
            'fields' => [
                'type' => ['required' => true, 'type' => 'string', 'enum' => ['summary_proposal']],
                'summary' => ['required' => true, 'type' => 'string', 'max_length' => 8000],
                'key_points' => [
                    'required' => true,
                    'type' => 'array',
                    'min_items' => 1,
                    'items' => ['type' => 'string', 'max_length' => 500],
                ],
            ],
        ];
    }
}
