<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One gallery waiting in the admin import queue. The browser walks the
 * queue in order: each item becomes a scheduled comic whose pages are
 * downloaded through the usual chunked import flow.
 */
class EhImportQueueItem extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_DONE = 'done';

    public const STATUS_FAILED = 'failed';

    public const STATUS_SKIPPED = 'skipped';

    protected $table = 'eh_import_queue';

    protected $fillable = [
        'position',
        'gid',
        'token',
        'title',
        'thumb',
        'source_url',
        'status',
        'redownload',
        'comic_id',
        'error',
        'processed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'gid' => 'integer',
            'redownload' => 'boolean',
            'processed_at' => 'datetime',
        ];
    }

    public function comic(): BelongsTo
    {
        return $this->belongsTo(Comic::class);
    }
}
