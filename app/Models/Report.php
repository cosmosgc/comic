<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A user report against a post, reviewed in the admin queue.
 */
class Report extends Model
{
    protected $fillable = [
        'reporter_id',
        'post_id',
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
        return [
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * Report reasons shown in the form and admin table.
     *
     * @return array<string, string>
     */
    public static function reasons(): array
    {
        return [
            'spam' => 'Spam',
            'abuse' => 'Abuso / assédio',
            'hate' => 'Discurso de ódio',
            'illegal' => 'Conteúdo ilegal',
            'other' => 'Outro',
        ];
    }

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

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
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
