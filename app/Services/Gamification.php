<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use App\Models\UserBadge;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Experience, levels, streaks and badges.
 *
 * The single entry point is award(), called from ActivityLogger after
 * an interaction is recorded. Like the logger, it must never break the
 * underlying action, so it degrades gracefully when the gamification
 * columns are missing on a host with lagging migrations.
 */
class Gamification
{
    /** @var array<string, int> per-request metric cache, keyed "id:metric" */
    protected static array $metricCache = [];

    /**
     * Grant experience for an action, extend the daily streak and
     * award any badges the user just earned.
     *
     * @return array{exp: int, leveled_up: bool, level: int, badges: array<int, array{key: string, label: string, icon: string}>}
     */
    public static function award(User $user, string $action, ?Model $subject = null, ?int $excludeLogId = null): array
    {
        $result = ['exp' => 0, 'leveled_up' => false, 'level' => (int) $user->level, 'badges' => []];

        if (! config('gamification.enabled', true) || ! Schema::hasColumn('users', 'experience')) {
            return $result;
        }

        // Metric caching only needs to live for this one award pass;
        // a process-wide cache would leak counts across requests/tests
        // that reuse user ids.
        self::$metricCache = [];

        try {
            // Action names contain dots, so read the array directly —
            // dot notation would try to walk nested keys.
            $gained = (int) (config('gamification.exp', [])[$action] ?? 0);

            // Anti-farming: some actions only pay the first time they
            // are done to a given subject (like/unlike/re-like loops).
            if ($gained > 0
                && $subject !== null
                && in_array($action, config('gamification.unique_subject_actions', []), true)
                && self::alreadyPaid($user, $action, $subject, $excludeLogId)) {
                $gained = 0;
            }

            $gained += self::touchStreak($user);

            $experienced = (int) $user->experience + $gained;
            $user->experience = $experienced;

            $level = self::levelFor($experienced);
            $result['leveled_up'] = $level > (int) $user->level;
            $user->level = $level;
            $result['level'] = $level;

            $user->save();

            $result['exp'] = $gained;
            $result['badges'] = self::awardBadges($user);
        } catch (Throwable $e) {
            report($e);
        }

        return $result;
    }

    /**
     * Progress snapshot for the profile card.
     *
     * @return array{level: int, exp: int, into_level: int, needed: int, remaining: int, percent: int, next_level: int}
     */
    public static function progress(User $user): array
    {
        $exp = (int) $user->experience;
        $level = self::levelFor($exp);
        $currentThreshold = self::thresholdFor($level);
        $nextThreshold = self::thresholdFor($level + 1);
        $needed = max(1, $nextThreshold - $currentThreshold);
        $intoLevel = $exp - $currentThreshold;

        return [
            'level' => $level,
            'exp' => $exp,
            'into_level' => $intoLevel,
            'needed' => $needed,
            'remaining' => max(0, $nextThreshold - $exp),
            'percent' => (int) max(0, min(100, round(($intoLevel / $needed) * 100))),
            'next_level' => $level + 1,
        ];
    }

    public static function levelFor(int $experience): int
    {
        $base = max(1, (int) config('gamification.level_base', 100));
        $level = 1;
        while (self::thresholdFor($level + 1, $base) <= $experience) {
            $level++;
        }

        return $level;
    }

    /**
     * Total experience required to reach a level (L1 = 0).
     */
    public static function thresholdFor(int $level, ?int $base = null): int
    {
        $base = $base ?? max(1, (int) config('gamification.level_base', 100));
        if ($level <= 1) {
            return 0;
        }

        return (int) ($base * ($level - 1) * $level / 2);
    }

