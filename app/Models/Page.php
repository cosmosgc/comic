<?php

namespace App\Models;

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

    // Define relationship with Comic
    public function comic(): BelongsTo
    {
        return $this->belongsTo(Comic::class);
    }
}
