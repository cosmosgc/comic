<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

/**
 * Public leaderboard: all-time experience plus a weekly board computed
 * from the activity feed (using the same exp map as live awarding).
 */
class LeaderboardController extends Controller
{
    public function index(Request $request): View
    {
        $tab = $request->input('tab') === 'weekly' ? 'weekly' : 'all-time';

        $users = $tab === 'weekly' ? $this->weeklyLeaders() : $this->allTimeLeaders();

        $myRank = null;
        $viewer = $request->user();
        if ($viewer !== null && $tab === 'all-time') {
            $myRank = User::where('experience', '>', (int) $viewer->experience)->count() + 1;
        }

        return view('leaderboard.index', [
            'users' => $users,
            'tab' => $tab,
            'myRank' => $myRank,
            'viewer' => $viewer,
        ]);
    }

    /**
     * @return Collection<int, User>
     */
    protected function allTimeLeaders(): Collection
    {
        return User::query()
            ->with('badges')
            ->orderByDesc('experience')
            ->orderByDesc('id')
            ->limit(50)
            ->get(['id', 'name', 'avatar_image_path', 'experience', 'level', 'current_streak']);
    }

    /**
     * @return Collection<int, User>
     */
    protected function weeklyLeaders(): Collection
    {
        if (! Schema::hasTable('activity_logs')) {
            return User::query()->whereRaw('1 = 0')->get();
        }

        $rows = ActivityLog::query()
            ->whereNotNull('user_id')
            ->where('created_at', '>=', now()->subDays(7))
            ->selectRaw('user_id, action, COUNT(*) as count')
            ->groupBy('user_id', 'action')
            ->get();

        $exp = config('gamification.exp', []);
        $totals = [];
        foreach ($rows as $row) {
            $points = (int) ($exp[$row->action] ?? 0) * (int) $row->count;
            if ($points > 0) {
                $totals[(int) $row->user_id] = ($totals[(int) $row->user_id] ?? 0) + $points;
            }
        }

        arsort($totals);
        $totals = array_slice($totals, 0, 50, true);

        if ($totals === []) {
            return User::query()->whereRaw('1 = 0')->get();
        }

        $users = User::whereIn('id', array_keys($totals))
            ->with('badges')
            ->get(['id', 'name', 'avatar_image_path', 'experience', 'level', 'current_streak'])
            ->keyBy('id');

        return collect($totals)
            ->map(function (int $points, int $userId) use ($users) {
                $user = $users->get($userId);
                if ($user !== null) {
                    $user->weekly_exp = $points;
                }

                return $user;
            })
            ->filter()
            ->values();
    }
}
