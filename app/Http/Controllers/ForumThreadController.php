<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreForumThreadRequest;
use App\Models\ForumAttachment;
use App\Models\ForumCategory;
use App\Models\ForumModerationLog;
use App\Models\ForumPost;
use App\Models\ForumReport;
use App\Models\ForumTag;
use App\Models\ForumThread;
use App\Services\Gamification;
use App\Support\ActivityLogger;
use App\Support\Forum;
use App\Support\ForumNotifier;
use App\Support\ImageStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Thread write actions: create, subscribe, report, mark-solved and the
 * moderation toggles (pin/lock/move/approve/delete).
 */
class ForumThreadController extends Controller
{
    public function store(StoreForumThreadRequest $request, string $categorySlug): RedirectResponse
    {
        abort_unless(Forum::enabled(), 404);

        $category = ForumCategory::query()->visible()->where('slug', $categorySlug)->firstOrFail();
        abort_unless(Forum::canStartThread($request->user(), $category), 403, 'Você não pode criar tópicos aqui.');

        $data = $request->validated();

        if ($word = Forum::prohibitedWord($data['title'].' '.$data['body'])) {
            return back()->withInput()->withErrors(['title' => 'O texto contém um termo não permitido ("'.$word.'").']);
        }

        $user = $request->user();

        $thread = DB::transaction(function () use ($category, $data, $user, $request) {
            $thread = ForumThread::create([
                'forum_category_id' => $category->id,
                'user_id' => $user->id,
                'title' => $data['title'],
                'slug' => ForumThread::uniqueSlug($category->id, $data['title']),
                'type' => $data['type'] ?? 'discussion',
                'banner_position_x' => (int) ($data['banner_position_x'] ?? 50),
                'banner_position_y' => (int) ($data['banner_position_y'] ?? 50),
                'is_approved' => true,
            ]);

            $opening = $thread->posts()->create([
                'user_id' => $user->id,
                'body' => $data['body'],
                'body_format' => 'markdown',
                'position' => 1,
                'is_approved' => Forum::needsApproval(new ForumPost(['position' => 1])) === false,
            ]);

            if ($thread->isPoll()) {
                if (! Schema::hasTable('forum_polls')) {
                    throw ValidationException::withMessages([
                        'type' => 'Enquetes ainda não estão disponíveis neste servidor.',
                    ]);
                }

                $options = collect($data['poll_options'] ?? [])
                    ->map(fn ($label) => trim((string) $label))
                    ->filter()
                    ->unique(fn ($label) => mb_strtolower($label))
                    ->values();

                if ($options->count() < 2) {
                    throw ValidationException::withMessages([
                        'poll_options' => 'Informe pelo menos duas opções diferentes.',
                    ]);
                }

                $poll = $thread->poll()->create([
                    'question' => $data['poll_question'],
                    'multiple' => (bool) ($data['poll_multiple'] ?? false),
                    'closes_at' => $data['poll_closes_at'] ?? null,
                ]);

                $options->each(fn (string $label, int $index) => $poll->options()->create([
                    'label' => $label,
                    'position' => $index + 1,
                ]));
            }

            if (config('forum.approval_mode', 'none') !== 'none') {
                $thread->update(['is_approved' => false]);
            }

            if (! empty($data['tags'])) {
                ForumTag::syncForThread($thread, $data['tags']);
            }

            if ($request->hasFile('attachments')) {
                ForumAttachment::storeMany($opening, $request->file('attachments'), $user->id);
            }

            // Topic banner (premium feature in the future; public for now).
            if ($request->hasFile('banner')) {
                $thread->update([
                    'banner_image_path' => ImageStore::put($request->file('banner'), 'forum/banners'),
                ]);
            }

            $thread->refreshCounters();
            $category->refreshCounters();

            return $thread;
        });

        ActivityLogger::log('forum.thread.created', [
            'user' => $user,
            'subject' => $thread,
            'description' => 'Criou o tópico "'.$thread->title.'" em '.$category->name,
            'metadata' => ['thread_id' => $thread->id, 'category_id' => $category->id],
            'ip' => $request->ip(),
        ]);

        if ($thread->is_approved) {
            ForumNotifier::postCreated($thread->posts()->first());
        }

        $message = $thread->is_approved
            ? 'Tópico criado.'
            : 'Tópico enviado e aguardando aprovação da moderação.';

        return redirect()->route('forum.thread', [
            'categorySlug' => $category->slug,
            'threadSlug' => $thread->slug,
        ])->with('success', $message);
    }

