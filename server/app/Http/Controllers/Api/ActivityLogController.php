<?php

namespace App\Http\Controllers\Api;

use App\Application\ActivityLogs\ExportActivityLogsUseCase;
use App\Application\ActivityLogs\ListActivityLogsUseCase;
use App\Application\Saas\EntitlementService;
use App\Domain\Saas\Exceptions\EntitlementLimitException;
use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

final class ActivityLogController extends Controller
{
    public function __construct(
        private readonly ListActivityLogsUseCase $listLogs,
        private readonly ExportActivityLogsUseCase $exportLogs,
        private readonly EntitlementService $entitlements,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->query(), [
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'event_type' => ['nullable', 'string', 'in:task_completed,task_continued,subtask_completed,task_started,task_abandoned,mini_pause,emergency_pause,break_start,break_end,boost_start,boost_end'],
            'limit' => ['nullable', 'integer', 'between:1,200'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        $logs = $this->listLogs->__invoke(
            $request->user()->id,
            isset($data['from']) ? CarbonImmutable::parse($data['from']) : null,
            isset($data['to']) ? CarbonImmutable::parse($data['to']) : null,
            $data['event_type'] ?? null,
            $data['limit'] ?? 50,
        );

        return response()->json([
            'logs' => array_map(static fn ($log) => $log->toArray(), $logs),
        ]);
    }

    public function export(Request $request): JsonResponse
    {
        // TASK-P23-007 — export is an entitled capability.
        try {
            $this->entitlements->assertCan($request->user()->id, 'export', 'Export is available on paid plans.');
        } catch (EntitlementLimitException $e) {
            return response()->json($e->toResponse(), 403);
        }

        $validator = Validator::make($request->all(), [
            'format' => ['required', 'string', 'in:json,csv'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        try {
            $export = $this->exportLogs->__invoke(
                $request->user()->id,
                $data['format'],
                isset($data['from']) ? CarbonImmutable::parse($data['from']) : null,
                isset($data['to']) ? CarbonImmutable::parse($data['to']) : null,
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json($export);
    }
}
