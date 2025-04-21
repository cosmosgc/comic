<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Widget extends Model
{
    use HasFactory;

    protected $table = 'widgets';

    // Mass assignable attributes
    protected $fillable = [
        'title',
        'content',
        'position_index',
    ];

    // Optional: cast content as HTML-safe string (if you want to customize later)
    protected $casts = [
        'position_index' => 'integer',
    ];
}
