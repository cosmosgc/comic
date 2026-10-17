<?php

namespace App\Support;

use App\Models\Post;
use App\Models\User;
use App\Notifications\SocialNotification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

/**
 * Fan-out of in-app notifications for social interactions: mentions,
 * replies, quotes, likes and follows. Uses the existing Laravel database
 * notification channel (the navbar bell) and never lets a notification
 * failure break the underlying action.
 */
class SocialNotifier
{
    /**
     * After a post is created: mention notifications, then the reply and
     * quote targets (deduplicated per run, never the author).
     */
    public static function postCreated(Post $post): void
    {
        try {
            $author = $post->author;
            $notified = [];

            foreach (PostText::extractMentions($post->text) as $token) {
                $user = User::findByMentionToken($token);
                if ($user === null || ($author && $user->is($author))) {
                    continue;
                }
                self::notify($user, 'mention', 'Você foi mencionado', self::excerpt(
                    ($author?->name ?? 'Alguém').' mencionou você: '.$post->text
                ), route('posts.show', $post), '📣');
                $notified[$user->id] = true;
            }

            if ($post->parent_id) {
                $parent = $post->parent;
                if ($parent && $parent->author && (! $author || ! $parent->author->is($author))
                    && ! isset($notified[$parent->author->id])) {
                    self::notify($parent->author, 'reply', 'Nova resposta', self::excerpt(
                        ($author?->name ?? 'Alguém').' respondeu: '.$post->text
                    ), route('posts.show', $post), '💬');
                    $notified[$parent->author->id] = true;
                }
            }

            if ($post->referenced_post_id) {
                $quoted = $post->referencedPost;
                if ($quoted && $quoted->author && (! $author || ! $quoted->author->is($author))
                    && ! isset($notified[$quoted->author->id])) {
                    self::notify($quoted->author, 'quote', 'Seu post foi citado', self::excerpt(
                        ($author?->name ?? 'Alguém').' citou você: '.$post->text
                    ), route('posts.show', $post), '🔁');
                }
            }
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * Notify the post author when someone likes their post.
     */
    public static function postLiked(Post $post, User $actor): void
    {
        try {
            $author = $post->author;
            if ($author === null || $author->is($actor)) {
                return;
            }

            self::notify($author, 'like', 'Seu post recebeu uma curtida', self::excerpt(
                $actor->name.' curtiu: '.$post->text
            ), route('posts.show', $post), '❤️');
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * Notify the followed user.
     */
    public static function followed(User $follower, User $followed): void
    {
        try {
            self::notify($followed, 'follow', 'Novo seguidor', $follower->name.' começou a seguir você',
                route('profile.public.show.id', $follower->getKey()), '➕');
        } catch (Throwable $e) {
            report($e);
        }
    }

    protected static function notify(User $user, string $kind, string $title, string $message, string $url, string $icon): void
    {
        if (! Schema::hasTable('notifications')) {
            return;
        }

        $user->notify(new SocialNotification($kind, $title, $message, $url, $icon));
    }

    protected static function excerpt(?string $text, int $limit = 90): string
    {
        return (string) Str::limit(trim(preg_replace('/\s+/', ' ', (string) $text) ?? ''), $limit);
    }
}
