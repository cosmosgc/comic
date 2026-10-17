<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

/**
 * In-app notifications feed: level-ups and badge unlocks delivered
 * through Laravel's database notification channel.
 */
class NotificationController extends Controller
{
    /**
     * Latest notifications as JSON (the bell refreshes from here).
     */
    public function index(Request $request)
    {
        $notifications = $request->user()->notifications()
            ->latest()
            ->limit(15)
            ->get()
            ->map(fn ($notification) => [
                'id' => $notification->id,
                'data' => $notification->data,
                'read' => $notification->read_at !== null,
                'created_at' => $notification->created_at?->diffForHumans(),
            ]);

        return response()->json([
            'unread' => $request->user()->unreadNotifications()->count(),
            'data' => $notifications,
        ]);
    }

    /**
     * Mark one notification read and follow its link.
     */
    public function read(Request $request, string $id)
    {
        $notification = $request->user()->notifications()->where('id', $id)->firstOrFail();
        $notification->markAsRead();

        return redirect()->to($notification->data['url'] ?? route('profile.show'));
    }

    /**
     * Mark every notification read.
     */
    public function readAll(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();

        return redirect()->back();
    }
}
