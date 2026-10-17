<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Comic;
use App\Models\Comment;
use App\Models\GamificationSetting;
use App\Models\Post;
use App\Models\User;
use App\Services\Gamification;
use App\Support\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

/**
 * Admin → Gamificação: retune exp/curve/badges, hand out XP and
 * badges, and retro-fit experience onto content that predates the
 * activity feed.
 */
class AdminGamificationController extends Controller
{
    public function index()
    {
        $actions = ActivityLog::actions();
        $exp = config('gamification.exp', []);
        $expRows = [];
        foreach ($actions as $action => $label) {
            $expRows[$action] = (int) ($exp[$action] ?? 0);
        }

        return view('admin.gamification', [
            'actions' => $actions,
            'exp' => $expRows,
            'uniqueActions' => array_values(config('gamification.unique_subject_actions', [])),
            'enabled' => (bool) config('gamification.enabled', true),
            'levelBase' => (int) config('gamification.level_base', 100),
            'levelCurve' => (string) config('gamification.level_curve', 'triangular'),
            'streakBonus' => (int) config('gamification.streak_bonus', 5),
            'curves' => Gamification::curveOptions(),
            'curvePreview' => Gamification::curvePreview(15),
            'badges' => config('gamification.badges', []),
            'metrics' => Gamification::metricOptions(),
            'overridden' => GamificationSetting::overridden(),
            'topUsers' => User::query()->orderByDesc('experience')->orderByDesc('id')
                ->limit(10)->get(['id', 'name', 'experience', 'level'])
                // Show the level implied by the current curve, not the
                // stored snapshot (which lags until a resync/award).
                ->each(fn (User $user) => $user->setAttribute('level', Gamification::levelFor((int) $user->experience))),
            'totals' => $this->contentTotals(),
        ]);
    }

    /**
     * Persist the exp map, curve, streak bonus and badge catalogue.
     * Writes override rows; deleting a row (blank field) would restore
     * the file default — here we always store the submitted values.
     */
    public function saveSettings(Request $request)
    {
        $validated = $request->validate([
            'level_base' => 'required|integer|min:1|max:1000000',
            'level_curve' => 'required|in:'.implode(',', array_keys(Gamification::curveOptions())),
            'streak_bonus' => 'required|integer|min:0|max:1000',
            'exp' => 'nullable|array',
            'exp.*' => 'nullable|integer|min:0|max:100000',
            'unique_subject_actions' => 'nullable|array',
            'unique_subject_actions.*' => 'string',
            'badges' => 'nullable|array',
            'badges_remove' => 'nullable|array',
            'new_badge_key' => 'nullable|string|max:50|regex:/^[a-z0-9_]+$/',
            'new_badge_label' => 'nullable|string|max:100',
            'new_badge_icon' => 'nullable|string|max:16',
            'new_badge_metric' => 'nullable|string|max:50',
            'new_badge_threshold' => 'nullable|integer|min:0|max:1000000',
        ]);

        // -- exp map (only known actions) ---------------------------------
        $exp = [];
        foreach (array_keys(ActivityLog::actions()) as $action) {
            $exp[$action] = max(0, (int) ($validated['exp'][$action] ?? 0));
        }

        // -- badges: keep existing, apply edits, honor removals, add new --
        $badges = config('gamification.badges', []);
        $removed = array_keys($validated['badges_remove'] ?? []);
        foreach ($removed as $key) {
            unset($badges[$key]);
        }

        foreach (($validated['badges'] ?? []) as $key => $row) {
            if (! is_array($row) || ! isset($badges[$key])) {
                continue;
            }
            $badges[$key]['label'] = trim((string) ($row['label'] ?? $badges[$key]['label']));
            $badges[$key]['icon'] = trim((string) ($row['icon'] ?? $badges[$key]['icon']));
            $badges[$key]['description'] = trim((string) ($row['description'] ?? ($badges[$key]['description'] ?? '')));
            $badges[$key]['metric'] = array_key_exists((string) ($row['metric'] ?? ''), Gamification::metricOptions())
                ? (string) $row['metric']
                : ($badges[$key]['metric'] ?? 'experience');
            $badges[$key]['threshold'] = max(0, (int) ($row['threshold'] ?? $badges[$key]['threshold']));
        }

        $newKey = trim((string) ($validated['new_badge_key'] ?? ''));
        if ($newKey !== '' && ! isset($badges[$newKey]) && ! empty($validated['new_badge_label'])) {
            $badges[$newKey] = [
                'label' => (string) $validated['new_badge_label'],
                'description' => '',
                'icon' => (string) ($validated['new_badge_icon'] ?: '🏅'),
                'metric' => array_key_exists((string) $validated['new_badge_metric'], Gamification::metricOptions())
                    ? (string) $validated['new_badge_metric']
                    : 'experience',
                'threshold' => max(0, (int) ($validated['new_badge_threshold'] ?? 0)),
            ];
        }

        // -- unique (once-only) actions -----------------------------------
        $unique = array_values(array_intersect(
            array_keys(ActivityLog::actions()),
            $validated['unique_subject_actions'] ?? []
        ));

        // Snapshot the effective curve before the overlay changes it:
        // a retuned curve/base invalidates every stored level.
        $oldBase = (int) config('gamification.level_base', 100);
        $oldCurve = (string) config('gamification.level_curve', 'triangular');

        GamificationSetting::store('enabled', $request->boolean('enabled'));
        GamificationSetting::store('level_base', $validated['level_base']);
        GamificationSetting::store('level_curve', $validated['level_curve']);
        GamificationSetting::store('streak_bonus', $validated['streak_bonus']);
        GamificationSetting::store('exp', $exp);
        GamificationSetting::store('unique_subject_actions', $unique);
        GamificationSetting::store('badges', $badges);
        GamificationSetting::applyToConfig();

        $message = 'Configurações de gamificação salvas.';

        // Levels are stored snapshots, so a curve/base change would leave
        // every user on their old number until they earn XP again.
        if ($oldBase !== (int) $validated['level_base'] || $oldCurve !== (string) $validated['level_curve']) {
            $resynced = Gamification::resyncLevels();
            $message .= " Níveis recalculados para {$resynced} usuário(s).";
        }

        return redirect()->route('admin.gamification')
            ->with('success', $message);
    }

