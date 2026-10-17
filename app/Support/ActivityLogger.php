<?php

namespace App\Support;

use App\Models\ActivityLog;
use App\Models\Collection;
use App\Models\Comic;
use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
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
     *   ip?: ?string
     * }  $options
     */
    public static function log(string $action, array $options = []): ?ActivityLog
    {
        try {
            $subject = $options['subject'] ?? null;
            $url = $options['url'] ?? ($subject instanceof Model ? self::urlFor($subject) : null);

            return ActivityLog::create([
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
        } catch (Throwable $e) {
            report($e);

            return null;
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
