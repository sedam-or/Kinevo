<?php

namespace App\Http\Controllers\Api;

use App\Application\Scheduling\ApplyRescheduleProposalUseCase;
use App\Application\Scheduling\ApplyScheduleDraftUseCase;
use App\Domain\Goals\Contracts\GoalRepository;
use App\Domain\Milestones\Contracts\MilestoneRepository;
use App\Domain\Scheduling\Contracts\HardLandscapeRepository;
use App\Domain\Scheduling\Contracts\ScheduleAssignmentRepository;
use App\Domain\Scheduling\DraftAssignment;
use App\Domain\Scheduling\DraftInput;
use App\Domain\Scheduling\DynamicRescheduler;
use App\Domain\Scheduling\HardConstraintEngine;
use App\Domain\Scheduling\HardLandscapeEvent;
use App\Domain\Scheduling\RescheduleProposal;
use App\Domain\Scheduling\ScheduleAssignment;
use App\Domain\Scheduling\ScheduleAssignmentLockedConflict;
use App\Domain\Scheduling\ScheduleDraft;
use App\Domain\Scheduling\ScheduleDraftGenerator;
use App\Domain\Scheduling\ScheduleState;
use App\Domain\Scheduling\ScheduleTask;
use App\Domain\Scheduling\ScheduleVersionConflict;
use App\Domain\Scheduling\SlotCalculator;
use App\Domain\Scheduling\TaskMove;
use App\Domain\Scheduling\TaskRankingEngine;
use App\Domain\Scheduling\UnassignedTask;
use App\Domain\Scheduling\ValueObjects\PriorityTier;
use App\Domain\Scheduling\ValueObjects\ScheduleVersion;
use App\Domain\Scheduling\ValueObjects\TimeRange;
use App\Domain\Tasks\Contracts\TaskRepository;
use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

/**
 * Schedule Draft / Dynamic Rescheduler (FR-27/FR-28; SRS §7.1). Generates a
 * deterministic draft or reschedule proposal for an owner-scoped date range,
 * and explicitly applies an approved draft/proposal at the next schedule
 * version. Generating/previewing never mutates the schedule.
 */
final class ScheduleDraftController extends Controller
{
    private readonly ScheduleDraftGenerator $generator;

    private readonly DynamicRescheduler $rescheduler;

    public function __construct(
        private readonly ApplyScheduleDraftUseCase $applyDraft,
        private readonly ApplyRescheduleProposalUseCase $applyProposal,
        private readonly ScheduleAssignmentRepository $assignments,
        private readonly HardLandscapeRepository $hardLandscape,
        private readonly TaskRepository $tasks,
        private readonly GoalRepository $goals,
        private readonly MilestoneRepository $milestones,
    ) {
        $this->generator = new ScheduleDraftGenerator(
            new SlotCalculator,
            HardConstraintEngine::default(),
            TaskRankingEngine::default(),
        );
        $this->rescheduler = new DynamicRescheduler(
            $this->generator,
            HardConstraintEngine::default(),
        );
    }

