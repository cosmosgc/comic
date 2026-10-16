<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EhImportPage extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_DOWNLOADING = 'downloading';

    public const STATUS_DONE = 'done';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'eh_import_id',
        'page_number',
        'page_url',
        'image_url',
        'file_size',
        'status',
        'attempts',
        'error',
    ];

    public function import(): BelongsTo
    {
        return $this->belongsTo(EhImport::class, 'eh_import_id');
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }
}
