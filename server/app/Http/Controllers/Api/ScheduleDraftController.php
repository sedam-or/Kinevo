<?php

namespace App\Http\Controllers\Api;

use App\Application\Scheduling\ApplyRescheduleProposalUseCase;
use App\Application\Scheduling\ApplyScheduleDraftUseCase;
use App\Application\Scheduling\AssembleScheduleInput;
use App\Application\Scheduling\DiscardScheduleDraftUseCase;
use App\Application\Scheduling\SyncNowResult as SyncNowUseCaseResult;
use App\Application\Scheduling\SyncNowUseCase;
use App\Domain\Scheduling\Contracts\ScheduleAssignmentRepository;
use App\Domain\Scheduling\Contracts\ScheduleDraftRepository;
use App\Domain\Scheduling\DraftAssignment;
use App\Domain\Scheduling\DynamicRescheduler;
use App\Domain\Scheduling\HardConstraintEngine;
use App\Domain\Scheduling\RescheduleProposal;
use App\Domain\Scheduling\ScheduleAssignment;
use App\Domain\Scheduling\ScheduleAssignmentLockedConflict;
use App\Domain\Scheduling\ScheduleDraft;
use App\Domain\Scheduling\ScheduleDraftGenerator;
use App\Domain\Scheduling\ScheduleDraftRecord;
use App\Domain\Scheduling\ScheduleState;
use App\Domain\Scheduling\ScheduleVersionConflict;
use App\Domain\Scheduling\SlotCalculator;
use App\Domain\Scheduling\TaskMove;
use App\Domain\Scheduling\TaskRankingEngine;
use App\Domain\Scheduling\UnassignedTask;
use App\Domain\Scheduling\ValueObjects\ScheduleVersion;
use App\Domain\Scheduling\ValueObjects\TimeRange;
use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

/**
 * Schedule Draft / Dynamic Rescheduler / Sync Now (FR-27/FR-28; ADR-016).
 * Generates a deterministic draft, reschedule proposal, or sync diff for an
 * owner-scoped date range, and explicitly applies an approved draft/proposal
 * at the next schedule version. Generating/previewing never mutates the
 * schedule; only the explicit apply endpoints persist it.
 */
final class ScheduleDraftController extends Controller
{
    private readonly ScheduleDraftGenerator $generator;

    private readonly DynamicRescheduler $rescheduler;

    public function __construct(
        private readonly ApplyScheduleDraftUseCase $applyDraft,
        private readonly ApplyRescheduleProposalUseCase $applyProposal,
        private readonly ScheduleDraftRepository $draftRepository,
        private readonly AssembleScheduleInput $assembleInput,
        private readonly SyncNowUseCase $syncNow,
        private readonly DiscardScheduleDraftUseCase $discardDraft,
        private readonly ScheduleAssignmentRepository $assignments,
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
            $assembled = ($this->assembleInput)($userId, $from, $to);
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
            'draft_id' => ['sometimes', 'integer', 'min:1'],
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

            $result = $this->applyDraft->__invoke(
                $userId,
                $draft,
                $baseVersion,
                isset($data['draft_id']) ? (int) $data['draft_id'] : null,
            );
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
            $assembled = ($this->assembleInput)($userId, $from, $to);
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
     * ADR-016 §2.2 — manual Sync Now: deterministic diff of the accepted
     * schedule against the current Effective Landscape. Read-only; the diff
     * is applied only via the explicit reschedule-apply endpoint.
     */
    public function sync(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date', 'after_or_equal:from'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $userId = $request->user()->id;
        $validated = $validator->validated();

        try {
            $result = ($this->syncNow)(
                $userId,
                isset($validated['from']) ? CarbonImmutable::parse($validated['from']) : null,
                isset($validated['to']) ? CarbonImmutable::parse($validated['to']) : null,
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json([
            'status' => $result->status,
            'needs_review' => $result->needsReview,
            'base_version' => $result->baseVersion->value,
            'new_version' => $result->newVersion,
            'proposal' => $result->status === SyncNowUseCaseResult::PROPOSAL ? [
                'base_version' => $result->baseVersion->value,
                'new_version' => $result->newVersion,
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
                    $result->moves,
                ),
                'conflict_task_ids' => array_values($result->conflictTaskIds),
            ] : null,
        ]);
    }

    /**
     * ADR-016 §2.5 — pending persisted drafts (weekly trigger) for review.
     */
    public function drafts(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $currentVersion = $this->assignments->currentScheduleVersion($userId)->value;

        $records = array_map(
            fn (ScheduleDraftRecord $record) => $record->toArray($currentVersion),
            $this->draftRepository->listPendingForUser($userId),
        );

        return response()->json(['drafts' => $records, 'base_version' => $currentVersion]);
    }

    /**
     * ADR-016 §2.5 — discard a pending draft. Never mutates the schedule.
     */
    public function discardDraft(Request $request, int $draftId): JsonResponse
    {
        try {
            ($this->discardDraft)($request->user()->id, $draftId);
        } catch (InvalidArgumentException $e) {
            $status = $e->getMessage() === 'Draft not found.' ? 404 : 422;

            return response()->json(['error' => $e->getMessage()], $status);
        }

        return response()->json(['discarded' => true]);
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
