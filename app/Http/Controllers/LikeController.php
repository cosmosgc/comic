<?php

namespace App\Http\Controllers;

use App\Models\Comic;
use App\Models\Post;
use App\Support\ActivityLogger;
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

        ActivityLogger::log($liked ? 'post.liked' : 'post.unliked', [
            'user' => $user,
            'subject' => $post,
            'description' => ($liked ? 'Curtiu o post #' : 'Descurtiu o post #').$post->id
                .($post->text ? ' — '.ActivityLogger::excerpt($post->text, 100) : ''),
            'metadata' => ['post_id' => $post->id, 'liked' => $liked],
            'ip' => $request->ip(),
        ]);

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

        ActivityLogger::log($liked ? 'comic.liked' : 'comic.unliked', [
            'user' => $user,
            'subject' => $comic,
            'description' => ($liked ? 'Curtiu o comic "' : 'Descurtiu o comic "').$comic->title.'"',
            'metadata' => ['comic_id' => $comic->id, 'liked' => $liked],
            'ip' => $request->ip(),
        ]);

        return response()->json([
            'liked' => $liked,
            'count' => $comic->likedByUsers()->count(),
        ]);
    }
}
