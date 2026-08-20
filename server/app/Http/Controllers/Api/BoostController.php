<?php

namespace App\Http\Controllers\Api;

use App\Application\Boosts\EndBoostTargetUseCase;
use App\Application\Boosts\GetBoostSetupUseCase;
use App\Application\Boosts\SetBoostTargetUseCase;
use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

/**
 * Boost Mode (FR-37/FR-38). Shows the current boost target and a recommendation
 * computed from the Capacity feedback loop, saves a confirmed boost target with
 * a validity period scoped to the active break, and ends an active target early.
 */
final class BoostController extends Controller
{
    public function __construct(
        private readonly GetBoostSetupUseCase $setup,
        private readonly SetBoostTargetUseCase $setTarget,
        private readonly EndBoostTargetUseCase $endTarget,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $result = ($this->setup)($request->user()->id);

        return response()->json($result->toArray(), 200);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'target_percent' => ['required', 'integer', 'min:1', 'max:100'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'break_period_id' => ['nullable', 'integer', 'min:1'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();
        $userId = $request->user()->id;

        try {
            $result = $this->setTarget->__invoke(
                $userId,
                isset($data['break_period_id']) ? (int) $data['break_period_id'] : null,
                isset($data['start_date'])
                    ? CarbonImmutable::parse($data['start_date'])
                    : $this->breakRange($userId)['start'],
                isset($data['end_date'])
                    ? CarbonImmutable::parse($data['end_date'])
                    : $this->breakRange($userId)['end'],
                (int) $data['target_percent'],
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json($result->toArray(), 200);
    }

    public function end(Request $request): JsonResponse
    {
        $result = $this->endTarget->__invoke($request->user()->id);

        return response()->json($result->toArray(), $result->applied ? 200 : 202);
    }

    /**
     * The active break's date range, used as the default validity period.
     *
     * @return array{start: CarbonImmutable, end: CarbonImmutable}
     */
    private function breakRange(int $userId): array
    {
        $setup = ($this->setup)($userId);

        if ($setup->breakStartDate === null || $setup->breakEndDate === null) {
            throw new InvalidArgumentException('Break Mode is not active; confirm a break before setting a boost target.');
        }

        return [
            'start' => CarbonImmutable::parse($setup->breakStartDate),
            'end' => CarbonImmutable::parse($setup->breakEndDate),
        ];
    }
}
