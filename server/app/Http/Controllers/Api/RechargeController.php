<?php

namespace App\Http\Controllers\Api;

use App\Application\Recharge\AbandonRechargeUseCase;
use App\Application\Recharge\CompleteRechargeUseCase;
use App\Application\Recharge\GetRechargeStatusUseCase;
use App\Application\Recharge\ListRechargeSessionsUseCase;
use App\Application\Recharge\PauseRechargeUseCase;
use App\Application\Recharge\ResumeRechargeUseCase;
use App\Application\Recharge\StartRechargeUseCase;
use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

/**
 * Recharge timer (FR-05, TASK-121): start/pause/resume/complete/abandon plus
 * the Today CTA and Work-Life Ratio. The timer is persisted server-side; the
 * recorded duration is the tracked duration (never the nominal 15 minutes).
 */
final class RechargeController extends Controller
{
    public function __construct(
        private readonly StartRechargeUseCase $startRecharge,
        private readonly PauseRechargeUseCase $pauseRecharge,
        private readonly ResumeRechargeUseCase $resumeRecharge,
        private readonly CompleteRechargeUseCase $completeRecharge,
        private readonly AbandonRechargeUseCase $abandonRecharge,
        private readonly GetRechargeStatusUseCase $statusUseCase,
        private readonly ListRechargeSessionsUseCase $listSessions,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->query(), [
            'limit' => ['nullable', 'integer', 'between:1,200'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $sessions = $this->listSessions->__invoke(
            $request->user()->id,
            $validator->validated()['limit'] ?? 50,
        );

        return response()->json([
            'recharges' => array_map(
                static fn ($session) => $session->toArray(CarbonImmutable::now()),
                $sessions,
            ),
        ]);
    }

    public function status(Request $request): JsonResponse
    {
        $validator = Validator::make($request->query(), [
            'date' => ['nullable', 'date'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $date = $validator->validated()['date'] ?? null;
        $day = $date !== null ? CarbonImmutable::parse($date) : CarbonImmutable::now();

        $status = $this->statusUseCase->__invoke(
            $request->user()->id,
            $day->startOfDay(),
            $day->endOfDay(),
            CarbonImmutable::now(),
        );

        return response()->json($status);
    }

    public function start(Request $request): JsonResponse
    {
        try {
            $session = $this->startRecharge->__invoke(
                $request->user()->id,
                CarbonImmutable::now(),
            );
        } catch (InvalidArgumentException $e) {
            if ($e->getMessage() === 'A recharge timer is already running.') {
                return response()->json(['error' => $e->getMessage()], 409);
            }

            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json(['recharge' => $session->toArray(CarbonImmutable::now())], 201);
    }

    public function pause(Request $request, int $sessionId): JsonResponse
    {
        try {
            $session = $this->pauseRecharge->__invoke(
                $request->user()->id,
                $sessionId,
                CarbonImmutable::now(),
            );
        } catch (InvalidArgumentException $e) {
            return $this->mapSessionError($e);
        }

        return response()->json(['recharge' => $session->toArray(CarbonImmutable::now())]);
    }

    public function resume(Request $request, int $sessionId): JsonResponse
    {
        try {
            $session = $this->resumeRecharge->__invoke(
                $request->user()->id,
                $sessionId,
                CarbonImmutable::now(),
            );
        } catch (InvalidArgumentException $e) {
            return $this->mapSessionError($e);
        }

        return response()->json(['recharge' => $session->toArray(CarbonImmutable::now())]);
    }

    public function complete(Request $request, int $sessionId): JsonResponse
    {
        try {
            $session = $this->completeRecharge->__invoke(
                $request->user()->id,
                $sessionId,
                CarbonImmutable::now(),
            );
        } catch (InvalidArgumentException $e) {
            return $this->mapSessionError($e);
        }

        return response()->json(['recharge' => $session->toArray(CarbonImmutable::now())]);
    }

    public function abandon(Request $request, int $sessionId): JsonResponse
    {
        try {
            $session = $this->abandonRecharge->__invoke(
                $request->user()->id,
                $sessionId,
                CarbonImmutable::now(),
            );
        } catch (InvalidArgumentException $e) {
            return $this->mapSessionError($e);
        }

        return response()->json(['recharge' => $session->toArray(CarbonImmutable::now())]);
    }

    private function mapSessionError(InvalidArgumentException $e): JsonResponse
    {
        return match ($e->getMessage()) {
            'Recharge session not found.' => response()->json(['error' => $e->getMessage()], 404),
            default => response()->json(['error' => $e->getMessage()], 422),
        };
    }
}
