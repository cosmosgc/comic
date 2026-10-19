<?php

namespace App\Http\Controllers;

use App\Models\ForumPollVote;
use App\Models\ForumThread;
use App\Support\Forum;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Forum polls: members pick one option (or several on multi-choice polls)
 * and can change their mind while the poll is open.
 */
class ForumPollController extends Controller
{
    public function vote(Request $request, ForumThread $thread)
    {
        abort_unless(Forum::enabled(), 404);
        abort_unless(Schema::hasTable('forum_polls'), 404);
        abort_if($thread->is_locked, 403, 'Este tópico está trancado.');

        $category = $thread->category;
        abort_if($category === null || ! $category->is_visible, 404);
        abort_unless($thread->is_approved || Forum::isModerator($request->user(), $category), 404);

        $poll = $thread->poll()->with('options')->firstOrFail();
        abort_if(Forum::isBanned($request->user(), $thread->forum_category_id), 403);

        if ($poll->isClosed()) {
            return back()->withErrors(['poll' => 'Esta enquete já foi encerrada.']);
        }

        $validated = $request->validate([
            'options' => 'required|array|min:1',
            'options.*' => 'integer',
        ]);

        $ids = collect($validated['options'])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($ids->diff($poll->options->pluck('id'))->isNotEmpty()) {
            return back()->withErrors(['options' => 'Opção de voto inválida.']);
        }

        if (! $poll->multiple && $ids->count() > 1) {
            return back()->withErrors(['options' => 'Escolha apenas uma opção.']);
        }

        DB::transaction(function () use ($poll, $ids, $request) {
            // Re-voting replaces the previous selection; this is also what
            // keeps single-choice polls to one row per member.
            ForumPollVote::query()
                ->where('forum_poll_id', $poll->id)
                ->where('user_id', $request->user()->id)
                ->delete();

            foreach ($ids as $optionId) {
                ForumPollVote::create([
                    'forum_poll_id' => $poll->id,
                    'forum_poll_option_id' => $optionId,
                    'user_id' => $request->user()->id,
                ]);
            }

            foreach ($poll->options as $option) {
                $option->update(['votes_count' => $option->votes()->count()]);
            }
        });

        return back()->with('success', 'Voto registrado.');
    }
}
