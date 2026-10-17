<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A report against a thread or post, reviewed in the admin queue.
 */
class ForumReport extends Model
{
    protected $fillable = [
        'reporter_id',
        'forum_thread_id',
        'forum_post_id',
        'reason',
        'details',
        'status',
        'reviewed_by',
        'reviewed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime'];
    }

    /**
     * @return array<string, string>
     */
    public static function reasons(): array
    {
        return [
            'spam' => 'Spam',
            'abuse' => 'Abuso / assédio',
            'oftopic' => 'Fora do tópico',
            'illegal' => 'Conteúdo ilegal',
            'other' => 'Outro',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function statuses(): array
    {
        return [
            'pending' => 'Pendente',
            'reviewed' => 'Revisado',
            'dismissed' => 'Descartado',
        ];
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function thread(): BelongsTo
    {
        return $this->belongsTo(ForumThread::class, 'forum_thread_id');
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(ForumPost::class, 'forum_post_id');
    }

    public function reasonLabel(): string
    {
        return self::reasons()[$this->reason] ?? $this->reason;
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }
}
