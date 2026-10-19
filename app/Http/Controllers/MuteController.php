<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/**
 * Mute / unmute another member. Muted authors are hidden from the viewer's
 * feed, hashtag timelines and post reply lists (see Post::scopeNotMutedBy).
 */
class MuteController extends Controller
{
    public function toggle(Request $request, User $user): JsonResponse|RedirectResponse
    {
        $actor = $request->user();
        abort_if($actor->is($user), 422, 'Você não pode silenciar a si mesmo.');

        if (! Schema::hasTable('mutes')) {
            if ($request->wantsJson()) {
                return response()->json(['muted' => false]);
            }

            return back()->with('success', 'Recurso indisponível.');
        }

        if ($actor->mutedUsers()->whereKey($user->getKey())->exists()) {
            $actor->mutedUsers()->detach($user->getKey());
            $muted = false;
        } else {
            $actor->mutedUsers()->attach($user->getKey());
            $muted = true;
        }

        if ($request->wantsJson()) {
            return response()->json(['muted' => $muted]);
        }

        return back()->with('success', $muted ? 'Usuário silenciado.' : 'Usuário não está mais silenciado.');
    }
}