    /**
     * Ensure the user has every badge whose threshold is met, returning
     * only those newly awarded. Idempotent.
     *
     * @return array<int, array{key: string, label: string, icon: string}>
     */
    public static function awardBadges(User $user): array
    {
        $definitions = config('gamification.badges', []);
        if ($definitions === [] || ! Schema::hasTable('user_badges')) {
            return [];
        }

        $earned = UserBadge::where('user_id', $user->id)->pluck('badge')->all();
        $newly = [];

        foreach ($definitions as $key => $definition) {
            if (in_array($key, $earned, true)) {
                continue;
            }
            $threshold = (int) ($definition['threshold'] ?? 0);
            if (self::metric($user, (string) ($definition['metric'] ?? '')) < $threshold) {
                continue;
            }

            UserBadge::create([
                'user_id' => $user->id,
                'badge' => $key,
                'awarded_at' => now(),
            ]);
            $newly[] = [
                'key' => $key,
                'label' => (string) ($definition['label'] ?? $key),
                'icon' => (string) ($definition['icon'] ?? '🏅'),
            ];
        }

        return $newly;
    }

    /**
     * Resolve a badge metric for a user. Cached per request so a single
     * award() never runs the same count twice. Every table is guarded
     * because hosts may lag behind the migrations.
     */
    public static function metric(User $user, string $metric): int
    {
        $cacheKey = $user->id.':'.$metric;
        if (array_key_exists($cacheKey, self::$metricCache)) {
            return self::$metricCache[$cacheKey];
        }

        $value = match ($metric) {
            'experience' => (int) $user->experience,
            'level' => (int) $user->level,
            'current_streak' => (int) $user->current_streak,
            'longest_streak' => max((int) $user->current_streak, (int) $user->longest_streak),
            'comics_count' => $user->comics()->count(),
            'posts_count' => Schema::hasTable('posts') ? Post::where('author_id', $user->id)->count() : 0,
            'comments_count' => Schema::hasTable('comments') ? Comment::where('user_id', $user->id)->count() : 0,
            'collections_count' => Schema::hasColumn('collections', 'user_id')
                ? $user->collections()->count()
                : 0,
            'likes_given' => self::likesGiven($user),
            'searches_count' => Schema::hasTable('activity_logs')
                ? ActivityLog::where('user_id', $user->id)
                    ->whereIn('action', ['search.comics', 'search.posts'])
                    ->count()
                : 0,
            default => 0,
        };

        return self::$metricCache[$cacheKey] = (int) $value;
    }

    /**
     * Any activity on a new calendar day extends the streak. Returns
     * the bonus exp for the day (0 if today already counted).
     */
    protected static function touchStreak(User $user): int
    {
        $today = now()->startOfDay();
        $last = $user->last_active_on?->copy()->startOfDay();

        if ($last !== null && $last->equalTo($today)) {
            return 0;
        }

        $user->current_streak = ($last !== null && $last->diffInDays($today) === 1)
            ? (int) $user->current_streak + 1
            : 1;
        $user->last_active_on = $today;
        $user->longest_streak = max((int) $user->longest_streak, (int) $user->current_streak);

        return (int) config('gamification.streak_bonus', 5) * (int) $user->current_streak;
    }

    protected static function alreadyPaid(User $user, string $action, Model $subject, ?int $excludeLogId): bool
    {
        if (! Schema::hasTable('activity_logs')) {
            return false;
        }

        return ActivityLog::query()
            ->where('user_id', $user->id)
            ->where('action', $action)
            ->where('subject_type', $subject->getMorphClass())
            ->where('subject_id', $subject->getKey())
            ->when($excludeLogId !== null, fn ($q) => $q->where('id', '!=', $excludeLogId))
            ->exists();
    }

    protected static function likesGiven(User $user): int
    {
        $count = 0;
        if (Schema::hasTable('comic_user_likes')) {
            $count += DB::table('comic_user_likes')->where('user_id', $user->id)->count();
        }
        if (Schema::hasTable('post_likes')) {
            $count += DB::table('post_likes')->where('user_id', $user->id)->count();
        }

        return $count;
    }
}
