<?php

namespace App\Http\Controllers\Api;

use App\Application\Notifications\ListNotificationsUseCase;
use App\Application\Notifications\MarkNotificationReadUseCase;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

final class NotificationController extends Controller
{
    public function __construct(
        private readonly ListNotificationsUseCase $listNotifications,
        private readonly MarkNotificationReadUseCase $markNotificationRead,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->query(), [
            'unread' => ['nullable', 'boolean'],
            'limit' => ['nullable', 'integer', 'between:1,200'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        $notifications = $this->listNotifications->__invoke(
            $request->user()->id,
            filter_var($data['unread'] ?? false, FILTER_VALIDATE_BOOL),
            $data['limit'] ?? 50,
        );

        return response()->json([
            'notifications' => array_map(static fn ($notification) => $notification->toArray(), $notifications),
        ]);
    }

    public function read(Request $request, int $notificationId): JsonResponse
    {
        $notification = $this->markNotificationRead->__invoke($request->user()->id, $notificationId);

        if ($notification === null) {
            return response()->json(['error' => 'Notification not found.'], 404);
        }

        return response()->json(['notification' => $notification->toArray()]);
    }
}
