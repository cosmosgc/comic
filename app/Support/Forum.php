<?php

namespace App\Support;

use App\Models\ForumBan;
use App\Models\ForumCategory;
use App\Models\ForumModerator;
use App\Models\ForumPost;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

/**
 * Forum-wide helpers: moderator resolution and ban enforcement. Kept
 * out of controllers/policies so the rules live in one place and stay
 * guarded on hosts with lagging migrations.
 */
class Forum
{
    /**
     * Whether the forum is enabled at all.
     */
    public static function enabled(): bool
    {
        return (bool) config('forum.enabled', true);
    }

    /**
     * Admin (admin_level >= 1) or a forum_moderators row matching the
     * category (or forum-wide).
     */
    public static function isModerator(?User $user, ?ForumCategory $category = null): bool
    {
        if ($user === null) {
            return false;
        }

        if ((int) ($user->admin_level ?? 0) >= 1) {
            return true;
        }

        if (! Schema::hasTable('forum_moderators')) {
            return false;
        }

        $categoryId = $category?->getKey();

        return ForumModerator::query()
            ->where('user_id', $user->id)
            ->where(function ($query) use ($categoryId) {
                $query->whereNull('forum_category_id');
                if ($categoryId !== null) {
                    $query->orWhere('forum_category_id', $categoryId);
                }
            })
            ->exists();
    }

    /**
     * An active ban covering the given category (null = forum-wide).
     */
    public static function isBanned(?User $user, ?int $categoryId = null): bool
    {
        if ($user === null || ! Schema::hasTable('forum_bans')) {
            return false;
        }

        return ForumBan::query()
            ->where('user_id', $user->id)
            ->active()
            ->get()
            ->contains(fn (ForumBan $ban) => $ban->coversCategory($categoryId));
    }

    /**
     * Role level used against ForumCategory::min_role.
     */
    public static function roleLevel(?User $user, ?ForumCategory $category = null): int
    {
        if ($user === null) {
            return 0;
        }
        if (self::isModerator($user, $category)) {
            return (int) ($user->admin_level ?? 0) >= 1 ? 3 : 2;
        }

        return 1;
    }

    /**
     * Whether the user may start a thread in the category: visible,
     * unlocked, meeting min_role, and not banned.
     */
    public static function canStartThread(?User $user, ForumCategory $category): bool
    {
        if (! self::enabled() || ! $category->is_visible || $category->is_locked) {
            return false;
        }

        if ($user === null) {
            return false;
        }

        if (self::isBanned($user, $category->getKey())) {
            return false;
        }

        return self::roleLevel($user, $category) >= $category->minRoleLevel();
    }

    /**
     * Whether a post needs approval given the current approval mode.
     */
    public static function needsApproval(ForumPost $post): bool
    {
        return match ((string) config('forum.approval_mode', 'none')) {
            'all' => true,
            'first_post' => (int) $post->position === 1,
            default => false,
        };
    }

    /**
     * Reject prohibited words in user-generated text. Returns the
     * offending word or null.
     */
    public static function prohibitedWord(?string $text): ?string
    {
        $words = config('forum.prohibited_words', []);
        if (! is_array($words) || $words === []) {
            return null;
        }

        $haystack = mb_strtolower((string) $text);
        foreach ($words as $word) {
            $word = trim(mb_strtolower((string) $word));
            if ($word !== '' && str_contains($haystack, $word)) {
                return $word;
            }
        }

        return null;
    }
}
