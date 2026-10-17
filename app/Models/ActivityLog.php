<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Collection;

class ActivityLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'action',
        'subject_type',
        'subject_id',
        'description',
        'url',
        'metadata',
        'ip_address',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    /**
     * Every loggable interaction. Keys are stored in `action`; values
     * are the human-readable labels shown in the admin panel filter
     * and table badges.
     *
     * @return array<string, string>
     */
    public static function actions(): array
    {
        return [
            'search.comics' => 'Busca de comics',
            'search.posts' => 'Busca de posts',
            'comic.uploaded' => 'Comic enviado',
            'comic.liked' => 'Comic curtido',
            'comic.unliked' => 'Descurtiu comic',
            'comic.commented' => 'Comentário em comic',
            'post.created' => 'Post criado',
            'post.replied' => 'Resposta em thread',
            'post.liked' => 'Post curtido',
            'post.unliked' => 'Descurtiu post',
            'collection.created' => 'Coleção criada',
            'collection.added' => 'Comic em coleção',
            'collection.favorited' => 'Favoritou comic',
            'collection.unfavorited' => 'Desfavoritou comic',
            'auth.login' => 'Login',
            'auth.logout' => 'Logout',
            'auth.register' => 'Registro',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Attribute guest rows to a signed-in identity seen from the same
     * IP address before. Queries activity_logs first, then falls back
     * to the historical analytics rows (logins/page views) so older
     * IPs that predate the activity feed still resolve.
     *
     * @param  iterable<int, string|null>  $ips
     * @return Collection<string, User> keyed by IP address
     */
    public static function usersForIps(iterable $ips): Collection
    {
        $ips = collect($ips)->filter(fn ($ip) => is_string($ip) && $ip !== '')
            ->unique()->values()->all();
        if ($ips === []) {
            return collect();
        }

        /** @var Collection<string, int> $userIds */
        $userIds = collect();

        // Most recent non-null user per IP, in this table.
        $rows = static::query()
            ->whereIn('ip_address', $ips)
            ->whereNotNull('user_id')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get(['ip_address', 'user_id']);
        foreach ($rows as $row) {
            if (! $userIds->has($row->ip_address)) {
                $userIds->put($row->ip_address, (int) $row->user_id);
            }
        }

        // Fallback: the analytics table (login + page_view history).
        $missing = array_values(array_diff($ips, $userIds->keys()->all()));
        if ($missing !== []) {
            $rows = Analytics::query()
                ->whereIn('ip_address', $missing)
                ->whereNotNull('user_id')
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->get(['ip_address', 'user_id']);
            foreach ($rows as $row) {
                if (! $userIds->has($row->ip_address)) {
                    $userIds->put($row->ip_address, (int) $row->user_id);
                }
            }
        }

        if ($userIds->isEmpty()) {
            return collect();
        }

        $users = User::whereIn('id', $userIds->values()->unique()->all())
            ->get()
            ->keyBy('id');

        return $userIds->mapWithKeys(fn (int $id, string $ip) => $users->has($id)
            ? [$ip => $users->get($id)]
            : []);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function label(): string
    {
        return self::actions()[$this->action] ?? $this->action;
    }
}
