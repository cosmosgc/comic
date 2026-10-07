<?php

namespace App\Http\Controllers;

use App\Models\Comic;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LikeController extends Controller
{
    /**
     * Toggle the authenticated user's like on a post (idempotent).
     */
    public function toggle(Request $request, Post $post): JsonResponse
    {
        $user = $request->user();

        if ($post->likedByUsers()->where('user_id', $user->id)->exists()) {
            $post->likedByUsers()->detach($user->id);
            $liked = false;
        } else {
            $post->likedByUsers()->attach($user->id);
            $liked = true;
        }

        return response()->json([
            'liked' => $liked,
            'count' => $post->likedByUsers()->count(),
        ]);
    }

    /**
     * Toggle the authenticated user's like on a comic (idempotent).
     */
    public function toggleComic(Request $request, Comic $comic): JsonResponse
    {
        $user = $request->user();

        if ($comic->likedByUsers()->where('user_id', $user->id)->exists()) {
            $comic->likedByUsers()->detach($user->id);
            $liked = false;
        } else {
            $comic->likedByUsers()->attach($user->id);
            $liked = true;
        }

        return response()->json([
            'liked' => $liked,
            'count' => $comic->likedByUsers()->count(),
        ]);
    }
}
