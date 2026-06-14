<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MobileNotification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $limit = min(max($request->integer('limit', 20), 1), 100);

        $items = $request->user()
            ->mobileNotifications()
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn (MobileNotification $notification): array => $this->format($notification));

        return response()->json(['status' => 'success', 'data' => $items]);
    }

    public function unreadCount(Request $request)
    {
        return response()->json([
            'status' => 'success',
            'data' => [
                'count' => $request->user()->mobileNotifications()->whereNull('read_at')->count(),
            ],
        ]);
    }

    public function markRead(Request $request, MobileNotification $notification)
    {
        $this->authorizeNotification($request, $notification);
        $notification->update(['read_at' => $notification->read_at ?? now()]);

        return response()->json(['status' => 'success']);
    }

    public function markAllRead(Request $request)
    {
        $request->user()->mobileNotifications()->whereNull('read_at')->update(['read_at' => now()]);

        return response()->json(['status' => 'success']);
    }

    public function destroy(Request $request, MobileNotification $notification)
    {
        $this->authorizeNotification($request, $notification);
        $notification->delete();

        return response()->json(['status' => 'success']);
    }

    public function action(Request $request, MobileNotification $notification)
    {
        $this->authorizeNotification($request, $notification);

        $validated = $request->validate([
            'action' => ['required', 'in:read,delete'],
        ]);

        if ($validated['action'] === 'delete') {
            $notification->delete();
        } else {
            $notification->update([
                'read_at' => $notification->read_at ?? now(),
            ]);
        }

        return response()->json(['status' => 'success']);
    }

    public function clearAll(Request $request)
    {
        $request->user()->mobileNotifications()->delete();

        return response()->json(['status' => 'success']);
    }

    private function authorizeNotification(Request $request, MobileNotification $notification): void
    {
        abort_unless($notification->user_id === $request->user()->id, 404);
    }

    private function format(MobileNotification $notification): array
    {
        return [
            'id' => $notification->id,
            'user_id' => $notification->user_id,
            'type' => $notification->type,
            'title' => $notification->title,
            'body' => $notification->body,
            'data' => $notification->data,
            'is_read' => $notification->read_at !== null,
            'created_at' => $notification->created_at?->toIso8601String(),
        ];
    }
}
