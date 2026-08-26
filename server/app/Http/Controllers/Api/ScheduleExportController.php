<?php

namespace App\Http\Controllers\Api;

use App\Application\Exports\ExportScheduleIcsUseCase;
use App\Application\Saas\EntitlementService;
use App\Domain\Saas\Exceptions\EntitlementLimitException;
use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Schedule export (FR-30 / TASK-143): download the selected schedule range as
 * a valid iCalendar (.ics) document. Requires an authenticated user context
 * (NFR-03) and exposes only fields designated as exportable.
 */
final class ScheduleExportController extends Controller
{
    public function __construct(
        private readonly ExportScheduleIcsUseCase $exportIcs,
    ) {}

    public function ical(Request $request): Response
    {
        // TASK-P23-007 — export entitlement gate.
        $check = app(EntitlementService::class);
        try {
            $check->assertCan($request->user()->id, 'export', 'Export is available on paid plans.');
        } catch (EntitlementLimitException $e) {
            return response($e->toResponse()['error'], 403)
                ->header('Content-Type', 'text/plain');
        }

        $validator = Validator::make($request->query(), [
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        try {
            $content = $this->exportIcs->__invoke(
                $request->user()->id,
                CarbonImmutable::parse($data['from']),
                CarbonImmutable::parse($data['to']),
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response($content, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="kinevo-schedule.ics"',
        ]);
    }
}
