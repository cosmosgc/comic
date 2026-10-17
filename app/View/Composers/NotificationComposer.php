<?php

namespace App\View\Composers;

use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

/**
 * Supplies the navbar bell with the viewer's unread count and latest
 * notifications. Kept out of the Blade so no queries hide in the view,
 * and guarded so a host without the notifications table still renders.
 */
class NotificationComposer
{
    public function compose(View $view): void
    {
        $user = auth()->user();
        if ($user === null || ! Schema::hasTable('notifications')) {
            return;
        }

        $view->with('unreadNotificationCount', $user->unreadNotifications()->count());
        $view->with('recentNotifications', $user->notifications()->latest()->limit(8)->get());
    }
}
