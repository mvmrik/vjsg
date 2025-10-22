<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class NotificationsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $userId = $request->session()->get('user_id') ?: \Illuminate\Support\Facades\Auth::id();
        
        if (!$userId) {
            return response()->json(['success' => false, 'message' => 'Not authenticated'], 401);
        }

        $query = Notification::where('user_id', $userId)
            ->active()
            ->orderBy('created_at', 'desc');

        $notifications = $query->paginate(20);

        return response()->json([
            'success' => true,
            'notifications' => $notifications
        ]);
    }

    public function show(Request $request, $id): JsonResponse
    {
        $userId = $request->session()->get('user_id');
        
        if (!$userId) {
            return response()->json(['success' => false, 'message' => 'Not authenticated'], 401);
        }

        $notification = Notification::find($id);
        
        if (!$notification || $notification->user_id !== $userId) {
            return response()->json(['success' => false, 'message' => 'Notification not found'], 404);
        }

        // Mark as read if not functional type
        if ($notification->type !== 'functional' && !$notification->is_read) {
            $notification->update(['is_read' => true]);
        }

        return response()->json([
            'success' => true,
            'notification' => $notification
        ]);
    }

    public function markAsRead(Request $request, $id): JsonResponse
    {
        $userId = $request->session()->get('user_id');
        
        if (!$userId) {
            return response()->json(['success' => false, 'message' => 'Not authenticated'], 401);
        }

        $notification = Notification::find($id);
        
        if (!$notification || $notification->user_id !== $userId) {
            return response()->json(['success' => false, 'message' => 'Notification not found'], 404);
        }

        $notification->update(['is_read' => true]);

        return response()->json([
            'success' => true,
            'message' => 'Notification marked as read'
        ]);
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        $userId = $request->session()->get('user_id');
        
        if (!$userId) {
            return response()->json(['success' => false, 'message' => 'Not authenticated'], 401);
        }

        Notification::where('user_id', $userId)
            ->where('is_read', false)
            ->where('type', '!=', 'functional')
            ->update(['is_read' => true]);

        return response()->json([
            'success' => true,
            'message' => 'All notifications marked as read'
        ]);
    }

    public function confirm(Request $request, $id): JsonResponse
    {
        $userId = $request->session()->get('user_id');
        
        if (!$userId) {
            return response()->json(['success' => false, 'message' => 'Not authenticated'], 401);
        }

        $notification = Notification::find($id);
        
        if (!$notification || $notification->user_id !== $userId || $notification->type !== 'functional') {
            return response()->json(['success' => false, 'message' => 'Invalid notification'], 403);
        }

        $notification->update(['is_confirmed' => true]);

        return response()->json([
            'success' => true,
            'message' => 'Notification confirmed'
        ]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $userId = $request->session()->get('user_id');
        
        if (!$userId) {
            return response()->json(['success' => true, 'count' => 0]);
        }

        $count = Notification::where('user_id', $userId)
            ->unread()
            ->active()
            ->count();

        return response()->json([
            'success' => true,
            'count' => $count
        ]);
    }

    public function latestUnread(Request $request): JsonResponse
    {
        $userId = $request->session()->get('user_id');
        
        if (!$userId) {
            return response()->json(['success' => false, 'message' => 'Not authenticated'], 401);
        }

        $notification = Notification::where('user_id', $userId)
            ->unread()
            ->active()
            ->orderBy('created_at', 'desc')
            ->first();

        return response()->json([
            'success' => true,
            'notification' => $notification
        ]);
    }
}