    /**
     * Recompute every stored level from experience with the current
     * curve (public ranking and chips read the stored column).
     */
    public function resyncLevels()
    {
        $updated = Gamification::resyncLevels();

        return redirect()->route('admin.gamification')
            ->with('success', "Níveis recalculados: {$updated} usuário(s) atualizado(s).");
    }

    /**
     * Hand out (or take away) experience and grant/revoke a badge.
     */
    public function grant(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'exp' => 'nullable|integer|min:-1000000|max:1000000',
            'badge' => 'nullable|string|max:50',
            'badge_action' => 'nullable|in:grant,revoke',
        ]);

        $user = User::findOrFail($validated['user_id']);
        $notes = [];

        $delta = (int) ($validated['exp'] ?? 0);
        if ($delta !== 0) {
            $result = Gamification::grant($user, $delta);
            $notes[] = ($delta > 0 ? '+' : '').$delta.' XP (agora '.$result['after'].' XP, nível '.$result['level'].')';
            if ($result['badges'] !== []) {
                $notes[] = count($result['badges']).' conquista(s) desbloqueada(s)';
            }
        }

        $badge = (string) ($validated['badge'] ?? '');
        if ($badge !== '' && in_array($validated['badge_action'] ?? '', ['grant', 'revoke'], true)) {
            if ($validated['badge_action'] === 'grant') {
                $ok = Gamification::grantBadge($user, $badge);
                $notes[] = $ok ? "Conquista \"{$badge}\" concedida" : "Conquista \"{$badge}\" inválida";
            } else {
                $ok = Gamification::revokeBadge($user, $badge);
                $notes[] = $ok ? "Conquista \"{$badge}\" removida" : "O usuário não tinha \"{$badge}\"";
            }
        }

        if ($notes === []) {
            return redirect()->route('admin.gamification')
                ->with('error', 'Nada a fazer: informe XP ou uma conquista.');
        }

        ActivityLogger::log('admin.gift', [
            'user' => $request->user(),
            'subject' => $user,
            'description' => 'Recompensou '.$user->name.': '.implode('; ', $notes),
            'url' => route('admin.gamification'),
            'metadata' => ['target_user_id' => $user->id, 'exp' => $delta, 'badge' => $badge ?: null],
            'ip' => $request->ip(),
        ]);

        return redirect()->route('admin.gamification')
            ->with('success', $user->name.': '.implode('; ', $notes).'.');
    }

    /**
     * Read-only preview of what a full recalculation would award.
     */
    public function backfillPreview()
    {
        $users = 0;
        $changed = 0;
        $exp = 0;
        $badges = 0;

        User::query()->chunkById(200, function ($batch) use (&$users, &$changed, &$exp, &$badges) {
            foreach ($batch as $user) {
                $users++;
                $gain = Gamification::backfillPreview($user);
                if ($gain > 0) {
                    $changed++;
                    $exp += $gain;
                }
                $badges += count(Gamification::pendingBadges($user));
            }
        });

        return redirect()->route('admin.gamification')
            ->with('success', "Prévia: {$changed} de {$users} usuário(s) ganhariam {$exp} XP e {$badges} conquista(s).");
    }

    /**
     * Run the backfill for real (retro-fits XP from existing content).
     */
    public function backfill()
    {
        $exit = Artisan::call('gamification:backfill');
        $output = trim(Artisan::output());

        return redirect()->route('admin.gamification')
            ->with($exit === 0 ? 'success' : 'error', $output !== '' ? $output : 'Recálculo concluído.')
            ->with('migrate_output', $output);
    }

    /**
     * Typeahead source for the "give XP/badge" panel (admin JSON).
     */
    public function searchUsers(Request $request)
    {
        $term = trim((string) $request->input('q', ''));
        if (mb_strlen($term) < 2) {
            return response()->json(['data' => []]);
        }

        $users = User::query()
            ->where(fn ($q) => $q->where('name', 'like', '%'.$term.'%')->orWhere('email', 'like', '%'.$term.'%'))
            ->with('badges')
            ->orderBy('name')
            ->limit(10)
            ->get(['id', 'name', 'email', 'experience', 'level']);

        return response()->json([
            'data' => $users->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'experience' => (int) $user->experience,
                'level' => (int) $user->level,
                'badges' => $user->badges->map(fn ($badge) => [
                    'key' => $badge->badge,
                    'label' => $badge->label,
                    'icon' => $badge->icon,
                ])->values(),
            ])->values(),
        ]);
    }

    /**
     * @return array<string, int>
     */
    protected function contentTotals(): array
    {
        return [
            'users' => User::count(),
            'comics' => Schema::hasTable('comics') ? Comic::count() : 0,
            'posts' => Schema::hasTable('posts') ? Post::count() : 0,
            'comments' => Schema::hasTable('comments') ? Comment::count() : 0,
        ];
    }
}
