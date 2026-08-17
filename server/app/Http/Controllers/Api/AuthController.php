<?php

namespace App\Http\Controllers\Api;

use App\Application\Identity\LoginUserUseCase;
use App\Application\Identity\LogoutUserUseCase;
use App\Application\Identity\RegisterUserUseCase;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;
use Throwable;

final class AuthController extends Controller
{
    public function register(Request $request, RegisterUserUseCase $useCase): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'max:255'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $result = $useCase($validator->validated()['name'], $validator->validated()['email'], $validator->validated()['password']);
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 409);
        }

        return response()->json([
            'user' => $result['user']->only(['id', 'name', 'email']),
            'token' => $result['token'],
            'profile' => $result['profile'],
        ], 201);
    }

    public function login(Request $request, LoginUserUseCase $useCase): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $result = $useCase($validator->validated()['email'], $validator->validated()['password']);
        } catch (Throwable $e) {
            return response()->json(['error' => 'The provided credentials are incorrect.'], 401);
        }

        return response()->json([
            'user' => $result['user']->only(['id', 'name', 'email']),
            'token' => $result['token'],
        ]);
    }

    public function logout(Request $request, LogoutUserUseCase $useCase): JsonResponse
    {
        $useCase($request->user());

        return response()->json(['message' => 'Logged out.']);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'user' => $user->only(['id', 'name', 'email']),
        ]);
    }
}
