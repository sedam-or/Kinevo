<?php

namespace App\Http\Controllers\Api;

use App\Application\Identity\GetProfileUseCase;
use App\Application\Identity\UpdateProfileUseCase;
use App\Domain\Identity\ValueObjects\ProfileSettings;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

final class ProfileController extends Controller
{
    public function __construct(
        private readonly GetProfileUseCase $getProfileUseCase,
        private readonly UpdateProfileUseCase $updateProfileUseCase,
    ) {}

    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'profile' => $this->getProfileUseCase->__invoke($request->user()->id)->toArray(),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'display_name' => ['nullable', 'string', 'max:255'],
            'locale' => ['sometimes', 'string', 'max:10'],
            'timezone' => ['sometimes', 'string', 'max:64'],
            'week_start_day' => ['sometimes', 'string', 'max:9'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $existing = $this->getProfileUseCase->__invoke($request->user()->id)->toArray();
        $data = $validator->validated();

        try {
            $settings = new ProfileSettings(
                $data['display_name'] ?? $existing['display_name'],
                $data['locale'] ?? $existing['locale'],
                $data['timezone'] ?? $existing['timezone'],
                $data['week_start_day'] ?? $existing['week_start_day'],
            );

            $updated = $this->updateProfileUseCase->__invoke($request->user()->id, $settings);
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json([
            'profile' => $updated->toArray(),
        ]);
    }
}
