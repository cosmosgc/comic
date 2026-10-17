<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\Report;
use App\Support\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/**
 * Report a post for moderation. Reviews happen in the admin queue.
 */
class ReportController extends Controller
{
    public function store(Request $request, Post $post)
    {
        $user = $request->user();
        abort_if((int) $post->author_id === (int) $user->id, 403, 'Você não pode denunciar seu próprio post.');

        if (! Schema::hasTable('reports')) {
            return $request->wantsJson()
                ? response()->json(['ok' => false], 503)
                : back()->with('success', 'Denúncia registrada.');
        }

        $validated = $request->validate([
            'reason' => 'required|string|in:'.implode(',', array_keys(Report::reasons())),
            'details' => 'nullable|string|max:1000',
        ]);

        // One open report per reporter/post is enough.
        $existing = Report::query()
            ->where('reporter_id', $user->id)
            ->where('post_id', $post->id)
            ->where('status', 'pending')
            ->exists();

        if (! $existing) {
            Report::create([
                'reporter_id' => $user->id,
                'post_id' => $post->id,
                'reason' => $validated['reason'],
                'details' => $validated['details'] ?? null,
            ]);

            ActivityLogger::log('post.reported', [
                'user' => $user,
                'subject' => $post,
                'description' => 'Denunciou o post #'.$post->id.' ('.$validated['reason'].')',
                'metadata' => ['post_id' => $post->id, 'reason' => $validated['reason']],
                'ip' => $request->ip(),
            ]);
        }

        if ($request->wantsJson()) {
            return response()->json(['ok' => true]);
        }

        return back()->with('success', 'Denúncia enviada. Obrigado por ajudar.');
    }
}
