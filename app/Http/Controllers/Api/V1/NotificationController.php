<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $notifications = $user->notifications()->paginate(20);
        $unreadCount = $user->unreadNotifications()->count();

        return new JsonResponse([
            'data' => $notifications->items(),
            'unread_count' => $unreadCount,
            'meta' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'total' => $notifications->total(),
            ],
        ]);
    }

    public function markRead(Request $request, string $id): JsonResponse
    {
        $notification = $request->user()->notifications()->where('id', $id)->first();

        if ($notification === null) {
            return new JsonResponse(['message' => 'Not found.'], 404);
        }

        $notification->markAsRead();

        return new JsonResponse(['data' => ['id' => $id, 'read_at' => now()->toIso8601String()]]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return new JsonResponse(['data' => ['marked_read_at' => now()->toIso8601String()]]);
    }
}