    /**
     * Owner-editable thread settings. For now this only covers the topic
     * banner, which previews the upcoming premium feature (public for now).
     */
    public function update(Request $request, ForumThread $thread): RedirectResponse
    {
        abort_unless(Forum::enabled(), 404);
        $this->authorize('update', $thread);

        $request->validate([
            'banner' => 'nullable|image|mimes:jpeg,jpg,png,gif,webp|max:4096',
            'banner_position_x' => 'nullable|integer|min:0|max:100',
            'banner_position_y' => 'nullable|integer|min:0|max:100',
            'remove_banner' => 'nullable|boolean',
        ]);

        // The focal point changes independently from the file upload.
        $thread->update([
            'banner_position_x' => $request->filled('banner_position_x') ? (int) $request->input('banner_position_x') : 50,
            'banner_position_y' => $request->filled('banner_position_y') ? (int) $request->input('banner_position_y') : 50,
        ]);

        if ($request->hasFile('banner')) {
            ImageStore::delete($thread->banner_image_path);
            $thread->update([
                'banner_image_path' => ImageStore::put($request->file('banner'), 'forum/banners'),
            ]);
        } elseif ($request->boolean('remove_banner')) {
            ImageStore::delete($thread->banner_image_path);
            $thread->update(['banner_image_path' => null]);
        }

        return back()->with('success', 'Banner do tópico atualizado.');
    }

    public function subscribe(Request $request, ForumThread $thread): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        $existing = $thread->subscriptions()->where('user_id', $user->id)->first();

        if ($existing) {
            $existing->delete();
            $subscribed = false;
        } else {
            $thread->subscriptions()->create(['user_id' => $user->id, 'notify' => 'db']);
            $subscribed = true;
        }

        if ($request->wantsJson()) {
            return response()->json(['subscribed' => $subscribed]);
        }

