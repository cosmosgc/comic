<?php

namespace App\Http\Controllers;

use App\Models\Comic;
use App\Models\Comment;
use App\Support\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CommentController extends Controller
{
    /**
     * Paginated comments for the reader info modal (newest first).
     */
    public function index(Request $request, Comic $comic): JsonResponse|RedirectResponse
    {
        $comments = $comic->comments()
            ->with([
                'user' => fn ($query) => $query->select('id', 'name', 'avatar_image_path', 'level'),
                'user.badges',
            ])
            ->paginate(10);

        if ($request->wantsJson()) {
            return response()->json($comments);
        }

        return redirect()->route('comics.showById', $comic);
    }

    /**
     * Store a comment on a comic (auth required).
     */
    public function store(Request $request, Comic $comic): JsonResponse|RedirectResponse
    {
        $request->validate([
            'body' => 'required|string|max:2000',
        ]);

        // Comment::$fillable is only ['body']; ids attach via relations.
        $comment = $comic->comments()->make([
            'body' => $request->input('body'),
        ]);
        $comment->user()->associate($request->user());
        $comment->save();
        $comment->load('user:id,name,avatar_image_path,level', 'user.badges');

        ActivityLogger::log('comic.commented', [
            'user' => $request->user(),
            'subject' => $comment,
            'url' => ActivityLogger::urlFor($comic),
            'description' => 'Comentou em "'.$comic->title.'" — '.ActivityLogger::excerpt($comment->body, 100),
            'metadata' => ['comment_id' => $comment->id, 'comic_id' => $comic->id],
            'ip' => $request->ip(),
        ]);

        if ($request->wantsJson()) {
            return response()->json($comment, 201);
        }

        return redirect()->back()->with('success', 'Comentário publicado.');
    }

    /**
     * Update a comment — author or admin only.
     */
    public function update(Request $request, Comment $comment): JsonResponse|RedirectResponse
    {
        $this->authorize('update', $comment);

        $request->validate([
            'body' => 'required|string|max:2000',
        ]);

        $comment->update(['body' => $request->input('body')]);

        if ($request->wantsJson()) {
            return response()->json($comment->fresh(['user' => fn ($query) => $query->select('id', 'name', 'avatar_image_path', 'level'), 'user.badges']));
        }

        return redirect()->back()->with('success', 'Comentário atualizado.');
    }

    /**
     * Delete a comment — author or admin only.
     */
    public function destroy(Request $request, Comment $comment): JsonResponse|RedirectResponse
    {
        $this->authorize('delete', $comment);

        $comment->delete();

        if ($request->wantsJson()) {
            return response()->json(['deleted' => true]);
        }

        return redirect()->back()->with('success', 'Comentário excluído.');
    }
}
