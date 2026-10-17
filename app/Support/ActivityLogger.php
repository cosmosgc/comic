<?php

namespace App\Support;

use App\Models\ActivityLog;
use App\Models\Collection;
use App\Models\Comic;
use App\Models\Comment;
use App\Models\ForumPost;
use App\Models\ForumThread;
use App\Models\Post;
use App\Models\User;
use App\Services\Gamification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

/**
 * Single entry point for recording user interactions into the
 * `activity_logs` table (admin "Recent activities" panel).
 *
 * Logging must never break the underlying action, so every failure
 * is swallowed and reported to the log channel instead.
 */
class ActivityLogger
{
    /**
     * @param  string  $action  one of ActivityLog::actions() keys
     * @param  array{
     *   user?: User|int|null,
     *   subject?: Model|null,
     *   description?: ?string,
     *   url?: ?string,
     *   metadata?: ?array<string, mixed>,
     *   ip?: ?string,
     *   award?: bool
     * }  $options
     */
    public static function log(string $action, array $options = []): ?ActivityLog
    {
        try {
            $subject = $options['subject'] ?? null;
            $url = $options['url'] ?? ($subject instanceof Model ? self::urlFor($subject) : null);

            $log = ActivityLog::create([
                'user_id' => self::userId($options['user'] ?? null),
                'action' => mb_substr($action, 0, 50),
                'subject_type' => $subject instanceof Model ? $subject->getMorphClass() : null,
                'subject_id' => $subject instanceof Model ? $subject->getKey() : null,
                'description' => isset($options['description'])
                    ? mb_substr((string) $options['description'], 0, 2000)
                    : null,
                'url' => $url !== null ? mb_substr($url, 0, 2000) : null,
                'metadata' => $options['metadata'] ?? null,
                'ip_address' => $options['ip'] ?? request()->ip(),
            ]);

            // Gamification: grant exp/level/badges for this interaction.
            // Isolated so a gamification failure never loses the log.
            // Callers that award exp to a *different* user can set
            // `award => false` and call Gamification::award() themselves.
            if ($log->user_id !== null && ($options['award'] ?? true)) {
                try {
                    if ($log->user !== null) {
                        Gamification::award($log->user, $action, $subject instanceof Model ? $subject : null, $log->id);
                    }
                } catch (Throwable $e) {
                    report($e);
                }
            }

            return $log;
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    /**
     * Has this user already triggered $action on $subject? Used for
     * one-shot interactions ("started reading a comic") that should
     * appear in the feed only the first time.
     */
    public static function alreadyLogged(string $action, Model $subject, User|int|null $user = null): bool
    {
        try {
            $id = self::userId($user);
            if ($id === null || ! Schema::hasTable('activity_logs')) {
                return false;
            }

            return ActivityLog::query()
                ->where('user_id', $id)
                ->where('action', $action)
                ->where('subject_type', $subject->getMorphClass())
                ->where('subject_id', $subject->getKey())
                ->exists();
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }

    protected static function userId(User|int|null $user = null): ?int
    {
        if ($user instanceof User) {
            return $user->getKey();
        }
        if (is_int($user)) {
            return $user;
        }

        try {
            return auth()->id();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Best-effort "see what it is about" link for a subject.
     * Stored at log time so the admin view is a plain anchor.
     */
    public static function urlFor(Model $subject): ?string
    {
        try {
            if ($subject instanceof Comic) {
                return $subject->slug
                    ? route('comics.showBySlug', $subject->slug)
                    : route('comics.showById', $subject->getKey());
            }
            if ($subject instanceof Post) {
                return route('posts.show', $subject->getKey());
            }
            if ($subject instanceof ForumThread) {
                return $subject->category && $subject->slug
                    ? route('forum.thread', ['categorySlug' => $subject->category->slug, 'threadSlug' => $subject->slug])
                    : route('forum.index');
            }
            if ($subject instanceof ForumPost) {
                $thread = $subject->thread;
                if ($thread && $thread->category && $thread->slug) {
                    return route('forum.thread', ['categorySlug' => $thread->category->slug, 'threadSlug' => $thread->slug]);
                }

                return route('forum.index');
            }
            if ($subject instanceof Comment) {
                return $subject->comic_id
                    ? route('comics.showById', $subject->comic_id)
                    : null;
            }
            if ($subject instanceof Collection) {
                return route('collections.show', $subject->getKey());
            }
            if ($subject instanceof User) {
                return route('profile.public.show.id', $subject->getKey());
            }
        } catch (Throwable) {
            return null;
        }

        return null;
    }

    public static function excerpt(?string $text, int $limit = 140): ?string
    {
        if ($text === null || trim($text) === '') {
            return null;
        }

        return Str::limit(trim(preg_replace('/\s+/', ' ', $text) ?? ''), $limit);
    }
}
