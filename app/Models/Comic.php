<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Comic extends Model
{
    use HasFactory;

    // Specify the table if necessary (Laravel assumes the plural form of the class name)
    protected $table = 'comics';

    // The fields that are mass assignable
    protected $fillable = [
        'title',
        'description',
        'author',
        'image_path',
    ];

    // If you need to cast any attributes to specific data types
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
    public function pages()
    {
        return $this->hasMany(Page::class);
    }
}
