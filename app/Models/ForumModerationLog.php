<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Audit trail for moderator actions.
 */
class ForumModerationLog extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'forum_moderation_log';

    protected $fillable = ['moderator_id', 'action', 'subject_type', 'subject_id', 'metadata', 'created_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function moderator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderator_id');
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public static function record(User $moderator, string $action, Model $subject, array $metadata = []): void
    {
        static::create([
            'moderator_id' => $moderator->id,
            'action' => $action,
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'metadata' => $metadata,
            'created_at' => now(),
        ]);
    }
}
