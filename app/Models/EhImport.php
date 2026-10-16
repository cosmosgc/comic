<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EhImport extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_DOWNLOADING = 'downloading';

    public const STATUS_COMPLETE = 'complete';

    protected $fillable = [
        'comic_id',
        'source_url',
        'gid',
        'token',
        'status',
        'total_pages',
        'log',
    ];

    public function comic(): BelongsTo
    {
        return $this->belongsTo(Comic::class);
    }

    public function pages(): HasMany
    {
        return $this->hasMany(EhImportPage::class)->orderBy('page_number');
    }

    public function isComplete(): bool
    {
        return $this->status === self::STATUS_COMPLETE;
    }
}
