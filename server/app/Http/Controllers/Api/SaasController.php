<?php

namespace App\Http\Controllers\Api;

use App\Application\Saas\GetPlanOverviewUseCase;
use App\Application\Saas\SetPlanUseCase;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

/** TASK-P23-008 — plan overview + self-serve switching (manual provider). */
final class SaasController extends Controller
{
    public function __construct(
        private readonly GetPlanOverviewUseCase $planOverview,
        private readonly SetPlanUseCase $setPlan,
    ) {}

    public function show(Request $request): JsonResponse
    {
        return response()->json($this->planOverview->__invoke($request->user()->id));
    }

    public function update(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'plan_code' => ['required', 'string', 'max:32'],
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $this->setPlan->__invoke($request->user()->id, $validator->validated()['plan_code']);
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json($this->planOverview->__invoke($request->user()->id));
    }
}
