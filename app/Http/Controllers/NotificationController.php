<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    /**
     * GET /api/notifications
     * Get all notifications for authenticated user
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        $limit = $request->input('limit', 20);
        $offset = $request->input('offset', 0);

        $notifications = $user->notifications()
            ->orderBy('created_at', 'desc')
            ->skip($offset)
            ->take($limit)
            ->get()
            ->map(function (Notification $notification) {
                $notification->setAttribute('entityId', $notification->entity_id);
                $notification->setAttribute('entityTitle', $notification->entity_title);
                $notification->setAttribute('fromUserName', $notification->from_user_name);
                $notification->setAttribute('createdAt', $notification->created_at?->toJSON());

                return $notification;
            });

        $unreadCount = $user->notifications()
            ->where('read', false)
            ->count();

        return response()->json([
            'notifications' => $notifications,
            'unreadCount' => $unreadCount,
            'total' => $user->notifications()->count(),
        ]);
    }

    /**
     * POST /api/notifications/:id/read
     * Mark single notification as read
     */
    public function markAsRead(Request $request, Notification $notification)
    {
        $user = Auth::user();

        // Verify notification belongs to user
        if ($notification->user_id !== $user->id) {
            return response()->json(['message' => 'غير مصرح'], 403);
        }

        $notification->update(['read' => true]);

        return response()->json([
            'message' => 'تم وضع علامة على الإخطار كمقروء',
            'notification' => $notification,
        ]);
    }

    /**
     * POST /api/notifications/read-all
     * Mark all notifications as read
     */
    public function markAllAsRead(Request $request)
    {
        $user = Auth::user();

        $user->notifications()->update(['read' => true]);

        return response()->json([
            'message' => 'تم وضع علامة على جميع الإخطارات كمقروءة',
        ]);
    }

    /**
     * DELETE /api/notifications/{notification}
     * Delete a notification
     */
    public function destroy(Request $request, Notification $notification)
    {
        $user = Auth::user();

        // Verify notification belongs to user
        if ($notification->user_id !== $user->id && $user->role !== 'admin') {
            return response()->json(['message' => 'غير مصرح'], 403);
        }

        $notification->delete();

        return response()->json(['message' => 'تم حذف الإخطار']);
    }

    /**
     * DELETE /api/notifications
     * Delete all notifications for user
     */
    public function deleteAll(Request $request)
    {
        $user = Auth::user();

        $user->notifications()->delete();

        return response()->json(['message' => 'تم حذف جميع الإخطارات']);
    }
}
