<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\ActivityLogger;
use App\Support\SocialNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/**
 * Follow / unfollow between users. Powers the "Following" feed tab and
 * the follower counts on profiles.
 */
class FollowController extends Controller
{
    public function toggle(Request $request, User $user): JsonResponse
    {
        $actor = $request->user();
        abort_if($actor->is($user), 422, 'Você não pode seguir a si mesmo.');

        if (! Schema::hasTable('follows')) {
            return response()->json(['following' => false, 'followers' => 0]);
        }

        if ($actor->following()->whereKey($user->getKey())->exists()) {
            $actor->following()->detach($user->getKey());
            $following = false;
        } else {
            $actor->following()->attach($user->getKey());
            $following = true;

            ActivityLogger::log('user.followed', [
                'user' => $actor,
                'subject' => $user,
                'description' => 'Seguiu '.$user->name,
                'metadata' => ['followed_id' => $user->getKey()],
                'ip' => $request->ip(),
            ]);

            SocialNotifier::followed($actor, $user);
        }

        return response()->json([
            'following' => $following,
            'followers' => $user->followers()->count(),
        ]);
    }
}