        return back()->with('success', $subscribed ? 'Inscrito no tópico.' : 'Inscrição removida.');
    }

    public function report(Request $request, ForumThread $thread): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        abort_if((int) $thread->user_id === (int) $user->id, 403, 'Você não pode denunciar seu próprio tópico.');

        $validated = $request->validate([
            'reason' => 'required|string|in:'.implode(',', array_keys(ForumReport::reasons())),
            'details' => 'nullable|string|max:1000',
        ]);

        if (Schema::hasTable('forum_reports')) {
            $exists = ForumReport::query()
                ->where('reporter_id', $user->id)
                ->where('forum_thread_id', $thread->id)
                ->where('status', 'pending')
                ->exists();

            if (! $exists) {
                ForumReport::create([
                    'reporter_id' => $user->id,
                    'forum_thread_id' => $thread->id,
                    'reason' => $validated['reason'],
                    'details' => $validated['details'] ?? null,
                ]);

                ActivityLogger::log('forum.reported', [
                    'user' => $user,
                    'subject' => $thread,
                    'description' => 'Denunciou o tópico "'.$thread->title.'" ('.$validated['reason'].')',
                    'metadata' => ['thread_id' => $thread->id, 'reason' => $validated['reason']],
                    'ip' => $request->ip(),
                ]);
            }
        }

        return $request->wantsJson()
            ? response()->json(['ok' => true])
            : back()->with('success', 'Denúncia enviada. Obrigado por ajudar.');
    }

    /**
     * The OP (or a moderator) marks a reply as the accepted answer.
     */
    public function solve(Request $request, ForumPost $post): RedirectResponse
    {
        $thread = $post->thread;
        abort_if($thread === null, 404);
        $this->authorize('solve', $thread);

        if ((int) $thread->solved_post_id === (int) $post->id) {
            $thread->update(['solved_post_id' => null]);

            return back()->with('success', 'Resposta desmarcada.');
        }

        $thread->update(['solved_post_id' => $post->id]);

        // Award the answer author (not the actor) and log the action.
        if ($post->author && (! $request->user() || ! $post->author->is($request->user()))) {
            Gamification::award($post->author, 'forum.thread.solved', $post);
        }

        ActivityLogger::log('forum.thread.solved', [
            'user' => $request->user(),
            'subject' => $thread,
            'description' => 'Aceitou uma resposta em "'.$thread->title.'"',
            'metadata' => ['thread_id' => $thread->id, 'post_id' => $post->id],
            'ip' => $request->ip(),
            'award' => false,
        ]);

        return back()->with('success', 'Resposta marcada como solução.');
    }

    public function pin(Request $request, ForumThread $thread): RedirectResponse
    {
        $this->authorize('moderate', $thread);
        $thread->update(['is_pinned' => ! $thread->is_pinned]);
        $this->record($request, $thread, $thread->is_pinned ? 'pinned' : 'unpinned');
        ForumNotifier::threadModerated($thread, $thread->is_pinned ? 'pinned' : 'unpinned', $request->user());

        return back()->with('success', $thread->is_pinned ? 'Tópico fixado.' : 'Tópico desafixado.');
    }

    public function lock(Request $request, ForumThread $thread): RedirectResponse
    {
        $this->authorize('moderate', $thread);
        $thread->update(['is_locked' => ! $thread->is_locked]);
        $this->record($request, $thread, $thread->is_locked ? 'locked' : 'unlocked');
        ForumNotifier::threadModerated($thread, $thread->is_locked ? 'locked' : 'unlocked', $request->user());

        return back()->with('success', $thread->is_locked ? 'Tópico trancado.' : 'Tópico reaberto.');
    }

    public function approve(Request $request, ForumThread $thread): RedirectResponse
    {
        $this->authorize('moderate', $thread);
        $thread->update(['is_approved' => ! $thread->is_approved]);
        $this->record($request, $thread, $thread->is_approved ? 'approved' : 'unapproved');
        ForumNotifier::threadModerated($thread, $thread->is_approved ? 'approved' : 'unapproved', $request->user());

        return back()->with('success', $thread->is_approved ? 'Tópico aprovado.' : 'Aprovação removida.');
    }

    public function move(Request $request, ForumThread $thread): RedirectResponse
    {
        $this->authorize('moderate', $thread);
        $validated = $request->validate([
            'forum_category_id' => 'required|integer|exists:forum_categories,id',
        ]);

        $from = $thread->forum_category_id;
        $to = (int) $validated['forum_category_id'];

        if ($from !== $to) {
            $old = ForumCategory::find($from);
            $thread->update([
                'forum_category_id' => $to,
                'slug' => ForumThread::uniqueSlug($to, $thread->title, $thread->id),
            ]);
            $thread->refreshCounters();
            $old?->refreshCounters();
            ForumCategory::find($to)?->refreshCounters();

            $this->record($request, $thread, 'moved', ['from' => $from, 'to' => $to]);
            ForumNotifier::threadModerated($thread, 'moved', $request->user());
        }

        return back()->with('success', 'Tópico movido.');
    }

    public function destroy(Request $request, ForumThread $thread): RedirectResponse
    {
        $this->authorize('delete', $thread);
        $category = $thread->category;
        $this->record($request, $thread, 'deleted');
        ForumNotifier::threadModerated($thread, 'deleted', $request->user());
        ImageStore::delete($thread->banner_image_path);
        $thread->delete();
        $category?->refreshCounters();

        return redirect()->route('forum.category', ['categorySlug' => $category?->slug ?? ''])
            ->with('success', 'Tópico removido.');
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    protected function record(Request $request, ForumThread $thread, string $action, array $metadata = []): void
    {
        if (! Schema::hasTable('forum_moderation_log')) {
            return;
        }

        ForumModerationLog::record($request->user(), 'thread.'.$action, $thread, $metadata + [
            'thread_id' => $thread->id,
            'title' => $thread->title,
        ]);

        ActivityLogger::log('forum.moderated', [
            'user' => $request->user(),
            'subject' => $thread,
            'description' => ucfirst($action).' o tópico "'.$thread->title.'"',
            'metadata' => ['action' => $action, 'thread_id' => $thread->id],
            'ip' => $request->ip(),
        ]);
    }
}
