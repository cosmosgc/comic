<?php

namespace App\Http\Controllers;

use App\Models\Comic;
use App\Models\Comment;
use Illuminate\Http\Request;

class CommentController extends Controller
{
    /**
     * Paginated comments for the reader info modal (newest first).
     */
    public function index(Request $request, Comic $comic)
    {
        $comments = $comic->comments()
            ->with('user:id,name,avatar_image_path')
            ->paginate(10);

        if ($request->wantsJson()) {
            return response()->json($comments);
        }

        return redirect()->route('comics.showById', $comic);
    }

    /**
     * Store a comment on a comic (auth required).
     */
    public function store(Request $request, Comic $comic)
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
        $comment->load('user:id,name,avatar_image_path');

        if ($request->wantsJson()) {
            return response()->json($comment, 201);
        }

        return redirect()->back()->with('success', 'Comment posted.');
    }

    /**
     * Update a comment — author or admin only.
     */
    public function update(Request $request, Comment $comment)
    {
        $this->authorizeComment($request, $comment);

        $request->validate([
            'body' => 'required|string|max:2000',
        ]);

        $comment->update(['body' => $request->input('body')]);

        if ($request->wantsJson()) {
            return response()->json($comment->fresh('user:id,name,avatar_image_path'));
        }

        return redirect()->back()->with('success', 'Comment updated.');
    }

    /**
     * Delete a comment — author or admin only.
     */
    public function destroy(Request $request, Comment $comment)
    {
        $this->authorizeComment($request, $comment);

        $comment->delete();

        if ($request->wantsJson()) {
            return response()->json(['deleted' => true]);
        }

        return redirect()->back()->with('success', 'Comment deleted.');
    }

    protected function authorizeComment(Request $request, Comment $comment): void
    {
        $user = $request->user();

        if ($user->id !== $comment->user_id && (int) $user->admin_level < 1) {
            abort(403, 'You can only modify your own comments.');
        }
    }
}
