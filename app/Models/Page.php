<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Page extends Model
{
    use HasFactory;

    protected $fillable = [
        'comic_id',
        'image_path',
        'page_number',
    ];

    // Define relationship with Comic
    public function comic()
    {
        return $this->belongsTo(Comic::class);
    }
}
