<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $notifications = $user->userNotifications()
            ->latest('id')
            ->limit(100)
            ->get();

        $unreadCount = $user->userNotifications()->whereNull('read_at')->count();
        $totalCount = $user->userNotifications()->count();
        $todayCount = $user->userNotifications()
            ->whereDate('created_at', today())
            ->count();

        return view('user.notifications.index', compact(
            'user',
            'notifications',
            'unreadCount',
            'totalCount',
            'todayCount'
        ));
    }

    public function markAsRead(Request $request, int $id)
    {
        $notification = $request->user()->userNotifications()->findOrFail($id);
        $notification->markAsRead();

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Notification marked as read.']);
        }

        return back()->with('success', 'Notification marked as read.');
    }

    public function markAllAsRead(Request $request)
    {
        $request->user()->userNotifications()
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        if ($request->expectsJson()) {
            return response()->json(['message' => 'All notifications marked as read.']);
        }

        return back()->with('success', 'All notifications marked as read.');
    }
}
