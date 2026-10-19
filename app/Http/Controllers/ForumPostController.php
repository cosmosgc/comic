<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreForumPostRequest;
use App\Http\Requests\UpdateForumPostRequest;
use App\Models\ForumAttachment;
use App\Models\ForumModerationLog;
use App\Models\ForumPost;
use App\Models\ForumPostReaction;
use App\Models\ForumReport;
use App\Models\ForumThread;
use App\Support\ActivityLogger;
use App\Support\Forum;
use App\Support\ForumNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/**
 * Post write actions: reply, edit, delete, react, report and the
 * moderation approve/delete.
 */
class ForumPostController extends Controller
{
    public function store(StoreForumPostRequest $request, ForumThread $thread): RedirectResponse
    {
        abort_unless(Forum::enabled(), 404);

        $category = $thread->category;
        abort_if($thread->is_locked && ! Forum::isModerator($request->user(), $category), 403, 'Este tópico está trancado.');
        abort_if(Forum::isBanned($request->user(), $category?->id), 403, 'Você está banido deste quadro.');

        $data = $request->validated();

        if ($word = Forum::prohibitedWord($data['body'])) {
            return back()->withInput()->withErrors(['body' => 'O texto contém um termo não permitido ("'.$word.'").']);
        }

        // A parent must belong to the same thread.
        $parentId = $data['parent_id'] ?? null;
        if ($parentId !== null && ! $thread->posts()->whereKey($parentId)->exists()) {
            $parentId = null;
        }

        $user = $request->user();
        $position = (int) $thread->posts()->max('position') + 1;

        $post = $thread->posts()->create([
            'user_id' => $user->id,
            'parent_id' => $parentId,
            'body' => $data['body'],
            'body_format' => 'markdown',
            'position' => $position,
            'is_approved' => ! Forum::needsApproval(new ForumPost(['position' => $position])),
        ]);

        if ($request->hasFile('attachments')) {
            ForumAttachment::storeMany($post, $request->file('attachments'), $user->id);
        }

        $thread->refreshCounters();
        $category?->refreshCounters();

        ActivityLogger::log('forum.post.replied', [
            'user' => $user,
            'subject' => $post,
            'description' => 'Respondeu em "'.$thread->title.'"',
            'metadata' => ['thread_id' => $thread->id, 'post_id' => $post->id, 'parent_id' => $parentId],
            'ip' => $request->ip(),
        ]);

        if ($post->is_approved) {
            ForumNotifier::postCreated($post);
        }

        $message = $post->is_approved ? 'Resposta publicada.' : 'Resposta enviada e aguardando aprovação.';

        return redirect()->to($this->threadUrl($thread).'#post-'.$post->id)->with('success', $message);
    }

    public function update(UpdateForumPostRequest $request, ForumPost $post): RedirectResponse
    {
        $this->authorize('update', $post);

        $data = $request->validated();
        if ($word = Forum::prohibitedWord($data['body'])) {
            return back()->withInput()->withErrors(['body' => 'O texto contém um termo não permitido ("'.$word.'").']);
        }

        $post->update([
            'body' => $data['body'],
            'edited_at' => now(),
            'edit_count' => (int) $post->edit_count + 1,
        ]);

        return back()->with('success', 'Post atualizado.');
    }

    public function destroy(Request $request, ForumPost $post): RedirectResponse
    {
        $this->authorize('delete', $post);
        $thread = $post->thread;
        $category = $thread?->category;

        if (Schema::hasTable('forum_moderation_log') && Forum::isModerator($request->user(), $category)) {
            ForumModerationLog::record($request->user(), 'post.deleted', $post, ['post_id' => $post->id]);
        }

        $post->delete();
        $thread?->refreshCounters();
        $category?->refreshCounters();

        return back()->with('success', 'Post removido.');
    }

    public function react(Request $request, ForumPost $post): JsonResponse
    {
        $validated = $request->validate([
            'reaction' => 'nullable|string|in:'.implode(',', array_keys(ForumPostReaction::kinds())),
        ]);
        $reaction = $validated['reaction'] ?? 'like';
        $user = $request->user();

        $existing = ForumPostReaction::query()
            ->where('forum_post_id', $post->id)
            ->where('user_id', $user->id)
            ->where('reaction', $reaction)
            ->first();

        if ($existing) {
            $existing->delete();
            $active = false;
        } else {
            ForumPostReaction::create([
                'forum_post_id' => $post->id,
                'user_id' => $user->id,
                'reaction' => $reaction,
            ]);
            $active = true;

            ActivityLogger::log('forum.post.reacted', [
                'user' => $user,
                'subject' => $post,
                'description' => 'Reagiu a um post no fórum',
                'metadata' => ['post_id' => $post->id, 'reaction' => $reaction],
                'ip' => $request->ip(),
            ]);
        }

        $post->thread?->refreshCounters();

        return response()->json([
            'reaction' => $reaction,
            'active' => $active,
            'count' => $post->reactions()->count(),
        ]);
    }

    public function report(Request $request, ForumPost $post): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        abort_if((int) $post->user_id === (int) $user->id, 403, 'Você não pode denunciar seu próprio post.');

        $validated = $request->validate([
            'reason' => 'required|string|in:'.implode(',', array_keys(ForumReport::reasons())),
            'details' => 'nullable|string|max:1000',
        ]);

        if (Schema::hasTable('forum_reports')) {
            $exists = ForumReport::query()
                ->where('reporter_id', $user->id)
                ->where('forum_post_id', $post->id)
                ->where('status', 'pending')
                ->exists();

            if (! $exists) {
                ForumReport::create([
                    'reporter_id' => $user->id,
                    'forum_post_id' => $post->id,
                    'reason' => $validated['reason'],
                    'details' => $validated['details'] ?? null,
                ]);

                ActivityLogger::log('forum.reported', [
                    'user' => $user,
                    'subject' => $post,
                    'description' => 'Denunciou um post do fórum ('.$validated['reason'].')',
                    'metadata' => ['post_id' => $post->id, 'reason' => $validated['reason']],
                    'ip' => $request->ip(),
                ]);
            }
        }

        return $request->wantsJson()
            ? response()->json(['ok' => true])
            : back()->with('success', 'Denúncia enviada. Obrigado por ajudar.');
    }

    public function approve(Request $request, ForumPost $post): RedirectResponse
    {
        $this->authorize('moderate', $post);
        $thread = $post->thread;

        $post->update(['is_approved' => ! $post->is_approved]);

        // Approving the opening post also approves the thread.
        if ($thread && $post->isOpening() && $post->is_approved && ! $thread->is_approved) {
            $thread->update(['is_approved' => true]);
        }

        if (Schema::hasTable('forum_moderation_log')) {
            ForumModerationLog::record($request->user(), 'post.'.($post->is_approved ? 'approved' : 'unapproved'), $post, [
                'post_id' => $post->id,
            ]);
        }

        $thread?->refreshCounters();

        return back()->with('success', $post->is_approved ? 'Post aprovado.' : 'Aprovação removida.');
    }

    protected function threadUrl(ForumThread $thread): string
    {
        return route('forum.thread', [
            'categorySlug' => $thread->category?->slug,
            'threadSlug' => $thread->slug,
        ]);
    }
}
