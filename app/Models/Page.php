<?php

namespace App\Models;

use App\Support\ComicDiskUsage;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Page extends Model
{
    use HasFactory;

    protected $fillable = [
        'comic_id',
        'image_path',
        'page_number',
    ];

    protected static function booted(): void
    {
        // A page record going in or out changes the per-comic disk total.
        static::created(fn () => ComicDiskUsage::flush());
        static::deleted(fn () => ComicDiskUsage::flush());
    }

    // Define relationship with Comic
    public function comic(): BelongsTo
    {
        return $this->belongsTo(Comic::class);
    }
}
