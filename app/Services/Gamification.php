<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Comment;
use App\Models\ForumPost;
use App\Models\ForumThread;
use App\Models\Post;
use App\Models\User;
use App\Models\UserBadge;
use App\Notifications\GamificationNotification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Experience, levels, streaks and badges.
 *
 * The live entry point is award(), called from ActivityLogger after an
 * interaction is recorded. recalculate() retro-fits experience onto
 * content that predates the activity feed. Like the logger, nothing
 * here may break the underlying action, so every write degrades
 * gracefully when the gamification columns are missing on a host with
 * lagging migrations.
 */
class Gamification
{
    /** @var array<string, int> per-award metric cache, keyed "id:metric" */
    protected static array $metricCache = [];

    /** @var array<string, mixed>|null result of the most recent award() */
    protected static ?array $lastAward = null;

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

            self::afterAward($user, $result);
        } catch (Throwable $e) {
            report($e);
        }

        return $result;
    }

    /**
     * Recompute a user's experience from the content they own, then add
     * the shortfall. Idempotent and never subtracts, so running it twice
     * adds nothing and admin/manual XP is preserved.
     *
     * @return array{delta: int, target: int, experience: int, badges: array<int, array{key: string, label: string, icon: string}>}
     */
    public static function recalculate(User $user): array
    {
        $result = ['delta' => 0, 'target' => 0, 'experience' => (int) $user->experience, 'badges' => []];

        if (! config('gamification.enabled', true) || ! Schema::hasColumn('users', 'experience')) {
            return $result;
        }

        self::$metricCache = [];

        try {
            $target = self::contentTarget($user);
            $current = (int) $user->experience;
            $delta = max(0, $target - $current);

            $user->experience = $current + $delta;
            $user->level = self::levelFor((int) $user->experience);
            $user->save();

            $result['delta'] = $delta;
            $result['target'] = $target;
            $result['experience'] = (int) $user->experience;
            $result['badges'] = self::awardBadges($user);
        } catch (Throwable $e) {
            report($e);
        }

        return $result;
    }

    /**
     * What experience a user would gain from a full recalculate right
     * now (read-only), used for the admin preview.
     */
    public static function backfillPreview(User $user): int
    {
        return max(0, self::contentTarget($user) - (int) $user->experience);
    }

    /**
     * Recompute the stored `level` column for every user from their
     * experience and the current curve. The column is a snapshot that
     * only moves when XP is awarded, so retuning the curve/base leaves
     * everyone with stale levels until this runs. Idempotent.
     *
     * @return int number of users whose level changed
     */
    public static function resyncLevels(int $chunk = 500): int
    {
        if (! Schema::hasColumn('users', 'level') || ! Schema::hasColumn('users', 'experience')) {
            return 0;
        }

        $updated = 0;

        User::query()
            ->select('id', 'experience', 'level')
            ->chunkById(max(1, $chunk), function ($users) use (&$updated) {
                foreach ($users as $user) {
                    $level = self::levelFor((int) $user->experience);
                    if ((int) $user->level === $level) {
                        continue;
                    }
                    // Query builder keeps updated_at untouched.
                    DB::table('users')->where('id', $user->id)->update(['level' => $level]);
                    $updated++;
                }
            });

        return $updated;
    }

    /**
     * Experience implied by a user's existing content (comics, posts,
     * replies, comments, likes given, collections), using the same exp
     * map as live awarding.
     */
    public static function contentTarget(User $user): int
    {
        $exp = config('gamification.exp', []);
        $value = fn (string $key) => (int) ($exp[$key] ?? 0);

        $comics = $user->comics()->count();

        $posts = Schema::hasTable('posts')
            ? Post::where('author_id', $user->id)->whereNull('parent_id')->count()
            : 0;

        $replies = Schema::hasTable('posts') && Schema::hasColumn('posts', 'parent_id')
            ? Post::where('author_id', $user->id)->whereNotNull('parent_id')->count()
            : 0;

        $comments = Schema::hasTable('comments')
            ? Comment::where('user_id', $user->id)->count()
            : 0;

        $comicLikes = Schema::hasTable('comic_user_likes')
            ? DB::table('comic_user_likes')->where('user_id', $user->id)->count()
            : 0;

        $postLikes = Schema::hasTable('post_likes')
            ? DB::table('post_likes')->where('user_id', $user->id)->count()
            : 0;

        $collections = Schema::hasColumn('collections', 'user_id')
            ? $user->collections()->count()
            : 0;

        $forumThreads = Schema::hasTable('forum_threads')
            ? ForumThread::where('user_id', $user->id)->count()
            : 0;

        $forumReplies = Schema::hasTable('forum_posts')
            ? ForumPost::where('user_id', $user->id)->where('position', '>', 1)->count()
            : 0;

        $forumReactions = Schema::hasTable('forum_post_reactions')
            ? DB::table('forum_post_reactions')->where('user_id', $user->id)->count()
            : 0;

        $forumSolved = self::forumSolvedCount($user);

        return $comics * $value('comic.uploaded')
            + $posts * $value('post.created')
            + $replies * $value('post.replied')
            + $comments * $value('comic.commented')
            + $comicLikes * $value('comic.liked')
            + $postLikes * $value('post.liked')
            + $collections * $value('collection.created')
            + $forumThreads * $value('forum.thread.created')
            + $forumReplies * $value('forum.post.replied')
            + $forumReactions * $value('forum.post.reacted')
            + $forumSolved * $value('forum.thread.solved');
    }

    /**
     * Manual admin grant (may be negative). Never notifies the user.
     *
     * @return array{exp: int, before: int, after: int, leveled_up: bool, level: int, badges: array<int, array{key: string, label: string, icon: string}>}
     */
    public static function grant(User $user, int $delta): array
    {
        $before = (int) $user->experience;
        $after = max(0, $before + $delta);

        self::$metricCache = [];

        $user->experience = $after;
        $level = self::levelFor($after);
        $result = [
            'exp' => $delta,
            'before' => $before,
            'after' => $after,
            'leveled_up' => $level > (int) $user->level,
            'level' => $level,
            'badges' => [],
        ];
        $user->level = $level;
        $user->save();

        if ($delta > 0) {
            $result['badges'] = self::awardBadges($user);
        }

        return $result;
    }

    /**
     * Manually hand a badge to a user (idempotent).
     */
    public static function grantBadge(User $user, string $badge): bool
    {
        if (! array_key_exists($badge, config('gamification.badges', [])) || ! Schema::hasTable('user_badges')) {
            return false;
        }

        UserBadge::firstOrCreate(
            ['user_id' => $user->id, 'badge' => $badge],
            ['awarded_at' => now()]
        );

        return true;
    }

    public static function revokeBadge(User $user, string $badge): bool
    {
        if (! Schema::hasTable('user_badges')) {
            return false;
        }

        return UserBadge::where('user_id', $user->id)->where('badge', $badge)->delete() > 0;
    }

    /**
     * The award() from the current request, for controllers that want to
     * surface it in a JSON response (AJAX like/comment).
     */
    public static function lastAward(): ?array
    {
        return self::$lastAward;
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
        $level = 1;
        while (self::thresholdFor($level + 1) <= $experience) {
            $level++;
        }

        return $level;
    }

    /**
     * Total experience required to reach a level (L1 = 0), per the
     * configured curve.
     */
    public static function thresholdFor(int $level, ?int $base = null, ?string $curve = null): int
    {
        if ($level <= 1) {
            return 0;
        }

        $base = $base ?? max(1, (int) config('gamification.level_base', 100));
        $curve = $curve ?? (string) config('gamification.level_curve', 'triangular');
        $n = $level - 1;

        return match ($curve) {
            'linear' => $base * $n,
            'quadratic' => $base * $n * $n,
            'exponential' => $base * ((2 ** min($n, 40)) - 1),
            default => (int) ($base * $n * ($n + 1) / 2),
        };
    }

    /**
     * @return array<string, string>
     */
    public static function curveOptions(): array
    {
        return [
            'linear' => 'Linear — custo constante',
            'triangular' => 'Triangular — crescimento moderado',
            'quadratic' => 'Quadrática — crescimento forte',
            'exponential' => 'Exponencial — para jogos longos',
        ];
    }

    /**
     * Preview rows for the admin curve table.
     *
     * @return array<int, array{level: int, total: int, needed: int}>
     */
    public static function curvePreview(int $levels = 15): array
    {
        $rows = [];
        for ($level = 1; $level <= $levels; $level++) {
            $total = self::thresholdFor($level);
            $rows[] = [
                'level' => $level,
                'total' => $total,
                'needed' => $total - self::thresholdFor($level - 1),
            ];
        }

        return $rows;
    }

    /**
     * Ensure the user has every badge whose threshold is met, returning
     * only those newly awarded. Idempotent.
     *
     * @return array<int, array{key: string, label: string, icon: string}>
     */
    public static function awardBadges(User $user): array
    {
        $newly = [];

        foreach (self::pendingBadges($user) as $key => $definition) {
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
     * Badges the user qualifies for but has not been given yet.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function pendingBadges(User $user): array
    {
        $definitions = config('gamification.badges', []);
        if ($definitions === [] || ! Schema::hasTable('user_badges')) {
            return [];
        }

        $earned = UserBadge::where('user_id', $user->id)->pluck('badge')->all();
        $pending = [];

        foreach ($definitions as $key => $definition) {
            if (in_array($key, $earned, true)) {
                continue;
            }
            $threshold = (int) ($definition['threshold'] ?? 0);
            if (self::metric($user, (string) ($definition['metric'] ?? '')) < $threshold) {
                continue;
            }
            $pending[$key] = $definition;
        }

        return $pending;
    }

    /**
     * Resolve a badge metric for a user. Cached per award pass so a
     * single award() never runs the same count twice. Every table is
     * guarded because hosts may lag behind the migrations.
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
            'posts_count' => Schema::hasTable('posts')
                ? Post::where('author_id', $user->id)->whereNull('parent_id')->count()
                : 0,
            'replies_count' => Schema::hasTable('posts') && Schema::hasColumn('posts', 'parent_id')
                ? Post::where('author_id', $user->id)->whereNotNull('parent_id')->count()
                : 0,
            'comments_count' => Schema::hasTable('comments') ? Comment::where('user_id', $user->id)->count() : 0,
            'collections_count' => Schema::hasColumn('collections', 'user_id')
                ? $user->collections()->count()
                : 0,
            'likes_given' => self::likesGiven($user),
            'comic_likes_given' => Schema::hasTable('comic_user_likes')
                ? DB::table('comic_user_likes')->where('user_id', $user->id)->count()
                : 0,
            'post_likes_given' => Schema::hasTable('post_likes')
                ? DB::table('post_likes')->where('user_id', $user->id)->count()
                : 0,
            'searches_count' => Schema::hasTable('activity_logs')
                ? ActivityLog::where('user_id', $user->id)
                    ->whereIn('action', ['search.comics', 'search.posts'])
                    ->count()
                : 0,
            'forum_threads_count' => Schema::hasTable('forum_threads')
                ? ForumThread::where('user_id', $user->id)->count()
                : 0,
            'forum_posts_count' => Schema::hasTable('forum_posts')
                ? ForumPost::where('user_id', $user->id)->count()
                : 0,
            'forum_solved_count' => self::forumSolvedCount($user),
            'forum_moderations_count' => Schema::hasTable('forum_moderators')
                ? DB::table('forum_moderators')->where('user_id', $user->id)->count()
                : 0,
            default => 0,
        };

        return self::$metricCache[$cacheKey] = (int) $value;
    }

    /**
     * @return array<string, string>
     */
    public static function metricOptions(): array
    {
        return [
            'comics_count' => 'Comics publicados',
            'posts_count' => 'Posts criados',
            'replies_count' => 'Respostas em threads',
            'comments_count' => 'Comentários em comics',
            'likes_given' => 'Curtidas dadas (comics + posts)',
            'comic_likes_given' => 'Comics curtidos',
            'post_likes_given' => 'Posts curtidos',
            'collections_count' => 'Coleções criadas',
            'searches_count' => 'Buscas feitas',
            'forum_threads_count' => 'Tópicos criados',
            'forum_posts_count' => 'Mensagens no fórum',
            'forum_solved_count' => 'Respostas aceitas',
            'forum_moderations_count' => 'Cargos de moderação',
            'current_streak' => 'Sequência atual (dias)',
            'longest_streak' => 'Maior sequência (dias)',
            'level' => 'Nível',
            'experience' => 'XP total',
        ];
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

    /**
     * Remember the award for JSON consumers, notify on milestones and
     * flash the pop animation for the acting user.
     */
    protected static function afterAward(User $user, array $result): void
    {
        self::$lastAward = $result;

        if ($result['exp'] <= 0 && $result['badges'] === [] && ! $result['leveled_up']) {
            return;
        }

        self::notifyMilestones($user, $result);
        self::flashToast($user, $result);
    }

    /**
     * Database notifications for level-ups and badge unlocks. Guarded so
     * a host without the notifications table keeps working.
     */
    protected static function notifyMilestones(User $user, array $result): void
    {
        if (! config('gamification.notify', true) || ! Schema::hasTable('notifications')) {
            return;
        }

        try {
            if ($result['leveled_up']) {
                $user->notify(new GamificationNotification(
                    'level_up',
                    'Nível '.$result['level'].' alcançado!',
                    'Você subiu para o nível '.$result['level'].'. Continue assim!',
                    route('profile.show'),
                    '🎉'
                ));
            }

            foreach ($result['badges'] as $badge) {
                $user->notify(new GamificationNotification(
                    'badge',
                    'Nova conquista: '.$badge['label'],
                    'Você desbloqueou a conquista "'.$badge['label'].'".',
                    route('profile.show'),
                    $badge['icon']
                ));
            }
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * Accumulate the award into the session so the layout can pop an
     * XP toast on the next full page render. Only for the acting user.
     */
    protected static function flashToast(User $user, array $result): void
    {
        if (app()->runningInConsole() || ! auth()->hasUser() || (int) auth()->id() !== (int) $user->id) {
            return;
        }

        try {
            $accumulated = session()->get('gamification_toast', []);
            $accumulated['exp'] = (int) ($accumulated['exp'] ?? 0) + (int) $result['exp'];
            $accumulated['leveled_up'] = (bool) ($accumulated['leveled_up'] ?? false) || (bool) $result['leveled_up'];
            if ($result['leveled_up']) {
                $accumulated['level'] = (int) $result['level'];
            }
            $accumulated['badges'] = array_merge($accumulated['badges'] ?? [], $result['badges']);

            session()->flash('gamification_toast', $accumulated);
        } catch (Throwable) {
            // Session unavailable (e.g. some CLI contexts): skip the toast.
        }
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

    /**
     * Accepted answers authored by the user (post marked solved in a
     * question thread).
     */
    protected static function forumSolvedCount(User $user): int
    {
        if (! Schema::hasTable('forum_threads') || ! Schema::hasTable('forum_posts')) {
            return 0;
        }

        return ForumPost::query()
            ->where('user_id', $user->id)
            ->whereIn('id', ForumThread::query()->whereNotNull('solved_post_id')->select('solved_post_id'))
            ->count();
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