    public function draft(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $userId = $request->user()->id;
        $validated = $validator->validated();
        $from = CarbonImmutable::parse($validated['from']);
        $to = CarbonImmutable::parse($validated['to']);

        try {
            $assembled = $this->assemble($userId, $from, $to);
            $draft = $this->generator->generate($assembled['input']);
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json([
            'draft' => [
                'assignments' => array_map(
                    static fn (DraftAssignment $assignment) => [
                        'task_id' => $assignment->taskId,
                        'title' => $assignment->title,
                        'start' => $assignment->slot->start->toISOString(),
                        'end' => $assignment->slot->end->toISOString(),
                    ],
                    $draft->assignments,
                ),
                'unassigned' => array_map(
                    static fn (UnassignedTask $task) => [
                        'task_id' => $task->taskId,
                        'title' => $task->title,
                        'reason' => $task->reason,
                    ],
                    $draft->unassigned,
                ),
            ],
            'base_version' => $assembled['base_version']->value,
        ]);
    }

    public function apply(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'draft' => ['required', 'array'],
            'draft.assignments' => ['present', 'array'],
            'draft.assignments.*.task_id' => ['required', 'string'],
            'draft.assignments.*.title' => ['required', 'string'],
            'draft.assignments.*.start' => ['required', 'date'],
            'draft.assignments.*.end' => ['required', 'date'],
            'draft.unassigned' => ['present', 'array'],
            'draft.unassigned.*.task_id' => ['required', 'string'],
            'draft.unassigned.*.title' => ['required', 'string'],
            'draft.unassigned.*.reason' => ['required', 'string'],
            'base_version' => ['required', 'integer', 'min:1'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $userId = $request->user()->id;
        $data = $validator->validated();
        $draftData = (array) $data['draft'];
        $baseVersion = new ScheduleVersion((int) $data['base_version']);

        try {
            $draft = new ScheduleDraft(
                array_map(
                    static fn (array $item) => new DraftAssignment(
                        (string) $item['task_id'],
                        (string) $item['title'],
                        TimeRange::from((string) $item['start'], (string) $item['end']),
                    ),
                    (array) $draftData['assignments'],
                ),
                array_map(
                    static fn (array $item) => new UnassignedTask(
                        (string) $item['task_id'],
                        (string) $item['title'],
                        (string) $item['reason'],
                    ),
                    (array) $draftData['unassigned'],
                ),
            );

            $result = $this->applyDraft->__invoke($userId, $draft, $baseVersion);
        } catch (ScheduleVersionConflict $e) {
            return response()->json(['error' => $e->getMessage()], 409);
        } catch (ScheduleAssignmentLockedConflict $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (InvalidArgumentException $e) {
            if (str_contains($e->getMessage(), 'Task not found')) {
                return response()->json(['error' => $e->getMessage()], 404);
            }

            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json([
            'version' => $result->version->value,
            'applied' => $result->applied,
            'assignments' => array_map(
                static fn (ScheduleAssignment $assignment) => $assignment->toArray(),
                $result->assignments,
            ),
        ]);
    }

    public function reschedule(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $userId = $request->user()->id;
        $validated = $validator->validated();
        $from = CarbonImmutable::parse($validated['from']);
        $to = CarbonImmutable::parse($validated['to']);

        try {
            $assembled = $this->assemble($userId, $from, $to);
            $state = new ScheduleState($assembled['base_version'], $assembled['slots_by_task']);
            $proposal = $this->rescheduler->propose($state, $assembled['input']);
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json([
            'proposal' => [
                'base_version' => $proposal->baseVersion->value,
                'new_version' => $proposal->newVersion->value,
                'moves' => array_map(
                    static fn (TaskMove $move) => [
                        'task_id' => $move->taskId,
                        'title' => $move->title,
                        'from' => $move->fromSlot !== null ? [
                            'start' => $move->fromSlot->start->toISOString(),
                            'end' => $move->fromSlot->end->toISOString(),
                        ] : null,
                        'to' => [
                            'start' => $move->toSlot->start->toISOString(),
                            'end' => $move->toSlot->end->toISOString(),
                        ],
                    ],
                    $proposal->moves,
                ),
                'conflict_task_ids' => array_values($proposal->conflictTaskIds),
            ],
            'has_changes' => $proposal->hasChanges(),
        ]);
    }

    public function rescheduleApply(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'proposal' => ['required', 'array'],
            'proposal.base_version' => ['required', 'integer', 'min:1'],
            'proposal.new_version' => ['required', 'integer', 'min:1'],
            'proposal.moves' => ['present', 'array'],
            'proposal.moves.*.task_id' => ['required', 'string'],
            'proposal.moves.*.title' => ['required', 'string'],
            'proposal.moves.*.from' => ['nullable'],
            'proposal.moves.*.to' => ['required', 'array'],
            'proposal.moves.*.to.start' => ['required', 'date'],
            'proposal.moves.*.to.end' => ['required', 'date'],
            'proposal.conflict_task_ids' => ['present', 'array'],
            'proposal.conflict_task_ids.*' => ['string'],
            'base_version' => ['required', 'integer', 'min:1'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $userId = $request->user()->id;
        $data = $validator->validated();
        $proposalData = (array) $data['proposal'];
        $baseVersion = new ScheduleVersion((int) $data['base_version']);

        try {
            $proposal = new RescheduleProposal(
                $baseVersion,
                new ScheduleVersion((int) $proposalData['new_version']),
                array_map(
                    fn (array $move) => $this->moveFromPayload($move),
                    (array) $proposalData['moves'],
                ),
                array_map('strval', (array) $proposalData['conflict_task_ids']),
            );

            $result = $this->applyProposal->__invoke($userId, $proposal);
        } catch (ScheduleVersionConflict $e) {
            return response()->json(['error' => $e->getMessage()], 409);
        } catch (ScheduleAssignmentLockedConflict $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (InvalidArgumentException $e) {
            if (str_contains($e->getMessage(), 'Task not found')) {
                return response()->json(['error' => $e->getMessage()], 404);
            }

            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json([
            'version' => $result->version->value,
            'applied' => $result->applied,
            'conflict_task_ids' => array_values($result->conflictTaskIds),
        ]);
    }

    /**
     * Assemble a DraftInput (and the current in-range schedule snapshot) for a
     * user from the persisted repositories. Terminal tasks are ineligible.
     *
     * @return array{
     *     input: DraftInput,
     *     base_version: ScheduleVersion,
     *     slots_by_task: array<string, TimeRange>,
     * }
     */
    private function assemble(int $userId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $horizon = new TimeRange($from->startOfDay(), $to->endOfDay());
        $baseVersion = $this->assignments->currentScheduleVersion($userId);

        $hardLandscape = array_map(
            static fn (HardLandscapeEvent $event) => $event->timeRange(),
            $this->hardLandscape->listForUserInRange($userId, $from, $to->endOfDay()),
        );

        /** @var array<string, TimeRange> $slotsByTask */
        $slotsByTask = [];
        foreach ($this->assignments->listForUserInRange($userId, $from, $to->endOfDay()) as $assignment) {
            $slotsByTask[(string) $assignment->taskId] = $assignment->timeRange();
        }

        $tasks = [];
        foreach ($this->tasks->listForUser($userId) as $task) {
            if ($task->status->isTerminal()) {
                continue;
            }

            $tasks[] = new ScheduleTask(
                taskId: (string) $task->id,
                title: $task->title,
                durationMinutes: $task->estimatedMinutes ?? 45,
                priorityTier: new PriorityTier($task->priorityTier),
                goalDeadline: $this->goalDeadline($userId, $task->goalId),
                milestoneDeadline: $this->milestoneDeadline($userId, $task->milestoneId),
                taskDeadline: $task->dueAt,
                progress: $task->progress,
                isLocked: false,
                isSacredAnchor: false,
                existingSlot: $slotsByTask[(string) $task->id] ?? null,
            );
        }

        return [
            'input' => new DraftInput(
                $horizon,
                hardLandscape: $hardLandscape,
                existingAssignments: array_values($slotsByTask),
                tasks: $tasks,
            ),
            'base_version' => $baseVersion,
            'slots_by_task' => $slotsByTask,
        ];
    }

    private function goalDeadline(int $userId, ?int $goalId): ?CarbonImmutable
    {
        if ($goalId === null) {
            return null;
        }

        return $this->goals->findForUser($userId, $goalId)?->targetDate;
    }

    private function milestoneDeadline(int $userId, ?int $milestoneId): ?CarbonImmutable
    {
        if ($milestoneId === null) {
            return null;
        }

        return $this->milestones->findForUser($userId, $milestoneId)?->targetDate;
    }

    private function moveFromPayload(array $move): TaskMove
    {
        $from = null;
        if ($move['from'] !== null) {
            $fromData = (array) $move['from'];
            $from = TimeRange::from(
                (string) ($fromData['start'] ?? ''),
                (string) ($fromData['end'] ?? ''),
            );
        }

        $toData = (array) $move['to'];

        return new TaskMove(
            (string) $move['task_id'],
            (string) $move['title'],
            $from,
            TimeRange::from((string) $toData['start'], (string) $toData['end']),
        );
    }
}
