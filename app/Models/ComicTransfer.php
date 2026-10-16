<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComicTransfer extends Model
{
    public const STATUS_SENDING = 'sending';

    public const STATUS_DONE = 'done';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'comic_id',
        'remote_url',
        'status',
        'remote_comic_id',
        'remote_view_url',
        'next_page',
        'total_pages',
        'error',
    ];

    public function comic(): BelongsTo
    {
        return $this->belongsTo(Comic::class);
    }

    public function isDone(): bool
    {
        return $this->status === self::STATUS_DONE;
    }
}
