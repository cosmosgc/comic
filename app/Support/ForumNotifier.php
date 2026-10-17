<?php

namespace App\Support;

use App\Models\ForumBan;
use App\Models\ForumPost;
use App\Models\ForumReport;
use App\Models\ForumThread;
use App\Models\User;
use App\Notifications\ForumNotification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

/**
 * Fan-out of in-app notifications for forum interactions: mentions,
 * replies, quotes, subscription updates and moderation outcomes. Uses
 * the existing database notification channel (the navbar bell) and
 * never lets a failure break the underlying action.
 */
class ForumNotifier
{
    /**
     * After a post is created: mentions, the reply/quote targets and
     * everyone subscribed to the thread (deduplicated, never the
     * author).
     */
    public static function postCreated(ForumPost $post): void
    {
        try {
            $thread = $post->thread;
            if ($thread === null) {
                return;
            }

            $author = $post->author;
            $notified = [];
            $url = self::threadUrl($thread);

            foreach (PostText::extractMentions($post->body) as $token) {
                $user = User::findByMentionToken($token);
                if ($user === null || ($author && $user->is($author)) || isset($notified[$user->id])) {
                    continue;
                }
                self::notify($user, 'mention', 'Você foi mencionado', self::excerpt(
                    ($author?->name ?? 'Alguém').' mencionou você em "'.$thread->title.'"'
                ), $url, '📣');
                $notified[$user->id] = true;
            }

            if (! $post->isOpening()) {
                $threadAuthor = $thread->author;
                if ($threadAuthor && (! $author || ! $threadAuthor->is($author)) && ! isset($notified[$threadAuthor->id])) {
                    self::notify($threadAuthor, 'reply', 'Nova resposta no seu tópico', self::excerpt(
                        ($author?->name ?? 'Alguém').' respondeu: '.$post->body
                    ), $url, '💬');
                    $notified[$threadAuthor->id] = true;
                }
            }

            if ($post->parent_id && $post->parent?->author) {
                $parentAuthor = $post->parent->author;
                if ((! $author || ! $parentAuthor->is($author)) && ! isset($notified[$parentAuthor->id])) {
                    self::notify($parentAuthor, 'quote', 'Responderam a você', self::excerpt(
                        ($author?->name ?? 'Alguém').' respondeu: '.$post->body
                    ), $url, '↩️');
                    $notified[$parentAuthor->id] = true;
                }
            }

            // Thread subscribers (the author opted in separately).
            if (Schema::hasTable('forum_subscriptions')) {
                $subscribers = $thread->subscriptions()->with('user')->get();
                foreach ($subscribers as $subscription) {
                    $user = $subscription->user;
                    if ($user === null || ($author && $user->is($author)) || isset($notified[$user->id])) {
                        continue;
                    }
                    self::notify($user, 'subscribed', 'Novo post em "'.$thread->title.'"', self::excerpt(
                        ($author?->name ?? 'Alguém').' publicou: '.$post->body
                    ), $url, '🔔');
                    $notified[$user->id] = true;
                }
            }
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * Notify the thread author when a moderator acts on their thread.
     */
    public static function threadModerated(ForumThread $thread, string $action, ?User $actor = null): void
    {
        try {
            $author = $thread->author;
            if ($author === null || ($actor && $author->is($actor))) {
                return;
            }

            $label = match ($action) {
                'locked' => 'trancado',
                'unlocked' => 'reaberto',
                'pinned' => 'fixado',
                'unpinned' => 'desafixado',
                'moved' => 'movido',
                'approved' => 'aprovado',
                'deleted' => 'removido',
                default => $action,
            };

            self::notify($author, 'moderation', 'Seu tópico foi '.$label, self::excerpt(
                'A moderação '.$label.' "'.$thread->title.'".'
            ), self::threadUrl($thread), '🛡️');
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * Tell the reporter their report was resolved.
     */
    public static function reportResolved(ForumReport $report, string $status): void
    {
        try {
            $reporter = $report->reporter;
            if ($reporter === null) {
                return;
            }

            $label = $status === 'reviewed' ? 'revisada' : 'descartada';
            self::notify($reporter, 'report', 'Sua denúncia foi '.$label, 'Obrigado por ajudar a manter o fórum saudável.',
                route('forum.index'), '🛡️');
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * Tell a user they were banned from the forum.
     */
    public static function banned(ForumBan $ban): void
    {
        try {
            $user = $ban->user;
            if ($user === null) {
                return;
            }

            $scope = $ban->forum_category_id === null ? 'o fórum' : 'este quadro';
            $until = $ban->isPermanent() ? 'permanentemente' : 'até '.$ban->banned_until?->format('d/m/Y H:i');

            self::notify($user, 'ban', 'Você foi banido', 'Você foi banido de '.$scope.' '.$until
                .($ban->reason ? ' — '.$ban->reason : ''), route('forum.index'), '⛔');
        } catch (Throwable $e) {
            report($e);
        }
    }

    protected static function threadUrl(ForumThread $thread): string
    {
        try {
            return route('forum.thread', [
                'categorySlug' => $thread->category?->slug,
                'threadSlug' => $thread->slug,
            ]);
        } catch (Throwable) {
            return route('forum.index');
        }
    }

    protected static function notify(User $user, string $kind, string $title, string $message, string $url, string $icon): void
    {
        if (! Schema::hasTable('notifications')) {
            return;
        }

        $user->notify(new ForumNotification($kind, $title, $message, $url, $icon));
    }

    protected static function excerpt(?string $text, int $limit = 90): string
    {
        return (string) Str::limit(trim(preg_replace('/\s+/', ' ', (string) $text) ?? ''), $limit);
    }
}
